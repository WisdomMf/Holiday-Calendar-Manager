(function () {
	'use strict';

	if (typeof HC_REGISTRATION === 'undefined') {
		return;
	}

	var cfg = HC_REGISTRATION;
	var i18n = cfg.i18n || {};
	var TOTAL_STEPS = 5;

	document.querySelectorAll('[data-hc-registration]').forEach(initForm);

	function initForm(root) {
		var form = root.querySelector('.hc-reg__form');
		if (!form) {
			return;
		}

		var currentStep = 1;
		var busy = false;

		var panels = form.querySelectorAll('[data-panel]');
		var btnBack = form.querySelector('[data-action="back"]');
		var btnNext = form.querySelector('[data-action="next"]');
		var btnSubmit = form.querySelector('[data-action="submit"]');
		var alertEl = root.querySelector('.hc-reg__alert');
		var successWrap = root.querySelector('.hc-reg__success');
		var successMsg = root.querySelector('.hc-reg__success-msg');
		var successRef = root.querySelector('[data-success-ref]');

		var revealCampMonth = root.querySelector('[data-reveal="camp-month"]');
		var revealSpecialNeeds = root.querySelector('[data-reveal="special-needs"]');
		var revealEmergencyAlt = root.querySelector('[data-reveal="emergency-alt"]');
		var revealPaymentWeekly = root.querySelector('[data-reveal="payment-weekly"]');
		var revealPaymentMonthly = root.querySelector('[data-reveal="payment-monthly"]');
		var amountDueEl = root.querySelector('[data-reg-amount-due]');
		var payStatusEl = root.querySelector('[data-reg-pay-status]');
		var payPanelsWrap = root.querySelector('[data-reg-method]');
		var stripeMount = root.querySelector('[id$="-stripe-element"]');
		var paypalMount = root.querySelector('[id$="-paypal-buttons"]');
		var btnStripePay = root.querySelector('[data-reg-stripe-pay]');
		var methodButtons = root.querySelectorAll('.hc-reg__pay-method');
		var paymentProviderInput = form.querySelector('[name="payment_provider"]');
		var paymentRefInput = form.querySelector('[name="payment_ref"]');

		var payState = {
			complete: false,
			provider: '',
			ref: '',
			method: cfg.stripeEnabled ? 'stripe' : 'paypal',
			stripe: null,
			elements: null,
			paymentElement: null,
			paypalRendered: false,
			payBusy: false,
			stripeReady: false,
			attempted: false
		};

		form.querySelectorAll('input[name="camp_session"]').forEach(function (el) {
			el.addEventListener('change', function () {
				updateCampSession();
				validateField('camp_session', true);
				validateField('camp_month', true);
			});
		});
		form.querySelectorAll('input[name="camp_month"]').forEach(function (el) {
			el.addEventListener('change', function () {
				updateCampSession();
				resetPayment();
				validateField('camp_month', true);
			});
		});
		form.querySelectorAll('input[name="special_needs"]').forEach(function (el) {
			el.addEventListener('change', function () {
				updateSpecialNeeds();
				validateField('special_needs', true);
				validateField('special_needs_details', true);
			});
		});
		form.querySelectorAll('input[name="emergency_same"]').forEach(function (el) {
			el.addEventListener('change', function () {
				updateEmergencySame();
				validateField('emergency_same', true);
				validateField('emergency_name', true);
			});
		});

		form.querySelectorAll('input[name="student_gender"]').forEach(function (el) {
			el.addEventListener('change', function () {
				validateField('student_gender', true);
			});
		});

		setupProactiveValidation();

		if (btnBack) {
			btnBack.addEventListener('click', function () {
				if (currentStep > 1) {
					goToStep(currentStep - 1);
				}
			});
		}

		if (btnNext) {
			btnNext.addEventListener('click', function () {
				if (validateStep(currentStep, { markTouched: true })) {
					goToStep(currentStep + 1);
				}
			});
		}

		if (btnSubmit) {
			btnSubmit.addEventListener('click', onSubmit);
		}

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			if (currentStep === TOTAL_STEPS) {
				onSubmit(e);
			}
		});

		form.addEventListener('keydown', function (e) {
			if (e.key !== 'Enter' || e.target.tagName === 'TEXTAREA') {
				return;
			}
			if (currentStep < TOTAL_STEPS && btnNext && !btnNext.hidden) {
				e.preventDefault();
				btnNext.click();
			}
		});

		var sigDate = form.querySelector('[name="signature_date"]');
		if (sigDate && !sigDate.value) {
			sigDate.value = todayISO();
		}

		updateCampSession();
		updateSpecialNeeds();
		updateEmergencySame();
		initPaymentUI();
		updateUI();

		function todayISO() {
			var d = new Date();
			var m = String(d.getMonth() + 1).padStart(2, '0');
			var day = String(d.getDate()).padStart(2, '0');
			return d.getFullYear() + '-' + m + '-' + day;
		}

		function getCampSession() {
			var checked = form.querySelector('input[name="camp_session"]:checked');
			return checked ? checked.value : '';
		}

		function getSpecialNeeds() {
			var checked = form.querySelector('input[name="special_needs"]:checked');
			return checked ? checked.value : '';
		}

		function getEmergencySame() {
			var checked = form.querySelector('input[name="emergency_same"]:checked');
			return checked ? checked.value : '';
		}

		function toggleReveal(el, show) {
			if (!el) {
				return;
			}
			if (show) {
				el.removeAttribute('hidden');
				el.classList.add('is-visible');
			} else {
				el.classList.remove('is-visible');
				el.setAttribute('hidden', '');
			}
		}

		function updateCampSession() {
			var session = getCampSession();
			var isMonthly = session === 'monthly';
			var isWeekly = session === 'weekly';

			toggleReveal(revealCampMonth, isMonthly);

			if (!isMonthly) {
				form.querySelectorAll('input[name="camp_month"]').forEach(function (r) {
					r.checked = false;
					r.removeAttribute('required');
				});
				clearFieldError('camp_month');
			} else {
				form.querySelectorAll('input[name="camp_month"]').forEach(function (r) {
					r.setAttribute('required', 'required');
				});
			}

			if (revealPaymentWeekly) {
				toggleReveal(revealPaymentWeekly, isWeekly || !session);
			}
			if (revealPaymentMonthly) {
				toggleReveal(revealPaymentMonthly, isMonthly);
			}

			updateAmountDue();
			resetPayment();
		}

		function getCampMonth() {
			var checked = form.querySelector('input[name="camp_month"]:checked');
			return checked ? checked.value : '';
		}

		function calculateAmountCents() {
			var session = getCampSession();
			var month = getCampMonth();
			if (session === 'weekly') {
				return 35000;
			}
			if (session === 'monthly') {
				if (month === 'both') {
					return 100000;
				}
				if (month === 'june' || month === 'july') {
					return 50000;
				}
			}
			return 0;
		}

		function formatMoney(cents) {
			var amount = (cents / 100).toFixed(2);
			return (cfg.currency || 'USD') + ' ' + amount;
		}

		function getAmountDueLabel() {
			var cents = calculateAmountCents();
			if (!cents) {
				return '';
			}
			var formatted = formatMoney(cents);
			if (getCampSession() === 'weekly') {
				return formatted + ' due today';
			}
			return formatted + ' deposit due today';
		}

		function updateAmountDue() {
			if (!amountDueEl) {
				return;
			}
			var label = getAmountDueLabel();
			amountDueEl.textContent = label || '—';
			updateStripeAmount();
		}

		function resetPayment() {
			payState.complete = false;
			payState.provider = '';
			payState.ref = '';
			if (paymentProviderInput) {
				paymentProviderInput.value = '';
			}
			if (paymentRefInput) {
				paymentRefInput.value = '';
			}
			clearFieldError('payment');
			if (payState.attempted || currentStep === 4) {
				validateField('payment', true);
			}
			if (payStatusEl) {
				payStatusEl.hidden = true;
				var statusText = payStatusEl.querySelector('[data-reg-pay-status-text]');
				if (statusText) {
					statusText.textContent = '';
				} else {
					payStatusEl.textContent = '';
				}
				payStatusEl.className = 'hc-reg__pay-status';
			}
			if (payPanelsWrap) {
				payPanelsWrap.classList.remove('is-paid');
			}
			updateNavButtons();
		}

		function setPaymentComplete(provider, ref) {
			payState.complete = true;
			payState.provider = provider;
			payState.ref = ref;
			if (paymentProviderInput) {
				paymentProviderInput.value = provider;
			}
			if (paymentRefInput) {
				paymentRefInput.value = ref;
			}
			if (payStatusEl) {
				var statusText = payStatusEl.querySelector('[data-reg-pay-status-text]');
				var message = i18n.paymentComplete || 'Payment received. You can continue to review and submit your registration.';
				if (statusText) {
					statusText.textContent = message;
				} else {
					payStatusEl.textContent = message;
				}
				payStatusEl.className = 'hc-reg__pay-status is-success';
				payStatusEl.hidden = false;
			}
			payState.attempted = false;
			clearFieldError('payment');
			validateField('payment', true);
			if (payPanelsWrap) {
				payPanelsWrap.classList.add('is-paid');
			}
			updateNavButtons();
		}

		function getPaymentPostData() {
			return {
				camp_session: getCampSession(),
				camp_month: getCampMonth(),
				email: (form.querySelector('[name="email"]') || {}).value || '',
				student_full_name: (form.querySelector('[name="student_full_name"]') || {}).value || ''
			};
		}

		function stripeErrorMessage(error) {
			if (!error) {
				return i18n.errorGeneric || 'Something went wrong. Please try again.';
			}
			if (typeof error === 'string') {
				return error;
			}
			if (error.message && typeof Error !== 'undefined' && error instanceof Error) {
				var msg = error.message;
				if (/^elements\.submit\(\)/i.test(msg) || /must be called before/i.test(msg)) {
					return i18n.paymentFailed || 'Payment could not be completed. Please check your card details and try again.';
				}
				return msg;
			}
			var technical = error.message && (/^elements\.submit\(\)/i.test(error.message) || /must be called before/i.test(error.message));
			if (technical) {
				return i18n.paymentFailed || 'Payment could not be completed. Please check your card details and try again.';
			}
			var byCode = {
				card_declined: i18n.cardDeclined || 'Your card was declined. Please try a different card.',
				expired_card: i18n.expiredCard || 'Your card has expired. Please use a different card.',
				incorrect_cvc: i18n.incorrectCvc || 'The security code is incorrect. Please check and try again.',
				incorrect_number: i18n.incorrectNumber || 'The card number is incorrect. Please check and try again.',
				insufficient_funds: i18n.insufficientFunds || 'Your card has insufficient funds.',
				incomplete_number: i18n.incompleteCard || 'Please enter your complete card details.',
				incomplete_expiry: i18n.incompleteCard || 'Please enter your complete card details.',
				incomplete_cvc: i18n.incompleteCard || 'Please enter your complete card details.'
			};
			if (error.code && byCode[error.code]) {
				return byCode[error.code];
			}
			if (error.type === 'card_error' || error.type === 'validation_error') {
				return error.message || i18n.paymentFailed || i18n.errorGeneric;
			}
			if (error.message) {
				return error.message;
			}
			return i18n.paymentFailed || i18n.errorGeneric || 'Payment could not be completed. Please try again.';
		}

		function postAjax(action, payload) {
			var body = new FormData();
			body.append('action', action);
			body.append('nonce', cfg.nonce);
			Object.keys(payload).forEach(function (key) {
				body.append(key, payload[key]);
			});
			return fetch(cfg.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: body
			}).then(function (res) {
				return res.json();
			});
		}

		function switchPayMethod(method) {
			if (payState.method === method) {
				return;
			}
			payState.method = method;
			if (payPanelsWrap) {
				payPanelsWrap.setAttribute('data-reg-method', method);
			}
			methodButtons.forEach(function (btn) {
				var active = btn.dataset.method === method;
				btn.classList.toggle('is-active', active);
				btn.setAttribute('aria-selected', active ? 'true' : 'false');
			});
			root.querySelectorAll('.hc-reg__pay-panel').forEach(function (panel) {
				panel.classList.toggle('is-visible', panel.dataset.panel === method);
			});
			updateNavButtons();
		}

		function setPayBusy(state) {
			payState.payBusy = state;
			if (btnStripePay) {
				btnStripePay.disabled = state || !payState.stripeReady || payState.complete;
				var text = btnStripePay.querySelector('.hc-reg__btn-text');
				var spinner = btnStripePay.querySelector('.hc-reg__spinner');
				if (text) {
					text.hidden = state;
				}
				if (spinner) {
					spinner.hidden = !state;
				}
			}
			updateNavButtons();
		}

		function initStripe() {
			if (!cfg.stripeEnabled || typeof Stripe === 'undefined' || !stripeMount || payState.stripe) {
				return;
			}
			var cents = calculateAmountCents() || 35000;
			payState.stripe = Stripe(cfg.stripePublishable);
			payState.elements = payState.stripe.elements({
				mode: 'payment',
				amount: cents,
				currency: (cfg.currency || 'USD').toLowerCase(),
				paymentMethodTypes: ['card'],
				appearance: {
					theme: 'stripe',
					variables: {
						colorPrimary: '#8f00eb',
						colorText: '#1a1a2e',
						borderRadius: '10px'
					}
				}
			});
			// Card-only Payment Element — no bank, Klarna, Link, or wallet tabs.
			payState.paymentElement = payState.elements.create('payment', {
				paymentMethodTypes: ['card'],
				wallets: {
					applePay: 'never',
					googlePay: 'never',
					link: 'never'
				}
			});
			payState.paymentElement.mount(stripeMount);
			payState.paymentElement.on('ready', function () {
				payState.stripeReady = true;
				if (btnStripePay) {
					btnStripePay.disabled = payState.complete || payState.payBusy;
				}
			});
		}

		function updateStripeAmount() {
			if (!payState.elements) {
				return;
			}
			var cents = calculateAmountCents();
			if (cents > 0) {
				payState.elements.update({
					amount: cents,
					currency: (cfg.currency || 'USD').toLowerCase()
				});
			}
		}

		function renderPayPal() {
			if (!cfg.paypalEnabled || typeof paypal === 'undefined' || !paypalMount || payState.paypalRendered) {
				return;
			}
			payState.paypalRendered = true;

			paypal.Buttons({
				style: {
					layout: 'vertical',
					color: 'gold',
					shape: 'rect',
					label: 'paypal'
				},
				onClick: function (data, actions) {
					if (!calculateAmountCents()) {
						showAlert(i18n.selectSession || i18n.fixFields, 'error');
						return actions.reject();
					}
					if (!validateStep(1) || !validateStep(2)) {
						showAlert(i18n.selectSession || i18n.fixFields, 'error');
						return actions.reject();
					}
					return actions.resolve();
				},
				createOrder: function () {
					return postAjax('hc_reg_create_paypal_order', getPaymentPostData()).then(function (json) {
						if (!json.success) {
							throw new Error(json.data && json.data.message ? json.data.message : i18n.errorGeneric);
						}
						if (json.data.amount_label && amountDueEl) {
							amountDueEl.textContent = json.data.amount_label;
						}
						return json.data.order_id;
					});
				},
				onApprove: function (data) {
					setPayBusy(true);
					var payload = getPaymentPostData();
					payload.order_id = data.orderID;
					return postAjax('hc_reg_capture_paypal_order', payload)
						.then(function (json) {
							if (!json.success) {
								throw new Error(json.data && json.data.message ? json.data.message : i18n.errorGeneric);
							}
							setPaymentComplete('paypal', json.data.order_id || data.orderID);
						})
						.catch(function (err) {
							setFieldError('payment', err.message || i18n.errorGeneric);
							showAlert(err.message || i18n.errorGeneric, 'error');
						})
						.finally(function () {
							setPayBusy(false);
						});
				},
				onError: function (err) {
					setFieldError('payment', err && err.message ? err.message : i18n.errorGeneric);
				}
			}).render(paypalMount);
		}

		function onStripePay() {
			if (payState.complete || payState.payBusy) {
				return;
			}
			if (!calculateAmountCents()) {
				showAlert(i18n.selectSession || i18n.fixFields, 'error');
				return;
			}
			if (!validateStep(1) || !validateStep(2)) {
				showAlert(i18n.selectSession || i18n.fixFields, 'error');
				return;
			}

			setPayBusy(true);
			hideAlert();

			var payload = getPaymentPostData();
			var email = form.querySelector('[name="email"]');

			payState.elements.submit()
				.then(function (submitResult) {
					if (submitResult.error) {
						throw submitResult.error;
					}
					return postAjax('hc_reg_create_stripe_intent', payload);
				})
				.then(function (json) {
					if (!json.success) {
						throw new Error(json.data && json.data.message ? json.data.message : i18n.errorGeneric);
					}
					if (json.data.amount_label && amountDueEl) {
						amountDueEl.textContent = json.data.amount_label;
					}
					return payState.stripe.confirmPayment({
						elements: payState.elements,
						clientSecret: json.data.client_secret,
						confirmParams: {
							return_url: window.location.href,
							payment_method_data: {
								billing_details: {
									email: email ? email.value : undefined,
									name: payload.student_full_name || undefined
								}
							}
						},
						redirect: 'if_required'
					});
				})
				.then(function (result) {
					if (result.error) {
						throw result.error;
					}
					if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
						setPaymentComplete('stripe', result.paymentIntent.id);
						hideAlert();
					}
				})
				.catch(function (err) {
					var msg = stripeErrorMessage(err);
					setFieldError('payment', msg);
					showAlert(msg, 'error');
				})
				.finally(function () {
					setPayBusy(false);
				});
		}

		function initPaymentUI() {
			if (!cfg.paymentsAvailable) {
				return;
			}
			methodButtons.forEach(function (btn) {
				btn.addEventListener('click', function () {
					switchPayMethod(btn.dataset.method);
				});
			});
			if (btnStripePay) {
				btnStripePay.addEventListener('click', onStripePay);
			}
			renderPayPal();
		}

		function updateSpecialNeeds() {
			var needs = getSpecialNeeds();
			var isYes = needs === 'yes';
			toggleReveal(revealSpecialNeeds, isYes);

			var details = form.querySelector('[name="special_needs_details"]');
			if (details) {
				if (isYes) {
					details.setAttribute('required', 'required');
				} else {
					details.removeAttribute('required');
					details.value = '';
					clearFieldError('special_needs_details');
				}
			}
		}

		function updateEmergencySame() {
			var same = getEmergencySame();
			var isNo = same === 'no';
			toggleReveal(revealEmergencyAlt, isNo);

			var nameField = form.querySelector('[name="emergency_name"]');
			if (nameField) {
				if (isNo) {
					nameField.setAttribute('required', 'required');
				} else {
					nameField.removeAttribute('required');
					nameField.value = '';
					clearFieldError('emergency_name');
				}
			}
		}

		function goToStep(step) {
			if (step < 1 || step > TOTAL_STEPS) {
				return;
			}
			currentStep = step;
			updateUI();
			hideAlert();
			if (step === 4 && cfg.paymentsAvailable) {
				updateAmountDue();
				initStripe();
			}
			root.querySelector('.hc-reg__card').scrollIntoView({ behavior: 'smooth', block: 'start' });
		}

		function paymentRequiredOnStep4() {
			return cfg.paymentsAvailable && calculateAmountCents() > 0;
		}

		function updateNavButtons() {
			if (btnBack) {
				btnBack.hidden = currentStep <= 1;
			}
			if (btnNext) {
				btnNext.hidden = currentStep >= TOTAL_STEPS;
				var blockContinue = currentStep === 4 && paymentRequiredOnStep4() && !payState.complete;
				if (busy || payState.payBusy || blockContinue) {
					btnNext.disabled = true;
					if (blockContinue && !busy && !payState.payBusy) {
						btnNext.setAttribute('aria-disabled', 'true');
					} else {
						btnNext.removeAttribute('aria-disabled');
					}
				} else {
					btnNext.disabled = false;
					btnNext.removeAttribute('aria-disabled');
				}
			}
			if (btnSubmit) {
				btnSubmit.hidden = currentStep !== TOTAL_STEPS;
			}
		}

		function updateUI() {
			panels.forEach(function (panel) {
				var num = parseInt(panel.getAttribute('data-panel'), 10);
				if (num === currentStep) {
					panel.removeAttribute('hidden');
					panel.classList.add('is-active');
				} else {
					panel.setAttribute('hidden', '');
					panel.classList.remove('is-active');
				}
			});

			updateNavButtons();
		}

		function debounce(fn, wait) {
			var timer;
			return function () {
				var args = arguments;
				var ctx = this;
				clearTimeout(timer);
				timer = setTimeout(function () {
					fn.apply(ctx, args);
				}, wait);
			};
		}

		function setupProactiveValidation() {
			var debouncedValidate = debounce(function (name) {
				validateField(name, true);
			}, 300);

			form.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], input[type="number"], input[type="date"], textarea').forEach(function (el) {
				if (!el.name) {
					return;
				}
				el.addEventListener('blur', function () {
					validateField(el.name, true);
				});
				el.addEventListener('input', function () {
					if (el.classList.contains('is-invalid') || el.dataset.touched === '1') {
						debouncedValidate(el.name);
					}
				});
			});

			form.querySelectorAll('input[type="checkbox"]').forEach(function (el) {
				el.addEventListener('change', function () {
					if (el.name === 'terms_consent') {
						validateField('terms_consent', true);
					} else if (el.name === 'referral_sources[]') {
						validateField('referral_sources', true);
					}
				});
			});

			form.querySelectorAll('input[type="radio"]').forEach(function (el) {
				if (['camp_session', 'camp_month', 'special_needs', 'emergency_same', 'student_gender'].indexOf(el.name) !== -1) {
					return;
				}
				el.addEventListener('change', function () {
					validateField(el.name, true);
				});
			});
		}

		function showAlert(msg, type) {
			if (!alertEl) {
				return;
			}
			alertEl.textContent = msg;
			alertEl.className = 'hc-reg__alert is-' + (type || 'error');
			alertEl.hidden = false;
		}

		function hideAlert() {
			if (alertEl) {
				alertEl.hidden = true;
				alertEl.textContent = '';
			}
		}

		function getFieldControls(name) {
			if (name === 'referral_sources') {
				return form.querySelectorAll('input[name="referral_sources[]"]');
			}
			return form.querySelectorAll('[name="' + name + '"]');
		}

		function getFieldWrapper(name) {
			var errEl = form.querySelector('.hc-reg__error[data-for="' + name + '"]');
			if (!errEl) {
				return null;
			}
			return errEl.closest('.hc-reg__field, fieldset.hc-reg__field, .hc-reg__consent, .hc-reg__field--payment');
		}

		function setFieldError(name, msg) {
			if (name === 'payment') {
				payState.attempted = true;
			}

			var errEl = form.querySelector('.hc-reg__error[data-for="' + name + '"]');
			var errId = errEl ? errEl.id || (errEl.id = 'hc-reg-err-' + name) : '';
			if (errEl) {
				errEl.textContent = msg || '';
			}

			var wrapper = getFieldWrapper(name);
			if (wrapper) {
				wrapper.classList.toggle('has-error', !!msg);
			}

			getFieldControls(name).forEach(function (el) {
				el.classList.add('is-invalid');
				el.setAttribute('aria-invalid', 'true');
				if (errId) {
					el.setAttribute('aria-describedby', errId);
				}
			});
		}

		function clearFieldError(name) {
			var errEl = form.querySelector('.hc-reg__error[data-for="' + name + '"]');
			if (errEl) {
				errEl.textContent = '';
			}

			var wrapper = getFieldWrapper(name);
			if (wrapper) {
				wrapper.classList.remove('has-error');
			}

			getFieldControls(name).forEach(function (el) {
				el.classList.remove('is-invalid');
				el.removeAttribute('aria-invalid');
				el.removeAttribute('aria-describedby');
			});
		}

		function clearAllErrors() {
			form.querySelectorAll('.hc-reg__error').forEach(function (el) {
				el.textContent = '';
			});
			form.querySelectorAll('.is-invalid').forEach(function (el) {
				el.classList.remove('is-invalid');
				el.removeAttribute('aria-invalid');
				el.removeAttribute('aria-describedby');
			});
			form.querySelectorAll('.has-error').forEach(function (el) {
				el.classList.remove('has-error');
			});
		}

		function isValidEmail(val) {
			return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
		}

		function isValidDate(val) {
			if (!val) {
				return false;
			}
			var d = new Date(val + 'T00:00:00');
			return !isNaN(d.getTime());
		}

		function validateField(name, showError) {
			var msg = getFieldErrorMessage(name);
			if (msg) {
				if (showError) {
					if (name === 'payment') {
						payState.attempted = true;
					}
					setFieldError(name, msg);
					var el = form.querySelector('[name="' + name + '"]') || getFieldControls(name)[0];
					if (el) {
						el.dataset.touched = '1';
					}
				}
				return false;
			}
			if (showError) {
				clearFieldError(name);
			}
			return true;
		}

		function getFieldErrorMessage(name) {
			if (name === 'student_full_name') {
				var studentName = form.querySelector('[name="student_full_name"]');
				if (!studentName || studentName.value.trim().length < 2) {
					return i18n.required;
				}
			}

			if (name === 'student_dob') {
				var dob = form.querySelector('[name="student_dob"]');
				if (dob && dob.value && !isValidDate(dob.value)) {
					return i18n.invalidDate;
				}
			}

			if (name === 'student_age') {
				var age = form.querySelector('[name="student_age"]');
				if (!age || age.value === '' || parseInt(age.value, 10) < 5) {
					return i18n.minAge;
				}
			}

			if (name === 'student_gender') {
				if (!form.querySelector('input[name="student_gender"]:checked')) {
					return i18n.required;
				}
			}

			if (name === 'camp_session') {
				if (!getCampSession()) {
					return i18n.required;
				}
			}

			if (name === 'camp_month') {
				if (getCampSession() === 'monthly' && !form.querySelector('input[name="camp_month"]:checked')) {
					return i18n.required;
				}
			}

			if (name === 'special_needs') {
				if (!getSpecialNeeds()) {
					return i18n.required;
				}
			}

			if (name === 'special_needs_details') {
				if (getSpecialNeeds() === 'yes') {
					var details = form.querySelector('[name="special_needs_details"]');
					if (!details || !details.value.trim()) {
						return i18n.required;
					}
				}
			}

			if (name === 'mother_name' || name === 'father_name' || name === 'mother_phone' || name === 'father_phone' || name === 'address') {
				var guardianField = form.querySelector('[name="' + name + '"]');
				if (!guardianField || !guardianField.value.trim()) {
					return i18n.required;
				}
			}

			if (name === 'email') {
				var email = form.querySelector('[name="email"]');
				if (!email || !isValidEmail(email.value.trim())) {
					return i18n.invalidEmail;
				}
			}

			if (name === 'emergency_same') {
				if (!getEmergencySame()) {
					return i18n.required;
				}
			}

			if (name === 'emergency_name') {
				if (getEmergencySame() === 'no') {
					var emergName = form.querySelector('[name="emergency_name"]');
					if (!emergName || !emergName.value.trim()) {
						return i18n.required;
					}
				}
			}

			if (name === 'payment') {
				if (cfg.paymentsAvailable && !calculateAmountCents()) {
					return i18n.selectSession || i18n.required;
				}
				if (cfg.paymentsAvailable && calculateAmountCents() && !payState.complete) {
					return i18n.paymentRequired || i18n.required;
				}
			}

			if (name === 'referral_sources') {
				if (!form.querySelectorAll('input[name="referral_sources[]"]:checked').length) {
					return i18n.selectOne;
				}
			}

			if (name === 'terms_consent') {
				var terms = form.querySelector('[name="terms_consent"]');
				if (!terms || !terms.checked) {
					return i18n.acceptTerms;
				}
			}

			if (name === 'signature_name') {
				var sig = form.querySelector('[name="signature_name"]');
				if (!sig || !sig.value.trim()) {
					return i18n.required;
				}
			}

			if (name === 'signature_date') {
				var sigDate = form.querySelector('[name="signature_date"]');
				if (!sigDate || !sigDate.value || !isValidDate(sigDate.value)) {
					return i18n.invalidDate;
				}
			}

			return '';
		}

		function stepFieldNames(step) {
			if (step === 1) {
				return ['student_full_name', 'student_dob', 'student_age', 'student_gender', 'camp_session', 'camp_month', 'special_needs', 'special_needs_details'];
			}
			if (step === 2) {
				return ['mother_name', 'father_name', 'mother_phone', 'father_phone', 'address', 'email'];
			}
			if (step === 3) {
				return ['emergency_same', 'emergency_name'];
			}
			if (step === 4) {
				return ['payment', 'camp_session'];
			}
			if (step === 5) {
				return ['referral_sources', 'terms_consent', 'signature_name', 'signature_date'];
			}
			return [];
		}

		function validateStep(step, options) {
			options = options || {};
			var markTouched = !!options.markTouched;
			var valid = true;
			var firstInvalid = null;

			stepFieldNames(step).forEach(function (fieldName) {
				if (!validateField(fieldName, markTouched)) {
					valid = false;
					if (!firstInvalid) {
						firstInvalid = form.querySelector('[name="' + fieldName + '"]') ||
							getFieldControls(fieldName)[0] ||
							form.querySelector('.hc-reg__error[data-for="' + fieldName + '"]');
					}
				}
			});

			if (!valid && markTouched) {
				showAlert(i18n.fixFields, 'error');
				if (firstInvalid && typeof firstInvalid.focus === 'function') {
					firstInvalid.focus();
				}
			} else if (valid) {
				hideAlert();
			}

			return valid;
		}

		function collectFormData() {
			var fd = new FormData(form);
			fd.append('action', 'hc_submit_registration');
			fd.append('nonce', cfg.nonce);
			return fd;
		}

		function setBusy(state) {
			busy = state;
			if (btnSubmit) {
				btnSubmit.disabled = state;
				var text = btnSubmit.querySelector('.hc-reg__btn-text');
				var spinner = btnSubmit.querySelector('.hc-reg__spinner');
				if (text) {
					text.hidden = state;
				}
				if (spinner) {
					spinner.hidden = !state;
				}
			}
			if (btnBack) {
				btnBack.disabled = state;
			}
			updateNavButtons();
		}

		function onSubmit(e) {
			if (e && e.preventDefault) {
				e.preventDefault();
			}
			if (busy || currentStep !== TOTAL_STEPS) {
				return;
			}

			for (var s = 1; s <= TOTAL_STEPS; s++) {
				if (!validateStep(s, { markTouched: true })) {
					goToStep(s);
					return;
				}
			}

			setBusy(true);
			hideAlert();

			fetch(cfg.ajaxUrl, {
				method: 'POST',
				body: collectFormData(),
				credentials: 'same-origin'
			})
				.then(function (res) {
					return res.json();
				})
				.then(function (json) {
					setBusy(false);
					if (json.success) {
						root.classList.add('is-submitted');
						if (successMsg) {
							successMsg.textContent = (json.data && json.data.message) || i18n.successMessage;
						}
						if (successRef && json.data && json.data.reference) {
							successRef.textContent = 'Reference: ' + json.data.reference;
							successRef.hidden = false;
						}
						root.scrollIntoView({ behavior: 'smooth', block: 'start' });
					} else {
						var msg = (json.data && json.data.message) || i18n.errorGeneric;
						showAlert(msg, 'error');
						if (json.data && json.data.fields) {
							Object.keys(json.data.fields).forEach(function (key) {
								setFieldError(key, json.data.fields[key]);
							});
							if (json.data.fields.payment) {
								goToStep(4);
							}
						}
					}
				})
				.catch(function () {
					setBusy(false);
					showAlert(i18n.errorGeneric, 'error');
				});
		}
	}
})();
