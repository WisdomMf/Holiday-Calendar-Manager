<?php
/**
 * Plugin Name:       Holiday Calendar
 * Plugin URI:        https://github.com/WisdomMf
 * Description:        An admin-editable interactive calendar that highlights weekends and admin-defined marked dates. Display it anywhere with the [holiday_calendar] shortcode.
 * Version:           1.0.20
 * Author:            Abishek Patel
 * Author URI:        https://github.com/WisdomMf
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       holiday-calendar
 *
 * Copyright (C) 2026 Abishek Patel
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'HC_VERSION', '1.0.20' );
define( 'HC_PATH', plugin_dir_path( __FILE__ ) );
define( 'HC_URL', plugin_dir_url( __FILE__ ) );

require_once HC_PATH . 'includes/helpers.php';
require_once HC_PATH . 'includes/class-hc-admin.php';
require_once HC_PATH . 'includes/class-hc-shortcode.php';
require_once HC_PATH . 'includes/class-hc-payment-stripe.php';
require_once HC_PATH . 'includes/class-hc-payment-paypal.php';
require_once HC_PATH . 'includes/class-hc-payments.php';
require_once HC_PATH . 'includes/class-hc-registration-form.php';

/**
 * Set sensible defaults on activation.
 */
register_activation_hook( __FILE__, 'hc_activate' );
function hc_activate() {
	if ( false === get_option( 'hc_settings' ) ) {
		add_option(
			'hc_settings',
			array(
				'highlight_weekends' => 1,
				'weekend_days'       => array( 0, 6 ), // 0 = Sunday, 6 = Saturday.
				'week_starts_on'     => 0,             // 0 = Sunday, 1 = Monday.
				'brand_color'        => hc_get_default_brand_color(),
			)
		);
	}
	if ( false === get_option( 'hc_dates' ) ) {
		add_option( 'hc_dates', array() );
	}
	if ( false === get_option( 'hc_payment_settings' ) ) {
		add_option(
			'hc_payment_settings',
			array(
				'sandbox_mode'           => 1,
				'currency'               => 'USD',
				'stripe_publishable_key' => '',
				'stripe_secret_key'      => '',
				'stripe_webhook_secret'  => '',
				'paypal_client_id'       => '',
				'paypal_secret'          => '',
				'paypal_webhook_id'      => '',
			)
		);
	}
	if ( false === get_option( 'hc_registration_settings' ) ) {
		add_option( 'hc_registration_settings', hc_get_registration_settings() );
	}
}

/**
 * Boot the plugin.
 */
add_action(
	'plugins_loaded',
	function () {
		new HC_Admin();
		new HC_Shortcode();
		new HC_Payments();
		new HC_Registration_Form();
	}
);
