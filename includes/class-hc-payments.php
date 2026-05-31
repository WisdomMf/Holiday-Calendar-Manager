<?php
/**
 * Payment module bootstrap: shortcode, admin settings, REST/AJAX, webhooks.
 *
 * Architecture:
 * - hc_payment_settings in wp_options holds secret keys (never localized to JS).
 * - REST endpoints create PaymentIntents / PayPal orders server-side with validated tier amounts.
 * - Frontend loads Stripe.js + PayPal SDK with publishable/client IDs only.
 * - Webhooks provide async confirmation; client verify endpoints double-check status.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HC_Payments {

	const REST_NAMESPACE = 'holiday-calendar/v1';

	/**
	 * @var bool
	 */
	private static $assets_enqueued = false;

	/**
	 * @var HC_Payment_Stripe|null
	 */
	private $stripe;

	/**
	 * @var HC_Payment_PayPal|null
	 */
	private $paypal;

	public function __construct() {
		$settings     = hc_get_payment_settings();
		$this->stripe = new HC_Payment_Stripe( $settings );
		$this->paypal = new HC_Payment_PayPal( $settings );

		add_shortcode( 'hc_payment_form', array( $this, 'render_shortcode' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'wp_ajax_hc_create_stripe_intent', array( $this, 'ajax_create_stripe_intent' ) );
		add_action( 'wp_ajax_nopriv_hc_create_stripe_intent', array( $this, 'ajax_create_stripe_intent' ) );
		add_action( 'wp_ajax_hc_verify_stripe_payment', array( $this, 'ajax_verify_stripe_payment' ) );
		add_action( 'wp_ajax_nopriv_hc_verify_stripe_payment', array( $this, 'ajax_verify_stripe_payment' ) );
		add_action( 'wp_ajax_hc_create_paypal_order', array( $this, 'ajax_create_paypal_order' ) );
		add_action( 'wp_ajax_nopriv_hc_create_paypal_order', array( $this, 'ajax_create_paypal_order' ) );
		add_action( 'wp_ajax_hc_capture_paypal_order', array( $this, 'ajax_capture_paypal_order' ) );
		add_action( 'wp_ajax_nopriv_hc_capture_paypal_order', array( $this, 'ajax_capture_paypal_order' ) );
	}

	public function register_admin_menu() {
		add_menu_page(
			__( 'Payment Form', 'holiday-calendar' ),
			__( 'Payment Form', 'holiday-calendar' ),
			'manage_options',
			'hc-payments',
			array( $this, 'render_admin_page' ),
			'dashicons-money-alt',
			59
		);
	}

	/**
	 * Enqueue admin assets only on the payment settings screen.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_hc-payments' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'hc-payment-admin', hc_asset_url( 'assets/payment-admin.css' ), array(), hc_asset_version( 'assets/payment-admin.css' ) );
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['hc_payment_action'] ) && 'save_payment_settings' === sanitize_text_field( wp_unslash( $_POST['hc_payment_action'] ) ) ) {
			check_admin_referer( 'hc_save_payment_settings' );
			$settings = array(
				'sandbox_mode'           => isset( $_POST['hc_sandbox_mode'] ) ? 1 : 0,
				'currency'               => isset( $_POST['hc_currency'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['hc_currency'] ) ) ) : 'USD',
				'stripe_publishable_key' => isset( $_POST['hc_stripe_publishable_key'] ) ? sanitize_text_field( wp_unslash( $_POST['hc_stripe_publishable_key'] ) ) : '',
				'stripe_secret_key'      => isset( $_POST['hc_stripe_secret_key'] ) ? sanitize_text_field( wp_unslash( $_POST['hc_stripe_secret_key'] ) ) : '',
				'stripe_webhook_secret'  => isset( $_POST['hc_stripe_webhook_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['hc_stripe_webhook_secret'] ) ) : '',
				'paypal_client_id'       => isset( $_POST['hc_paypal_client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['hc_paypal_client_id'] ) ) : '',
				'paypal_secret'          => isset( $_POST['hc_paypal_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['hc_paypal_secret'] ) ) : '',
				'paypal_webhook_id'      => isset( $_POST['hc_paypal_webhook_id'] ) ? sanitize_text_field( wp_unslash( $_POST['hc_paypal_webhook_id'] ) ) : '',
			);
			update_option( 'hc_payment_settings', $settings );
			$this->stripe = new HC_Payment_Stripe( $settings );
			$this->paypal = new HC_Payment_PayPal( $settings );
			add_settings_error( 'hc_payments', 'hc_payments_saved', __( 'Payment settings saved.', 'holiday-calendar' ), 'updated' );
		}

		$settings  = hc_get_payment_settings();
		$rest_base = rest_url( self::REST_NAMESPACE );
		?>
		<div class="wrap hc-payment-admin">
			<h1><?php esc_html_e( 'Payment Form', 'holiday-calendar' ); ?></h1>
			<?php settings_errors( 'hc_payments' ); ?>

			<p class="hc-shortcode-hint">
				<?php esc_html_e( 'Embed the payment form with:', 'holiday-calendar' ); ?>
				<code>[hc_payment_form]</code>
				&nbsp;|&nbsp;
				<?php esc_html_e( 'Summer camp registration:', 'holiday-calendar' ); ?>
				<code>[hc_summer_camp_registration]</code>
			</p>

			<form method="post" class="hc-card" style="max-width:720px;padding:1.25rem 1.5rem;">
				<?php wp_nonce_field( 'hc_save_payment_settings' ); ?>
				<input type="hidden" name="hc_payment_action" value="save_payment_settings" />

				<h2><?php esc_html_e( 'General', 'holiday-calendar' ); ?></h2>
				<p>
					<label>
						<input type="checkbox" name="hc_sandbox_mode" value="1" <?php checked( $settings['sandbox_mode'], 1 ); ?> />
						<?php esc_html_e( 'Sandbox / test mode', 'holiday-calendar' ); ?>
					</label>
				</p>
				<p>
					<label for="hc_currency"><?php esc_html_e( 'Currency', 'holiday-calendar' ); ?></label><br />
					<input type="text" id="hc_currency" name="hc_currency" value="<?php echo esc_attr( $settings['currency'] ); ?>" maxlength="3" class="regular-text" />
				</p>

				<h2><?php esc_html_e( 'Stripe', 'holiday-calendar' ); ?></h2>
				<p>
					<label for="hc_stripe_publishable_key"><?php esc_html_e( 'Publishable key', 'holiday-calendar' ); ?></label><br />
					<input type="text" id="hc_stripe_publishable_key" name="hc_stripe_publishable_key" value="<?php echo esc_attr( $settings['stripe_publishable_key'] ); ?>" class="large-text" autocomplete="off" />
				</p>
				<p>
					<label for="hc_stripe_secret_key"><?php esc_html_e( 'Secret key', 'holiday-calendar' ); ?></label><br />
					<input type="password" id="hc_stripe_secret_key" name="hc_stripe_secret_key" value="<?php echo esc_attr( $settings['stripe_secret_key'] ); ?>" class="large-text" autocomplete="new-password" />
				</p>
				<p>
					<label for="hc_stripe_webhook_secret"><?php esc_html_e( 'Webhook signing secret', 'holiday-calendar' ); ?></label><br />
					<input type="password" id="hc_stripe_webhook_secret" name="hc_stripe_webhook_secret" value="<?php echo esc_attr( $settings['stripe_webhook_secret'] ); ?>" class="large-text" autocomplete="new-password" />
					<br /><span class="description"><?php printf( esc_html__( 'Webhook URL: %s', 'holiday-calendar' ), '<code>' . esc_html( $rest_base . '/webhooks/stripe' ) . '</code>' ); ?></span>
				</p>

				<h2><?php esc_html_e( 'PayPal', 'holiday-calendar' ); ?></h2>
				<p>
					<label for="hc_paypal_client_id"><?php esc_html_e( 'Client ID', 'holiday-calendar' ); ?></label><br />
					<input type="text" id="hc_paypal_client_id" name="hc_paypal_client_id" value="<?php echo esc_attr( $settings['paypal_client_id'] ); ?>" class="large-text" autocomplete="off" />
				</p>
				<p>
					<label for="hc_paypal_secret"><?php esc_html_e( 'Secret', 'holiday-calendar' ); ?></label><br />
					<input type="password" id="hc_paypal_secret" name="hc_paypal_secret" value="<?php echo esc_attr( $settings['paypal_secret'] ); ?>" class="large-text" autocomplete="new-password" />
				</p>
				<p>
					<label for="hc_paypal_webhook_id"><?php esc_html_e( 'Webhook ID (optional)', 'holiday-calendar' ); ?></label><br />
					<input type="text" id="hc_paypal_webhook_id" name="hc_paypal_webhook_id" value="<?php echo esc_attr( $settings['paypal_webhook_id'] ); ?>" class="large-text" />
					<br /><span class="description"><?php printf( esc_html__( 'Webhook URL: %s', 'holiday-calendar' ), '<code>' . esc_html( $rest_base . '/webhooks/paypal' ) . '</code>' ); ?></span>
				</p>

				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save payment settings', 'holiday-calendar' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	public function render_shortcode( $atts ) {
		$settings = hc_get_payment_settings();
		$this->enqueue_assets( $settings );

		if ( ! hc_payments_available() ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<div class="hc-payment hc-payment--notice" role="alert"><p>' . esc_html__( 'Payments are not configured. Add your Stripe or PayPal keys under Payment Form in the admin menu.', 'holiday-calendar' ) . '</p></div>';
			}
			return '<div class="hc-payment hc-payment--notice" role="status"><p>' . esc_html__( 'Online payments are temporarily unavailable. Please try again later.', 'holiday-calendar' ) . '</p></div>';
		}

		ob_start();
		include HC_PATH . 'includes/views/payment-form.php';
		return ob_get_clean();
	}

	/**
	 * @param array $settings Payment settings.
	 */
	private function enqueue_assets( array $settings ) {
		if ( self::$assets_enqueued ) {
			return;
		}

		wp_enqueue_style( 'hc-payment-form', hc_asset_url( 'assets/payment-form.css' ), array(), hc_asset_version( 'assets/payment-form.css' ) );

		$script_deps = array();
		if ( $this->stripe->is_configured() ) {
			wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', array(), null, true );
			$script_deps[] = 'stripe-js';
		}

		if ( $this->paypal->is_configured() ) {
			$paypal_url = 'https://www.paypal.com/sdk/js?client-id=' . rawurlencode( $this->paypal->get_client_id() ) . '&currency=' . rawurlencode( strtoupper( $settings['currency'] ) ) . '&intent=capture';
			wp_enqueue_script( 'paypal-js', $paypal_url, array(), null, true );
			$script_deps[] = 'paypal-js';
		}

		wp_enqueue_script( 'hc-payment-form', hc_asset_url( 'assets/payment-form.js' ), $script_deps, hc_asset_version( 'assets/payment-form.js' ), true );

		$tiers = hc_get_payment_tiers();
		$tier_data = array();
		foreach ( $tiers as $slug => $tier ) {
			$tier_data[ $slug ] = array(
				'label'       => $tier['label'],
				'description' => $tier['description'],
				'amountLabel' => hc_format_money( $tier['amount_cents'], $settings['currency'] ),
				'amountCents' => (int) $tier['amount_cents'],
			);
		}

		wp_localize_script(
			'hc-payment-form',
			'HC_PAYMENT',
			array(
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'nonce'             => wp_create_nonce( 'hc_payment_form' ),
				'currency'          => strtoupper( $settings['currency'] ),
				'sandbox'           => ! empty( $settings['sandbox_mode'] ),
				'stripePublishable' => $this->stripe->is_configured() ? $this->stripe->get_publishable_key() : '',
				'paypalClientId'    => $this->paypal->is_configured() ? $this->paypal->get_client_id() : '',
				'stripeEnabled'     => $this->stripe->is_configured(),
				'paypalEnabled'     => $this->paypal->is_configured(),
				'tiers'             => $tier_data,
				'i18n'              => array(
					'payWithCard'    => __( 'Pay with card', 'holiday-calendar' ),
					'payWithPayPal'  => __( 'Pay with PayPal', 'holiday-calendar' ),
					'processing'     => __( 'Processing payment…', 'holiday-calendar' ),
					'success'        => __( 'Payment successful! Thank you.', 'holiday-calendar' ),
					'errorGeneric'   => __( 'Something went wrong. Please try again.', 'holiday-calendar' ),
					'invalidForm'    => __( 'Please fix the highlighted fields.', 'holiday-calendar' ),
					'selectMethod'   => __( 'Choose a payment method', 'holiday-calendar' ),
					'card'           => __( 'Card', 'holiday-calendar' ),
					'paypal'         => __( 'PayPal', 'holiday-calendar' ),
					'total'          => __( 'Total', 'holiday-calendar' ),
					'required'       => __( 'This field is required.', 'holiday-calendar' ),
					'invalidEmail'   => __( 'Enter a valid email address.', 'holiday-calendar' ),
					'acceptTerms'    => __( 'Please accept the terms.', 'holiday-calendar' ),
				),
			)
		);

		self::$assets_enqueued = true;
	}

	public function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/webhooks/stripe',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_stripe_webhook' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/webhooks/paypal',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_paypal_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function rest_stripe_webhook( WP_REST_Request $request ) {
		$payload    = $request->get_body();
		$sig_header = $request->get_header( 'stripe-signature' );
		if ( ! $sig_header ) {
			return new WP_REST_Response( array( 'error' => 'missing signature' ), 400 );
		}

		$result = $this->stripe->handle_webhook( $payload, $sig_header );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'error' => $result->get_error_message() ), 400 );
		}
		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	public function rest_paypal_webhook( WP_REST_Request $request ) {
		$headers = array();
		foreach ( $request->get_headers() as $key => $values ) {
			$headers[ strtoupper( str_replace( '-', '_', $key ) ) ] = is_array( $values ) ? implode( ',', $values ) : $values;
		}
		$result = $this->paypal->handle_webhook( $headers, $request->get_body() );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'error' => $result->get_error_message() ), 400 );
		}
		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	public function ajax_create_stripe_intent() {
		$this->guard_ajax_request( 'stripe_intent' );
		$data = $this->parse_submission();
		if ( is_wp_error( $data ) ) {
			wp_send_json_error( array( 'message' => $data->get_error_message() ), 400 );
		}

		$settings = hc_get_payment_settings();
		$metadata = array(
			'name'       => $data['name'],
			'email'      => $data['email'],
			'phone'      => $data['phone'],
			'tier'       => $data['tier'],
			'tier_label' => $data['tier_label'],
			'reference'  => $data['reference'],
		);

		$result = $this->stripe->create_payment_intent(
			$data['amount_cents'],
			$settings['currency'],
			$metadata
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( $result );
	}

	public function ajax_verify_stripe_payment() {
		$this->guard_ajax_request( 'stripe_verify' );
		$data = $this->parse_submission();
		if ( is_wp_error( $data ) ) {
			wp_send_json_error( array( 'message' => $data->get_error_message() ), 400 );
		}

		$intent_id = isset( $_POST['payment_intent_id'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_intent_id'] ) ) : '';
		$result    = $this->stripe->verify_payment_intent( $intent_id, $data['amount_cents'] );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		hc_log_payment(
			array(
				'provider' => 'stripe',
				'id'       => $intent_id,
				'amount'   => $data['amount_cents'],
				'currency' => hc_get_payment_settings()['currency'],
				'email'    => $data['email'],
				'name'     => $data['name'],
				'tier'     => $data['tier'],
				'source'   => 'client_verify',
			)
		);

		wp_send_json_success(
			array(
				'message' => __( 'Payment confirmed. Thank you!', 'holiday-calendar' ),
			)
		);
	}

	public function ajax_create_paypal_order() {
		$this->guard_ajax_request( 'paypal_order' );
		$data = $this->parse_submission();
		if ( is_wp_error( $data ) ) {
			wp_send_json_error( array( 'message' => $data->get_error_message() ), 400 );
		}

		$settings = hc_get_payment_settings();
		$metadata = array(
			'tier_label' => $data['tier_label'],
			'reference'  => $data['reference'],
			'email'      => $data['email'],
			'name'       => $data['name'],
		);

		$result = $this->paypal->create_order(
			$data['amount_cents'],
			$settings['currency'],
			$metadata
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( $result );
	}

	public function ajax_capture_paypal_order() {
		$this->guard_ajax_request( 'paypal_capture' );
		$data = $this->parse_submission();
		if ( is_wp_error( $data ) ) {
			wp_send_json_error( array( 'message' => $data->get_error_message() ), 400 );
		}

		$order_id = isset( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : '';
		$result   = $this->paypal->capture_order( $order_id, $data['amount_cents'] );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		hc_log_payment(
			array(
				'provider' => 'paypal',
				'id'       => $result['capture_id'] ?: $order_id,
				'amount'   => $data['amount_cents'],
				'currency' => hc_get_payment_settings()['currency'],
				'email'    => $data['email'],
				'name'     => $data['name'],
				'tier'     => $data['tier'],
				'source'   => 'client_capture',
			)
		);

		wp_send_json_success(
			array(
				'message' => __( 'Payment confirmed. Thank you!', 'holiday-calendar' ),
			)
		);
	}

	/**
	 * Nonce, rate limit, and method guard for AJAX handlers.
	 *
	 * @param string $rate_action Rate limit bucket.
	 */
	private function guard_ajax_request( $rate_action ) {
		if ( ! check_ajax_referer( 'hc_payment_form', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Refresh and try again.', 'holiday-calendar' ) ), 403 );
		}
		if ( ! hc_payment_rate_limit( $rate_action, 15, 60 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please wait a moment.', 'holiday-calendar' ) ), 429 );
		}
	}

	/**
	 * @return array|WP_Error
	 */
	private function parse_submission() {
		return hc_sanitize_payment_submission( wp_unslash( $_POST ) );
	}
}
