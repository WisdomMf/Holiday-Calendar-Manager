<?php
/**
 * Shared data helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin bootstrap file path (for plugins_url).
 *
 * @return string
 */
function hc_plugin_file() {
	return HC_PATH . 'holiday-calendar.php';
}

/**
 * Absolute path to a plugin asset.
 *
 * @param string $relative Path relative to the plugin root, e.g. assets/calendar.css.
 * @return string
 */
function hc_asset_path( $relative ) {
	return HC_PATH . ltrim( $relative, '/' );
}

/**
 * Public URL for a plugin asset via plugins_url().
 *
 * @param string $relative Path relative to the plugin root.
 * @return string
 */
function hc_asset_url( $relative ) {
	return plugins_url( ltrim( $relative, '/' ), hc_plugin_file() );
}

/**
 * Cache-busting version for a plugin asset (filemtime + plugin version).
 *
 * @param string $relative Path relative to the plugin root.
 * @return string
 */
function hc_asset_version( $relative ) {
	$path = hc_asset_path( $relative );
	if ( is_readable( $path ) ) {
		return HC_VERSION . '.' . filemtime( $path );
	}
	return HC_VERSION;
}

/**
 * Whether the current front-end request likely renders [holiday_calendar].
 *
 * Used to enqueue CSS/JS in wp_head instead of relying on shortcode render timing
 * (page builders and optimization plugins often drop footer-enqueued styles).
 *
 * @return bool
 */
function hc_page_needs_calendar_assets() {
	if ( is_admin() ) {
		return false;
	}

	global $post;
	if ( ! $post instanceof WP_Post ) {
		return false;
	}

	if ( has_shortcode( $post->post_content, 'holiday_calendar' ) ) {
		return true;
	}

	// Elementor and similar builders store shortcodes in post meta JSON.
	$meta_keys = array( '_elementor_data', '_elementor_css' );
	foreach ( $meta_keys as $meta_key ) {
		$meta = get_post_meta( $post->ID, $meta_key, true );
		if ( is_string( $meta ) && false !== strpos( $meta, 'holiday_calendar' ) ) {
			return true;
		}
	}

	/**
	 * Allow themes/builders to flag pages that render the calendar outside post_content.
	 *
	 * @param bool     $needs Whether assets are needed.
	 * @param WP_Post  $post  Current post object.
	 */
	return (bool) apply_filters( 'hc_page_needs_calendar_assets', false, $post );
}

/**
 * Return stored marked dates as a clean array.
 *
 * @return array[] Each item: date, label, color; optional date_end for ranges.
 */
function hc_get_dates() {
	$dates = get_option( 'hc_dates', array() );
	if ( ! is_array( $dates ) ) {
		return array();
	}
	return array_map( 'hc_normalize_date_entry', $dates );
}

/**
 * Normalise a stored date entry (backward compatible with single dates only).
 *
 * @param array $entry Raw stored entry.
 * @return array
 */
function hc_normalize_date_entry( array $entry ) {
	$date = isset( $entry['date'] ) ? sanitize_text_field( $entry['date'] ) : '';
	$out  = array(
		'date'  => $date,
		'label' => isset( $entry['label'] ) ? sanitize_text_field( $entry['label'] ) : '',
		'color' => isset( $entry['color'] ) ? hc_normalize_hex( $entry['color'] ) : hc_get_default_color(),
	);
	if ( ! empty( $entry['date_end'] ) && hc_is_valid_date( $entry['date_end'] ) && $entry['date_end'] !== $date ) {
		$out['date_end'] = sanitize_text_field( $entry['date_end'] );
	}
	return $out;
}

/**
 * Whether an entry spans multiple days.
 *
 * @param array $entry Normalised entry.
 * @return bool
 */
function hc_is_range_entry( array $entry ) {
	return ! empty( $entry['date_end'] ) && $entry['date_end'] !== $entry['date'];
}

/**
 * Inclusive end date for an entry (single or range).
 *
 * @param array $entry Normalised entry.
 * @return string YYYY-MM-DD.
 */
function hc_entry_end_date( array $entry ) {
	return hc_is_range_entry( $entry ) ? $entry['date_end'] : $entry['date'];
}

/**
 * Expand an entry to individual Y-m-d keys (inclusive).
 *
 * @param array $entry Normalised entry.
 * @return string[]
 */
function hc_expand_date_entry( array $entry ) {
	if ( ! hc_is_valid_date( $entry['date'] ) ) {
		return array();
	}
	$start = new DateTime( $entry['date'] );
	$end   = new DateTime( hc_entry_end_date( $entry ) );
	if ( $end < $start ) {
		return array( $entry['date'] );
	}
	$days  = array();
	$cursor = clone $start;
	while ( $cursor <= $end ) {
		$days[] = $cursor->format( 'Y-m-d' );
		$cursor->modify( '+1 day' );
	}
	return $days;
}

/**
 * Build date => {label,color} map for the calendar grid (ranges expanded per day).
 *
 * @return array<string, array{label:string,color:string}>
 */
