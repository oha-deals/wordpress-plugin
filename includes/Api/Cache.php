<?php
/**
 * Transient cache with stale-while-revalidate, negative caching and a circuit breaker.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Api;

use OhaDeals\Settings\Options;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Caches Partner API responses.
 *
 * - Fresh entries (younger than the soft TTL) are returned as they are.
 * - Stale entries (within the hard TTL) are returned and one deduplicated cron refresh is scheduled.
 * - Missing entries are fetched inline; failures are cached briefly so a down API does not slow every page view.
 * - After a transport error or a 5xx answer, a circuit breaker stops all inline fetches for 45 seconds.
 * - flush() bumps a version salt that is part of every key, so old entries are simply never read again.
 */
final class Cache {

	/**
	 * Transient key prefix.
	 */
	const PREFIX = 'oha_deals_';

	/**
	 * Option that holds the cache version salt.
	 */
	const VERSION_OPTION = 'oha_deals_cache_version';

	/**
	 * Cron hook for background refreshes.
	 */
	const REFRESH_HOOK = 'oha_deals_refresh';

	/**
	 * Transient that marks the API as down.
	 */
	const BREAKER = 'oha_deals_circuit_open';

	/**
	 * How long the circuit breaker stays open, in seconds.
	 */
	const BREAKER_TTL = 45;

	/**
	 * How long a failed request is remembered, in seconds.
	 */
	const NEGATIVE_TTL = 60;

	/**
	 * After a failed background refresh, stale reads wait this long (seconds) before scheduling the next one.
	 */
	const RETRY_DELAY = 300;

	/**
	 * Most refreshes run in one cron request; the rest are rescheduled a minute later.
	 */
	const MAX_REFRESHES_PER_RUN = 20;

	/**
	 * Longest lifetime of anything that contains Amazon prices (Amazon licence).
	 */
	const AMAZON_MAX_TTL = 3600;

	/**
	 * Lifetime of search results (editor product picker). They are never served stale.
	 */
	const SEARCH_TTL = 600;

	/**
	 * Transient with the newest price check time the API reported (ISO 8601), shown on the settings page.
	 */
	const LAST_PRICE_CHECK = 'oha_deals_last_price_check';

	/**
	 * Error codes that describe the request rather than a passing outage. They replace stale data.
	 */
	const DEFINITIVE_ERRORS = array( 'oha_deals_not_found', 'oha_deals_unauthorized', 'oha_deals_forbidden', 'oha_deals_invalid_request' );

	/**
	 * API client.
	 *
	 * @var Client
	 */
	private Client $client;

	/**
	 * Settings (soft TTL).
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Refreshes run in this request.
	 *
	 * @var int
	 */
	private int $refreshes = 0;

	/**
	 * Constructor.
	 *
	 * @param Client  $client  API client.
	 * @param Options $options Settings.
	 */
	public function __construct( Client $client, Options $options ) {
		$this->client  = $client;
		$this->options = $options;
	}

	/**
	 * Returns a response from the cache or the API.
	 *
	 * @param string               $path  API path.
	 * @param array<string, mixed> $query Query arguments.
	 * @param bool                 $fresh Skip the cache read and the circuit breaker and fetch now (the result is still stored).
	 * @return array{data: mixed, meta: array<string, mixed>}|WP_Error
	 */
	public function get( string $path, array $query = array(), bool $fresh = false ) {
		$query = self::normalize_query( $query );
		$key   = $this->key( $path, $query );

		if ( ! $fresh ) {
			$entry = get_transient( $key );

			if ( is_array( $entry ) && isset( $entry['error'] ) && is_array( $entry['error'] ) ) {
				return new WP_Error(
					(string) ( $entry['error']['code'] ?? 'oha_deals_internal' ),
					(string) ( $entry['error']['message'] ?? '' ),
					(array) ( $entry['error']['data'] ?? array() )
				);
			}

			if ( self::is_entry( $entry ) ) {
				$now = time();
				if ( $now - (int) $entry['fetched_at'] >= (int) $entry['soft_ttl'] && $now >= (int) ( $entry['retry_at'] ?? 0 ) ) {
					$this->schedule_refresh( $path, $query, $now );
				}

				return array(
					'data' => $entry['data'],
					'meta' => (array) $entry['meta'],
				);
			}

			if ( self::is_circuit_open() ) {
				return new WP_Error( 'oha_deals_unavailable', __( 'The oha.deals API is not reachable at the moment.', 'oha-deals' ), array( 'status' => 0 ) );
			}
		}

		$response = $this->client->get( $path, $query, self::is_page_view() );
		if ( is_wp_error( $response ) ) {
			$this->note_outage( $response );
			$this->store_error( $key, $response );

			return $response;
		}

		$this->store( $key, $path, $query, $response );

		return $response;
	}

