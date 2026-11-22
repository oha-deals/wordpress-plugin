<?php
/**
 * Locale-aware formatting of prices, percentages and dates.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Render;

defined( 'ABSPATH' ) || exit;

/**
 * Formats values as plain text. Callers escape on output.
 */
final class Formatter {

	/**
	 * Locales that put the currency symbol before the amount without a space.
	 */
	const SYMBOL_FIRST = array( 'en', 'ga', 'mt', 'cy', 'ja', 'zh', 'ko', 'he' );

	/**
	 * Locales that put the currency symbol before the amount with a space.
	 */
	const SYMBOL_FIRST_SPACED = array( 'nl', 'de_CH', 'it_CH', 'fr_CH' );

	/**
	 * Locale used for symbol placement, e.g. `de_DE`; empty follows the current locale.
	 *
	 * @var string
	 */
	private string $locale;

	/**
	 * Constructor.
	 *
	 * @param string $locale WordPress locale; empty follows determine_locale() at format time (switch_to_locale()).
	 */
	public function __construct( string $locale = '' ) {
		$this->locale = $locale;
	}

	/**
	 * A money amount with currency symbol, e.g. `944,99 €` (de_DE) or `€944.99` (en_US).
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency ISO currency code.
	 * @return string
	 */
	public function money( float $amount, string $currency = 'EUR' ): string {
		$number = number_format_i18n( $amount, 2 );
		$symbol = self::currency_symbol( $currency );

		if ( $this->matches( self::SYMBOL_FIRST_SPACED ) ) {
			return $symbol . "\u{00A0}" . $number;
		}
		if ( $this->matches( self::SYMBOL_FIRST ) ) {
			return $symbol . $number;
		}

		return $number . "\u{00A0}" . $symbol;
	}

	/**
	 * A signed percentage, e.g. `−12 %`. Uses a real minus sign.
	 *
	 * @param float $value    Percentage.
	 * @param int   $decimals Decimals.
	 * @return string
	 */
	public function percent( float $value, int $decimals = 0 ): string {
		$number = number_format_i18n( abs( $value ), $decimals );
		$sign   = '';
		if ( round( $value, $decimals ) < 0 ) {
			$sign = "\u{2212}";
		} elseif ( round( $value, $decimals ) > 0 ) {
			$sign = '+';
		}

		/* translators: %s: a number. Adjust spacing to your locale, e.g. "%s %%". */
		return $sign . sprintf( __( '%s%%', 'oha-deals' ), $number );
	}

	/**
	 * A date in the site's date format.
	 *
	 * @param string $iso ISO 8601 date or timestamp.
	 * @return string Empty when unparseable.
	 */
	public function date( string $iso ): string {
		$iso = trim( $iso );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $iso ) ) {
			// Noon UTC keeps a calendar date on the same day in every site time zone.
			$iso .= 'T12:00:00Z';
		}
		$time = strtotime( $iso );

		return false === $time ? '' : (string) wp_date( (string) get_option( 'date_format' ), $time );
	}

	/**
	 * A date and time in the site's formats and time zone.
	 *
	 * A bare date (`2026-11-20`) carries no time, so only the date is printed.
	 *
	 * @param string $iso ISO 8601 timestamp or date.
	 * @return string Empty when unparseable.
	 */
	public function datetime( string $iso ): string {
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', trim( $iso ) ) ) {
			return $this->date( $iso );
		}
		$time = strtotime( $iso );
		if ( false === $time ) {
			return '';
		}

		/* translators: 1: date, 2: time. */
		return sprintf( _x( '%1$s, %2$s', 'date and time', 'oha-deals' ), wp_date( (string) get_option( 'date_format' ), $time ), wp_date( (string) get_option( 'time_format' ), $time ) );
	}

	/**
	 * The shipping line. Unknown shipping is never shown as free.
	 *
	 * @param float|null $shipping Shipping cost, null when unknown.
	 * @param string     $currency Currency code.
	 * @return string
	 */
	public function shipping( ?float $shipping, string $currency = 'EUR' ): string {
		if ( null === $shipping ) {
			return __( 'Shipping unknown', 'oha-deals' );
		}
		if ( $shipping <= 0.0 ) {
			return __( 'Free shipping', 'oha-deals' );
		}

		/* translators: %s: shipping cost with currency. */
		return sprintf( __( '+ %s shipping', 'oha-deals' ), $this->money( $shipping, $currency ) );
	}

	/**
	 * A label for an availability key.
	 *
	 * @param string $availability `in_stock`, `short`, `long` or `on_order`.
	 * @return string Empty for unknown keys.
	 */
	public function availability( string $availability ): string {
		switch ( $availability ) {
			case 'in_stock':
				return __( 'In stock', 'oha-deals' );
			case 'short':
				return __( 'Ships in a few days', 'oha-deals' );
			case 'long':
				return __( 'Longer delivery time', 'oha-deals' );
			case 'on_order':
				return __( 'On order', 'oha-deals' );
			default:
				return '';
		}
	}

	/**
	 * A label for a condition key; empty for new goods.
	 *
	 * @param string $condition `new`, `used` or `refurbished`.
	 * @return string
	 */
	public function condition( string $condition ): string {
		switch ( $condition ) {
			case 'used':
				return __( 'Used', 'oha-deals' );
			case 'refurbished':
				return __( 'Refurbished', 'oha-deals' );
			default:
				return '';
		}
	}

	/**
	 * Currency symbol for a code.
	 *
	 * @param string $currency ISO currency code.
	 * @return string
	 */
	public static function currency_symbol( string $currency ): string {
		$symbols = array(
			'EUR' => "\u{20AC}",
			'GBP' => "\u{00A3}",
			'USD' => '$',
		);

		return $symbols[ strtoupper( $currency ) ] ?? strtoupper( $currency );
	}

	/**
	 * Whether the locale matches one of the given language codes or full locales.
	 *
	 * @param string[] $candidates Language codes (`en`) or locales (`de_CH`).
	 * @return bool
	 */
	private function matches( array $candidates ): bool {
		$locale   = '' !== $this->locale ? $this->locale : determine_locale();
		$language = strtolower( explode( '_', $locale, 2 )[0] );
		foreach ( $candidates as $candidate ) {
			if ( $candidate === $locale || $candidate === $language ) {
				return true;
			}
		}

		return false;
	}
}