function hc_build_dates_calendar_map() {
	$map = array();
	foreach ( hc_get_dates() as $entry ) {
		foreach ( hc_expand_date_entry( $entry ) as $day ) {
			$map[ $day ] = array(
				'label' => $entry['label'],
				'color' => $entry['color'],
			);
		}
	}
	return $map;
}

/**
 * Build logical holiday entries for list views (one row per single or range).
 *
 * @return array<int, array{id:int,date:string,date_end:string,label:string,color:string,type:string}>
 */
function hc_build_holiday_entries() {
	$entries = array();
	foreach ( hc_get_dates() as $i => $entry ) {
		$entries[] = array(
			'id'       => $i,
			'date'     => $entry['date'],
			'date_end' => hc_entry_end_date( $entry ),
			'label'    => $entry['label'],
			'color'    => $entry['color'],
			'type'     => hc_is_range_entry( $entry ) ? 'range' : 'single',
		);
	}
	return $entries;
}

/**
 * Human-readable date or range for admin tables.
 *
 * @param array $entry Normalised entry.
 * @return string
 */
function hc_format_date_display( array $entry ) {
	$start_ts = strtotime( $entry['date'] );
	if ( ! hc_is_range_entry( $entry ) ) {
		return date_i18n( get_option( 'date_format' ), $start_ts );
	}
	$end_ts   = strtotime( $entry['date_end'] );
	$start_y  = (int) gmdate( 'Y', $start_ts );
	$end_y    = (int) gmdate( 'Y', $end_ts );
	$start_m  = (int) gmdate( 'n', $start_ts );
	$end_m    = (int) gmdate( 'n', $end_ts );
	$start_d  = (int) gmdate( 'j', $start_ts );
	$end_d    = (int) gmdate( 'j', $end_ts );

	if ( $start_y === $end_y && $start_m === $end_m ) {
		/* translators: 1: start day, 2: end day, 3: month and year */
		return sprintf(
			__( '%1$s – %2$s %3$s', 'holiday-calendar' ),
			date_i18n( 'j', $start_ts ),
			date_i18n( 'j', $end_ts ),
			date_i18n( 'F Y', $start_ts )
		);
	}
	if ( $start_y === $end_y ) {
		/* translators: 1: start date, 2: end date, 3: year */
		return sprintf(
			__( '%1$s – %2$s %3$s', 'holiday-calendar' ),
			date_i18n( 'j M', $start_ts ),
			date_i18n( 'j M', $end_ts ),
			date_i18n( 'Y', $start_ts )
		);
	}
	/* translators: 1: start date, 2: end date */
	return sprintf(
		__( '%1$s – %2$s', 'holiday-calendar' ),
		date_i18n( get_option( 'date_format' ), $start_ts ),
		date_i18n( get_option( 'date_format' ), $end_ts )
	);
}

/**
 * Return plugin settings merged with defaults.
 *
 * @return array
 */
function hc_get_settings() {
	$defaults = array(
		'highlight_weekends' => 1,
		'weekend_days'       => array( 0, 6 ),
		'week_starts_on'     => 0,
		'brand_color'        => hc_get_default_brand_color(),
		'calendar_theme'     => 'simple',
	);
	$settings = get_option( 'hc_settings', array() );
	if ( ! is_array( $settings ) ) {
		$settings = array();
	}
	return wp_parse_args( $settings, $defaults );
}

/**
 * Validate a YYYY-MM-DD date string.
 *
 * @param string $date Date string.
 * @return bool
 */
function hc_is_valid_date( $date ) {
	$d = DateTime::createFromFormat( 'Y-m-d', $date );
	return $d && $d->format( 'Y-m-d' ) === $date;
}

/**
 * Predefined marker colours for admin and validation.
 *
 * @return array[] Each item: slug, label, hex.
 */
function hc_get_color_presets() {
	return array(
		array(
			'slug'  => 'red',
			'label' => __( 'Red – Office closed', 'holiday-calendar' ),
			'hex'   => '#e5484d',
		),
		array(
			'slug'  => 'green',
			'label' => __( 'Green – Public holiday', 'holiday-calendar' ),
			'hex'   => '#30a46c',
		),
		array(
			'slug'  => 'blue',
			'label' => __( 'Blue – Company event', 'holiday-calendar' ),
			'hex'   => '#0090ff',
		),
		array(
			'slug'  => 'amber',
			'label' => __( 'Amber – Half day / reduced hours', 'holiday-calendar' ),
			'hex'   => '#f5a623',
		),
		array(
			'slug'  => 'purple',
			'label' => __( 'Purple – Personal leave', 'holiday-calendar' ),
			'hex'   => '#8e4ec6',
		),
		array(
			'slug'  => 'grey',
			'label' => __( 'Grey – Other', 'holiday-calendar' ),
			'hex'   => '#8b8d98',
		),
	);
}

/**
 * Default export header / brand colour.
 *
 * @return string
 */
function hc_get_default_brand_color() {
	return '#260f53';
}

/**
 * Default marker hex when none is supplied.
 *
 * @return string
 */
function hc_get_default_color() {
	$presets = hc_get_color_presets();
	return $presets[0]['hex'];
}

/**
 * Normalise a hex colour to lowercase #rrggbb or empty string.
 *
 * @param string $hex Colour string.
 * @return string
 */
