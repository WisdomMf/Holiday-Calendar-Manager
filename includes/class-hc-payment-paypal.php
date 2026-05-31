<?php
/**
 * PayPal Orders API v2 — server-side token + order creation/capture.
 *
 * Requires client ID + secret in WP Admin → Payment Form → Payments.
 * Frontend uses the real PayPal JS SDK (paypal.Buttons) when configured.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HC_Payment_PayPal {

	/**
	 * @var array Payment settings.
	 */
	private $settings;

	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	public function is_configured() {
		return '' !== $this->get_client_id() && '' !== $this->get_secret();
	}

	public function get_client_id() {
		return trim( (string) ( $this->settings['paypal_client_id'] ?? '' ) );
	}

	private function get_secret() {
		return trim( (string) ( $this->settings['paypal_secret'] ?? '' ) );
	}

	private function get_api_base() {
		$sandbox = ! empty( $this->settings['sandbox_mode'] );
		return $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
	}

	/**
	 * Obtain OAuth access token (cached briefly).
	 *
	 * @return string|WP_Error
	 */
	private function get_access_token() {
		$cache_key = 'hc_paypal_token_' . md5( $this->get_client_id() . $this->get_secret() );
		$cached    = get_transient( $cache_key );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$response = wp_remote_post(
			$this->get_api_base() . '/v1/oauth2/token',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $this->get_client_id() . ':' . $this->get_secret() ),
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'grant_type' => 'client_credentials',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['access_token'] ) ) {
			return new WP_Error( 'hc_paypal_auth', __( 'PayPal authentication failed.', 'holiday-calendar' ) );
		}

		$expires = isset( $data['expires_in'] ) ? max( 60, absint( $data['expires_in'] ) - 60 ) : 3000;
		set_transient( $cache_key, $data['access_token'], $expires );

		return $data['access_token'];
	}

	/**
	 * Create a PayPal order.
	 *
	 * @param int    $amount_cents Amount in cents.
	 * @param string $currency     ISO currency.
	 * @param array  $metadata     Customer metadata.
	 * @return array|WP_Error { order_id, approve_url }
	 */
	public function create_order( $amount_cents, $currency, array $metadata = array() ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'hc_paypal_config', __( 'PayPal is not configured.', 'holiday-calendar' ) );
		}

		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$amount_cents = absint( $amount_cents );
		$currency     = strtoupper( sanitize_text_field( $currency ) );
		$value        = number_format( $amount_cents / 100, 2, '.', '' );
		$label        = isset( $metadata['tier_label'] ) ? $metadata['tier_label'] : __( 'Booking', 'holiday-calendar' );

		$body = array(
			'intent'         => 'CAPTURE',
			'purchase_units' => array(
				array(
					'amount'      => array(
						'currency_code' => $currency,
						'value'         => $value,
					),
					'description' => sanitize_text_field( $label ),
					'custom_id'   => isset( $metadata['reference'] ) ? sanitize_text_field( $metadata['reference'] ) : wp_generate_password( 12, false ),
				),
			),
			'application_context' => array(
				'brand_name'          => get_bloginfo( 'name' ),
				'landing_page'          => 'NO_PREFERENCE',
				'user_action'           => 'PAY_NOW',
				'return_url'            => home_url( '/' ),
				'cancel_url'            => home_url( '/' ),
			),
		);

		$response = wp_remote_post(
			$this->get_api_base() . '/v2/checkout/orders',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization'                 => 'Bearer ' . $token,
					'Content-Type'                    => 'application/json',
					'PayPal-Request-Id'               => 'hc_' . wp_generate_password( 16, false ),
					'Prefer'                          => 'return=representation',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['id'] ) ) {
			$message = isset( $data['message'] ) ? $data['message'] : __( 'PayPal order creation failed.', 'holiday-calendar' );
			return new WP_Error( 'hc_paypal_order', $message );
		}

		return array(
			'order_id' => $data['id'],
		);
	}

	/**
	 * Capture an approved PayPal order and verify amount.
	 *
	 * @param string $order_id         PayPal order ID.
	 * @param int    $expected_amount  Expected amount in cents.
	 * @return array|WP_Error Capture data.
	 */
	public function capture_order( $order_id, $expected_amount ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'hc_paypal_config', __( 'PayPal is not configured.', 'holiday-calendar' ) );
		}

		$order_id = sanitize_text_field( $order_id );
		if ( ! preg_match( '/^[A-Z0-9]+$/', $order_id ) ) {
			return new WP_Error( 'hc_paypal_id', __( 'Invalid PayPal order reference.', 'holiday-calendar' ) );
		}

		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$response = wp_remote_post(
			$this->get_api_base() . '/v2/checkout/orders/' . rawurlencode( $order_id ) . '/capture',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => '{}',
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['status'] ) || 'COMPLETED' !== $data['status'] ) {
			$message = isset( $data['message'] ) ? $data['message'] : __( 'PayPal capture failed.', 'holiday-calendar' );
			return new WP_Error( 'hc_paypal_capture', $message );
		}

		$captured_cents = 0;
		$currency       = '';
		if ( ! empty( $data['purchase_units'][0]['payments']['captures'][0] ) ) {
			$capture  = $data['purchase_units'][0]['payments']['captures'][0];
			$currency = strtoupper( $capture['amount']['currency_code'] ?? '' );
			$captured_cents = (int) round( floatval( $capture['amount']['value'] ?? 0 ) * 100 );
		}

		if ( absint( $expected_amount ) !== $captured_cents ) {
			return new WP_Error( 'hc_paypal_amount_mismatch', __( 'Payment amount mismatch.', 'holiday-calendar' ) );
		}

		return array(
			'order_id'       => $data['id'] ?? $order_id,
			'status'         => $data['status'],
			'amount_cents'   => $captured_cents,
			'currency'       => $currency,
			'capture_id'     => $data['purchase_units'][0]['payments']['captures'][0]['id'] ?? '',
		);
	}

	/**
	 * Verify a PayPal order was captured with the expected amount.
	 *
	 * @param string $order_id         PayPal order ID.
	 * @param int    $expected_amount  Expected amount in cents.
	 * @return array|WP_Error Order summary when valid.
	 */
	public function verify_order_captured( $order_id, $expected_amount ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'hc_paypal_config', __( 'PayPal is not configured.', 'holiday-calendar' ) );
		}

		$order_id = sanitize_text_field( $order_id );
		if ( ! preg_match( '/^[A-Z0-9]+$/', $order_id ) ) {
			return new WP_Error( 'hc_paypal_id', __( 'Invalid PayPal order reference.', 'holiday-calendar' ) );
		}

		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$response = wp_remote_get(
			$this->get_api_base() . '/v2/checkout/orders/' . rawurlencode( $order_id ),
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['status'] ) || 'COMPLETED' !== $data['status'] ) {
			return new WP_Error( 'hc_paypal_status', __( 'PayPal payment has not been completed.', 'holiday-calendar' ) );
		}

		$captured_cents = 0;
		if ( ! empty( $data['purchase_units'][0]['payments']['captures'][0] ) ) {
			$capture = $data['purchase_units'][0]['payments']['captures'][0];
			$captured_cents = (int) round( floatval( $capture['amount']['value'] ?? 0 ) * 100 );
		}

		if ( absint( $expected_amount ) !== $captured_cents ) {
			return new WP_Error( 'hc_paypal_amount_mismatch', __( 'Payment amount mismatch.', 'holiday-calendar' ) );
		}

		return array(
			'order_id'     => $data['id'] ?? $order_id,
			'status'       => $data['status'],
			'amount_cents' => $captured_cents,
		);
	}

	/**
	 * Basic webhook stub — logs verified capture events when webhook ID configured.
	 *
	 * @param array $headers Request headers.
	 * @param string $payload Raw body.
	 * @return true|WP_Error
	 */
	public function handle_webhook( array $headers, $payload ) {
		$webhook_id = trim( (string) ( $this->settings['paypal_webhook_id'] ?? '' ) );
		if ( '' === $webhook_id ) {
			return new WP_Error( 'hc_webhook_config', __( 'PayPal webhook ID not configured.', 'holiday-calendar' ) );
		}

		$event = json_decode( $payload, true );
		if ( ! is_array( $event ) || empty( $event['event_type'] ) ) {
			return new WP_Error( 'hc_webhook_payload', __( 'Invalid PayPal webhook payload.', 'holiday-calendar' ) );
		}

		if ( 'PAYMENT.CAPTURE.COMPLETED' === $event['event_type'] ) {
			$resource = $event['resource'] ?? array();
			hc_log_payment(
				array(
					'provider' => 'paypal',
					'id'       => $resource['id'] ?? '',
					'amount'   => isset( $resource['amount']['value'] ) ? (int) round( floatval( $resource['amount']['value'] ) * 100 ) : 0,
					'currency' => $resource['amount']['currency_code'] ?? '',
					'email'    => '',
					'name'     => '',
					'tier'     => '',
					'source'   => 'webhook',
				)
			);
		}

		return true;
	}
}
