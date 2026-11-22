<?php
/**
 * Tests for Render\Formatter.
 *
 * @package OhaDeals
 */

namespace OhaDeals\Tests;

use OhaDeals\Render\Formatter;

/**
 * Tests for Formatter.
 *
 * @covers \OhaDeals\Render\Formatter
 */
class FormatterTest extends TestCase {

	public function test_money_puts_the_symbol_per_locale() {
		$this->assertSame( "\u{20AC}944.99", ( new Formatter( 'en_US' ) )->money( 944.99 ) );
		$this->assertStringEndsWith( "\u{00A0}\u{20AC}", ( new Formatter( 'de_DE' ) )->money( 944.99 ) );
		$this->assertStringStartsWith( "\u{20AC}\u{00A0}", ( new Formatter( 'nl_NL' ) )->money( 5 ) );
		$this->assertStringEndsWith( 'CHF', ( new Formatter( 'de_DE' ) )->money( 5, 'CHF' ) );
	}

	public function test_percent_uses_a_real_minus_sign() {
		$formatter = new Formatter( 'en_US' );

		$this->assertSame( "\u{2212}12%", $formatter->percent( -12 ) );
		$this->assertSame( '+3%', $formatter->percent( 3 ) );
		$this->assertSame( '0%', $formatter->percent( 0.2 ) );
	}

	public function test_unknown_shipping_is_never_free() {
		$formatter = new Formatter( 'en_US' );

		$this->assertSame( 'Shipping unknown', $formatter->shipping( null ) );
		$this->assertSame( 'Free shipping', $formatter->shipping( 0.0 ) );
		$this->assertStringContainsString( '4.99', $formatter->shipping( 4.99 ) );
	}

	public function test_dates_use_the_site_formats() {
		update_option( 'date_format', 'Y-m-d' );
		update_option( 'time_format', 'H:i' );
		update_option( 'timezone_string', 'UTC' );
		$formatter = new Formatter( 'en_US' );

		$this->assertSame( '2026-11-20', $formatter->date( '2026-11-20' ) );
		$this->assertSame( '2026-11-20, 08:15', $formatter->datetime( '2026-11-20T08:15:00.000Z' ) );
		$this->assertSame( '2026-11-20', $formatter->datetime( '2026-11-20' ), 'A bare date has no time to show.' );
		$this->assertSame( '', $formatter->date( 'not a date' ) );
	}

	public function test_bare_dates_do_not_shift_with_the_time_zone() {
		update_option( 'date_format', 'Y-m-d' );
		update_option( 'timezone_string', 'America/Los_Angeles' );

		$this->assertSame( '2026-11-20', ( new Formatter( 'en_US' ) )->date( '2026-11-20' ) );
	}

	public function test_labels() {
		$formatter = new Formatter( 'en_US' );

		$this->assertSame( 'In stock', $formatter->availability( 'in_stock' ) );
		$this->assertSame( '', $formatter->availability( 'unknown' ) );
		$this->assertSame( 'Used', $formatter->condition( 'used' ) );
		$this->assertSame( '', $formatter->condition( 'new' ) );
	}
}
