<?php
/**
 * Clean up plugin options on uninstall.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'hc_dates' );
delete_option( 'hc_settings' );