function hc_normalize_hex( $hex ) {
	$hex = sanitize_hex_color( $hex );
	return $hex ? strtolower( $hex ) : '';
}

/**
 * Look up a preset hex by slug.
 *
 * @param string $slug Preset slug.
 * @return string Hex or empty when unknown.
 */
function hc_preset_hex_by_slug( $slug ) {
	foreach ( hc_get_color_presets() as $preset ) {
		if ( $preset['slug'] === $slug ) {
			return $preset['hex'];
		}
	}
	return '';
}

/**
 * Find a preset slug matching a stored hex colour.
 *
 * @param string $hex Hex colour.
 * @return string Preset slug or empty string.
 */
function hc_find_preset_slug_for_hex( $hex ) {
	$hex = hc_normalize_hex( $hex );
	if ( ! $hex ) {
		return '';
	}
	foreach ( hc_get_color_presets() as $preset ) {
		if ( hc_normalize_hex( $preset['hex'] ) === $hex ) {
			return $preset['slug'];
		}
	}
	return '';
}

/**
 * Resolve and validate a marker colour from preset choice and/or custom hex.
 *
 * @param string $color       Submitted hex (custom or synced from preset).
 * @param string $preset_slug Preset slug, "custom", or empty.
 * @return string Valid hex colour.
 */
function hc_sanitize_mark_color( $color, $preset_slug = '' ) {
	$preset_slug = sanitize_key( $preset_slug );
	if ( $preset_slug && 'custom' !== $preset_slug ) {
		$from_preset = hc_preset_hex_by_slug( $preset_slug );
		if ( $from_preset ) {
			return hc_normalize_hex( $from_preset );
		}
	}

	$color = hc_normalize_hex( $color );
	if ( $color ) {
		return $color;
	}

	return hc_get_default_color();
}

/**
 * Sort marked dates ascending by date string.
 *
 * @param array[] $dates Marked dates.
 * @return array[]
 */
function hc_sort_dates( $dates ) {
	usort(
		$dates,
		function ( $a, $b ) {
			return strcmp( $a['date'], $b['date'] );
		}
	);
	return $dates;
}

/**
 * Check whether a date falls within any existing single or range entry.
 *
 * @param string   $date          YYYY-MM-DD date.
 * @param int|null $exclude_index Optional index to skip (when editing).
 * @return bool
 */
function hc_date_exists( $date, $exclude_index = null ) {
	return hc_date_range_overlaps( $date, $date, $exclude_index );
}

/**
 * Check whether a date range overlaps any existing entry.
 *
 * @param string   $start         Range start YYYY-MM-DD.
 * @param string   $end           Range end YYYY-MM-DD (inclusive).
 * @param int|null $exclude_index Optional index to skip (when editing).
 * @return bool
 */
function hc_date_range_overlaps( $start, $end, $exclude_index = null ) {
	if ( ! hc_is_valid_date( $start ) || ! hc_is_valid_date( $end ) ) {
		return false;
	}
	if ( strcmp( $end, $start ) < 0 ) {
		$tmp   = $start;
		$start = $end;
		$end   = $tmp;
	}

	foreach ( hc_get_dates() as $i => $entry ) {
		if ( null !== $exclude_index && (int) $exclude_index === (int) $i ) {
			continue;
		}
		$existing_start = $entry['date'];
		$existing_end   = hc_entry_end_date( $entry );
		if ( strcmp( $start, $existing_end ) <= 0 && strcmp( $end, $existing_start ) >= 0 ) {
			return true;
		}
	}
	return false;
}

/**
 * Validate and build a stored entry from start/end dates.
 *
 * @param string $date      Start YYYY-MM-DD.
 * @param string $date_end  End YYYY-MM-DD (optional; same as start for singles).
 * @param string $label     Label.
 * @param string $color     Hex colour.
 * @param int|null $exclude_index Skip when checking overlaps.
 * @return array|WP_Error
 */
function hc_prepare_marked_date_entry( $date, $date_end, $label, $color, $exclude_index = null ) {
	if ( ! hc_is_valid_date( $date ) ) {
		return new WP_Error( 'hc_invalid', __( 'Please enter a valid date.', 'holiday-calendar' ) );
	}
	$date_end = $date_end ? sanitize_text_field( $date_end ) : $date;
	if ( ! hc_is_valid_date( $date_end ) ) {
		return new WP_Error( 'hc_invalid', __( 'Please enter a valid end date.', 'holiday-calendar' ) );
	}
	if ( strcmp( $date_end, $date ) < 0 ) {
		return new WP_Error( 'hc_invalid_range', __( 'End date must be on or after the start date.', 'holiday-calendar' ) );
	}
	if ( hc_date_range_overlaps( $date, $date_end, $exclude_index ) ) {
		return new WP_Error( 'hc_duplicate', __( 'These dates overlap an existing holiday.', 'holiday-calendar' ) );
	}

	$entry = array(
		'date'  => $date,
		'label' => $label ? $label : __( 'Marked', 'holiday-calendar' ),
		'color' => hc_sanitize_mark_color( $color ),
	);
	if ( $date_end !== $date ) {
		$entry['date_end'] = $date_end;
	}
	return $entry;
}

