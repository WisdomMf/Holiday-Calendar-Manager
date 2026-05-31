/* Holiday Calendar - admin add / edit form. */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var form = document.getElementById( 'hc-date-form' );
		if ( ! form ) {
			return;
		}

		var titleEl = document.getElementById( 'hc-date-form-title' );
		var actionEl = document.getElementById( 'hc_action' );
		var indexEl = document.getElementById( 'hc_index' );
		var nonceEl = document.getElementById( 'hc_wpnonce' );
		var dateEl = document.getElementById( 'hc_date' );
		var dateStartEl = document.getElementById( 'hc_date_start' );
		var dateEndEl = document.getElementById( 'hc_date_end' );
		var singleWrapEl = document.getElementById( 'hc-date-single-wrap' );
		var rangeWrapEl = document.getElementById( 'hc-date-range-wrap' );
		var typeSingleEl = document.getElementById( 'hc_date_type_single' );
		var typeRangeEl = document.getElementById( 'hc_date_type_range' );
		var labelEl = document.getElementById( 'hc_label' );
		var colorHiddenEl = document.getElementById( 'hc_color' );
		var colorPickerEl = document.getElementById( 'hc_color_picker' );
		var customWrapEl = document.getElementById( 'hc-color-custom-wrap' );
		var choiceEls = form.querySelectorAll( 'input[name="hc_color_choice"]' );
		var submitEl = document.getElementById( 'hc-submit' );
		var cancelEl = document.getElementById( 'hc-cancel-edit' );
		var addNonce = form.getAttribute( 'data-add-nonce' );
		var editNonce = form.getAttribute( 'data-edit-nonce' );
		var defaultHex = colorHiddenEl ? colorHiddenEl.value : '#e5484d';

		function normalizeHex( hex ) {
			if ( ! hex ) {
				return '';
			}
			hex = String( hex ).trim().toLowerCase();
			if ( hex.charAt( 0 ) !== '#' ) {
				hex = '#' + hex;
			}
			return hex;
		}

		function isRangeMode() {
			return typeRangeEl && typeRangeEl.checked;
		}

		function presetForHex( hex ) {
			var normalized = normalizeHex( hex );
			var match = null;
			choiceEls.forEach( function ( el ) {
				if ( el.value === 'custom' ) {
					return;
				}
				if ( normalizeHex( el.getAttribute( 'data-hex' ) ) === normalized ) {
					match = el;
				}
			} );
			return match;
		}

		function selectedChoice() {
			var selected = null;
			choiceEls.forEach( function ( el ) {
				if ( el.checked ) {
					selected = el;
				}
			} );
			return selected;
		}

		function syncHiddenColor() {
			var choice = selectedChoice();
			if ( ! choice || ! colorHiddenEl ) {
				return;
			}
			if ( 'custom' === choice.value ) {
				if ( colorPickerEl ) {
					colorHiddenEl.value = colorPickerEl.value;
				}
			} else {
				colorHiddenEl.value = choice.getAttribute( 'data-hex' ) || defaultHex;
			}
		}

		function updateCustomVisibility() {
			var choice = selectedChoice();
			var isCustom = choice && 'custom' === choice.value;
			if ( customWrapEl ) {
				customWrapEl.hidden = ! isCustom;
			}
			syncHiddenColor();
		}

		function setColorChoice( hex ) {
			var preset = presetForHex( hex );
			choiceEls.forEach( function ( el ) {
				el.checked = false;
			} );
			if ( preset ) {
				preset.checked = true;
			} else {
				choiceEls.forEach( function ( el ) {
					if ( 'custom' === el.value ) {
						el.checked = true;
					}
				} );
				if ( colorPickerEl ) {
					colorPickerEl.value = hex || defaultHex;
				}
			}
			updateCustomVisibility();
		}

		function updateDateTypeVisibility() {
			var range = isRangeMode();
			if ( singleWrapEl ) {
				singleWrapEl.hidden = range;
			}
			if ( rangeWrapEl ) {
				rangeWrapEl.hidden = ! range;
			}
			if ( dateEl ) {
				dateEl.required = ! range;
			}
			if ( dateStartEl ) {
				dateStartEl.required = range;
			}
			if ( dateEndEl ) {
				dateEndEl.required = range;
			}
		}

		function setDateType( type ) {
			if ( typeRangeEl ) {
				typeRangeEl.checked = 'range' === type;
			}
			if ( typeSingleEl ) {
				typeSingleEl.checked = 'range' !== type;
			}
			updateDateTypeVisibility();
		}

		function syncRangeToHiddenDate() {
			if ( isRangeMode() && dateStartEl && dateEl ) {
				dateEl.value = dateStartEl.value;
			}
		}

		choiceEls.forEach( function ( el ) {
			el.addEventListener( 'change', updateCustomVisibility );
		} );

		if ( colorPickerEl ) {
			colorPickerEl.addEventListener( 'input', syncHiddenColor );
			colorPickerEl.addEventListener( 'change', syncHiddenColor );
		}

		if ( typeSingleEl ) {
			typeSingleEl.addEventListener( 'change', updateDateTypeVisibility );
		}
		if ( typeRangeEl ) {
			typeRangeEl.addEventListener( 'change', updateDateTypeVisibility );
		}

		form.addEventListener( 'submit', function () {
			syncHiddenColor();
			syncRangeToHiddenDate();
		} );

		function resetForm() {
			actionEl.value = 'add_date';
			indexEl.value = '';
			nonceEl.value = addNonce;
			setDateType( 'single' );
			dateEl.value = '';
			if ( dateStartEl ) {
				dateStartEl.value = '';
			}
			if ( dateEndEl ) {
				dateEndEl.value = '';
			}
			labelEl.value = '';
			setColorChoice( defaultHex );
			submitEl.textContent = submitEl.getAttribute( 'data-add-label' );
			titleEl.textContent = titleEl.getAttribute( 'data-add-title' );
			cancelEl.hidden = true;
			dateEl.focus();
		}

		function startEdit( btn ) {
			var dateType = btn.getAttribute( 'data-date-type' ) || 'single';
			var startDate = btn.getAttribute( 'data-date' ) || '';
			var endDate = btn.getAttribute( 'data-date-end' ) || '';

			actionEl.value = 'edit_date';
			indexEl.value = btn.getAttribute( 'data-index' );
			nonceEl.value = editNonce;
			setDateType( dateType );

			if ( 'range' === dateType ) {
				if ( dateStartEl ) {
					dateStartEl.value = startDate;
				}
				if ( dateEndEl ) {
					dateEndEl.value = endDate;
				}
				dateEl.value = startDate;
			} else {
				dateEl.value = startDate;
				if ( dateStartEl ) {
					dateStartEl.value = '';
				}
				if ( dateEndEl ) {
					dateEndEl.value = '';
				}
			}

			labelEl.value = btn.getAttribute( 'data-label' );
			setColorChoice( btn.getAttribute( 'data-color' ) || defaultHex );
			submitEl.textContent = submitEl.getAttribute( 'data-edit-label' );
			titleEl.textContent = titleEl.getAttribute( 'data-edit-title' );
			cancelEl.hidden = false;
			form.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			if ( 'range' === dateType && dateStartEl ) {
				dateStartEl.focus();
			} else {
				dateEl.focus();
			}
		}

		submitEl.setAttribute( 'data-add-label', submitEl.textContent );
		submitEl.setAttribute( 'data-edit-label', submitEl.getAttribute( 'data-edit-label' ) || 'Save changes' );
		titleEl.setAttribute( 'data-add-title', titleEl.textContent );
		titleEl.setAttribute( 'data-edit-title', titleEl.getAttribute( 'data-edit-title' ) || 'Edit marked date' );

		document.querySelectorAll( '.hc-edit-date' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				startEdit( btn );
			} );
		} );

		if ( cancelEl ) {
			cancelEl.addEventListener( 'click', resetForm );
		}

		updateCustomVisibility();
		updateDateTypeVisibility();

		if ( ! dateEl.value ) {
			dateEl.focus();
		}
	} );
} )();
