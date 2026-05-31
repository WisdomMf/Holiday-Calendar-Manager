<?php
/**
 * Summer Camp Registration form markup.
 *
 * @var array  $settings From hc_get_registration_settings().
 * @var string $form_id  Unique form instance ID.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$referral_labels = array(
	'social_media'   => __( 'Social Media', 'holiday-calendar' ),
	'friend_family'  => __( 'Friend / Family', 'holiday-calendar' ),
	'event'          => __( 'Event', 'holiday-calendar' ),
	'google'         => __( 'Google', 'holiday-calendar' ),
	'other'          => __( 'Other', 'holiday-calendar' ),
);
?>
<div class="hc-reg" id="<?php echo esc_attr( $form_id ); ?>" data-hc-registration>
	<div class="hc-reg__card">
		<div class="hc-reg__alert" role="alert" aria-live="polite" hidden></div>

		<form class="hc-reg__form" novalidate>
			<!-- Step 1: Student Details -->
			<div class="hc-reg__panel is-active" data-panel="1" aria-labelledby="<?php echo esc_attr( $form_id ); ?>-heading-1">
				<h2 class="hc-reg__step-title" id="<?php echo esc_attr( $form_id ); ?>-heading-1"><?php esc_html_e( 'Student Details', 'holiday-calendar' ); ?></h2>

				<div class="hc-reg__grid hc-reg__grid--2">
					<div class="hc-reg__field">
						<label for="<?php echo esc_attr( $form_id ); ?>-student-name">
							<?php esc_html_e( 'Full Name', 'holiday-calendar' ); ?>
							<span class="hc-reg__req" aria-hidden="true">*</span>
						</label>
						<input type="text" id="<?php echo esc_attr( $form_id ); ?>-student-name" name="student_full_name" autocomplete="name" required minlength="2" />
						<span class="hc-reg__error" data-for="student_full_name" role="alert"></span>
					</div>
					<div class="hc-reg__field">
						<label for="<?php echo esc_attr( $form_id ); ?>-student-dob"><?php esc_html_e( 'Date of Birth', 'holiday-calendar' ); ?></label>
						<input type="date" id="<?php echo esc_attr( $form_id ); ?>-student-dob" name="student_dob" />
						<span class="hc-reg__error" data-for="student_dob" role="alert"></span>
					</div>
				</div>

				<div class="hc-reg__grid hc-reg__grid--2">
					<div class="hc-reg__field">
						<label for="<?php echo esc_attr( $form_id ); ?>-student-age">
							<?php esc_html_e( 'Age', 'holiday-calendar' ); ?>
							<span class="hc-reg__req" aria-hidden="true">*</span>
							<span class="hc-reg__hint"><?php esc_html_e( 'Min 5', 'holiday-calendar' ); ?></span>
						</label>
						<input type="number" id="<?php echo esc_attr( $form_id ); ?>-student-age" name="student_age" min="5" max="18" required inputmode="numeric" />
						<span class="hc-reg__error" data-for="student_age" role="alert"></span>
					</div>
					<fieldset class="hc-reg__field hc-reg__field--radios">
						<legend>
							<?php esc_html_e( 'Gender', 'holiday-calendar' ); ?>
							<span class="hc-reg__req" aria-hidden="true">*</span>
						</legend>
						<div class="hc-reg__radio-group">
							<label class="hc-reg__radio-pill">
								<input type="radio" name="student_gender" value="male" required />
								<span><?php esc_html_e( 'Male', 'holiday-calendar' ); ?></span>
							</label>
							<label class="hc-reg__radio-pill">
								<input type="radio" name="student_gender" value="female" />
								<span><?php esc_html_e( 'Female', 'holiday-calendar' ); ?></span>
							</label>
						</div>
						<span class="hc-reg__error" data-for="student_gender" role="alert"></span>
					</fieldset>
				</div>

				<div class="hc-reg__grid hc-reg__grid--2">
					<fieldset class="hc-reg__field hc-reg__field--radios">
						<legend>
							<?php esc_html_e( 'Camp Session', 'holiday-calendar' ); ?>
							<span class="hc-reg__req" aria-hidden="true">*</span>
						</legend>
						<div class="hc-reg__radio-group">
							<label class="hc-reg__radio-pill">
								<input type="radio" name="camp_session" value="weekly" required data-conditional-trigger="camp-session" />
								<span><?php esc_html_e( 'Weekly', 'holiday-calendar' ); ?></span>
							</label>
							<label class="hc-reg__radio-pill">
								<input type="radio" name="camp_session" value="monthly" data-conditional-trigger="camp-session" />
								<span><?php esc_html_e( 'Monthly', 'holiday-calendar' ); ?></span>
							</label>
						</div>
						<span class="hc-reg__error" data-for="camp_session" role="alert"></span>
					</fieldset>

					<div class="hc-reg__reveal" data-reveal="camp-month" hidden>
						<fieldset class="hc-reg__field hc-reg__field--radios">
							<legend>
								<?php esc_html_e( 'Camp Month', 'holiday-calendar' ); ?>
								<span class="hc-reg__req" aria-hidden="true">*</span>
							</legend>
							<div class="hc-reg__radio-group">
								<label class="hc-reg__radio-pill">
									<input type="radio" name="camp_month" value="june" />
									<span><?php esc_html_e( 'June', 'holiday-calendar' ); ?></span>
								</label>
								<label class="hc-reg__radio-pill">
									<input type="radio" name="camp_month" value="july" />
									<span><?php esc_html_e( 'July', 'holiday-calendar' ); ?></span>
								</label>
								<label class="hc-reg__radio-pill">
									<input type="radio" name="camp_month" value="both" />
									<span><?php esc_html_e( 'Both', 'holiday-calendar' ); ?></span>
								</label>
							</div>
							<span class="hc-reg__error" data-for="camp_month" role="alert"></span>
						</fieldset>
					</div>
				</div>

				<fieldset class="hc-reg__field hc-reg__field--radios">
					<legend>
						<?php esc_html_e( 'Is there any specific thing we need to know about your child?', 'holiday-calendar' ); ?>
						<span class="hc-reg__req" aria-hidden="true">*</span>
					</legend>
					<div class="hc-reg__radio-group">
						<label class="hc-reg__radio-pill">
							<input type="radio" name="special_needs" value="yes" required data-conditional-trigger="special-needs" />
							<span><?php esc_html_e( 'Yes', 'holiday-calendar' ); ?></span>
						</label>
						<label class="hc-reg__radio-pill">
							<input type="radio" name="special_needs" value="no" data-conditional-trigger="special-needs" />
							<span><?php esc_html_e( 'No', 'holiday-calendar' ); ?></span>
						</label>
					</div>
					<span class="hc-reg__error" data-for="special_needs" role="alert"></span>
				</fieldset>

				<div class="hc-reg__reveal" data-reveal="special-needs" hidden>
					<div class="hc-reg__field">
						<label for="<?php echo esc_attr( $form_id ); ?>-special-details">
							<?php esc_html_e( 'Please tell us more', 'holiday-calendar' ); ?>
							<span class="hc-reg__req" aria-hidden="true">*</span>
						</label>
						<textarea id="<?php echo esc_attr( $form_id ); ?>-special-details" name="special_needs_details" rows="4" maxlength="1000"></textarea>
						<span class="hc-reg__error" data-for="special_needs_details" role="alert"></span>
					</div>
				</div>
			</div>

			<!-- Step 2: Guardian Details -->
			<div class="hc-reg__panel" data-panel="2" hidden aria-labelledby="<?php echo esc_attr( $form_id ); ?>-heading-2">
				<h2 class="hc-reg__step-title" id="<?php echo esc_attr( $form_id ); ?>-heading-2"><?php esc_html_e( 'Guardian Details', 'holiday-calendar' ); ?></h2>

				<div class="hc-reg__grid hc-reg__grid--2">
					<div class="hc-reg__field">
						<label for="<?php echo esc_attr( $form_id ); ?>-mother-name">
							<?php esc_html_e( "Mother's Name", 'holiday-calendar' ); ?>
							<span class="hc-reg__req" aria-hidden="true">*</span>
						</label>
						<input type="text" id="<?php echo esc_attr( $form_id ); ?>-mother-name" name="mother_name" required autocomplete="given-name" />
						<span class="hc-reg__error" data-for="mother_name" role="alert"></span>
					</div>
					<div class="hc-reg__field">
						<label for="<?php echo esc_attr( $form_id ); ?>-father-name">
							<?php esc_html_e( "Father's Name", 'holiday-calendar' ); ?>
							<span class="hc-reg__req" aria-hidden="true">*</span>
						</label>
						<input type="text" id="<?php echo esc_attr( $form_id ); ?>-father-name" name="father_name" required />
						<span class="hc-reg__error" data-for="father_name" role="alert"></span>
					</div>
					<div class="hc-reg__field">
						<label for="<?php echo esc_attr( $form_id ); ?>-mother-phone">
							<?php esc_html_e( "Mother's Phone", 'holiday-calendar' ); ?>
							<span class="hc-reg__req" aria-hidden="true">*</span>
						</label>
						<input type="tel" id="<?php echo esc_attr( $form_id ); ?>-mother-phone" name="mother_phone" required autocomplete="tel" />
						<span class="hc-reg__error" data-for="mother_phone" role="alert"></span>
					</div>
					<div class="hc-reg__field">
						<label for="<?php echo esc_attr( $form_id ); ?>-father-phone">
							<?php esc_html_e( "Father's Phone", 'holiday-calendar' ); ?>
							<span class="hc-reg__req" aria-hidden="true">*</span>
						</label>
						<input type="tel" id="<?php echo esc_attr( $form_id ); ?>-father-phone" name="father_phone" required autocomplete="tel" />
						<span class="hc-reg__error" data-for="father_phone" role="alert"></span>
					</div>
				</div>

				<div class="hc-reg__grid hc-reg__grid--2">
					<div class="hc-reg__field hc-reg__field--span-2">
						<label for="<?php echo esc_attr( $form_id ); ?>-address">
							<?php esc_html_e( 'Address', 'holiday-calendar' ); ?>
							<span class="hc-reg__req" aria-hidden="true">*</span>
						</label>
						<textarea id="<?php echo esc_attr( $form_id ); ?>-address" name="address" rows="3" required autocomplete="street-address"></textarea>
						<span class="hc-reg__error" data-for="address" role="alert"></span>
					</div>
					<div class="hc-reg__field">
						<label for="<?php echo esc_attr( $form_id ); ?>-email">
							<?php esc_html_e( 'Email Address', 'holiday-calendar' ); ?>
							<span class="hc-reg__req" aria-hidden="true">*</span>
						</label>
						<input type="email" id="<?php echo esc_attr( $form_id ); ?>-email" name="email" required autocomplete="email" />
						<span class="hc-reg__error" data-for="email" role="alert"></span>
					</div>
				</div>
			</div>

			<!-- Step 3: Emergency Contact -->
			<div class="hc-reg__panel" data-panel="3" hidden aria-labelledby="<?php echo esc_attr( $form_id ); ?>-heading-3">
				<h2 class="hc-reg__step-title" id="<?php echo esc_attr( $form_id ); ?>-heading-3"><?php esc_html_e( 'Emergency Contact', 'holiday-calendar' ); ?></h2>

				<fieldset class="hc-reg__field hc-reg__field--radios">
					<legend>
						<?php esc_html_e( 'Is the emergency contact the same as the guardian details?', 'holiday-calendar' ); ?>
						<span class="hc-reg__req" aria-hidden="true">*</span>
					</legend>
					<div class="hc-reg__radio-group hc-reg__radio-group--stack">
						<label class="hc-reg__radio-pill hc-reg__radio-pill--wide">
							<input type="radio" name="emergency_same" value="yes" required data-conditional-trigger="emergency-same" />
							<span><?php esc_html_e( 'Yes — use guardian details above', 'holiday-calendar' ); ?></span>
						</label>
						<label class="hc-reg__radio-pill hc-reg__radio-pill--wide">
							<input type="radio" name="emergency_same" value="no" data-conditional-trigger="emergency-same" />
							<span><?php esc_html_e( 'No — provide alternate contact', 'holiday-calendar' ); ?></span>
						</label>
					</div>
					<span class="hc-reg__error" data-for="emergency_same" role="alert"></span>
				</fieldset>

				<div class="hc-reg__reveal" data-reveal="emergency-alt" hidden>
					<div class="hc-reg__grid hc-reg__grid--2">
						<div class="hc-reg__field">
							<label for="<?php echo esc_attr( $form_id ); ?>-emergency-name">
								<?php esc_html_e( 'Emergency Contact Name', 'holiday-calendar' ); ?>
								<span class="hc-reg__req" aria-hidden="true">*</span>
							</label>
							<input type="text" id="<?php echo esc_attr( $form_id ); ?>-emergency-name" name="emergency_name" />
							<span class="hc-reg__error" data-for="emergency_name" role="alert"></span>
						</div>
						<div class="hc-reg__field">
							<label for="<?php echo esc_attr( $form_id ); ?>-emergency-mobile">
								<?php esc_html_e( 'Mobile', 'holiday-calendar' ); ?>
								<span class="hc-reg__optional"><?php esc_html_e( 'Optional', 'holiday-calendar' ); ?></span>
							</label>
							<input type="tel" id="<?php echo esc_attr( $form_id ); ?>-emergency-mobile" name="emergency_mobile" autocomplete="tel" />
						</div>
					</div>
				</div>

				<div class="hc-reg__notice hc-reg__notice--whatsapp">
					<?php if ( ! empty( $settings['whatsapp_qr_url'] ) ) : ?>
						<div class="hc-reg__whatsapp-qr">
							<img src="<?php echo esc_url( $settings['whatsapp_qr_url'] ); ?>" alt="<?php esc_attr_e( 'WhatsApp QR Code', 'holiday-calendar' ); ?>" width="72" height="72" loading="lazy" />
						</div>
					<?php else : ?>
						<div class="hc-reg__whatsapp-icon" aria-hidden="true">
							<svg width="40" height="40" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.75.75 0 0 0 .917.917l4.458-1.495A11.945 11.945 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.75a9.714 9.714 0 0 1-4.988-1.378l-.357-.213-3.02 1.013 1.013-3.02-.213-.357A9.714 9.714 0 0 1 2.25 12C2.25 6.615 6.615 2.25 12 2.25S21.75 6.615 21.75 12 17.385 21.75 12 21.75z"/></svg>
						</div>
					<?php endif; ?>
					<div>
						<strong><?php esc_html_e( 'WhatsApp Communication — Mandatory', 'holiday-calendar' ); ?></strong>
						<p>
							<?php esc_html_e( 'During the Summer Camp, parents/guardians are required to communicate with the studio via WhatsApp.', 'holiday-calendar' ); ?>
							<?php if ( ! empty( $settings['whatsapp_number'] ) ) : ?>
								<br />
								<?php esc_html_e( 'Contact us:', 'holiday-calendar' ); ?>
								<strong><?php echo esc_html( $settings['whatsapp_number'] ); ?></strong>
							<?php endif; ?>
						</p>
					</div>
				</div>

				<div class="hc-reg__field">
					<label for="<?php echo esc_attr( $form_id ); ?>-notes">
						<?php esc_html_e( 'Additional Notes', 'holiday-calendar' ); ?>
						<span class="hc-reg__optional"><?php esc_html_e( 'Optional', 'holiday-calendar' ); ?></span>
					</label>
					<textarea id="<?php echo esc_attr( $form_id ); ?>-notes" name="additional_notes" rows="3" maxlength="500"></textarea>
				</div>
			</div>

			<!-- Step 4: Payment -->
			<div class="hc-reg__panel" data-panel="4" hidden aria-labelledby="<?php echo esc_attr( $form_id ); ?>-heading-4">
				<h2 class="hc-reg__step-title" id="<?php echo esc_attr( $form_id ); ?>-heading-4"><?php esc_html_e( 'Payment', 'holiday-calendar' ); ?></h2>

				<?php if ( ! $payments_available ) : ?>
					<div class="hc-reg__notice hc-reg__notice--warn" role="alert">
						<p><?php esc_html_e( 'Online payments are not configured. Please contact the studio to complete your registration.', 'holiday-calendar' ); ?></p>
					</div>
				<?php else : ?>
					<div class="hc-reg__reveal is-visible" data-reveal="payment-weekly">
						<div class="hc-reg__notice hc-reg__notice--gold">
							<strong><?php esc_html_e( 'Weekly Session — $350/week', 'holiday-calendar' ); ?></strong>
							<p><?php esc_html_e( 'Full payment of $350 per week is required at registration to secure your child\'s spot. All payments are non-refundable.', 'holiday-calendar' ); ?></p>
						</div>
					</div>

					<div class="hc-reg__reveal" data-reveal="payment-monthly" hidden>
						<div class="hc-reg__notice hc-reg__notice--gold">
							<strong><?php esc_html_e( 'Monthly Session — $500 deposit (50%)', 'holiday-calendar' ); ?></strong>
							<p><?php esc_html_e( 'A 50% ($500) deposit is due now; the remaining balance is due within one week of the camp start date. All payments are non-refundable.', 'holiday-calendar' ); ?></p>
							<p><?php esc_html_e( 'If booking both June & July, the deposit is $1,000. The remaining $500 for each session is due after the first week of that month.', 'holiday-calendar' ); ?></p>
						</div>
					</div>

					<div class="hc-reg__field hc-reg__field--payment">
						<div class="hc-reg__pay-summary">
							<div class="hc-reg__pay-summary-main">
								<span class="hc-reg__pay-summary-label"><?php esc_html_e( 'Amount due today', 'holiday-calendar' ); ?></span>
								<strong class="hc-reg__pay-amount" data-reg-amount-due>—</strong>
							</div>
							<p class="hc-reg__pay-summary-hint"><?php esc_html_e( 'Complete payment below, then continue to review and submit.', 'holiday-calendar' ); ?></p>
						</div>

						<div class="hc-reg__pay-status" data-reg-pay-status hidden role="status">
							<span class="hc-reg__pay-status-icon" aria-hidden="true">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
							</span>
							<span class="hc-reg__pay-status-text" data-reg-pay-status-text></span>
						</div>
						<span class="hc-reg__error" data-for="payment" role="alert"></span>
					</div>

					<input type="hidden" name="payment_provider" value="" />
					<input type="hidden" name="payment_ref" value="" />

					<fieldset class="hc-reg__pay-section">
						<legend><?php esc_html_e( 'Payment method', 'holiday-calendar' ); ?></legend>

						<div class="hc-reg__pay-methods" role="tablist" aria-label="<?php esc_attr_e( 'Payment method', 'holiday-calendar' ); ?>" data-reg-pay-methods>
							<?php if ( $stripe_enabled ) : ?>
								<button type="button" class="hc-reg__pay-method is-active" role="tab" aria-selected="true" data-method="stripe" id="<?php echo esc_attr( $form_id ); ?>-tab-stripe">
									<svg class="hc-reg__pay-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
									<?php esc_html_e( 'Card', 'holiday-calendar' ); ?>
								</button>
							<?php endif; ?>
							<?php if ( $paypal_enabled ) : ?>
								<button type="button" class="hc-reg__pay-method<?php echo ! $stripe_enabled ? ' is-active' : ''; ?>" role="tab" aria-selected="<?php echo ! $stripe_enabled ? 'true' : 'false'; ?>" data-method="paypal" id="<?php echo esc_attr( $form_id ); ?>-tab-paypal">
									<svg class="hc-reg__pay-icon hc-reg__pay-icon--paypal" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7.076 21.337H2.47a.641.641 0 0 1-.633-.74L4.944 3.72a.77.77 0 0 1 .762-.646h6.64c2.21 0 3.782.465 4.676 1.382.865.89 1.18 2.213.936 3.935-.02.142-.047.29-.079.443-.976 5.032-4.267 6.796-8.647 6.796h-2.19c-.524 0-.767.306-.855.637l-1.072 4.533-.015.098a.477.477 0 0 1-.47.393z"/><path d="M19.314 8.5c-.393 2.59-1.815 4.406-4.222 5.337h-2.845c-.365 0-.673.267-.745.625l-1.22 5.163a.477.477 0 0 1-.47.393H6.75l.89-3.768 1.072-4.533.015-.098a.641.641 0 0 1 .633-.537h2.19c4.38 0 7.671-1.764 8.647-6.796.044-.153.07-.3.09-.443.19-1.245.05-2.28-.393-3.118z" opacity=".7"/></svg>
									<?php esc_html_e( 'PayPal', 'holiday-calendar' ); ?>
								</button>
							<?php endif; ?>
							<span class="hc-reg__pay-method-indicator" aria-hidden="true"></span>
						</div>

						<div class="hc-reg__pay-panels" data-reg-method="<?php echo esc_attr( $stripe_enabled ? 'stripe' : 'paypal' ); ?>">
							<?php if ( $stripe_enabled ) : ?>
								<div class="hc-reg__pay-panel is-visible" role="tabpanel" data-panel="stripe" aria-labelledby="<?php echo esc_attr( $form_id ); ?>-tab-stripe">
									<p class="hc-reg__pay-field-label"><?php esc_html_e( 'Card details', 'holiday-calendar' ); ?></p>
									<div id="<?php echo esc_attr( $form_id ); ?>-stripe-element" class="hc-reg__stripe-element"></div>
									<button type="button" class="hc-reg__btn hc-reg__btn--pay hc-reg__btn--primary" data-reg-stripe-pay disabled>
										<span class="hc-reg__btn-text"><?php esc_html_e( 'Pay with card', 'holiday-calendar' ); ?></span>
										<span class="hc-reg__spinner" hidden aria-hidden="true"></span>
									</button>
								</div>
							<?php endif; ?>
							<?php if ( $paypal_enabled ) : ?>
								<div class="hc-reg__pay-panel<?php echo ! $stripe_enabled ? ' is-visible' : ''; ?>" role="tabpanel" data-panel="paypal" aria-labelledby="<?php echo esc_attr( $form_id ); ?>-tab-paypal">
									<p class="hc-reg__pay-field-label hc-reg__pay-field-label--center"><?php esc_html_e( 'Pay securely with your PayPal account', 'holiday-calendar' ); ?></p>
									<div id="<?php echo esc_attr( $form_id ); ?>-paypal-buttons" class="hc-reg__paypal-buttons"></div>
								</div>
							<?php endif; ?>
						</div>
					</fieldset>
				<?php endif; ?>
			</div>

			<!-- Step 5: Confirmation -->
			<div class="hc-reg__panel" data-panel="5" hidden aria-labelledby="<?php echo esc_attr( $form_id ); ?>-heading-5">
				<h2 class="hc-reg__step-title" id="<?php echo esc_attr( $form_id ); ?>-heading-5"><?php esc_html_e( 'Confirmation & Submission', 'holiday-calendar' ); ?></h2>

				<div class="hc-reg__info-panel hc-reg__info-panel--soft">
					<p><?php esc_html_e( 'You\'re almost done! Please review all your details carefully, ensure emergency contact information is included, and let us know how you heard about us.', 'holiday-calendar' ); ?></p>
				</div>

				<fieldset class="hc-reg__field">
					<legend>
						<?php esc_html_e( 'How Did You Hear About Us?', 'holiday-calendar' ); ?>
						<span class="hc-reg__req" aria-hidden="true">*</span>
						<span class="hc-reg__hint"><?php esc_html_e( 'Select all that apply', 'holiday-calendar' ); ?></span>
					</legend>
					<div class="hc-reg__checkbox-grid">
						<?php foreach ( $referral_labels as $key => $label ) : ?>
							<label class="hc-reg__checkbox-pill">
								<input type="checkbox" name="referral_sources[]" value="<?php echo esc_attr( $key ); ?>" />
								<span><?php echo esc_html( $label ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
					<span class="hc-reg__error" data-for="referral_sources" role="alert"></span>
				</fieldset>

				<div class="hc-reg__consent">
					<label class="hc-reg__checkbox-consent">
						<input type="checkbox" name="terms_consent" value="1" required />
						<span>
							<?php
							printf(
								/* translators: 1: terms link, 2: privacy link */
								wp_kses_post( __( 'I/We certify the accuracy of the information provided and agree to the <a href="%1$s" target="_blank" rel="noopener noreferrer">Summer Camp Terms &amp; Conditions</a> and <a href="%2$s" target="_blank" rel="noopener noreferrer">Privacy Policy</a> of Shruti\'s SOPA.', 'holiday-calendar' ) ),
								esc_url( $settings['terms_url'] ),
								esc_url( $settings['privacy_url'] )
							);
							?>
						</span>
					</label>
					<span class="hc-reg__error" data-for="terms_consent" role="alert"></span>
				</div>

				<h3 class="hc-reg__subheading"><?php esc_html_e( 'Signature', 'holiday-calendar' ); ?></h3>
				<div class="hc-reg__grid hc-reg__grid--2">
					<div class="hc-reg__field">
						<label for="<?php echo esc_attr( $form_id ); ?>-signature">
							<?php esc_html_e( 'Parent / Guardian Signature (Full Name)', 'holiday-calendar' ); ?>
							<span class="hc-reg__req" aria-hidden="true">*</span>
						</label>
						<input type="text" id="<?php echo esc_attr( $form_id ); ?>-signature" name="signature_name" required autocomplete="name" />
						<span class="hc-reg__error" data-for="signature_name" role="alert"></span>
					</div>
					<div class="hc-reg__field">
						<label for="<?php echo esc_attr( $form_id ); ?>-sig-date">
							<?php esc_html_e( 'Date', 'holiday-calendar' ); ?>
							<span class="hc-reg__req" aria-hidden="true">*</span>
						</label>
						<input type="date" id="<?php echo esc_attr( $form_id ); ?>-sig-date" name="signature_date" required />
						<span class="hc-reg__error" data-for="signature_date" role="alert"></span>
					</div>
				</div>
			</div>

			<footer class="hc-reg__nav">
				<button type="button" class="hc-reg__btn hc-reg__btn--ghost" data-action="back" hidden>
					<?php esc_html_e( 'Back', 'holiday-calendar' ); ?>
				</button>
				<button type="button" class="hc-reg__btn hc-reg__btn--primary" data-action="next">
					<?php esc_html_e( 'Continue', 'holiday-calendar' ); ?>
				</button>
				<button type="button" class="hc-reg__btn hc-reg__btn--primary" data-action="submit" hidden>
					<span class="hc-reg__btn-text"><?php esc_html_e( 'Submit registration', 'holiday-calendar' ); ?></span>
					<span class="hc-reg__spinner" hidden aria-hidden="true"></span>
				</button>
			</footer>
		</form>

		<div class="hc-reg__success" role="status" aria-live="polite" hidden>
			<div class="hc-reg__success-icon" aria-hidden="true">✓</div>
			<h3><?php esc_html_e( 'Registration complete!', 'holiday-calendar' ); ?></h3>
			<p class="hc-reg__success-msg"></p>
			<p class="hc-reg__success-ref" data-success-ref hidden></p>
		</div>

		<footer class="hc-reg__footer">
			<span><strong><?php esc_html_e( "Shruti's SOPA", 'holiday-calendar' ); ?></strong> | <?php esc_html_e( 'Dance & Yoga', 'holiday-calendar' ); ?></span>
			<span><?php esc_html_e( 'Summer Camp 2026', 'holiday-calendar' ); ?></span>
		</footer>
	</div>
</div>
