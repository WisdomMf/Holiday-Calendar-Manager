<?php
/**
 * Hardcoded payment form markup — fields are fixed, amounts resolved server-side by tier slug.
 *
 * @var array $settings From hc_get_payment_settings().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tiers    = hc_get_payment_tiers();
$currency = strtoupper( $settings['currency'] );
$form_id  = 'hc-payment-' . wp_unique_id();
$default_tier = 'basic';
?>
<div class="hc-payment" id="<?php echo esc_attr( $form_id ); ?>" data-hc-payment>
	<div class="hc-payment__header">
		<h2 class="hc-payment__title"><?php esc_html_e( 'Complete Your Booking', 'holiday-calendar' ); ?></h2>
		<p class="hc-payment__subtitle"><?php esc_html_e( 'Secure checkout powered by Stripe and PayPal.', 'holiday-calendar' ); ?></p>
	</div>

	<div class="hc-payment__alert" role="alert" aria-live="polite" hidden></div>

	<form class="hc-payment__form" novalidate>
		<fieldset class="hc-payment__section">
			<legend><?php esc_html_e( 'Contact details', 'holiday-calendar' ); ?></legend>

			<div class="hc-payment__field">
				<label for="<?php echo esc_attr( $form_id ); ?>-name"><?php esc_html_e( 'Full name', 'holiday-calendar' ); ?> <span class="hc-payment__req" aria-hidden="true">*</span></label>
				<input type="text" id="<?php echo esc_attr( $form_id ); ?>-name" name="name" autocomplete="name" required minlength="2" />
				<span class="hc-payment__error" data-for="name" role="alert"></span>
			</div>

			<div class="hc-payment__field">
				<label for="<?php echo esc_attr( $form_id ); ?>-email"><?php esc_html_e( 'Email address', 'holiday-calendar' ); ?> <span class="hc-payment__req" aria-hidden="true">*</span></label>
				<input type="email" id="<?php echo esc_attr( $form_id ); ?>-email" name="email" autocomplete="email" required />
				<span class="hc-payment__error" data-for="email" role="alert"></span>
			</div>

			<div class="hc-payment__field">
				<label for="<?php echo esc_attr( $form_id ); ?>-phone"><?php esc_html_e( 'Phone (optional)', 'holiday-calendar' ); ?></label>
				<input type="tel" id="<?php echo esc_attr( $form_id ); ?>-phone" name="phone" autocomplete="tel" />
			</div>
		</fieldset>

		<fieldset class="hc-payment__section">
			<legend><?php esc_html_e( 'Booking option', 'holiday-calendar' ); ?></legend>
			<div class="hc-payment__tiers" role="radiogroup" aria-label="<?php esc_attr_e( 'Select a booking tier', 'holiday-calendar' ); ?>">
				<?php foreach ( $tiers as $slug => $tier ) : ?>
					<label class="hc-payment__tier<?php echo $slug === $default_tier ? ' is-selected' : ''; ?>">
						<input type="radio" name="tier" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $slug, $default_tier ); ?> required />
						<span class="hc-payment__tier-body">
							<span class="hc-payment__tier-name"><?php echo esc_html( $tier['label'] ); ?></span>
							<span class="hc-payment__tier-desc"><?php echo esc_html( $tier['description'] ); ?></span>
							<span class="hc-payment__tier-price"><?php echo esc_html( hc_format_money( $tier['amount_cents'], $currency ) ); ?></span>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
			<span class="hc-payment__error" data-for="tier" role="alert"></span>
		</fieldset>

		<div class="hc-payment__field">
			<label for="<?php echo esc_attr( $form_id ); ?>-notes"><?php esc_html_e( 'Notes (optional)', 'holiday-calendar' ); ?></label>
			<textarea id="<?php echo esc_attr( $form_id ); ?>-notes" name="notes" rows="3" maxlength="500"></textarea>
		</div>

		<fieldset class="hc-payment__section hc-payment__section--pay">
			<legend><?php esc_html_e( 'Payment method', 'holiday-calendar' ); ?></legend>

			<div class="hc-payment__methods" role="tablist" aria-label="<?php esc_attr_e( 'Payment method', 'holiday-calendar' ); ?>">
				<?php if ( $this->stripe->is_configured() ) : ?>
					<button type="button" class="hc-payment__method is-active" role="tab" aria-selected="true" data-method="stripe" id="<?php echo esc_attr( $form_id ); ?>-tab-stripe">
						<span class="hc-payment__method-icon" aria-hidden="true">💳</span>
						<?php esc_html_e( 'Card', 'holiday-calendar' ); ?>
					</button>
				<?php endif; ?>
				<?php if ( $this->paypal->is_configured() ) : ?>
					<button type="button" class="hc-payment__method<?php echo ! $this->stripe->is_configured() ? ' is-active' : ''; ?>" role="tab" aria-selected="<?php echo ! $this->stripe->is_configured() ? 'true' : 'false'; ?>" data-method="paypal" id="<?php echo esc_attr( $form_id ); ?>-tab-paypal">
						<span class="hc-payment__method-icon" aria-hidden="true">🅿️</span>
						<?php esc_html_e( 'PayPal', 'holiday-calendar' ); ?>
					</button>
				<?php endif; ?>
				<span class="hc-payment__method-indicator" aria-hidden="true"></span>
			</div>

			<div class="hc-payment__panels">
				<?php if ( $this->stripe->is_configured() ) : ?>
					<div class="hc-payment__panel is-visible" role="tabpanel" data-panel="stripe" aria-labelledby="<?php echo esc_attr( $form_id ); ?>-tab-stripe">
						<p class="hc-payment__pay-field-label"><?php esc_html_e( 'Card details', 'holiday-calendar' ); ?></p>
						<div id="<?php echo esc_attr( $form_id ); ?>-stripe-element" class="hc-payment__stripe-element"></div>
					</div>
				<?php endif; ?>
				<?php if ( $this->paypal->is_configured() ) : ?>
					<div class="hc-payment__panel<?php echo ! $this->stripe->is_configured() ? ' is-visible' : ''; ?>" role="tabpanel" data-panel="paypal" aria-labelledby="<?php echo esc_attr( $form_id ); ?>-tab-paypal">
						<p class="hc-payment__pay-field-label hc-payment__pay-field-label--center"><?php esc_html_e( 'Pay securely with your PayPal account', 'holiday-calendar' ); ?></p>
						<div id="<?php echo esc_attr( $form_id ); ?>-paypal-buttons" class="hc-payment__paypal-buttons"></div>
					</div>
				<?php endif; ?>
			</div>
		</fieldset>

		<div class="hc-payment__summary">
			<div class="hc-payment__summary-row">
				<span><?php esc_html_e( 'Total due today', 'holiday-calendar' ); ?></span>
				<strong class="hc-payment__total" data-total><?php echo esc_html( hc_format_money( $tiers[ $default_tier ]['amount_cents'], $currency ) ); ?></strong>
			</div>
		</div>

		<label class="hc-payment__terms">
			<input type="checkbox" name="terms" value="1" required />
			<span><?php esc_html_e( 'I agree to the booking terms and refund policy.', 'holiday-calendar' ); ?></span>
		</label>
		<span class="hc-payment__error" data-for="terms" role="alert"></span>

		<button type="submit" class="hc-payment__submit" disabled>
			<span class="hc-payment__submit-text"><?php esc_html_e( 'Pay with card', 'holiday-calendar' ); ?></span>
			<span class="hc-payment__spinner" aria-hidden="true" hidden></span>
		</button>
	</form>

	<div class="hc-payment__success" role="status" aria-live="polite" hidden>
		<div class="hc-payment__success-icon" aria-hidden="true">✓</div>
		<h3><?php esc_html_e( 'Payment successful', 'holiday-calendar' ); ?></h3>
		<p class="hc-payment__success-msg"></p>
	</div>
</div>
