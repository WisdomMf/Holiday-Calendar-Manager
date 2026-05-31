(function () {
	'use strict';

	if (typeof HC_PAYMENT === 'undefined') {
		return;
	}

	var cfg = HC_PAYMENT;
	var i18n = cfg.i18n || {};

	function stripeErrorMessage(error) {
		if (!error) {
			return i18n.errorGeneric || 'Something went wrong. Please try again.';
		}
		if (typeof error === 'string') {
			return error;
		}
		if (error.message && typeof Error !== 'undefined' && error instanceof Error) {
			return error.message;
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

	document.querySelectorAll('[data-hc-payment]').forEach(initForm);

	function initForm(root) {
		var form = root.querySelector('.hc-payment__form');
		if (!form) {
			return;
		}

		var alertEl = root.querySelector('.hc-payment__alert');
		var submitBtn = form.querySelector('.hc-payment__submit');
		var totalEl = root.querySelector('[data-total]');
		var successWrap = root.querySelector('.hc-payment__success');
		var successMsg = root.querySelector('.hc-payment__success-msg');
		var methodButtons = form.querySelectorAll('.hc-payment__method');
		var tierInputs = form.querySelectorAll('input[name="tier"]');
		var stripeMount = root.querySelector('[id$="-stripe-element"]');
		var paypalMount = root.querySelector('[id$="-paypal-buttons"]');

		var state = {
			method: getDefaultMethod(),
			stripe: null,
			elements: null,
			paymentElement: null,
			paypalRendered: false,
			busy: false
		};

		root.dataset.method = state.method;

		tierInputs.forEach(function (input) {
			input.addEventListener('change', onTierChange);
		});
		onTierChange();

		methodButtons.forEach(function (btn) {
			btn.addEventListener('click', function () {
				switchMethod(btn.dataset.method);
			});
		});

		form.querySelectorAll('input, textarea').forEach(function (el) {
			el.addEventListener('input', validateForm);
			el.addEventListener('blur', validateForm);
		});

		form.addEventListener('submit', onSubmit);

		if (cfg.stripeEnabled && typeof Stripe !== 'undefined' && stripeMount) {
			initStripe(stripeMount);
		}

		if (cfg.paypalEnabled && typeof paypal !== 'undefined' && paypalMount) {
			renderPayPal(paypalMount);
		}

		updateSubmitLabel();
		validateForm();

		function getDefaultMethod() {
			if (cfg.stripeEnabled) {
				return 'stripe';
			}
			if (cfg.paypalEnabled) {
				return 'paypal';
			}
			return 'stripe';
		}

		function switchMethod(method) {
			if (state.method === method) {
				return;
			}
			state.method = method;
			root.dataset.method = method;

			methodButtons.forEach(function (btn) {
				var active = btn.dataset.method === method;
				btn.classList.toggle('is-active', active);
				btn.setAttribute('aria-selected', active ? 'true' : 'false');
			});

			root.querySelectorAll('.hc-payment__panel').forEach(function (panel) {
				panel.classList.toggle('is-visible', panel.dataset.panel === method);
			});

			updateSubmitLabel();
			validateForm();
		}

		function updateSubmitLabel() {
			if (!submitBtn) {
				return;
			}
			var textEl = submitBtn.querySelector('.hc-payment__submit-text');
			if (textEl && state.method === 'stripe') {
				textEl.textContent = i18n.payWithCard || 'Pay with card';
			}
		}

		function onTierChange() {
			tierInputs.forEach(function (input) {
				input.closest('.hc-payment__tier').classList.toggle('is-selected', input.checked);
			});

			var tier = getSelectedTier();
			if (tier && totalEl) {
				totalEl.textContent = tier.amountLabel;
			}
			updateStripeAmount();
			validateForm();
		}

		function getSelectedTier() {
			var selected = form.querySelector('input[name="tier"]:checked');
			if (!selected) {
				return null;
			}
			return cfg.tiers[selected.value] || null;
		}

		function getFormData() {
			return {
				name: form.querySelector('[name="name"]').value.trim(),
				email: form.querySelector('[name="email"]').value.trim(),
				phone: form.querySelector('[name="phone"]').value.trim(),
				notes: form.querySelector('[name="notes"]').value.trim(),
				tier: form.querySelector('input[name="tier"]:checked') ? form.querySelector('input[name="tier"]:checked').value : '',
				terms: form.querySelector('[name="terms"]').checked ? '1' : ''
			};
		}

		function showAlert(message, type) {
			if (!alertEl) {
				return;
			}
			alertEl.textContent = message;
			alertEl.hidden = !message;
			alertEl.classList.remove('is-error', 'is-success');
			if (type) {
				alertEl.classList.add(type === 'success' ? 'is-success' : 'is-error');
			}
		}

		function setFieldError(name, message) {
			var field = form.querySelector('[name="' + name + '"]');
			var err = form.querySelector('[data-for="' + name + '"]');
			if (field) {
				field.classList.toggle('is-invalid', !!message);
			}
			if (err) {
				err.textContent = message || '';
			}
		}

		function validateForm() {
			var data = getFormData();
			var valid = true;

			setFieldError('name', '');
			setFieldError('email', '');
			setFieldError('terms', '');

			if (data.name.length < 2) {
				valid = false;
			}
			if (!data.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)) {
				valid = false;
			}
			if (!data.tier || !cfg.tiers[data.tier]) {
				valid = false;
			}
			if (!data.terms) {
				valid = false;
			}

			var canPay = valid && !state.busy;
			if (state.method === 'stripe') {
				canPay = canPay && !!state.paymentElement;
			}

			submitBtn.disabled = !canPay;
			return valid;
		}

		function setBusy(busy) {
			state.busy = busy;
			submitBtn.classList.toggle('is-loading', busy);
			submitBtn.disabled = busy || !validateForm();
			var spinner = submitBtn.querySelector('.hc-payment__spinner');
			if (spinner) {
				spinner.hidden = !busy;
			}
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

		function showSuccess(message) {
			root.classList.add('is-success');
			if (successWrap) {
				successWrap.hidden = false;
			}
			if (successMsg) {
				successMsg.textContent = message || i18n.success;
			}
		}

		function initStripe(mountEl) {
			var tier = getSelectedTier();
			state.stripe = Stripe(cfg.stripePublishable);
			state.elements = state.stripe.elements({
				mode: 'payment',
				amount: tier ? tier.amountCents : 4900,
				currency: (cfg.currency || 'usd').toLowerCase(),
				paymentMethodTypes: ['card'],
				appearance: {
					theme: 'stripe',
					variables: {
						colorPrimary: '#670086',
						colorText: '#260f53',
						borderRadius: '10px'
					}
				}
			});

			// Card-only Payment Element — no bank, Klarna, Link, or wallet tabs.
			state.paymentElement = state.elements.create('payment', {
				paymentMethodTypes: ['card'],
				wallets: {
					applePay: 'never',
					googlePay: 'never',
					link: 'never'
				}
			});
			state.paymentElement.mount(mountEl);
			state.paymentElement.on('ready', validateForm);
		}

		function updateStripeAmount() {
			if (!state.elements) {
				return;
			}
			var tier = getSelectedTier();
			if (!tier) {
				return;
			}
			state.elements.update({
				amount: tier.amountCents,
				currency: (cfg.currency || 'usd').toLowerCase()
			});
		}

		function renderPayPal(mountEl) {
			if (state.paypalRendered) {
				return;
			}
			state.paypalRendered = true;

			paypal.Buttons({
				style: {
					layout: 'vertical',
					color: 'gold',
					shape: 'rect',
					label: 'paypal'
				},
				onClick: function (data, actions) {
					if (!validateForm()) {
						showAlert(i18n.invalidForm, 'error');
						highlightInvalid();
						return actions.reject();
					}
					return actions.resolve();
				},
				createOrder: function () {
					return postAjax('hc_create_paypal_order', getFormData()).then(function (json) {
						if (!json.success) {
							throw new Error(json.data && json.data.message ? json.data.message : i18n.errorGeneric);
						}
						return json.data.order_id;
					});
				},
				onApprove: function (data) {
					setBusy(true);
					showAlert(i18n.processing, 'success');
					var payload = getFormData();
					payload.order_id = data.orderID;
					return postAjax('hc_capture_paypal_order', payload)
						.then(function (json) {
							if (!json.success) {
								throw new Error(json.data && json.data.message ? json.data.message : i18n.errorGeneric);
							}
							showAlert('', '');
							showSuccess(json.data.message || i18n.success);
						})
						.catch(function (err) {
							showAlert(err.message || i18n.errorGeneric, 'error');
						})
						.finally(function () {
							setBusy(false);
						});
				},
				onError: function (err) {
					showAlert(err && err.message ? err.message : i18n.errorGeneric, 'error');
				}
			}).render(mountEl);
		}

		function highlightInvalid() {
			var data = getFormData();
			setFieldError('name', data.name.length < 2 ? i18n.required : '');
			setFieldError('email', !data.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email) ? i18n.invalidEmail : '');
			setFieldError('terms', !data.terms ? i18n.acceptTerms : '');
		}

		function onSubmit(event) {
			event.preventDefault();
			if (state.method !== 'stripe') {
				return;
			}
			if (!validateForm()) {
				showAlert(i18n.invalidForm, 'error');
				highlightInvalid();
				return;
			}

			setBusy(true);
			showAlert(i18n.processing, 'success');

			var formData = getFormData();

			state.elements.submit()
				.then(function (submitResult) {
					if (submitResult.error) {
						throw submitResult.error;
					}
					return postAjax('hc_create_stripe_intent', formData);
				})
				.then(function (json) {
					if (!json.success) {
						throw new Error(json.data && json.data.message ? json.data.message : i18n.errorGeneric);
					}
					return state.stripe.confirmPayment({
						elements: state.elements,
						clientSecret: json.data.client_secret,
						confirmParams: {
							return_url: window.location.href,
							payment_method_data: {
								billing_details: {
									name: formData.name,
									email: formData.email,
									phone: formData.phone || undefined
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
						var verifyPayload = getFormData();
						verifyPayload.payment_intent_id = result.paymentIntent.id;
						return postAjax('hc_verify_stripe_payment', verifyPayload);
					}
					return null;
				})
				.then(function (json) {
					if (!json) {
						return;
					}
					if (!json.success) {
						throw new Error(json.data && json.data.message ? json.data.message : i18n.errorGeneric);
					}
					showAlert('', '');
					showSuccess(json.data.message || i18n.success);
				})
				.catch(function (err) {
					showAlert(stripeErrorMessage(err), 'error');
				})
				.finally(function () {
					setBusy(false);
				});
		}
	}
})();