/**
 * Add a marked date or date range.
 *
 * @param string $date      Start YYYY-MM-DD.
 * @param string $label     Optional label.
 * @param string $color     Optional hex colour.
 * @param string $date_end  Optional end YYYY-MM-DD (inclusive range).
 * @return true|WP_Error
 */
function hc_add_marked_date( $date, $label = '', $color = '', $date_end = '' ) {
	$entry = hc_prepare_marked_date_entry( $date, $date_end, $label, $color );
	if ( is_wp_error( $entry ) ) {
		return $entry;
	}

	$dates   = hc_get_dates();
	$dates[] = $entry;
	update_option( 'hc_dates', hc_sort_dates( $dates ) );
	return true;
}

/**
 * Update an existing marked date or range.
 *
 * @param int    $index    Array index of the entry to update.
 * @param string $date     Start YYYY-MM-DD.
 * @param string $label    Optional label.
 * @param string $color    Optional hex colour.
 * @param string $date_end Optional end YYYY-MM-DD (inclusive range).
 * @return true|WP_Error
 */
function hc_update_marked_date( $index, $date, $label = '', $color = '', $date_end = '' ) {
	$dates = hc_get_dates();
	if ( ! isset( $dates[ $index ] ) ) {
		return new WP_Error( 'hc_not_found', __( 'Date not found.', 'holiday-calendar' ) );
	}

	$entry = hc_prepare_marked_date_entry( $date, $date_end, $label, $color, $index );
	if ( is_wp_error( $entry ) ) {
		return $entry;
	}

	$dates[ $index ] = $entry;
	update_option( 'hc_dates', hc_sort_dates( $dates ) );
	return true;
}

/**
 * Delete a marked date by index.
 *
 * @param int $index Array index.
 * @return bool True when removed, false when index missing.
 */
function hc_delete_marked_date( $index ) {
	$dates = hc_get_dates();
	if ( ! isset( $dates[ $index ] ) ) {
		return false;
	}
	array_splice( $dates, $index, 1 );
	update_option( 'hc_dates', $dates );
	return true;
}

/**
 * Payment settings merged with defaults.
 *
 * @return array
 */
function hc_get_payment_settings() {
	$defaults = array(
		'sandbox_mode'            => 1,
		'currency'                => 'USD',
		'stripe_publishable_key'  => '',
		'stripe_secret_key'       => '',
		'stripe_webhook_secret'   => '',
		'paypal_client_id'        => '',
		'paypal_secret'           => '',
		'paypal_webhook_id'       => '',
	);
	$settings = get_option( 'hc_payment_settings', array() );
	if ( ! is_array( $settings ) ) {
		$settings = array();
	}
	return wp_parse_args( $settings, $defaults );
}

/**
 * Hardcoded booking tiers — amounts resolved server-side only.
 *
 * @return array<string, array{label:string,amount_cents:int,description:string}>
 */
function hc_get_payment_tiers() {
	return array(
		'basic'    => array(
			'label'        => __( 'Basic Booking', 'holiday-calendar' ),
			'amount_cents' => 4900,
			'description'  => __( 'Standard holiday calendar booking package.', 'holiday-calendar' ),
		),
		'standard' => array(
			'label'        => __( 'Standard Booking', 'holiday-calendar' ),
			'amount_cents' => 7900,
			'description'  => __( 'Extended booking with priority support.', 'holiday-calendar' ),
		),
		'premium'  => array(
			'label'        => __( 'Premium Booking', 'holiday-calendar' ),
			'amount_cents' => 9900,
			'description'  => __( 'Full-service premium booking package.', 'holiday-calendar' ),
		),
	);
}

/**
 * Resolve a tier by slug with server-side validation.
 *
 * @param string $tier_slug Tier key.
 * @return array|WP_Error
 */
function hc_resolve_payment_tier( $tier_slug ) {
	$tier_slug = sanitize_key( $tier_slug );
	$tiers     = hc_get_payment_tiers();
	if ( ! isset( $tiers[ $tier_slug ] ) ) {
		return new WP_Error( 'hc_invalid_tier', __( 'Please select a valid booking option.', 'holiday-calendar' ) );
	}
	return $tiers[ $tier_slug ];
}

/**
 * Format cents as a localized currency string.
 *
 * @param int    $amount_cents Amount in smallest unit.
 * @param string $currency     ISO currency code.
 * @return string
 */
function hc_format_money( $amount_cents, $currency = 'USD' ) {
	$amount = absint( $amount_cents ) / 100;
	return sprintf(
		/* translators: %1$s: currency symbol/code, %2$s: formatted amount */
		__( '%1$s %2$s', 'holiday-calendar' ),
		strtoupper( sanitize_text_field( $currency ) ),
		number_format_i18n( $amount, 2 )
	);
}

/**
 * Best-effort client IP for rate limiting.
 *
 * @return string
 */
