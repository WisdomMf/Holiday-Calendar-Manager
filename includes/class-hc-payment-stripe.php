<?php
/**
 * Stripe Payment Intents — server-side only. Secret keys never leave PHP.
 *
 * Requires publishable + secret keys in WP Admin → Payment Form → Payments.
 * PaymentIntents are card-only (no bank, Klarna, Link, or other Stripe tabs).
 *
 * Uses stripe/stripe-php when available via Composer; otherwise wp_remote_post.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HC_Payment_Stripe {

	/**
	 * @var array Payment settings from wp_options.
	 */
	private $settings;

	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Whether Stripe is configured for the current mode.
	 */
	public function is_configured() {
		return '' !== $this->get_secret_key() && '' !== $this->get_publishable_key();
	}

	public function get_publishable_key() {
		return trim( (string) ( $this->settings['stripe_publishable_key'] ?? '' ) );
	}

	private function get_secret_key() {
		return trim( (string) ( $this->settings['stripe_secret_key'] ?? '' ) );
	}

	private function get_webhook_secret() {
		return trim( (string) ( $this->settings['stripe_webhook_secret'] ?? '' ) );
	}

	/**
	 * Create a PaymentIntent with a server-validated amount.
	 *
	 * @param int    $amount_cents Amount in smallest currency unit.
	 * @param string $currency     ISO currency code.
	 * @param array  $metadata     Sanitized metadata.
	 * @return array|WP_Error { client_secret, payment_intent_id }
	 */
	public function create_payment_intent( $amount_cents, $currency, array $metadata = array() ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'hc_stripe_config', __( 'Stripe is not configured.', 'holiday-calendar' ) );
		}

		$amount_cents = absint( $amount_cents );
		if ( $amount_cents < 50 ) {
			return new WP_Error( 'hc_stripe_amount', __( 'Invalid payment amount.', 'holiday-calendar' ) );
		}

		$currency = strtolower( sanitize_text_field( $currency ) );
		if ( ! preg_match( '/^[a-z]{3}$/', $currency ) ) {
			return new WP_Error( 'hc_stripe_currency', __( 'Invalid currency.', 'holiday-calendar' ) );
		}

		$body = array(
			'amount'                      => $amount_cents,
			'currency'                    => $currency,
			'payment_method_types[0]'     => 'card',
			'description'                 => isset( $metadata['tier_label'] ) ? $metadata['tier_label'] : '',
		);

		foreach ( $metadata as $key => $value ) {
			$body[ 'metadata[' . sanitize_key( $key ) . ']' ] = sanitize_text_field( (string) $value );
		}

		$idempotency_key = 'hc_' . wp_generate_password( 32, false );

		if ( $this->has_sdk() ) {
			return $this->create_intent_via_sdk( $body, $idempotency_key );
		}

		return $this->stripe_request( 'POST', 'payment_intents', $body, $idempotency_key );
	}

	/**
	 * Retrieve and verify a PaymentIntent succeeded.
	 *
	 * @param string $payment_intent_id Stripe PI id.
	 * @param int    $expected_amount   Expected amount in cents.
	 * @return array|WP_Error PaymentIntent data.
	 */
	public function verify_payment_intent( $payment_intent_id, $expected_amount ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'hc_stripe_config', __( 'Stripe is not configured.', 'holiday-calendar' ) );
		}

		$payment_intent_id = sanitize_text_field( $payment_intent_id );
		if ( ! preg_match( '/^pi_[a-zA-Z0-9]+$/', $payment_intent_id ) ) {
			return new WP_Error( 'hc_stripe_id', __( 'Invalid payment reference.', 'holiday-calendar' ) );
		}

		if ( $this->has_sdk() ) {
			$result = $this->retrieve_intent_via_sdk( $payment_intent_id );
		} else {
			$result = $this->stripe_request( 'GET', 'payment_intents/' . $payment_intent_id );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( 'succeeded' !== ( $result['status'] ?? '' ) ) {
			return new WP_Error( 'hc_stripe_status', __( 'Payment has not been completed.', 'holiday-calendar' ) );
		}

		if ( absint( $result['amount'] ?? 0 ) !== absint( $expected_amount ) ) {
			return new WP_Error( 'hc_stripe_amount_mismatch', __( 'Payment amount mismatch.', 'holiday-calendar' ) );
		}

		return $result;
	}

	/**
	 * Handle Stripe webhook (payment_intent.succeeded).
	 *
	 * @param string $payload   Raw body.
	 * @param string $sig_header Stripe-Signature header.
	 * @return true|WP_Error
	 */
	public function handle_webhook( $payload, $sig_header ) {
		$webhook_secret = $this->get_webhook_secret();
		if ( '' === $webhook_secret ) {
			return new WP_Error( 'hc_webhook_config', __( 'Stripe webhook secret not configured.', 'holiday-calendar' ) );
		}

		if ( $this->has_sdk() ) {
			try {
				$event = \Stripe\Webhook::constructEvent( $payload, $sig_header, $webhook_secret );
			} catch ( Exception $e ) {
				return new WP_Error( 'hc_webhook_sig', $e->getMessage() );
			}
		} else {
			$event = $this->construct_webhook_event( $payload, $sig_header, $webhook_secret );
			if ( is_wp_error( $event ) ) {
				return $event;
			}
		}

		if ( 'payment_intent.succeeded' === $event['type'] ) {
			$intent = $event['data']['object'];
			hc_log_payment(
				array(
					'provider' => 'stripe',
					'id'       => $intent['id'] ?? '',
					'amount'   => isset( $intent['amount'] ) ? absint( $intent['amount'] ) : 0,
					'currency' => $intent['currency'] ?? '',
					'email'    => $intent['metadata']['email'] ?? '',
					'name'     => $intent['metadata']['name'] ?? '',
					'tier'     => $intent['metadata']['tier'] ?? '',
					'source'   => 'webhook',
				)
			);
		}

		return true;
	}

	private function has_sdk() {
		static $loaded = null;
		if ( null === $loaded ) {
			$autoload = HC_PATH . 'vendor/autoload.php';
			if ( file_exists( $autoload ) ) {
				require_once $autoload;
			}
			$loaded = class_exists( '\Stripe\StripeClient' );
		}
		return $loaded;
	}

	private function create_intent_via_sdk( array $body, $idempotency_key ) {
		try {
			$client = new \Stripe\StripeClient( $this->get_secret_key() );
			$params = $this->flatten_stripe_body( $body );
			$intent = $client->paymentIntents->create(
				$params,
				array( 'idempotency_key' => $idempotency_key )
			);
			return array(
				'client_secret'     => $intent->client_secret,
				'payment_intent_id' => $intent->id,
			);
		} catch ( Exception $e ) {
			return new WP_Error( 'hc_stripe_api', $e->getMessage() );
		}
	}

	private function retrieve_intent_via_sdk( $payment_intent_id ) {
		try {
			$client = new \Stripe\StripeClient( $this->get_secret_key() );
			$intent = $client->paymentIntents->retrieve( $payment_intent_id );
			return array(
				'id'       => $intent->id,
				'status'   => $intent->status,
				'amount'   => $intent->amount,
				'currency' => $intent->currency,
				'metadata' => (array) $intent->metadata,
			);
		} catch ( Exception $e ) {
			return new WP_Error( 'hc_stripe_api', $e->getMessage() );
		}
	}

	/**
	 * @return array|WP_Error
	 */
	private function stripe_request( $method, $endpoint, array $body = array(), $idempotency_key = '' ) {
		$url  = 'https://api.stripe.com/v1/' . ltrim( $endpoint, '/' );
		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->get_secret_key(),
			),
		);

		if ( $idempotency_key ) {
			$args['headers']['Idempotency-Key'] = $idempotency_key;
		}

		if ( 'POST' === $method && ! empty( $body ) ) {
			$args['body'] = $body;
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 || isset( $data['error'] ) ) {
			$message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Stripe request failed.', 'holiday-calendar' );
			return new WP_Error( 'hc_stripe_api', $message );
		}

		if ( 'payment_intents' === basename( $endpoint ) && 'POST' === $method ) {
			return array(
				'client_secret'     => $data['client_secret'] ?? '',
				'payment_intent_id' => $data['id'] ?? '',
			);
		}

		return $data;
	}

	/**
	 * Convert bracket-notation body keys to nested arrays for SDK.
	 */
	private function flatten_stripe_body( array $body ) {
		$params = array(
			'amount'   => (int) ( $body['amount'] ?? 0 ),
			'currency' => $body['currency'] ?? 'usd',
		);

		$params['payment_method_types'] = array( 'card' );

		if ( ! empty( $body['description'] ) ) {
			$params['description'] = $body['description'];
		}

		$params['metadata'] = array();
		foreach ( $body as $key => $value ) {
			if ( preg_match( '/^metadata\[(.+)\]$/', $key, $m ) ) {
				$params['metadata'][ $m[1] ] = $value;
			}
		}

		return $params;
	}

	/**
	 * Minimal webhook signature verification without SDK.
	 *
	 * @return array|WP_Error Decoded event.
	 */
	private function construct_webhook_event( $payload, $sig_header, $secret ) {
		$parts = array();
		foreach ( explode( ',', $sig_header ) as $item ) {
			$pair = explode( '=', trim( $item ), 2 );
			if ( 2 === count( $pair ) ) {
				$parts[ $pair[0] ][] = $pair[1];
			}
		}

		if ( empty( $parts['t'] ) || empty( $parts['v1'] ) ) {
			return new WP_Error( 'hc_webhook_sig', __( 'Invalid Stripe signature header.', 'holiday-calendar' ) );
		}

		$timestamp = $parts['t'][0];
		$signed    = $timestamp . '.' . $payload;
		$expected  = hash_hmac( 'sha256', $signed, $secret );

		$valid = false;
		foreach ( $parts['v1'] as $sig ) {
			if ( hash_equals( $expected, $sig ) ) {
				$valid = true;
				break;
			}
		}

		if ( ! $valid ) {
			return new WP_Error( 'hc_webhook_sig', __( 'Stripe webhook signature verification failed.', 'holiday-calendar' ) );
		}

		if ( absint( time() ) - absint( $timestamp ) > 300 ) {
			return new WP_Error( 'hc_webhook_sig', __( 'Stripe webhook timestamp too old.', 'holiday-calendar' ) );
		}

		$event = json_decode( $payload, true );
		if ( ! is_array( $event ) ) {
			return new WP_Error( 'hc_webhook_payload', __( 'Invalid webhook payload.', 'holiday-calendar' ) );
		}

		return $event;
	}
}
