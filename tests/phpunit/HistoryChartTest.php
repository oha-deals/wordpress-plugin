<?php
/**
 * Tests for Render\HistoryChart.
 *
 * @package OhaDeals
 */

namespace OhaDeals\Tests;

use OhaDeals\Render\Formatter;
use OhaDeals\Render\HistoryChart;

/**
 * Tests for HistoryChart.
 *
 * @covers \OhaDeals\Render\HistoryChart
 */
class HistoryChartTest extends TestCase {

	/**
	 * Chart under test, with "today" fixed to 2026-11-20.
	 *
	 * @var HistoryChart
	 */
	private $chart;

	/**
	 * Chart with an English formatter and a fixed date.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'date_format', 'Y-m-d' );
		$this->chart = new HistoryChart( new Formatter( 'en_US' ), '2026-11-20' );
	}

	/**
	 * A history point.
	 *
	 * @param string     $date  Date.
	 * @param float      $price Price.
	 * @param float|null $avg   Average.
	 * @return array
	 */
	private function point( $date, $price, $avg = null ) {
		return array(
			'date'  => $date,
			'price' => $price,
			'avg'   => $avg,
		);
	}

	/**
	 * The coordinate pairs of the price line.
	 *
	 * @param string $html Chart markup.
	 * @return array<int, array{0: float, 1: float}>
	 */
	private function line( $html ) {
		$this->assertSame( 1, preg_match( '/class="oha-deals-chart__line"[^>]* points="([^"]+)"/', $html, $match ) );

		return array_map(
			static function ( $pair ) {
				return array_map( 'floatval', explode( ',', $pair ) );
			},
			explode( ' ', $match[1] )
		);
	}

	public function test_no_points_render_nothing() {
		$this->assertSame( '', $this->chart->render( array(), 'Product' ) );
	}

	public function test_one_point_is_extended_to_today() {
		$html = $this->chart->render( array( $this->point( '2026-11-10', 99.0 ) ), 'Product', 220, 'EUR', 30 );

		$this->assertStringContainsString( 'oha-deals-chart__marker--now', $html );
		$this->assertStringNotContainsString( 'oha-deals-chart__marker--low', $html, 'A price that never changed has no lowest point.' );
		$this->assertStringNotContainsString( 'Highest price', $html );
		$this->assertStringContainsString( 'The price has been', $html );
		$line = $this->line( $html );
		$this->assertCount( 2, $line );
		$this->assertSame( $line[0][1], $line[1][1], 'A flat segment from the change to today.' );
		$this->assertEqualsWithDelta( 1000.0, $line[1][0], 0.1, 'The line ends at today (right edge).' );
		$this->assertDoesNotMatchRegularExpression( '/\b(NaN|-?INF)\b/', $html );
	}

	public function test_point_before_the_window_is_clamped_to_the_window_start() {
		$html = $this->chart->render(
			array(
				$this->point( '2026-01-01', 120.0 ),
				$this->point( '2026-06-01', 110.0 ),
				$this->point( '2026-11-10', 100.0 ),
			),
			'Product',
			220,
			'EUR',
			30
		);

		$line = $this->line( $html );
		$this->assertEqualsWithDelta( 0.0, $line[0][0], 0.1, 'The first point sits on the window start (left edge).' );
		$this->assertStringNotContainsString( '2026-01-01', $html, 'Older points are dropped.' );
		$this->assertStringContainsString( '<th scope="row">2026-10-21</th>', $html, 'The clamped point carries the window start date.' );
		$this->assertSame( 2, substr_count( $html, '<tr><th scope="row">' ) );
	}

	public function test_area_closes_the_line_to_the_bottom() {
		$html = $this->chart->render( array( $this->point( '2026-11-10', 99.0 ) ), 'Product', 200, 'EUR', 30 );

		$this->assertSame( 1, preg_match( '/class="oha-deals-chart__area"[^>]* points="([^"]+)"/', $html, $match ) );
		$points = explode( ' ', $match[1] );
		$line   = $this->line( $html );
		$this->assertSame( number_format( $line[0][0], 1, '.', '' ) . ',200', $points[0] );
		$this->assertSame( '1000.0,200', $points[ count( $points ) - 1 ] );
	}