function hc_get_client_ip() {
	$candidates = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
	foreach ( $candidates as $key ) {
		if ( empty( $_SERVER[ $key ] ) ) {
			continue;
		}
		$raw = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
		foreach ( explode( ',', $raw ) as $part ) {
			$ip = trim( $part );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
	}
	return '0.0.0.0';
}

/**
 * Simple transient-based rate limiter.
 *
 * @param string $action Action identifier.
 * @param int    $limit  Max requests per window.
 * @param int    $window Window in seconds.
 * @return bool True when allowed.
 */
function hc_payment_rate_limit( $action, $limit = 10, $window = 60 ) {
	$key   = 'hc_pay_rl_' . md5( sanitize_key( $action ) . hc_get_client_ip() );
	$count = (int) get_transient( $key );
	if ( $count >= $limit ) {
		return false;
	}
	set_transient( $key, $count + 1, $window );
	return true;
}

/**
 * Sanitize payment form submission fields.
 *
 * @param array $input Raw request data.
 * @return array|WP_Error
 */
function hc_sanitize_payment_submission( array $input ) {
	$name  = isset( $input['name'] ) ? sanitize_text_field( wp_unslash( $input['name'] ) ) : '';
	$email = isset( $input['email'] ) ? sanitize_email( wp_unslash( $input['email'] ) ) : '';
	$phone = isset( $input['phone'] ) ? sanitize_text_field( wp_unslash( $input['phone'] ) ) : '';
	$notes = isset( $input['notes'] ) ? sanitize_textarea_field( wp_unslash( $input['notes'] ) ) : '';
	$tier  = isset( $input['tier'] ) ? sanitize_key( wp_unslash( $input['tier'] ) ) : '';
	$terms = ! empty( $input['terms'] );

	if ( '' === $name || strlen( $name ) < 2 ) {
		return new WP_Error( 'hc_name', __( 'Please enter your full name.', 'holiday-calendar' ) );
	}
	if ( ! is_email( $email ) ) {
		return new WP_Error( 'hc_email', __( 'Please enter a valid email address.', 'holiday-calendar' ) );
	}
	if ( ! $terms ) {
		return new WP_Error( 'hc_terms', __( 'Please accept the terms to continue.', 'holiday-calendar' ) );
	}

	$tier_data = hc_resolve_payment_tier( $tier );
	if ( is_wp_error( $tier_data ) ) {
		return $tier_data;
	}

	return array(
		'name'         => $name,
		'email'        => $email,
		'phone'        => $phone,
		'notes'        => $notes,
		'tier'         => $tier,
		'tier_label'   => $tier_data['label'],
		'amount_cents' => (int) $tier_data['amount_cents'],
		'reference'    => 'hc_' . wp_generate_password( 10, false, false ),
	);
}

/**
 * Append a payment log entry (last 100 kept).
 *
 * @param array $entry Log data.
 */
function hc_log_payment( array $entry ) {
	$logs   = get_option( 'hc_payment_logs', array() );
	$logs   = is_array( $logs ) ? $logs : array();
	$logs[] = array_merge(
		array(
			'time' => current_time( 'mysql' ),
		),
		$entry
	);
	if ( count( $logs ) > 100 ) {
		$logs = array_slice( $logs, -100 );
	}
	update_option( 'hc_payment_logs', $logs, false );
}

/**
 * Whether any payment provider is configured.
 *
 * @return bool
 */
function hc_payments_available() {
	$settings = hc_get_payment_settings();
	$stripe   = new HC_Payment_Stripe( $settings );
	$paypal   = new HC_Payment_PayPal( $settings );
	return $stripe->is_configured() || $paypal->is_configured();
}

/**
 * Summer camp registration form settings merged with defaults.
 *
 * @return array
 */
function hc_get_registration_settings() {
	$defaults = array(
		'form_title'        => __( "Shruti's SOPA – Summer Camp Registration", 'holiday-calendar' ),
		'form_subtitle'     => __( 'Submit the form to book your slot today!', 'holiday-calendar' ) . ' | shrutissopa.com',
		'logo_url'          => 'https://shrutissopa.com/wp-content/uploads/2025/07/Shrutis-SOPA-Logo-Transparent-500x500-1.png',
		'badge_line1'       => 'June 2026',
		'badge_line2'       => 'Camp',
		'badge_line3'       => 'Registration',
		'zelle_email'       => 'shrutisdanceyoga@gmail.com',
		'whatsapp_number'   => '+1-469-978-1433',
		'whatsapp_qr_url'   => '',
		'terms_url'         => 'https://shrutissopa.com/summer-camp-terms-conditions/',
		'privacy_url'       => 'https://shrutissopa.com/privacy-policy/',
		'notify_admin'      => 1,
		'admin_email'       => get_option( 'admin_email' ),
	);
	$settings = get_option( 'hc_registration_settings', array() );
	if ( ! is_array( $settings ) ) {
		$settings = array();
	}
	return wp_parse_args( $settings, $defaults );
}

/**
 * Valid referral source keys for registration form.
 *
 * @return string[]
 */
function hc_get_registration_referral_sources() {
	return array( 'social_media', 'friend_family', 'event', 'google', 'other' );
}

/**
 * Server-side summer camp registration amount (cents, USD).
 *
 * Weekly: $350. Monthly June/July: $500 deposit. Monthly both: $1,000 deposit.
 *
 * @param string $camp_session weekly|monthly.
 * @param string $camp_month   june|july|both (monthly only).
 * @return int|WP_Error Amount in cents.
 */
function hc_registration_calculate_amount( $camp_session, $camp_month = '' ) {
	$camp_session = sanitize_key( $camp_session );
	$camp_month   = sanitize_key( $camp_month );

	if ( 'weekly' === $camp_session ) {
		return 35000;
	}

	if ( 'monthly' === $camp_session ) {
		if ( 'both' === $camp_month ) {
			return 100000;
		}
		if ( in_array( $camp_month, array( 'june', 'july' ), true ) ) {
			return 50000;
		}
		return new WP_Error( 'hc_reg_amount', __( 'Please select a camp month for monthly session.', 'holiday-calendar' ) );
	}

	return new WP_Error( 'hc_reg_amount', __( 'Please select a valid camp session.', 'holiday-calendar' ) );
}

/**
 * Human-readable amount due label for registration payment step.
 *
 * @param string $camp_session weekly|monthly.
 * @param string $camp_month   june|july|both.
 * @return string
 */
function hc_registration_amount_due_label( $camp_session, $camp_month = '' ) {
	$amount = hc_registration_calculate_amount( $camp_session, $camp_month );
	if ( is_wp_error( $amount ) ) {
		return '';
	}

	$settings = hc_get_payment_settings();
	$formatted = hc_format_money( $amount, $settings['currency'] );

	if ( 'weekly' === sanitize_key( $camp_session ) ) {
		return sprintf(
			/* translators: %s: formatted amount */
			__( '%s due today', 'holiday-calendar' ),
			$formatted
		);
	}

	return sprintf(
		/* translators: %s: formatted deposit amount */
		__( '%s deposit due today', 'holiday-calendar' ),
		$formatted
	);
}

/**
 * Verify a completed registration payment (Stripe or PayPal).
 *
 * @param string $provider         stripe|paypal.
 * @param string $payment_ref      PaymentIntent or PayPal order ID.
 * @param int    $expected_cents   Expected amount in cents.
 * @return true|WP_Error
 */
function hc_verify_registration_payment( $provider, $payment_ref, $expected_cents ) {
	$settings = hc_get_payment_settings();
	$provider = sanitize_key( $provider );
	$expected_cents = absint( $expected_cents );

	if ( $expected_cents < 50 ) {
		return new WP_Error( 'hc_reg_payment', __( 'Invalid payment amount.', 'holiday-calendar' ) );
	}

	if ( 'stripe' === $provider ) {
		$stripe = new HC_Payment_Stripe( $settings );
		if ( ! $stripe->is_configured() ) {
			return new WP_Error( 'hc_reg_payment', __( 'Stripe is not configured.', 'holiday-calendar' ) );
		}
		$result = $stripe->verify_payment_intent( $payment_ref, $expected_cents );
		return is_wp_error( $result ) ? $result : true;
	}

	if ( 'paypal' === $provider ) {
		$paypal = new HC_Payment_PayPal( $settings );
		if ( ! $paypal->is_configured() ) {
			return new WP_Error( 'hc_reg_payment', __( 'PayPal is not configured.', 'holiday-calendar' ) );
		}
		$result = $paypal->verify_order_captured( $payment_ref, $expected_cents );
		return is_wp_error( $result ) ? $result : true;
	}

	return new WP_Error( 'hc_reg_payment', __( 'Invalid payment method.', 'holiday-calendar' ) );
}

/**
 * Sanitize and validate a summer camp registration submission.
 *
 * @param array $input Raw POST data.
 * @return array|WP_Error
 */
function hc_sanitize_registration_submission( array $input ) {
	$errors = array();

	$student_name = isset( $input['student_full_name'] ) ? sanitize_text_field( wp_unslash( $input['student_full_name'] ) ) : '';
	$student_dob  = isset( $input['student_dob'] ) ? sanitize_text_field( wp_unslash( $input['student_dob'] ) ) : '';
	$student_age  = isset( $input['student_age'] ) ? absint( wp_unslash( $input['student_age'] ) ) : 0;
	$gender       = isset( $input['student_gender'] ) ? sanitize_key( wp_unslash( $input['student_gender'] ) ) : '';
	$camp_session = isset( $input['camp_session'] ) ? sanitize_key( wp_unslash( $input['camp_session'] ) ) : '';
	$camp_month   = isset( $input['camp_month'] ) ? sanitize_key( wp_unslash( $input['camp_month'] ) ) : '';
	$special      = isset( $input['special_needs'] ) ? sanitize_key( wp_unslash( $input['special_needs'] ) ) : '';
	$special_det  = isset( $input['special_needs_details'] ) ? sanitize_textarea_field( wp_unslash( $input['special_needs_details'] ) ) : '';

	$mother_name  = isset( $input['mother_name'] ) ? sanitize_text_field( wp_unslash( $input['mother_name'] ) ) : '';
	$father_name  = isset( $input['father_name'] ) ? sanitize_text_field( wp_unslash( $input['father_name'] ) ) : '';
	$mother_phone = isset( $input['mother_phone'] ) ? sanitize_text_field( wp_unslash( $input['mother_phone'] ) ) : '';
	$father_phone = isset( $input['father_phone'] ) ? sanitize_text_field( wp_unslash( $input['father_phone'] ) ) : '';
	$address      = isset( $input['address'] ) ? sanitize_textarea_field( wp_unslash( $input['address'] ) ) : '';
	$email        = isset( $input['email'] ) ? sanitize_email( wp_unslash( $input['email'] ) ) : '';

	$emergency_same = isset( $input['emergency_same'] ) ? sanitize_key( wp_unslash( $input['emergency_same'] ) ) : '';
	$emergency_name = isset( $input['emergency_name'] ) ? sanitize_text_field( wp_unslash( $input['emergency_name'] ) ) : '';
	$emergency_mob  = isset( $input['emergency_mobile'] ) ? sanitize_text_field( wp_unslash( $input['emergency_mobile'] ) ) : '';
	$notes          = isset( $input['additional_notes'] ) ? sanitize_textarea_field( wp_unslash( $input['additional_notes'] ) ) : '';

	$payment_provider = isset( $input['payment_provider'] ) ? sanitize_key( wp_unslash( $input['payment_provider'] ) ) : '';
	$payment_ref      = isset( $input['payment_ref'] ) ? sanitize_text_field( wp_unslash( $input['payment_ref'] ) ) : '';

	$referrals_raw = isset( $input['referral_sources'] ) ? wp_unslash( $input['referral_sources'] ) : array();
	if ( ! is_array( $referrals_raw ) ) {
		$referrals_raw = array( $referrals_raw );
	}
	$allowed_refs = hc_get_registration_referral_sources();
	$referrals    = array();
	foreach ( $referrals_raw as $ref ) {
		$key = sanitize_key( $ref );
		if ( in_array( $key, $allowed_refs, true ) ) {
			$referrals[] = $key;
		}
	}
	$referrals = array_unique( $referrals );

	$terms     = ! empty( $input['terms_consent'] );
	$signature = isset( $input['signature_name'] ) ? sanitize_text_field( wp_unslash( $input['signature_name'] ) ) : '';
	$sig_date  = isset( $input['signature_date'] ) ? sanitize_text_field( wp_unslash( $input['signature_date'] ) ) : '';

	if ( '' === $student_name || strlen( $student_name ) < 2 ) {
		$errors['student_full_name'] = __( 'Please enter the student\'s full name.', 'holiday-calendar' );
	}
	if ( $student_dob && ! hc_is_valid_date( $student_dob ) ) {
		$errors['student_dob'] = __( 'Please enter a valid date of birth.', 'holiday-calendar' );
	}
	if ( $student_age < 5 ) {
		$errors['student_age'] = __( 'Student must be at least 5 years old.', 'holiday-calendar' );
	}
	if ( ! in_array( $gender, array( 'male', 'female' ), true ) ) {
		$errors['student_gender'] = __( 'Please select gender.', 'holiday-calendar' );
	}
	if ( ! in_array( $camp_session, array( 'weekly', 'monthly' ), true ) ) {
		$errors['camp_session'] = __( 'Please select a camp session.', 'holiday-calendar' );
	}
	if ( 'monthly' === $camp_session && ! in_array( $camp_month, array( 'june', 'july', 'both' ), true ) ) {
		$errors['camp_month'] = __( 'Please select camp month for monthly session.', 'holiday-calendar' );
	}
	if ( ! in_array( $special, array( 'yes', 'no' ), true ) ) {
		$errors['special_needs'] = __( 'Please answer the special needs question.', 'holiday-calendar' );
	}
	if ( 'yes' === $special && '' === $special_det ) {
		$errors['special_needs_details'] = __( 'Please describe what we need to know.', 'holiday-calendar' );
	}

	if ( '' === $mother_name ) {
		$errors['mother_name'] = __( 'Please enter mother\'s name.', 'holiday-calendar' );
	}
	if ( '' === $father_name ) {
		$errors['father_name'] = __( 'Please enter father\'s name.', 'holiday-calendar' );
	}
	if ( '' === $mother_phone ) {
		$errors['mother_phone'] = __( 'Please enter mother\'s phone number.', 'holiday-calendar' );
	}
	if ( '' === $father_phone ) {
		$errors['father_phone'] = __( 'Please enter father\'s phone number.', 'holiday-calendar' );
	}
	if ( '' === $address ) {
		$errors['address'] = __( 'Please enter your address.', 'holiday-calendar' );
	}
	if ( ! is_email( $email ) ) {
		$errors['email'] = __( 'Please enter a valid email address.', 'holiday-calendar' );
	}

	if ( ! in_array( $emergency_same, array( 'yes', 'no' ), true ) ) {
		$errors['emergency_same'] = __( 'Please indicate if emergency contact matches guardian.', 'holiday-calendar' );
	}
	if ( 'no' === $emergency_same && '' === $emergency_name ) {
		$errors['emergency_name'] = __( 'Please enter emergency contact name.', 'holiday-calendar' );
	}

	$amount_cents = 0;

	if ( hc_payments_available() ) {
		$amount_cents = hc_registration_calculate_amount( $camp_session, $camp_month );
		if ( is_wp_error( $amount_cents ) ) {
			$errors['camp_session'] = $amount_cents->get_error_message();
		} elseif ( ! in_array( $payment_provider, array( 'stripe', 'paypal' ), true ) || '' === $payment_ref ) {
			$errors['payment'] = __( 'Please complete payment before submitting your registration.', 'holiday-calendar' );
		} else {
			$payment_check = hc_verify_registration_payment( $payment_provider, $payment_ref, $amount_cents );
			if ( is_wp_error( $payment_check ) ) {
				$errors['payment'] = $payment_check->get_error_message();
			}
		}
	}

	if ( empty( $referrals ) ) {
		$errors['referral_sources'] = __( 'Please select at least one referral source.', 'holiday-calendar' );
	}
	if ( ! $terms ) {
		$errors['terms_consent'] = __( 'Please accept the terms and privacy policy.', 'holiday-calendar' );
	}
	if ( '' === $signature ) {
		$errors['signature_name'] = __( 'Please enter parent/guardian signature (full name).', 'holiday-calendar' );
	}
	if ( ! $sig_date || ! hc_is_valid_date( $sig_date ) ) {
		$errors['signature_date'] = __( 'Please enter a valid signature date.', 'holiday-calendar' );
	}

	if ( ! empty( $errors ) ) {
		return new WP_Error( 'hc_registration_invalid', __( 'Please fix the highlighted fields.', 'holiday-calendar' ), $errors );
	}

	return array(
		'student_full_name'      => $student_name,
		'student_dob'            => $student_dob,
		'student_age'            => $student_age,
		'student_gender'         => $gender,
		'camp_session'           => $camp_session,
		'camp_month'             => 'monthly' === $camp_session ? $camp_month : '',
		'special_needs'          => $special,
		'special_needs_details'  => 'yes' === $special ? $special_det : '',
		'mother_name'            => $mother_name,
		'father_name'            => $father_name,
		'mother_phone'           => $mother_phone,
		'father_phone'           => $father_phone,
		'address'                => $address,
		'email'                  => $email,
		'emergency_same'         => $emergency_same,
		'emergency_name'         => 'no' === $emergency_same ? $emergency_name : '',
		'emergency_mobile'       => 'no' === $emergency_same ? $emergency_mob : '',
		'additional_notes'       => $notes,
		'payment_provider'       => $payment_provider,
		'payment_ref'            => $payment_ref,
		'payment_amount_cents'   => (int) $amount_cents,
		'referral_sources'       => $referrals,
		'terms_consent'          => true,
		'signature_name'         => $signature,
		'signature_date'         => $sig_date,
		'reference'              => 'scr_' . wp_generate_password( 10, false, false ),
	);
}

/**
 * Persist a registration submission (last 200 kept).
 *
 * @param array $data Sanitized submission.
 * @return int Submission index.
 */
function hc_save_registration_submission( array $data ) {
	$submissions   = get_option( 'hc_registration_submissions', array() );
	$submissions   = is_array( $submissions ) ? $submissions : array();
	$submissions[] = array_merge(
		array(
			'submitted_at' => current_time( 'mysql' ),
			'ip'           => hc_get_client_ip(),
		),
		$data
	);
	if ( count( $submissions ) > 200 ) {
		$submissions = array_slice( $submissions, -200 );
	}
	update_option( 'hc_registration_submissions', $submissions, false );
	return count( $submissions ) - 1;
}

/**
 * Send admin notification email for a registration.
 *
 * @param array $data Sanitized submission.
 * @return bool
 */
function hc_send_registration_admin_email( array $data ) {
	$settings = hc_get_registration_settings();
	if ( empty( $settings['notify_admin'] ) ) {
		return false;
	}
	$to = sanitize_email( $settings['admin_email'] );
	if ( ! is_email( $to ) ) {
		return false;
	}

	$subject = sprintf(
		/* translators: %s: student name */
		__( 'New Summer Camp Registration: %s', 'holiday-calendar' ),
		$data['student_full_name']
	);

	$lines = array(
		__( 'A new summer camp registration was submitted.', 'holiday-calendar' ),
		'',
		__( 'Student', 'holiday-calendar' ) . ': ' . $data['student_full_name'],
		__( 'Age', 'holiday-calendar' ) . ': ' . $data['student_age'],
		__( 'Camp Session', 'holiday-calendar' ) . ': ' . ucfirst( $data['camp_session'] ),
	);
	if ( $data['camp_month'] ) {
		$lines[] = __( 'Camp Month', 'holiday-calendar' ) . ': ' . ucfirst( $data['camp_month'] );
	}
	$lines[] = __( 'Guardian Email', 'holiday-calendar' ) . ': ' . $data['email'];
	if ( ! empty( $data['payment_ref'] ) ) {
		$pay_settings = hc_get_payment_settings();
		$lines[] = __( 'Payment', 'holiday-calendar' ) . ': ' . ucfirst( $data['payment_provider'] ) . ' — ' . hc_format_money( $data['payment_amount_cents'], $pay_settings['currency'] );
		$lines[] = __( 'Payment reference', 'holiday-calendar' ) . ': ' . $data['payment_ref'];
	}
	$lines[] = __( 'Registration reference', 'holiday-calendar' ) . ': ' . $data['reference'];

	$body = implode( "\n", $lines );
	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

	return wp_mail( $to, $subject, $body, $headers );
}