	/**
	 * The cached response for a request, without ever calling the API or scheduling a refresh.
	 *
	 * @param string               $path  API path.
	 * @param array<string, mixed> $query Query arguments.
	 * @return array{data: mixed, fetched_at: int}|null Null when no successful response is cached.
	 */
	public function peek( string $path, array $query = array() ): ?array {
		$entry = get_transient( $this->key( $path, $query ) );
		if ( ! is_array( $entry ) || ! self::is_entry( $entry ) ) {
			return null;
		}

		return array(
			'data'       => $entry['data'],
			'fetched_at' => (int) $entry['fetched_at'],
		);
	}

	/**
	 * The newest price check time seen in a response, as an ISO 8601 timestamp, or an empty string.
	 *
	 * @return string
	 */
	public static function last_price_check(): string {
		$when = get_transient( self::LAST_PRICE_CHECK );

		return is_string( $when ) ? $when : '';
	}

	/**
	 * Cron callback: refetches one entry.
	 *
	 * A passing failure keeps the stale entry and holds further refreshes back for RETRY_DELAY seconds; a definitive
	 * one (not found, key rejected) replaces it. At most MAX_REFRESHES_PER_RUN run per request.
	 *
	 * @param string               $path  API path.
	 * @param array<string, mixed> $query Query arguments.
	 * @return void
	 */
	public function refresh( string $path, array $query = array() ): void {
		$query = self::normalize_query( $query );

		if ( $this->refreshes >= self::MAX_REFRESHES_PER_RUN ) {
			$this->schedule_refresh( $path, $query, time() + MINUTE_IN_SECONDS );
			return;
		}
		++$this->refreshes;

		$key      = $this->key( $path, $query );
		$response = $this->client->get( $path, $query );

		if ( ! is_wp_error( $response ) ) {
			$this->store( $key, $path, $query, $response );
			return;
		}

		$this->note_outage( $response );
		if ( in_array( $response->get_error_code(), self::DEFINITIVE_ERRORS, true ) ) {
			$this->store_error( $key, $response );
			return;
		}

		$entry = get_transient( $key );
		if ( self::is_entry( $entry ) ) {
			$remaining = (int) ( $entry['expires_at'] ?? 0 ) - time();
			if ( $remaining > 0 ) {
				$entry['retry_at'] = time() + self::RETRY_DELAY;
				set_transient( $key, $entry, $remaining );
			}
		}
	}

	/**
	 * Invalidates every cached response by bumping the version salt.
	 *
	 * @return void
	 */
	public function flush(): void {
		update_option( self::VERSION_OPTION, (string) ( self::version() + 1 ), true );
	}

	/**
	 * Current version salt.
	 *
	 * @return int
	 */
	public static function version(): int {
		return max( 1, (int) get_option( self::VERSION_OPTION, 1 ) );
	}

	/**
	 * Whether the circuit breaker is open (the API failed within the last BREAKER_TTL seconds).
	 *
	 * @return bool
	 */
	public static function is_circuit_open(): bool {
		return false !== get_transient( self::BREAKER );
	}

	/**
	 * Transient key for a request.
	 *
	 * @param string               $path  API path.
	 * @param array<string, mixed> $query Normalised query.
	 * @return string
	 */
	public function key( string $path, array $query ): string {
		return self::PREFIX . md5( self::version() . '|' . $path . '|' . (string) wp_json_encode( self::normalize_query( $query ) ) );
	}