	public function test_markers_show_the_lowest_and_highest_price() {
		$html = $this->chart->render(
			array(
				$this->point( '2026-10-21', 120.0 ),
				$this->point( '2026-10-31', 90.0 ),
				$this->point( '2026-11-05', 110.0 ),
			),
			'Product',
			220,
			'EUR',
			30
		);

		$this->assertMatchesRegularExpression( '/marker--high" style="--x:16\.6\d%;--y:[\d.]+%"[^>]*><span class="oha-deals-chart__marker-text">€120\.00</', $html, 'In the middle of the ten days at the highest price.' );
		$this->assertMatchesRegularExpression( '/marker--low" style="--x:41\.6\d%;--y:[\d.]+%"[^>]*><span class="oha-deals-chart__marker-text">€90\.00</', $html );
		$this->assertMatchesRegularExpression( '/<dt>Current price<\/dt><dd>€110\.00<\/dd>/', $html );
		$this->assertMatchesRegularExpression( '/<dt>Lowest price<\/dt><dd>€90\.00<\/dd>/', $html );
	}

	public function test_axis_values_are_round_and_enclose_the_prices() {
		$html = $this->chart->render( array( $this->point( '2026-10-21', 47.04 ), $this->point( '2026-11-10', 49.79 ) ), 'Product', 220, 'EUR', 30 );

		preg_match( '/<div class="oha-deals-chart__y"[^>]*>(.*?)<\/div>/', $html, $match );
		preg_match_all( '/--y:([\d.]+)%">€([\d.]+)</', $match[1], $labels );
		$this->assertSame( array( '46.00', '48.00', '50.00', '52.00' ), $labels[2] );
		$this->assertSame( '100.00', $labels[1][0], 'The lowest value sits on the bottom edge.' );
		$this->assertSame( '0.00', $labels[1][3] );
	}

	public function test_prices_near_zero_have_no_negative_axis_values() {
		$html = $this->chart->render( array( $this->point( '2026-10-21', 0.0 ), $this->point( '2026-11-10', 0.0 ) ), 'Product', 220, 'EUR', 30 );

		$this->assertDoesNotMatchRegularExpression( '/€-|-€|\x{2212}/u', $html );
		$this->assertDoesNotMatchRegularExpression( '/\b(NaN|-?INF)\b/', $html );
	}

	public function test_readout_context_matches_the_printed_state() {
		$html    = $this->chart->render( array( $this->point( '2026-10-21', 50.0 ), $this->point( '2026-11-15', 40.0 ) ), 'Product', 220, 'EUR', 30 );
		$readout = $this->readout( $html );
		$context = json_decode( (string) $readout->get_attribute( 'data-wp-context' ), true );

		$this->assertTrue( $readout->get_attribute( 'hidden' ), 'Hidden until the module runs.' );
		$this->assertSame( 'slider', $readout->get_attribute( 'role' ) );
		$this->assertSame( '0', $readout->get_attribute( 'aria-valuemin' ) );
		$this->assertSame( '1', $readout->get_attribute( 'aria-valuemax' ) );
		$this->assertSame( (string) $context['index'], $readout->get_attribute( 'aria-valuenow' ) );
		$this->assertSame( $context['text'], $readout->get_attribute( 'aria-valuetext' ) );
		$this->assertSame( $context['style'], $readout->get_attribute( 'style' ) );
		$this->assertFalse( $context['ready'] );

		$this->assertCount( 2, $context['segments'] );
		$this->assertSame( '2026-10-21 – 2026-11-14', $context['segments'][0]['date'], 'A price holds until the day before the next change.' );
		$this->assertSame( '2026-11-15 – 2026-11-20', $context['segments'][1]['date'] );
		$this->assertSame( '€50.00', $context['segments'][0]['price'] );
		$this->assertEqualsWithDelta( 83.33, $context['segments'][1]['x'], 0.01 );
		$this->assertEqualsWithDelta( 100, $context['segments'][1]['end'], 0.01 );
	}

