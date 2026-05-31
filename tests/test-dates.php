<?php
/**
 * Integration tests for marked date helpers.
 */

class Test_HC_Dates extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		update_option( 'hc_dates', array() );
	}

	public function test_hc_is_valid_date_accepts_valid_dates() {
		$this->assertTrue( hc_is_valid_date( '2026-01-15' ) );
		$this->assertTrue( hc_is_valid_date( '2020-02-29' ) );
	}

	public function test_hc_is_valid_date_rejects_invalid_dates() {
		$this->assertFalse( hc_is_valid_date( '' ) );
		$this->assertFalse( hc_is_valid_date( 'not-a-date' ) );
		$this->assertFalse( hc_is_valid_date( '2026-13-01' ) );
		$this->assertFalse( hc_is_valid_date( '2026-02-30' ) );
		$this->assertFalse( hc_is_valid_date( '01-15-2026' ) );
	}

	public function test_hc_add_marked_date_stores_and_sorts() {
		$result = hc_add_marked_date( '2026-03-01', 'March', '#ff0000' );
		$this->assertTrue( $result );
		hc_add_marked_date( '2026-01-01', 'January', '#00ff00' );

		$dates = hc_get_dates();
		$this->assertCount( 2, $dates );
		$this->assertSame( '2026-01-01', $dates[0]['date'] );
		$this->assertSame( '2026-03-01', $dates[1]['date'] );
	}

	public function test_hc_add_marked_date_rejects_duplicate() {
		hc_add_marked_date( '2026-05-01', 'First', '#e5484d' );

		$result = hc_add_marked_date( '2026-05-01', 'Duplicate', '#000000' );
		$this->assertWPError( $result );
		$this->assertSame( 'hc_duplicate', $result->get_error_code() );
		$this->assertCount( 1, hc_get_dates() );
	}

	public function test_hc_add_marked_date_rejects_invalid_date() {
		$result = hc_add_marked_date( 'invalid', 'Bad', '#e5484d' );
		$this->assertWPError( $result );
		$this->assertSame( 'hc_invalid', $result->get_error_code() );
	}

	public function test_hc_update_marked_date_changes_entry_and_resorts() {
		hc_add_marked_date( '2026-06-01', 'June', '#111111' );
		hc_add_marked_date( '2026-08-01', 'August', '#222222' );

		$result = hc_update_marked_date( 1, '2026-05-01', 'May moved', '#333333' );
		$this->assertTrue( $result );

		$dates = hc_get_dates();
		$this->assertCount( 2, $dates );
		$this->assertSame( '2026-05-01', $dates[0]['date'] );
		$this->assertSame( 'May moved', $dates[0]['label'] );
		$this->assertSame( '#333333', $dates[0]['color'] );
		$this->assertSame( '2026-06-01', $dates[1]['date'] );
	}

	public function test_hc_update_marked_date_allows_same_date_for_same_entry() {
		hc_add_marked_date( '2026-07-04', 'Independence Day', '#e5484d' );

		$result = hc_update_marked_date( 0, '2026-07-04', 'Updated label', '#abcdef' );
		$this->assertTrue( $result );

		$dates = hc_get_dates();
		$this->assertSame( 'Updated label', $dates[0]['label'] );
		$this->assertSame( '#abcdef', $dates[0]['color'] );
	}

	public function test_hc_update_marked_date_rejects_duplicate_of_another_entry() {
		hc_add_marked_date( '2026-09-01', 'September', '#e5484d' );
		hc_add_marked_date( '2026-10-01', 'October', '#e5484d' );

		$result = hc_update_marked_date( 1, '2026-09-01', 'Conflict', '#000000' );
		$this->assertWPError( $result );
		$this->assertSame( 'hc_duplicate', $result->get_error_code() );
	}

	public function test_hc_date_exists_respects_exclude_index() {
		update_option(
			'hc_dates',
			array(
				array(
					'date'  => '2026-04-01',
					'label' => 'April',
					'color' => '#e5484d',
				),
			)
		);

		$this->assertTrue( hc_date_exists( '2026-04-01' ) );
		$this->assertFalse( hc_date_exists( '2026-04-01', 0 ) );
	}

	public function test_hc_delete_marked_date_removes_entry() {
		hc_add_marked_date( '2026-11-01', 'November', '#e5484d' );
		hc_add_marked_date( '2026-12-01', 'December', '#e5484d' );

		$this->assertTrue( hc_delete_marked_date( 0 ) );
		$dates = hc_get_dates();
		$this->assertCount( 1, $dates );
		$this->assertSame( '2026-12-01', $dates[0]['date'] );
	}

	public function test_hc_delete_marked_date_returns_false_for_missing_index() {
		$this->assertFalse( hc_delete_marked_date( 99 ) );
	}

	public function test_hc_add_marked_date_range_stores_date_end() {
		$result = hc_add_marked_date( '2026-01-05', 'Winter break', '#e5484d', '2026-01-10' );
		$this->assertTrue( $result );

		$dates = hc_get_dates();
		$this->assertCount( 1, $dates );
		$this->assertSame( '2026-01-05', $dates[0]['date'] );
		$this->assertSame( '2026-01-10', $dates[0]['date_end'] );
		$this->assertTrue( hc_is_range_entry( $dates[0] ) );
	}

	public function test_hc_add_marked_date_rejects_end_before_start() {
		$result = hc_add_marked_date( '2026-02-10', 'Bad range', '#e5484d', '2026-02-01' );
		$this->assertWPError( $result );
		$this->assertSame( 'hc_invalid_range', $result->get_error_code() );
	}

	public function test_hc_add_marked_date_rejects_overlapping_range() {
		hc_add_marked_date( '2026-03-01', 'March break', '#e5484d', '2026-03-07' );

		$result = hc_add_marked_date( '2026-03-05', 'Overlap', '#000000', '2026-03-10' );
		$this->assertWPError( $result );
		$this->assertSame( 'hc_duplicate', $result->get_error_code() );
	}

	public function test_hc_add_marked_date_rejects_single_inside_range() {
		hc_add_marked_date( '2026-04-01', 'April break', '#e5484d', '2026-04-05' );

		$result = hc_add_marked_date( '2026-04-03', 'Conflict', '#000000' );
		$this->assertWPError( $result );
		$this->assertSame( 'hc_duplicate', $result->get_error_code() );
	}

	public function test_hc_expand_date_entry_includes_all_days() {
		$entry = array(
			'date'     => '2026-05-01',
			'date_end' => '2026-05-03',
			'label'    => 'Test',
			'color'    => '#e5484d',
		);
		$this->assertSame(
			array( '2026-05-01', '2026-05-02', '2026-05-03' ),
			hc_expand_date_entry( $entry )
		);
	}

	public function test_hc_build_dates_calendar_map_expands_ranges() {
		hc_add_marked_date( '2026-06-10', 'Range', '#ff0000', '2026-06-12' );
		hc_add_marked_date( '2026-06-20', 'Single', '#00ff00' );

		$map = hc_build_dates_calendar_map();
		$this->assertArrayHasKey( '2026-06-10', $map );
		$this->assertArrayHasKey( '2026-06-11', $map );
		$this->assertArrayHasKey( '2026-06-12', $map );
		$this->assertSame( 'Range', $map['2026-06-11']['label'] );
		$this->assertArrayHasKey( '2026-06-20', $map );
		$this->assertArrayNotHasKey( '2026-06-21', $map );
	}

	public function test_hc_build_holiday_entries_one_row_per_range() {
		hc_add_marked_date( '2026-07-01', 'July break', '#e5484d', '2026-07-05' );

		$entries = hc_build_holiday_entries();
		$this->assertCount( 1, $entries );
		$this->assertSame( 'range', $entries[0]['type'] );
		$this->assertSame( '2026-07-05', $entries[0]['date_end'] );
	}

	public function test_hc_get_dates_normalizes_legacy_single_entries() {
		update_option(
			'hc_dates',
			array(
				array(
					'date'  => '2026-08-15',
					'label' => 'Legacy',
					'color' => '#e5484d',
				),
			)
		);

		$dates = hc_get_dates();
		$this->assertCount( 1, $dates );
		$this->assertFalse( hc_is_range_entry( $dates[0] ) );
		$this->assertArrayNotHasKey( 'date_end', $dates[0] );
	}

	public function test_hc_update_marked_date_can_convert_single_to_range() {
		hc_add_marked_date( '2026-09-01', 'September', '#e5484d' );

		$result = hc_update_marked_date( 0, '2026-09-01', 'Extended', '#abcdef', '2026-09-05' );
		$this->assertTrue( $result );

		$dates = hc_get_dates();
		$this->assertSame( '2026-09-05', $dates[0]['date_end'] );
	}
}