	/**
	 * Whether a payload contains an Amazon price: a non-empty `priceAt`, or a visible price on an object whose shop
	 * id or merchant network is `amazon` (defensive, in case a timestamp is missing).
	 *
	 * @param mixed $data Decoded payload.
	 * @return bool
	 */
	public static function contains_amazon_price( $data ): bool {
		if ( ! is_array( $data ) ) {
			return false;
		}

		if ( isset( $data['priceAt'] ) && is_string( $data['priceAt'] ) && '' !== $data['priceAt'] ) {
			return true;
		}
		$has_price = isset( $data['price'] ) && is_numeric( $data['price'] );
		$shop_id   = is_array( $data['shop'] ?? null ) ? ( $data['shop']['id'] ?? null ) : null;
		$network   = is_array( $data['merchant'] ?? null ) ? ( $data['merchant']['network'] ?? null ) : null;
		if ( $has_price && ( 'amazon' === $shop_id || 'amazon' === $network ) ) {
			return true;
		}

		foreach ( $data as $value ) {
			if ( is_array( $value ) && self::contains_amazon_price( $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the current request renders a page for a visitor (as opposed to cron, admin, REST, AJAX or WP-CLI).
	 *
	 * @return bool
	 */
	public static function is_page_view(): bool {
		return ! is_admin()
			&& ! wp_doing_cron()
			&& ! wp_doing_ajax()
			&& ! ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			&& ! ( defined( 'WP_CLI' ) && WP_CLI );
	}

	/**
	 * Stores a successful response.
	 *
	 * @param string                                         $key      Transient key.
	 * @param string                                         $path     API path.
	 * @param array<string, mixed>                           $query    Query arguments.
	 * @param array{data: mixed, meta: array<string, mixed>} $response Response.
	 * @return void
	 */
	private function store( string $key, string $path, array $query, array $response ): void {
		/**
		 * Filters the soft cache lifetime (seconds) of one Partner API response.
		 *
		 * Responses that contain Amazon prices are capped at one hour afterwards and never served stale; search
		 * results are capped at ten minutes and never served stale either.
		 *
		 * @since 1.0.0
		 *
		 * @param int                  $ttl   Seconds.
		 * @param string               $path  API path.
		 * @param array<string, mixed> $query Query arguments.
		 */
		$soft = max( 60, (int) apply_filters( 'oha_deals_cache_ttl', $this->options->cache_ttl(), $path, $query ) );

		if ( self::contains_amazon_price( $response['data'] ) ) {
			$soft = min( $soft, self::AMAZON_MAX_TTL );
			$hard = $soft;
		} elseif ( '/products/search' === $path ) {
			$soft = min( $soft, self::SEARCH_TTL );
			$hard = $soft;
		} else {
			$hard = max( $soft * 6, DAY_IN_SECONDS );
		}

		$now = time();
		set_transient(
			$key,
			array(
				'data'       => $response['data'],
				'meta'       => $response['meta'],
				'fetched_at' => $now,
				'soft_ttl'   => $soft,
				'expires_at' => $now + $hard,
			),
			$hard
		);
		self::note_price_check( $response );
	}

	/**
	 * Remembers the newest `pricesCheckedAt` the API reported (in the data of a product or the meta of a deals list).
	 *
	 * @param array{data: mixed, meta: array<string, mixed>} $response Response.
	 * @return void
	 */
	private static function note_price_check( array $response ): void {
		$data = is_array( $response['data'] ) ? $response['data'] : array();
		$when = $response['meta']['pricesCheckedAt'] ?? $data['pricesCheckedAt'] ?? null;
		if ( ! is_string( $when ) ) {
			return;
		}
		$time = strtotime( $when );
		if ( false !== $time && $time > (int) strtotime( self::last_price_check() ) ) {
			set_transient( self::LAST_PRICE_CHECK, $when, WEEK_IN_SECONDS );
		}
	}

	/**
	 * Remembers a failure. Missing products are remembered for the soft TTL (at most an hour), everything else
	 * for 60 seconds or the server's Retry-After, whichever is longer. A missing API key is not cached.
	 *
	 * @param string   $key   Transient key.
	 * @param WP_Error $error Error.
	 * @return void
	 */
	private function store_error( string $key, WP_Error $error ): void {
		if ( 'oha_deals_no_key' === $error->get_error_code() ) {
			return;
		}

		$data = (array) $error->get_error_data();
		if ( 'oha_deals_not_found' === $error->get_error_code() ) {
			$ttl = min( $this->options->cache_ttl(), HOUR_IN_SECONDS );
		} else {
			$ttl = max( self::NEGATIVE_TTL, (int) ( $data['retry_after'] ?? 0 ) );
		}

		set_transient(
			$key,
			array(
				'error' => array(
					'code'    => $error->get_error_code(),
					'message' => $error->get_error_message(),
					'data'    => $data,
				),
			),
			$ttl
		);
	}

	/**
	 * Opens the circuit breaker after a transport error or a 5xx answer.
	 *
	 * @param WP_Error $error Error.
	 * @return void
	 */
	private function note_outage( WP_Error $error ): void {
		if ( Client::is_outage( $error ) ) {
			set_transient( self::BREAKER, time(), self::BREAKER_TTL );
		}
	}

	/**
	 * Schedules one background refresh for a request unless one is already queued.
	 *
	 * @param string               $path  API path.
	 * @param array<string, mixed> $query Normalised query.
	 * @param int                  $when  Unix time.
	 * @return void
	 */
	private function schedule_refresh( string $path, array $query, int $when ): void {
		$args = array( $path, $query );
		if ( false === wp_next_scheduled( self::REFRESH_HOOK, $args ) ) {
			wp_schedule_single_event( $when, self::REFRESH_HOOK, $args );
		}
	}

	/**
	 * Whether a transient value is a well-formed success entry.
	 *
	 * @param mixed $entry Transient value.
	 * @return bool
	 */
	private static function is_entry( $entry ): bool {
		return is_array( $entry )
			&& array_key_exists( 'data', $entry )
			&& isset( $entry['fetched_at'], $entry['soft_ttl'] );
	}

	/**
	 * Sorts query arguments by key and turns scalars into strings, so equal requests share one key.
	 *
	 * @param array<string, mixed> $query Query arguments.
	 * @return array<string, string>
	 */
	private static function normalize_query( array $query ): array {
		$clean = array();
		foreach ( $query as $name => $value ) {
			if ( null === $value || '' === $value || ! is_scalar( $value ) ) {
				continue;
			}
			if ( is_bool( $value ) ) {
				$value = $value ? 'true' : 'false';
			}
			$clean[ (string) $name ] = (string) $value;
		}
		ksort( $clean );

		return $clean;
	}
}
