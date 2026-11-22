<?php
/**
 * Readable descriptions of an API key.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Admin;

use OhaDeals\Api\Cache;
use OhaDeals\Render\Formatter;

defined( 'ABSPATH' ) || exit;

/**
 * Turns the API's plan and capability names into text for administrators. Used by the settings page, the connection
 * route and WP-CLI.
 */
final class KeyInfo {

	/**
	 * Labels of the known capabilities, in display order.
	 *
	 * @return array<string, string>
	 */
	public static function capability_labels(): array {
		return array(
			'ownLinks' => __( 'Own affiliate links', 'oha-deals' ),
			'history'  => __( 'Price history', 'oha-deals' ),
			'amazon'   => __( 'Amazon prices and images', 'oha-deals' ),
		);
	}

	/**
	 * The label of a plan.
	 *
	 * @param string $tier Tier name from the API.
	 * @return string
	 */
	public static function plan( string $tier ): string {
		switch ( $tier ) {
			case 'free':
				return __( 'Free', 'oha-deals' );
			case 'pro':
				return __( 'Pro', 'oha-deals' );
			case 'business':
				return __( 'Business', 'oha-deals' );
			case 'internal':
				return __( 'Internal', 'oha-deals' );
			case '':
				return __( 'Unknown', 'oha-deals' );
			default:
				return ucfirst( $tier );
		}
	}

	/**
	 * Every known capability plus any new one the API sends, with a readable label.
	 *
	 * @param array<string, bool> $capabilities Capabilities from the API.
	 * @return array<int, array{name: string, label: string, enabled: bool}>
	 */
	public static function capabilities( array $capabilities ): array {
		$labels = self::capability_labels();
		$list   = array();
		foreach ( $labels as $name => $label ) {
			$list[] = array(
				'name'    => $name,
				'label'   => $label,
				'enabled' => ! empty( $capabilities[ $name ] ),
			);
		}
		foreach ( $capabilities as $name => $enabled ) {
			if ( ! isset( $labels[ $name ] ) && '' !== $name ) {
				// Unknown to this version: "bulkExport" reads as "Bulk export".
				$words  = strtolower( (string) preg_replace( '/(?<=[a-z0-9])(?=[A-Z])|[_-]+/', ' ', $name ) );
				$list[] = array(
					'name'    => $name,
					'label'   => ucfirst( trim( $words ) ),
					'enabled' => $enabled,
				);
			}
		}

		return $list;
	}

	/**
	 * The labels of the active capabilities.
	 *
	 * @param array<string, bool> $capabilities Capabilities from the API.
	 * @return string[]
	 */
	public static function active_capabilities( array $capabilities ): array {
		$labels = array();
		foreach ( self::capabilities( $capabilities ) as $capability ) {
			if ( $capability['enabled'] ) {
				$labels[] = $capability['label'];
			}
		}

		return $labels;
	}

	/**
	 * The rate limit as text.
	 *
	 * @param int $per_minute Requests per minute.
	 * @param int $per_day    Requests per day.
	 * @return string
	 */
	public static function rate_limit( int $per_minute, int $per_day ): string {
		return sprintf(
			/* translators: 1: requests per minute, 2: requests per day. */
			__( '%1$s requests per minute, %2$s per day', 'oha-deals' ),
			number_format_i18n( $per_minute ),
			number_format_i18n( $per_day )
		);
	}

	/**
	 * Everything the status card shows about a connected key, as plain text.
	 *
	 * @param array<string, mixed> $me         Key description from Repository::me().
	 * @param int                  $checked_at Unix time of that answer.
	 * @param Formatter            $formatter  Formatter.
	 * @phpstan-param array{keyId: string, tier: string, name: string, site: string, capabilities: array<string, bool>, rateLimit: array{perMinute: int, perDay: int}} $me
	 * @return array{plan: string, capabilities: array<int, array{name: string, label: string, enabled: bool}>, rateLimit: string, pricesCheckedAt: string, checkedAt: string}
	 */
	public static function summary( array $me, int $checked_at, Formatter $formatter ): array {
		$prices = Cache::last_price_check();

		return array(
			'plan'            => self::plan( $me['tier'] ),
			'capabilities'    => self::capabilities( $me['capabilities'] ),
			'rateLimit'       => self::rate_limit( $me['rateLimit']['perMinute'], $me['rateLimit']['perDay'] ),
			'pricesCheckedAt' => '' !== $prices ? $formatter->datetime( $prices ) : '',
			'checkedAt'       => $formatter->datetime( gmdate( 'Y-m-d\TH:i:s\Z', $checked_at ) ),
		);
	}
}
