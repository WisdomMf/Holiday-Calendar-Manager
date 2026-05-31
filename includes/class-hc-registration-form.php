<?php
/**
 * Summer Camp Registration form: shortcode, assets, AJAX submit, admin settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HC_Registration_Form {

	/**
	 * @var bool
	 */
	private static $assets_enqueued = false;

	/**
	 * @var HC_Payment_Stripe
	 */
	private $stripe;

	/**
	 * @var HC_Payment_PayPal
	 */
	private $paypal;

	public function __construct() {
		$settings     = hc_get_payment_settings();
		$this->stripe = new HC_Payment_Stripe( $settings );
		$this->paypal = new HC_Payment_PayPal( $settings );

		add_shortcode( 'hc_summer_camp_registration', array( $this, 'render_shortcode' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 11 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_hc_submit_registration', array( $this, 'ajax_submit' ) );
		add_action( 'wp_ajax_nopriv_hc_submit_registration', array( $this, 'ajax_submit' ) );
		add_action( 'wp_ajax_hc_reg_create_stripe_intent', array( $this, 'ajax_create_stripe_intent' ) );
		add_action( 'wp_ajax_nopriv_hc_reg_create_stripe_intent', array( $this, 'ajax_create_stripe_intent' ) );
		add_action( 'wp_ajax_hc_reg_create_paypal_order', array( $this, 'ajax_create_paypal_order' ) );
		add_action( 'wp_ajax_nopriv_hc_reg_create_paypal_order', array( $this, 'ajax_create_paypal_order' ) );
		add_action( 'wp_ajax_hc_reg_capture_paypal_order', array( $this, 'ajax_capture_paypal_order' ) );
		add_action( 'wp_ajax_nopriv_hc_reg_capture_paypal_order', array( $this, 'ajax_capture_paypal_order' ) );
	}

	public function register_admin_menu() {
		add_submenu_page(
			'hc-payments',
			__( 'Summer Camp Registration', 'holiday-calendar' ),
			__( 'Summer Camp Registration', 'holiday-calendar' ),
			'manage_options',
			'hc-registration',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * @param string $hook Admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'payment-form_page_hc-registration' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'hc-payment-admin', hc_asset_url( 'assets/payment-admin.css' ), array(), hc_asset_version( 'assets/payment-admin.css' ) );
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['hc_registration_action'] ) && 'save_registration_settings' === sanitize_text_field( wp_unslash( $_POST['hc_registration_action'] ) ) ) {
			check_admin_referer( 'hc_save_registration_settings' );
			$settings = array(
				'form_title'      => isset( $_POST['hc_reg_form_title'] ) ? sanitize_text_field( wp_unslash( $_POST['hc_reg_form_title'] ) ) : '',
				'form_subtitle'   => isset( $_POST['hc_reg_form_subtitle'] ) ? sanitize_text_field( wp_unslash( $_POST['hc_reg_form_subtitle'] ) ) : '',
				'logo_url'        => isset( $_POST['hc_reg_logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['hc_reg_logo_url'] ) ) : '',
				'badge_line1'     => isset( $_POST['hc_reg_badge_line1'] ) ? sanitize_text_field( wp_unslash( $_POST['hc_reg_badge_line1'] ) ) : '',
				'badge_line2'     => isset( $_POST['hc_reg_badge_line2'] ) ? sanitize_text_field( wp_unslash( $_POST['hc_reg_badge_line2'] ) ) : '',
				'badge_line3'     => isset( $_POST['hc_reg_badge_line3'] ) ? sanitize_text_field( wp_unslash( $_POST['hc_reg_badge_line3'] ) ) : '',
				'whatsapp_number' => isset( $_POST['hc_reg_whatsapp_number'] ) ? sanitize_text_field( wp_unslash( $_POST['hc_reg_whatsapp_number'] ) ) : '',
				'whatsapp_qr_url' => isset( $_POST['hc_reg_whatsapp_qr_url'] ) ? esc_url_raw( wp_unslash( $_POST['hc_reg_whatsapp_qr_url'] ) ) : '',
				'terms_url'       => isset( $_POST['hc_reg_terms_url'] ) ? esc_url_raw( wp_unslash( $_POST['hc_reg_terms_url'] ) ) : '',
				'privacy_url'     => isset( $_POST['hc_reg_privacy_url'] ) ? esc_url_raw( wp_unslash( $_POST['hc_reg_privacy_url'] ) ) : '',
				'notify_admin'    => isset( $_POST['hc_reg_notify_admin'] ) ? 1 : 0,
				'admin_email'     => isset( $_POST['hc_reg_admin_email'] ) ? sanitize_email( wp_unslash( $_POST['hc_reg_admin_email'] ) ) : '',
			);
			update_option( 'hc_registration_settings', $settings );
			add_settings_error( 'hc_registration', 'hc_registration_saved', __( 'Registration settings saved.', 'holiday-calendar' ), 'updated' );
		}

		$settings    = hc_get_registration_settings();
		$submissions = get_option( 'hc_registration_submissions', array() );
		$count       = is_array( $submissions ) ? count( $submissions ) : 0;
		?>
		<div class="wrap hc-payment-admin">
			<h1><?php esc_html_e( 'Summer Camp Registration', 'holiday-calendar' ); ?></h1>
			<?php settings_errors( 'hc_registration' ); ?>

			<p class="hc-shortcode-hint">
				<?php esc_html_e( 'Embed the registration form with:', 'holiday-calendar' ); ?>
				<code>[hc_summer_camp_registration]</code>
			</p>

			<p class="description">
				<?php esc_html_e( 'Registration payments use the Stripe and PayPal API keys configured under Payment Form in this menu. Enable sandbox mode there when testing.', 'holiday-calendar' ); ?>
			</p>

			<p>
				<?php
				printf(
					/* translators: %d: submission count */
					esc_html__( 'Total submissions stored: %d (last 200 retained).', 'holiday-calendar' ),
					(int) $count
				);
				?>
			</p>

			<form method="post" class="hc-card" style="max-width:720px;padding:1.25rem 1.5rem;">
				<?php wp_nonce_field( 'hc_save_registration_settings' ); ?>
				<input type="hidden" name="hc_registration_action" value="save_registration_settings" />

				<h2><?php esc_html_e( 'Form branding', 'holiday-calendar' ); ?></h2>
				<p>
					<label for="hc_reg_form_title"><?php esc_html_e( 'Form title', 'holiday-calendar' ); ?></label><br />
					<input type="text" id="hc_reg_form_title" name="hc_reg_form_title" value="<?php echo esc_attr( $settings['form_title'] ); ?>" class="large-text" />
				</p>
				<p>
					<label for="hc_reg_form_subtitle"><?php esc_html_e( 'Subtitle', 'holiday-calendar' ); ?></label><br />
					<input type="text" id="hc_reg_form_subtitle" name="hc_reg_form_subtitle" value="<?php echo esc_attr( $settings['form_subtitle'] ); ?>" class="large-text" />
				</p>
				<p>
					<label for="hc_reg_logo_url"><?php esc_html_e( 'Logo URL', 'holiday-calendar' ); ?></label><br />
					<input type="url" id="hc_reg_logo_url" name="hc_reg_logo_url" value="<?php echo esc_url( $settings['logo_url'] ); ?>" class="large-text" />
				</p>
				<p>
					<label><?php esc_html_e( 'Badge text (3 lines)', 'holiday-calendar' ); ?></label><br />
					<input type="text" name="hc_reg_badge_line1" value="<?php echo esc_attr( $settings['badge_line1'] ); ?>" placeholder="June 2026" class="regular-text" />
					<input type="text" name="hc_reg_badge_line2" value="<?php echo esc_attr( $settings['badge_line2'] ); ?>" placeholder="Camp" class="regular-text" />
					<input type="text" name="hc_reg_badge_line3" value="<?php echo esc_attr( $settings['badge_line3'] ); ?>" placeholder="Registration" class="regular-text" />
				</p>

				<h2><?php esc_html_e( 'Contact', 'holiday-calendar' ); ?></h2>
				<p>
					<label for="hc_reg_whatsapp_number"><?php esc_html_e( 'WhatsApp number', 'holiday-calendar' ); ?></label><br />
					<input type="text" id="hc_reg_whatsapp_number" name="hc_reg_whatsapp_number" value="<?php echo esc_attr( $settings['whatsapp_number'] ); ?>" class="regular-text" />
				</p>
				<p>
					<label for="hc_reg_whatsapp_qr_url"><?php esc_html_e( 'WhatsApp QR code image URL', 'holiday-calendar' ); ?></label><br />
					<input type="url" id="hc_reg_whatsapp_qr_url" name="hc_reg_whatsapp_qr_url" value="<?php echo esc_url( $settings['whatsapp_qr_url'] ); ?>" class="large-text" />
				</p>
				<p>
					<label for="hc_reg_terms_url"><?php esc_html_e( 'Terms & Conditions URL', 'holiday-calendar' ); ?></label><br />
					<input type="url" id="hc_reg_terms_url" name="hc_reg_terms_url" value="<?php echo esc_url( $settings['terms_url'] ); ?>" class="large-text" />
				</p>
				<p>
					<label for="hc_reg_privacy_url"><?php esc_html_e( 'Privacy Policy URL', 'holiday-calendar' ); ?></label><br />
					<input type="url" id="hc_reg_privacy_url" name="hc_reg_privacy_url" value="<?php echo esc_url( $settings['privacy_url'] ); ?>" class="large-text" />
				</p>

				<h2><?php esc_html_e( 'Notifications', 'holiday-calendar' ); ?></h2>
				<p>
					<label>
						<input type="checkbox" name="hc_reg_notify_admin" value="1" <?php checked( $settings['notify_admin'], 1 ); ?> />
						<?php esc_html_e( 'Email admin on new submission', 'holiday-calendar' ); ?>
					</label>
				</p>
				<p>
					<label for="hc_reg_admin_email"><?php esc_html_e( 'Notification email', 'holiday-calendar' ); ?></label><br />
					<input type="email" id="hc_reg_admin_email" name="hc_reg_admin_email" value="<?php echo esc_attr( $settings['admin_email'] ); ?>" class="regular-text" />
				</p>

				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save registration settings', 'holiday-calendar' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$this->enqueue_assets();
		$settings           = hc_get_registration_settings();
		$payment_settings   = hc_get_payment_settings();
		$form_id            = 'hc-reg-' . wp_unique_id();
		$payments_available = hc_payments_available();
		$stripe_enabled     = $this->stripe->is_configured();
		$paypal_enabled     = $this->paypal->is_configured();

		ob_start();
		include HC_PATH . 'includes/views/registration-form.php';
		return ob_get_clean();
	}

	private function enqueue_assets() {
		if ( self::$assets_enqueued ) {
			return;
		}

		$payment_settings = hc_get_payment_settings();

		wp_enqueue_style(
			'hc-registration-fonts',
			'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Source+Sans+3:wght@300;400;600;700&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'hc-registration-form',
			hc_asset_url( 'assets/registration-form.css' ),
			array( 'hc-registration-fonts' ),
			hc_asset_version( 'assets/registration-form.css' )
		);

		$script_deps = array();
		if ( $this->stripe->is_configured() ) {
			wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', array(), null, true );
			$script_deps[] = 'stripe-js';
		}
		if ( $this->paypal->is_configured() ) {
			$paypal_url = 'https://www.paypal.com/sdk/js?client-id=' . rawurlencode( $this->paypal->get_client_id() ) . '&currency=' . rawurlencode( strtoupper( $payment_settings['currency'] ) ) . '&intent=capture';
			wp_enqueue_script( 'paypal-js', $paypal_url, array(), null, true );
			$script_deps[] = 'paypal-js';
		}

		wp_enqueue_script(
			'hc-registration-form',
			hc_asset_url( 'assets/registration-form.js' ),
			$script_deps,
			hc_asset_version( 'assets/registration-form.js' ),
			true
		);

		wp_localize_script(
			'hc-registration-form',
			'HC_REGISTRATION',
			array(
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'nonce'             => wp_create_nonce( 'hc_registration_form' ),
				'currency'          => strtoupper( $payment_settings['currency'] ),
				'sandbox'           => ! empty( $payment_settings['sandbox_mode'] ),
				'stripePublishable' => $this->stripe->is_configured() ? $this->stripe->get_publishable_key() : '',
				'stripeEnabled'     => $this->stripe->is_configured(),
				'paypalEnabled'     => $this->paypal->is_configured(),
				'paymentsAvailable' => hc_payments_available(),
				'i18n'              => array(
					'required'         => __( 'This field is required.', 'holiday-calendar' ),
					'invalidEmail'     => __( 'Enter a valid email address.', 'holiday-calendar' ),
					'minAge'           => __( 'Student must be at least 5 years old.', 'holiday-calendar' ),
					'selectOne'        => __( 'Please select at least one option.', 'holiday-calendar' ),
					'acceptTerms'      => __( 'Please accept the terms to continue.', 'holiday-calendar' ),
					'invalidDate'      => __( 'Please enter a valid date.', 'holiday-calendar' ),
					'fixFields'        => __( 'Please fix the highlighted fields before continuing.', 'holiday-calendar' ),
					'submitting'       => __( 'Submitting registration…', 'holiday-calendar' ),
					'successTitle'     => __( 'Registration complete!', 'holiday-calendar' ),
					'successMessage'   => __( 'Thank you! Your summer camp registration has been received. We will confirm your spot shortly.', 'holiday-calendar' ),
					'errorGeneric'     => __( 'Something went wrong. Please try again.', 'holiday-calendar' ),
					'step'             => __( 'Step', 'holiday-calendar' ),
					'of'               => __( 'of', 'holiday-calendar' ),
					'back'             => __( 'Back', 'holiday-calendar' ),
					'continue'         => __( 'Continue', 'holiday-calendar' ),
					'submit'           => __( 'Submit registration', 'holiday-calendar' ),
					'paymentsUnavailable' => __( 'Online payments are not configured. Please contact the studio to complete your registration.', 'holiday-calendar' ),
					'selectSession'    => __( 'Please complete student details (camp session) before paying.', 'holiday-calendar' ),
					'paymentRequired'  => __( 'Please complete payment before continuing.', 'holiday-calendar' ),
					'paymentComplete'  => __( 'Payment received. You can continue to confirmation.', 'holiday-calendar' ),
					'processing'       => __( 'Processing payment…', 'holiday-calendar' ),
					'payWithCard'      => __( 'Pay with card', 'holiday-calendar' ),
					'card'             => __( 'Card', 'holiday-calendar' ),
					'paypal'           => __( 'PayPal', 'holiday-calendar' ),
					'amountDue'        => __( 'Amount due today', 'holiday-calendar' ),
					'paymentMethod'    => __( 'Payment method', 'holiday-calendar' ),
				),
			)
		);

		self::$assets_enqueued = true;
	}

	/**
	 * Guard registration AJAX: nonce and rate limit.
	 *
	 * @param string $rate_action Rate limit bucket.
	 */
	private function guard_registration_ajax( $rate_action ) {
		if ( ! check_ajax_referer( 'hc_registration_form', 'nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed. Refresh and try again.', 'holiday-calendar' ) ),
				403
			);
		}
		if ( ! hc_payment_rate_limit( $rate_action, 15, 60 ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Too many requests. Please wait a moment.', 'holiday-calendar' ) ),
				429
			);
		}
	}

	/**
	 * Parse camp session/month from POST and return amount + metadata context.
	 *
	 * @return array|WP_Error
	 */
	private function parse_registration_payment_context() {
		$camp_session = isset( $_POST['camp_session'] ) ? sanitize_key( wp_unslash( $_POST['camp_session'] ) ) : '';
		$camp_month   = isset( $_POST['camp_month'] ) ? sanitize_key( wp_unslash( $_POST['camp_month'] ) ) : '';
		$email        = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$student_name = isset( $_POST['student_full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['student_full_name'] ) ) : '';
		$reference    = isset( $_POST['reference'] ) ? sanitize_text_field( wp_unslash( $_POST['reference'] ) ) : '';

		if ( '' === $reference ) {
			$reference = 'scr_' . wp_generate_password( 10, false, false );
		}

		$amount_cents = hc_registration_calculate_amount( $camp_session, $camp_month );
		if ( is_wp_error( $amount_cents ) ) {
			return $amount_cents;
		}

		$label = hc_registration_amount_due_label( $camp_session, $camp_month );

		return array(
			'camp_session'   => $camp_session,
			'camp_month'     => $camp_month,
			'amount_cents'   => (int) $amount_cents,
			'amount_label'   => $label,
			'email'          => $email,
			'student_name'   => $student_name,
			'reference'      => $reference,
			'tier_label'     => sprintf(
				/* translators: 1: session, 2: month if any */
				__( 'Summer Camp %1$s%2$s', 'holiday-calendar' ),
				ucfirst( $camp_session ),
				$camp_month ? ' — ' . ucfirst( $camp_month ) : ''
			),
		);
	}

	public function ajax_create_stripe_intent() {
		$this->guard_registration_ajax( 'reg_stripe_intent' );

		if ( ! $this->stripe->is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'Stripe is not configured.', 'holiday-calendar' ) ), 400 );
		}

		$ctx = $this->parse_registration_payment_context();
		if ( is_wp_error( $ctx ) ) {
			wp_send_json_error( array( 'message' => $ctx->get_error_message() ), 400 );
		}

		$settings = hc_get_payment_settings();
		$metadata = array(
			'email'        => $ctx['email'],
			'name'         => $ctx['student_name'],
			'reference'    => $ctx['reference'],
			'camp_session' => $ctx['camp_session'],
			'camp_month'   => $ctx['camp_month'],
			'tier_label'   => $ctx['tier_label'],
			'source'       => 'summer_camp_registration',
		);

		$result = $this->stripe->create_payment_intent(
			$ctx['amount_cents'],
			$settings['currency'],
			$metadata
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array_merge(
				$result,
				array(
					'amount_cents' => $ctx['amount_cents'],
					'amount_label' => $ctx['amount_label'],
					'reference'    => $ctx['reference'],
				)
			)
		);
	}

	public function ajax_create_paypal_order() {
		$this->guard_registration_ajax( 'reg_paypal_order' );

		if ( ! $this->paypal->is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'PayPal is not configured.', 'holiday-calendar' ) ), 400 );
		}

		$ctx = $this->parse_registration_payment_context();
		if ( is_wp_error( $ctx ) ) {
			wp_send_json_error( array( 'message' => $ctx->get_error_message() ), 400 );
		}

		$settings = hc_get_payment_settings();
		$metadata = array(
			'email'      => $ctx['email'],
			'name'       => $ctx['student_name'],
			'reference'  => $ctx['reference'],
			'tier_label' => $ctx['tier_label'],
		);

		$result = $this->paypal->create_order(
			$ctx['amount_cents'],
			$settings['currency'],
			$metadata
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array_merge(
				$result,
				array(
					'amount_cents' => $ctx['amount_cents'],
					'amount_label' => $ctx['amount_label'],
					'reference'    => $ctx['reference'],
				)
			)
		);
	}

	public function ajax_capture_paypal_order() {
		$this->guard_registration_ajax( 'reg_paypal_capture' );

		$ctx = $this->parse_registration_payment_context();
		if ( is_wp_error( $ctx ) ) {
			wp_send_json_error( array( 'message' => $ctx->get_error_message() ), 400 );
		}

		$order_id = isset( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : '';
		$result   = $this->paypal->capture_order( $order_id, $ctx['amount_cents'] );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		hc_log_payment(
			array(
				'provider' => 'paypal',
				'id'       => $result['capture_id'] ?: $order_id,
				'amount'   => $ctx['amount_cents'],
				'currency' => hc_get_payment_settings()['currency'],
				'email'    => $ctx['email'],
				'name'     => $ctx['student_name'],
				'tier'     => 'summer_camp',
				'source'   => 'registration_capture',
			)
		);

		wp_send_json_success(
			array(
				'order_id'     => $result['order_id'],
				'message'      => __( 'Payment confirmed.', 'holiday-calendar' ),
				'amount_label' => $ctx['amount_label'],
			)
		);
	}

	public function ajax_submit() {
		if ( ! check_ajax_referer( 'hc_registration_form', 'nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed. Refresh and try again.', 'holiday-calendar' ) ),
				403
			);
		}

		if ( ! hc_payment_rate_limit( 'registration_submit', 5, 300 ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Too many submissions. Please wait a moment.', 'holiday-calendar' ) ),
				429
			);
		}

		$data = hc_sanitize_registration_submission( wp_unslash( $_POST ) );
		if ( is_wp_error( $data ) ) {
			wp_send_json_error(
				array(
					'message' => $data->get_error_message(),
					'fields'  => $data->get_error_data(),
				),
				400
			);
		}

		hc_log_payment(
			array(
				'provider' => $data['payment_provider'],
				'id'       => $data['payment_ref'],
				'amount'   => $data['payment_amount_cents'],
				'currency' => hc_get_payment_settings()['currency'],
				'email'    => $data['email'],
				'name'     => $data['student_full_name'],
				'tier'     => 'summer_camp_' . $data['camp_session'],
				'source'   => 'registration_submit',
			)
		);

		hc_save_registration_submission( $data );
		hc_send_registration_admin_email( $data );

		wp_send_json_success(
			array(
				'message'   => __( 'Registration submitted successfully. Thank you!', 'holiday-calendar' ),
				'reference' => $data['reference'],
			)
		);
	}
}
