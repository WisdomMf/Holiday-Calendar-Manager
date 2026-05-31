<?php
/**
 * Admin settings page: add / delete marked dates and edit display settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HC_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function add_menu() {
		add_menu_page(
			__( 'Holiday Calendar', 'holiday-calendar' ),
			__( 'Holiday Calendar', 'holiday-calendar' ),
			'manage_options',
			'holiday-calendar',
			array( $this, 'render_page' ),
			'dashicons-calendar-alt',
			58
		);
	}

	public function enqueue( $hook ) {
		if ( 'toplevel_page_holiday-calendar' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'hc-admin', hc_asset_url( 'assets/admin.css' ), array(), hc_asset_version( 'assets/admin.css' ) );
		wp_enqueue_script( 'hc-admin', hc_asset_url( 'assets/admin.js' ), array(), hc_asset_version( 'assets/admin.js' ), true );
	}

	/**
	 * Process form submissions. Each form carries its own nonce + hc_action.
	 */
	private function handle_post() {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['hc_action'] ) ) {
			return;
		}
		$action = sanitize_text_field( wp_unslash( $_POST['hc_action'] ) );

		if ( 'add_date' === $action && check_admin_referer( 'hc_add_date' ) ) {
			$date      = isset( $_POST['hc_date'] ) ? sanitize_text_field( wp_unslash( $_POST['hc_date'] ) ) : '';
			$date_end  = isset( $_POST['hc_date_end'] ) ? sanitize_text_field( wp_unslash( $_POST['hc_date_end'] ) ) : '';
			$date_type = isset( $_POST['hc_date_type'] ) ? sanitize_key( wp_unslash( $_POST['hc_date_type'] ) ) : 'single';
			if ( 'single' === $date_type ) {
				$date_end = '';
			}
			$label = isset( $_POST['hc_label'] ) ? sanitize_text_field( wp_unslash( $_POST['hc_label'] ) ) : '';
			$color = $this->color_from_post();

			$result = hc_add_marked_date( $date, $label, $color, $date_end );
			if ( is_wp_error( $result ) ) {
				add_settings_error( 'hc', $result->get_error_code(), $result->get_error_message(), 'error' );
			} else {
				add_settings_error( 'hc', 'hc_added', __( 'Date added.', 'holiday-calendar' ), 'updated' );
			}
		}

		if ( 'edit_date' === $action && check_admin_referer( 'hc_edit_date' ) ) {
			$index     = isset( $_POST['hc_index'] ) ? absint( $_POST['hc_index'] ) : -1;
			$date      = isset( $_POST['hc_date'] ) ? sanitize_text_field( wp_unslash( $_POST['hc_date'] ) ) : '';
			$date_end  = isset( $_POST['hc_date_end'] ) ? sanitize_text_field( wp_unslash( $_POST['hc_date_end'] ) ) : '';
			$date_type = isset( $_POST['hc_date_type'] ) ? sanitize_key( wp_unslash( $_POST['hc_date_type'] ) ) : 'single';
			if ( 'single' === $date_type ) {
				$date_end = '';
			}
			$label = isset( $_POST['hc_label'] ) ? sanitize_text_field( wp_unslash( $_POST['hc_label'] ) ) : '';
			$color = $this->color_from_post();

			$result = hc_update_marked_date( $index, $date, $label, $color, $date_end );
			if ( is_wp_error( $result ) ) {
				add_settings_error( 'hc', $result->get_error_code(), $result->get_error_message(), 'error' );
			} else {
				add_settings_error( 'hc', 'hc_updated', __( 'Date updated.', 'holiday-calendar' ), 'updated' );
			}
		}

		if ( 'delete_date' === $action && check_admin_referer( 'hc_delete_date' ) ) {
			$index = isset( $_POST['hc_index'] ) ? absint( $_POST['hc_index'] ) : -1;
			if ( hc_delete_marked_date( $index ) ) {
				add_settings_error( 'hc', 'hc_deleted', __( 'Date removed.', 'holiday-calendar' ), 'updated' );
			}
		}

		if ( 'save_settings' === $action && check_admin_referer( 'hc_save_settings' ) ) {
			$weekend_days = isset( $_POST['hc_weekend_days'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['hc_weekend_days'] ) ) : array();
			$brand_color  = isset( $_POST['hc_brand_color'] ) ? wp_unslash( $_POST['hc_brand_color'] ) : '';
			$brand_color  = hc_normalize_hex( $brand_color ) ? hc_normalize_hex( $brand_color ) : hc_get_default_brand_color();
			$calendar_theme = isset( $_POST['hc_calendar_theme'] ) ? sanitize_key( wp_unslash( $_POST['hc_calendar_theme'] ) ) : 'simple';
			if ( ! in_array( $calendar_theme, array( 'simple', 'enhanced' ), true ) ) {
				$calendar_theme = 'simple';
			}
			$settings     = array(
				'highlight_weekends' => isset( $_POST['hc_highlight_weekends'] ) ? 1 : 0,
				'weekend_days'       => array_values( array_intersect( range( 0, 6 ), $weekend_days ) ),
				'week_starts_on'     => isset( $_POST['hc_week_starts_on'] ) ? absint( $_POST['hc_week_starts_on'] ) : 0,
				'brand_color'        => $brand_color,
				'calendar_theme'     => $calendar_theme,
			);
			update_option( 'hc_settings', $settings );
			add_settings_error( 'hc', 'hc_saved', __( 'Settings saved.', 'holiday-calendar' ), 'updated' );
		}
	}

	/**
	 * Read and validate colour from the add/edit form.
	 *
	 * @return string Hex colour.
	 */
	private function color_from_post() {
		$preset = isset( $_POST['hc_color_choice'] ) ? sanitize_key( wp_unslash( $_POST['hc_color_choice'] ) ) : '';
		$color  = isset( $_POST['hc_color'] ) ? wp_unslash( $_POST['hc_color'] ) : '';
		return hc_sanitize_mark_color( $color, $preset );
	}

	public function render_page() {
		$this->handle_post();
		$dates     = hc_get_dates();
		$settings  = hc_get_settings();
		$presets   = hc_get_color_presets();
		$default_color = hc_get_default_color();
		$day_names = array(
			0 => __( 'Sunday', 'holiday-calendar' ),
			1 => __( 'Monday', 'holiday-calendar' ),
			2 => __( 'Tuesday', 'holiday-calendar' ),
			3 => __( 'Wednesday', 'holiday-calendar' ),
			4 => __( 'Thursday', 'holiday-calendar' ),
			5 => __( 'Friday', 'holiday-calendar' ),
			6 => __( 'Saturday', 'holiday-calendar' ),
		);
		?>
		<div class="wrap hc-admin">
			<h1><?php esc_html_e( 'Holiday Calendar', 'holiday-calendar' ); ?></h1>
			<?php settings_errors( 'hc' ); ?>

			<p class="hc-shortcode-hint">
				<?php esc_html_e( 'Show the calendar on any page or post with this shortcode:', 'holiday-calendar' ); ?>
				<code>[holiday_calendar]</code>
			</p>

			<div class="hc-grid">
				<div class="hc-card">
					<h2 id="hc-date-form-title" data-edit-title="<?php esc_attr_e( 'Edit marked date', 'holiday-calendar' ); ?>"><?php esc_html_e( 'Add a marked date', 'holiday-calendar' ); ?></h2>
					<form method="post" id="hc-date-form" data-add-nonce="<?php echo esc_attr( wp_create_nonce( 'hc_add_date' ) ); ?>" data-edit-nonce="<?php echo esc_attr( wp_create_nonce( 'hc_edit_date' ) ); ?>">
						<input type="hidden" name="_wpnonce" id="hc_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'hc_add_date' ) ); ?>" />
						<input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr( esc_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) ); ?>" />
						<input type="hidden" name="hc_action" id="hc_action" value="add_date" />
						<input type="hidden" name="hc_index" id="hc_index" value="" />
						<fieldset class="hc-date-type-field">
							<legend><?php esc_html_e( 'Date type', 'holiday-calendar' ); ?></legend>
							<label class="hc-inline">
								<input type="radio" name="hc_date_type" id="hc_date_type_single" value="single" checked />
								<?php esc_html_e( 'Single date', 'holiday-calendar' ); ?>
							</label>
							<label class="hc-inline">
								<input type="radio" name="hc_date_type" id="hc_date_type_range" value="range" />
								<?php esc_html_e( 'Date range', 'holiday-calendar' ); ?>
							</label>
						</fieldset>
						<p id="hc-date-single-wrap">
							<label for="hc_date"><?php esc_html_e( 'Date', 'holiday-calendar' ); ?></label><br />
							<input type="date" id="hc_date" name="hc_date" required />
						</p>
						<div id="hc-date-range-wrap" hidden>
							<p>
								<label for="hc_date_start"><?php esc_html_e( 'Start date', 'holiday-calendar' ); ?></label><br />
								<input type="date" id="hc_date_start" />
							</p>
							<p>
								<label for="hc_date_end"><?php esc_html_e( 'End date', 'holiday-calendar' ); ?></label><br />
								<input type="date" id="hc_date_end" name="hc_date_end" />
							</p>
						</div>
						<p>
							<label for="hc_label"><?php esc_html_e( 'Label', 'holiday-calendar' ); ?></label><br />
							<input type="text" id="hc_label" name="hc_label" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Office closed', 'holiday-calendar' ); ?>" />
						</p>
						<fieldset class="hc-color-field">
							<legend><label><?php esc_html_e( 'Colour', 'holiday-calendar' ); ?></label></legend>
							<?php
							$first = true;
							foreach ( $presets as $preset ) :
								?>
								<label class="hc-color-option">
									<input
										type="radio"
										name="hc_color_choice"
										value="<?php echo esc_attr( $preset['slug'] ); ?>"
										data-hex="<?php echo esc_attr( $preset['hex'] ); ?>"
										<?php checked( $first ); ?>
									/>
									<span class="hc-swatch" style="background:<?php echo esc_attr( $preset['hex'] ); ?>"></span>
									<?php echo esc_html( $preset['label'] ); ?>
								</label>
								<?php
								$first = false;
							endforeach;
							?>
							<label class="hc-color-option">
								<input type="radio" name="hc_color_choice" value="custom" />
								<?php esc_html_e( 'Custom', 'holiday-calendar' ); ?>
							</label>
							<p class="hc-color-custom" id="hc-color-custom-wrap" hidden>
								<label for="hc_color_picker" class="screen-reader-text"><?php esc_html_e( 'Custom colour', 'holiday-calendar' ); ?></label>
								<input type="color" id="hc_color_picker" value="<?php echo esc_attr( $default_color ); ?>" />
							</p>
							<input type="hidden" id="hc_color" name="hc_color" value="<?php echo esc_attr( $default_color ); ?>" />
						</fieldset>
						<p class="hc-form-actions">
							<button type="submit" class="button button-primary" id="hc-submit" data-edit-label="<?php esc_attr_e( 'Save changes', 'holiday-calendar' ); ?>"><?php esc_html_e( 'Add date', 'holiday-calendar' ); ?></button>
							<button type="button" class="button" id="hc-cancel-edit" hidden><?php esc_html_e( 'Cancel', 'holiday-calendar' ); ?></button>
						</p>
					</form>
				</div>

				<div class="hc-card">
					<h2><?php esc_html_e( 'Display settings', 'holiday-calendar' ); ?></h2>
					<form method="post">
						<?php wp_nonce_field( 'hc_save_settings' ); ?>
						<input type="hidden" name="hc_action" value="save_settings" />
						<p>
							<label>
								<input type="checkbox" name="hc_highlight_weekends" value="1" <?php checked( $settings['highlight_weekends'], 1 ); ?> />
								<?php esc_html_e( 'Highlight weekends automatically', 'holiday-calendar' ); ?>
							</label>
						</p>
						<fieldset>
							<legend><strong><?php esc_html_e( 'Which days are the weekend?', 'holiday-calendar' ); ?></strong></legend>
							<?php foreach ( $day_names as $num => $name ) : ?>
								<label class="hc-inline">
									<input type="checkbox" name="hc_weekend_days[]" value="<?php echo esc_attr( $num ); ?>" <?php checked( in_array( $num, array_map( 'intval', $settings['weekend_days'] ), true ) ); ?> />
									<?php echo esc_html( $name ); ?>
								</label>
							<?php endforeach; ?>
						</fieldset>
						<p>
							<label for="hc_week_starts_on"><?php esc_html_e( 'Week starts on', 'holiday-calendar' ); ?></label><br />
							<select id="hc_week_starts_on" name="hc_week_starts_on">
								<option value="0" <?php selected( $settings['week_starts_on'], 0 ); ?>><?php esc_html_e( 'Sunday', 'holiday-calendar' ); ?></option>
								<option value="1" <?php selected( $settings['week_starts_on'], 1 ); ?>><?php esc_html_e( 'Monday', 'holiday-calendar' ); ?></option>
							</select>
						</p>
						<fieldset>
							<legend><strong><?php esc_html_e( 'Calendar theme', 'holiday-calendar' ); ?></strong></legend>
							<label class="hc-inline">
								<input type="radio" name="hc_calendar_theme" value="simple" <?php checked( $settings['calendar_theme'], 'simple' ); ?> />
								<?php esc_html_e( 'Simple — clean light layout (default)', 'holiday-calendar' ); ?>
							</label>
							<label class="hc-inline">
								<input type="radio" name="hc_calendar_theme" value="enhanced" <?php checked( $settings['calendar_theme'], 'enhanced' ); ?> />
								<?php esc_html_e( 'Enhanced — richer colours, depth, and polish', 'holiday-calendar' ); ?>
							</label>
						</fieldset>
						<p>
							<label for="hc_brand_color"><?php esc_html_e( 'Export header colour', 'holiday-calendar' ); ?></label><br />
							<input
								type="color"
								id="hc_brand_color"
								name="hc_brand_color"
								value="<?php echo esc_attr( hc_normalize_hex( $settings['brand_color'] ) ? hc_normalize_hex( $settings['brand_color'] ) : hc_get_default_brand_color() ); ?>"
							/>
							<span class="description"><?php esc_html_e( 'Background colour for the branded header band on downloaded calendar images.', 'holiday-calendar' ); ?></span>
						</p>
						<p>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'holiday-calendar' ); ?></button>
						</p>
					</form>
				</div>
			</div>

			<div class="hc-card hc-card-wide">
				<h2><?php esc_html_e( 'Marked dates', 'holiday-calendar' ); ?></h2>
				<?php if ( empty( $dates ) ) : ?>
					<p><?php esc_html_e( 'No dates yet. Add one above.', 'holiday-calendar' ); ?></p>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Date', 'holiday-calendar' ); ?></th>
								<th><?php esc_html_e( 'Label', 'holiday-calendar' ); ?></th>
								<th><?php esc_html_e( 'Colour', 'holiday-calendar' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'holiday-calendar' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $dates as $i => $d ) : ?>
								<tr>
									<td><?php echo esc_html( hc_format_date_display( $d ) ); ?></td>
									<td><?php echo esc_html( $d['label'] ); ?></td>
									<td>
										<?php
										$preset_slug = hc_find_preset_slug_for_hex( $d['color'] );
										if ( $preset_slug ) {
											foreach ( $presets as $preset ) {
												if ( $preset['slug'] === $preset_slug ) {
													echo '<span class="hc-swatch" style="background:' . esc_attr( $preset['hex'] ) . '"></span> ';
													echo esc_html( $preset['label'] );
													break;
												}
											}
										} else {
											echo '<span class="hc-swatch" style="background:' . esc_attr( $d['color'] ) . '"></span> ';
											echo esc_html( $d['color'] );
										}
										?>
									</td>
									<td class="hc-row-actions">
										<button
											type="button"
											class="button-link hc-edit-date"
											data-index="<?php echo esc_attr( $i ); ?>"
											data-date="<?php echo esc_attr( $d['date'] ); ?>"
											data-date-end="<?php echo esc_attr( hc_is_range_entry( $d ) ? $d['date_end'] : '' ); ?>"
											data-date-type="<?php echo esc_attr( hc_is_range_entry( $d ) ? 'range' : 'single' ); ?>"
											data-label="<?php echo esc_attr( $d['label'] ); ?>"
											data-color="<?php echo esc_attr( $d['color'] ); ?>"
										><?php esc_html_e( 'Edit', 'holiday-calendar' ); ?></button>
										<span class="hc-action-sep">|</span>
										<form method="post" class="hc-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Remove this date?', 'holiday-calendar' ) ); ?>');">
											<?php wp_nonce_field( 'hc_delete_date' ); ?>
											<input type="hidden" name="hc_action" value="delete_date" />
											<input type="hidden" name="hc_index" value="<?php echo esc_attr( $i ); ?>" />
											<button type="submit" class="button-link button-link-delete"><?php esc_html_e( 'Delete', 'holiday-calendar' ); ?></button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