	public function test_readout_context_is_escaped() {
		$evil = "'\"><script>alert(1)</script>&";
		update_option( 'date_format', 'Y' );
		$html = $this->chart->render( array( $this->point( '2026-11-18', 1.0 ), $this->point( '2026-11-20', 2.0 ) ), $evil, 220, $evil );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( "'\"><", $html );
		$context = json_decode( (string) $this->readout( $html )->get_attribute( 'data-wp-context' ), true );
		$this->assertStringContainsString( strtoupper( $evil ), $context['segments'][0]['price'], 'The value survives the attribute.' );
	}

	public function test_script_module_is_enqueued_for_shortcodes_and_product_cards() {
		if ( ! class_exists( '\WP_Script_Modules' ) || ! is_readable( OHA_DEALS_DIR . 'build/blocks/price-history/view.asset.php' ) ) {
			$this->markTestSkipped( 'build/ is missing; run npm run build.' );
		}
		$this->assertSame( 'oha-deals-price-history-view-script-module', generate_block_asset_handle( 'oha-deals/price-history', 'viewScriptModule' ) );
		$this->assertSame( HistoryChart::MODULE_ID, generate_block_asset_handle( 'oha-deals/price-history', 'viewScriptModule' ) );

		$this->chart->render( array( $this->point( '2026-11-20', 1.0 ) ), 'Product' );
		ob_start();
		wp_script_modules()->print_enqueued_script_modules();
		$this->assertStringContainsString( 'blocks/price-history/view.js', ob_get_clean() );
	}

	/**
	 * The readout element.
	 *
	 * @param string $html Chart markup.
	 * @return \WP_HTML_Tag_Processor Positioned on the readout.
	 */
	private function readout( $html ) {
		$tags = new \WP_HTML_Tag_Processor( $html );
		$this->assertTrue( $tags->next_tag( array( 'class_name' => 'oha-deals-chart__readout' ) ) );

		return $tags;
	}

	public function test_x_positions_follow_the_dates_and_the_line_steps() {
		$html = $this->chart->render(
			array(
				$this->point( '2026-10-21', 50.0 ),
				$this->point( '2026-11-15', 40.0 ),
			),
			'Product',
			220,
			'EUR',
			30
		);

		$line = $this->line( $html );
		// Start, step across at 2026-11-15 (25 of 30 days), step down, extend to today.
		$this->assertCount( 4, $line );
		$this->assertEqualsWithDelta( 0.0, $line[0][0], 0.1 );
		$this->assertEqualsWithDelta( 1000.0 * 25 / 30, $line[1][0], 0.1 );
		$this->assertSame( $line[0][1], $line[1][1], 'The old price holds until the change.' );
		$this->assertSame( $line[1][0], $line[2][0], 'The change is a vertical step.' );
		$this->assertEqualsWithDelta( 1000.0, $line[3][0], 0.1 );
	}

	public function test_two_flat_points_render_without_division_by_zero() {
		$html = $this->chart->render( array( $this->point( '2026-11-19', 50.0 ), $this->point( '2026-11-20', 50.0 ) ), 'Product' );

		$this->assertDoesNotMatchRegularExpression( '/\b(NaN|-?INF)\b/', $html );
	}

	public function test_chart_is_accessible() {
		$html = $this->chart->render(
			array(
				$this->point( '2026-11-18', 959.0, 965.5 ),
				$this->point( '2026-11-19', 949.0, 961.2 ),
				$this->point( '2026-11-20', 944.99, 960.1 ),
			),
			'Synology DS925+'
		);

		$this->assertMatchesRegularExpression( '/role="img" aria-labelledby="(oha-deals-chart-\d+)-title \1-desc"/', $html );
		$this->assertStringContainsString( '<title id="', $html );
		$this->assertStringContainsString( '<desc id="', $html );
		$this->assertStringContainsString( '<caption>', $html );
		$this->assertStringContainsString( '<th scope="col">', $html );
		$this->assertStringContainsString( '<li class="oha-deals-chart__key oha-deals-chart__key--avg">Average price</li>', $html, 'The legend names both lines.' );
		$this->assertMatchesRegularExpression( '/class="oha-deals-chart__avg"[^>]* stroke-dasharray="/', $html, 'The lines differ in shape, not only in colour.' );
		$this->assertStringNotContainsString( '<script', $html );
	}

	public function test_label_is_escaped() {
		$html = $this->chart->render( array( $this->point( '2026-11-20', 1.0 ) ), '<script>alert(1)</script>' );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}
}
