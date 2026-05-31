<?php
/**
 * [holiday_calendar] shortcode + front-end rendering.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HC_Shortcode {

	/**
	 * Ensure data is localized to the script only once per page.
	 *
	 * @var bool
	 */
	private static $localized = false;

	/**
	 * Track whether front-end assets were registered for this request.
	 *
	 * @var bool
	 */
	private static $assets_enqueued = false;

	public function __construct() {
		add_shortcode( 'holiday_calendar', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets_early' ), 20 );
	}

	/**
	 * Enqueue in wp_enqueue_scripts when the shortcode is on the current page.
	 *
	 * Styles must load in the document head; enqueuing only inside the shortcode
	 * (after wp_head) pushes CSS to the footer, which many cache/minify plugins skip.
	 */
	public function maybe_enqueue_assets_early() {
		if ( hc_page_needs_calendar_assets() ) {
			$this->enqueue_assets();
		}
	}

	/**
	 * Register calendar stylesheet and script.
	 */
	private function enqueue_assets() {
		if ( self::$assets_enqueued ) {
			return;
		}

		wp_enqueue_style(
			'hc-calendar',
			hc_asset_url( 'assets/calendar.css' ),
			array(),
			hc_asset_version( 'assets/calendar.css' )
		);
		wp_enqueue_script(
			'hc-calendar',
			hc_asset_url( 'assets/calendar.js' ),
			array(),
			hc_asset_version( 'assets/calendar.js' ),
			true
		);

		self::$assets_enqueued = true;
	}

	/**
	 * When CSS was queued after wp_head, inject a link beside the calendar.
	 *
	 * Some hosts still omit footer-printed styles even when JS loads from the footer.
	 *
	 * @return string Empty string or a link tag.
	 */
	private function stylesheet_fallback_link() {
		if ( ! did_action( 'wp_head' ) ) {
			return '';
		}
		if ( wp_style_is( 'hc-calendar', 'done' ) ) {
			return '';
		}

		wp_dequeue_style( 'hc-calendar' );

		$url = add_query_arg(
			'ver',
			hc_asset_version( 'assets/calendar.css' ),
			hc_asset_url( 'assets/calendar.css' )
		);

		return '<link rel="stylesheet" id="hc-calendar-css" href="' . esc_url( $url ) . '" media="all" />';
	}

	/**
	 * Pass marked dates and settings to calendar.js (once per page).
	 */
	private function localize_script() {
		if ( self::$localized ) {
			return;
		}

		$this->enqueue_assets();

		$settings = hc_get_settings();

		// Build a date => {label,color} lookup for the grid; entries for list views.
		$map     = hc_build_dates_calendar_map();
		$entries = hc_build_holiday_entries();

		$theme = isset( $settings['calendar_theme'] ) && 'enhanced' === $settings['calendar_theme'] ? 'enhanced' : 'simple';

		$data = array(
			'dates'             => $map,
			'entries'           => $entries,
			'highlightWeekends' => 1 === (int) $settings['highlight_weekends'],
			'weekendDays'       => array_map( 'intval', $settings['weekend_days'] ),
			'weekStartsOn'      => (int) $settings['week_starts_on'],
			'calendarTheme'     => $theme,
			'monthNames'        => $this->month_names(),
			'dayNamesShort'     => $this->day_names_short(),
			'today'             => wp_date( 'Y-m-d' ),
			'brand'             => $this->branding_data(),
			'i18n'              => array(
				'prev'              => __( 'Previous month', 'holiday-calendar' ),
				'next'              => __( 'Next month', 'holiday-calendar' ),
				'holidaysThisMonth' => __( 'Holidays this month', 'holiday-calendar' ),
				'noHolidays'        => __( 'No holidays this month', 'holiday-calendar' ),
				'exportMonth'       => __( 'Download current month as image', 'holiday-calendar' ),
				'exportAll'         => __( 'Download all holidays as image', 'holiday-calendar' ),
				'savingImage'       => __( 'Generating image…', 'holiday-calendar' ),
				'allHolidays'       => __( 'All holidays', 'holiday-calendar' ),
				'noHolidaysAll'     => __( 'No holidays marked', 'holiday-calendar' ),
				'viewCalendar'      => __( 'Calendar', 'holiday-calendar' ),
				'viewAll'           => __( 'All holidays', 'holiday-calendar' ),
			),
		);

		wp_localize_script( 'hc-calendar', 'HC_DATA', $data );
		self::$localized = true;
	}

	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'months' => 1, // Reserved for a future multi-month view.
			),
			$atts,
			'holiday_calendar'
		);

		$this->enqueue_assets();
		$this->localize_script();

		$settings = hc_get_settings();
		$theme    = isset( $settings['calendar_theme'] ) && 'enhanced' === $settings['calendar_theme'] ? 'enhanced' : 'simple';
		$id       = 'hc-cal-' . wp_unique_id();

		return $this->stylesheet_fallback_link()
			. '<div class="hc-calendar hc-theme-' . esc_attr( $theme ) . '" data-hc-calendar id="' . esc_attr( $id ) . '"></div>';
	}

	private function month_names() {
		global $wp_locale;
		$names = array();
		for ( $m = 1; $m <= 12; $m++ ) {
			$names[] = $wp_locale->get_month( $m );
		}
		return $names;
	}

	private function day_names_short() {
		global $wp_locale;
		$names = array();
		for ( $d = 0; $d <= 6; $d++ ) {
			$names[] = $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( $d ) );
		}
		return $names;
	}

	/**
	 * Site identity for export branding (custom logo, site icon fallback).
	 *
	 * @return array{logoUrl:string,siteName:string,tagline:string,siteUrl:string,headerColor:string}
	 */
	private function branding_data() {
		$settings = hc_get_settings();
		$logo_url = '';

		$custom_logo_id = get_theme_mod( 'custom_logo' );
		if ( $custom_logo_id ) {
			$logo_url = wp_get_attachment_image_url( (int) $custom_logo_id, 'medium' );
		}

		if ( ! $logo_url ) {
			$site_icon_id = get_option( 'site_icon' );
			if ( $site_icon_id ) {
				$logo_url = wp_get_attachment_image_url( (int) $site_icon_id, 'full' );
			}
		}

		$brand_color = hc_normalize_hex( $settings['brand_color'] );
		if ( ! $brand_color ) {
			$brand_color = hc_get_default_brand_color();
		}

		return array(
			'logoUrl'     => $logo_url ? esc_url_raw( $logo_url ) : '',
			'siteName'    => get_bloginfo( 'name' ),
			'tagline'     => get_bloginfo( 'description' ),
			'siteUrl'     => home_url( '/' ),
			'headerColor' => $brand_color,
		);
	}
}
