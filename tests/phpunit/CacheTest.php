<?php
/**
 * Tests for Api\Cache.
 *
 * @package OhaDeals
 */

namespace OhaDeals\Tests;

use OhaDeals\Api\Cache;
use OhaDeals\Api\Client;
use OhaDeals\Settings\Options;

/**
 * Tests for Cache.
 *
 * @covers \OhaDeals\Api\Cache
 */
class CacheTest extends TestCase {

	/**
	 * Cache under test.
	 *
	 * @var Cache
	 */
	private $cache;

	/**
	 * Fresh cache with the default one-hour soft TTL and an empty cron queue.
	 */
	public function set_up() {
		parent::set_up();
		$this->cache = $this->make_cache();
		_set_cron_array( array() );
	}

	/**
	 * A cache wired to the current settings.
	 *
	 * @return Cache
	 */
	private function make_cache() {
		$options = new Options();

		return new Cache( new Client( $options ), $options );
	}

	/**
	 * Makes a cached entry look older than its soft TTL.
	 *
	 * @param string $path  API path.
	 * @param array  $query Query.
	 * @return void
	 */
	private function age_entry( $path, array $query = array() ) {
		$key                 = $this->cache->key( $path, $query );
		$entry               = get_transient( $key );
		$entry['fetched_at'] = time() - 7200;
		set_transient( $key, $entry, DAY_IN_SECONDS );
	}

	/**
	 * Number of queued refresh events.
	 *
	 * @return int
	 */
	private function queued_refreshes() {
		$events = 0;
		foreach ( (array) _get_cron_array() as $hooks ) {
			$events += isset( $hooks[ Cache::REFRESH_HOOK ] ) ? count( $hooks[ Cache::REFRESH_HOOK ] ) : 0;
		}

		return $events;
	}

	/**
	 * Expiry time of a cached entry.
	 *
	 * @param string $path  API path.
	 * @param array  $query Query.
	 * @return int
	 */
	private function expiry( $path, array $query = array() ) {
		return (int) get_option( '_transient_timeout_' . $this->cache->key( $path, $query ) );
	}

	public function test_fresh_entries_are_served_from_the_cache() {
		$this->respond( '/me', $this->fixture( 'me' ) );

		$this->cache->get( '/me' );
		$second = $this->cache->get( '/me' );

		$this->assertSame( 'pro', $second['data']['tier'] );
		$this->assertCount( 1, $this->requests );
	}

	public function test_query_order_does_not_matter() {
		$this->respond( '/deals', $this->fixture( 'deals' ) );

		$this->cache->get(
			'/deals',
			array(
				'mode'  => 'all',
				'limit' => 6,
			)
		);
		$this->cache->get(
			'/deals',
			array(
				'limit' => '6',
				'mode'  => 'all',
			)
		);

		$this->assertCount( 1, $this->requests );
	}

	public function test_page_views_use_the_quick_mode() {
		$this->respond_error( '/me', 500, 'internal' );

		$this->cache->get( '/me' );

		$this->assertTrue( Cache::is_page_view() );
		$this->assertCount( 1, $this->requests, 'No inline retry while a visitor waits.' );
		$this->assertSame( Client::QUICK_TIMEOUT, $this->requests[0]['args']['timeout'] );
	}

	public function test_stale_entries_are_served_and_one_refresh_is_scheduled() {
		$this->respond( '/me', $this->fixture( 'me' ) );
		$this->cache->get( '/me' );
		$this->age_entry( '/me' );

		$stale = $this->cache->get( '/me' );
		$this->cache->get( '/me' );

		$this->assertSame( 'pro', $stale['data']['tier'] );
		$this->assertCount( 1, $this->requests, 'Stale reads do not block on the API.' );
		$this->assertNotFalse( wp_next_scheduled( Cache::REFRESH_HOOK, array( '/me', array() ) ) );
		$this->assertSame( 1, $this->queued_refreshes(), 'The refresh is scheduled once.' );
	}

	public function test_refresh_replaces_the_entry() {
		$me = $this->fixture( 'me' );
		$this->respond( '/me', $me );
		$this->cache->get( '/me' );

		$me['data']['tier']  = 'business';
		$this->routes['/me'] = array();
		$this->respond( '/me', $me );
		$this->cache->refresh( '/me' );

		$this->assertSame( 'business', $this->cache->get( '/me' )['data']['tier'] );
	}

	public function test_cron_hook_runs_the_refresh() {
		$me = $this->fixture( 'me' );
		$this->respond( '/me', $me );
		\OhaDeals\Plugin::instance()->cache()->get( '/me' );

		$me['data']['tier']  = 'business';
		$this->routes['/me'] = array();
		$this->respond( '/me', $me );
		do_action( Cache::REFRESH_HOOK, '/me', array() );

		$this->assertSame( 'business', \OhaDeals\Plugin::instance()->cache()->get( '/me' )['data']['tier'] );
		$this->assertCount( 2, $this->requests );
	}

	public function test_failed_refresh_keeps_the_entry_and_backs_off() {
		$this->respond( '/me', $this->fixture( 'me' ) );
		$this->cache->get( '/me' );
		$this->age_entry( '/me' );

		$this->routes['/me'] = array();
		$this->respond_error( '/me', 503, 'internal' );
		$this->cache->refresh( '/me' );
		delete_transient( Cache::BREAKER );
		_set_cron_array( array() );

		$this->assertSame( 'pro', $this->cache->get( '/me' )['data']['tier'] );
		$this->assertSame( 0, $this->queued_refreshes(), 'Stale reads wait for the retry delay before scheduling again.' );
		$entry = get_transient( $this->cache->key( '/me', array() ) );
		$this->assertGreaterThan( time(), $entry['retry_at'] );
	}

	public function test_definitive_error_on_refresh_replaces_the_entry() {
		$this->respond( '/products/gone', $this->fixture( 'product' ) );
		$this->cache->get( '/products/gone' );

		$this->routes['/products/gone'] = array();
		$this->respond_error( '/products/gone', 404, 'not_found' );
		$this->cache->refresh( '/products/gone' );

		$this->assertWPError( $this->cache->get( '/products/gone' ) );
	}

	public function test_refreshes_per_run_are_capped() {
		$this->respond( '/me', $this->fixture( 'me' ) );

		for ( $i = 0; $i <= Cache::MAX_REFRESHES_PER_RUN; $i++ ) {
			$this->cache->refresh( '/me', array( 'n' => $i ) );
		}

		$this->assertCount( Cache::MAX_REFRESHES_PER_RUN, $this->requests );
		$this->assertNotFalse(
			wp_next_scheduled( Cache::REFRESH_HOOK, array( '/me', array( 'n' => (string) Cache::MAX_REFRESHES_PER_RUN ) ) ),
			'The rest is rescheduled.'
		);
	}

	public function test_errors_are_cached_briefly() {
		$this->respond_error( '/me', 500, 'internal' );

		$first = $this->cache->get( '/me' );
		delete_transient( Cache::BREAKER );
		$second = $this->cache->get( '/me' );

		$this->assertWPError( $first );
		$this->assertWPError( $second );
		$this->assertSame( 'oha_deals_internal', $second->get_error_code() );
		$this->assertCount( 1, $this->requests, 'The negative cache answers the second read.' );
		$this->assertEqualsWithDelta( time() + Cache::NEGATIVE_TTL, $this->expiry( '/me' ), 2 );
	}

	public function test_retry_after_extends_the_negative_cache() {
		$this->respond_error( '/me', 429, 'rate_limited', array( 'retry-after' => '120' ) );

		$this->cache->get( '/me' );

		$this->assertEqualsWithDelta( time() + 120, $this->expiry( '/me' ), 2 );
	}

	public function test_outage_opens_the_circuit_breaker() {
		$this->respond( '/products/cached', $this->fixture( 'product' ) );
		$this->cache->get( '/products/cached' );
		$this->age_entry( '/products/cached' );
		$this->respond_error( '/me', 502, 'internal' );

		$this->cache->get( '/me' );
		$blocked = $this->cache->get( '/categories' );
		$stale   = $this->cache->get( '/products/cached' );

		$this->assertTrue( Cache::is_circuit_open() );
		$this->assertWPError( $blocked );
		$this->assertSame( 'oha_deals_unavailable', $blocked->get_error_code() );
		$this->assertCount( 0, $this->requests_to( '/categories' ), 'No inline fetch while the breaker is open.' );
		$this->assertIsArray( $stale, 'Stale data is still served.' );
		$this->assertNotFalse( $this->cache->get( '/categories', array(), true ), 'A fresh fetch (connection test) ignores the breaker.' );
		$this->assertCount( 1, $this->requests_to( '/categories' ) );
	}

	public function test_answers_about_the_request_do_not_open_the_breaker() {
		$this->respond_error( '/products/nope', 404, 'not_found' );

		$this->cache->get( '/products/nope' );

		$this->assertFalse( Cache::is_circuit_open() );
	}

	public function test_missing_key_is_not_cached() {
		update_option( Options::OPTION, Options::defaults() );
		$cache = $this->make_cache();

		$cache->get( '/me' );

		$this->assertFalse( get_transient( $cache->key( '/me', array() ) ) );
		$this->assertFalse( Cache::is_circuit_open() );
	}

	public function test_amazon_prices_are_never_kept_longer_than_an_hour_or_served_stale() {
		update_option(
			Options::OPTION,
			array_merge(
				Options::defaults(),
				array(
					'api_key'   => self::KEY,
					'cache_ttl' => 86400,
				)
			)
		);
		$cache = $this->make_cache();
		$this->respond( '/products/echo-dot-5', $this->fixture( 'product-amazon' ) );

		$cache->get( '/products/echo-dot-5' );

		$entry = get_transient( $cache->key( '/products/echo-dot-5', array() ) );
		$this->assertSame( Cache::AMAZON_MAX_TTL, $entry['soft_ttl'] );
		$this->assertSame( $entry['fetched_at'] + $entry['soft_ttl'], $entry['expires_at'], 'Hard TTL equals soft TTL: no stale window.' );
		$this->assertLessThanOrEqual( time() + Cache::AMAZON_MAX_TTL, $this->expiry( '/products/echo-dot-5' ) );
	}

	public function test_search_results_have_a_short_ttl_without_stale_window() {
		$this->respond( '/products/search', $this->fixture( 'search' ) );

		$this->cache->get( '/products/search', array( 'q' => 'nas' ) );

		$entry = get_transient( $this->cache->key( '/products/search', array( 'q' => 'nas' ) ) );
		$this->assertSame( Cache::SEARCH_TTL, $entry['soft_ttl'] );
		$this->assertEqualsWithDelta( time() + Cache::SEARCH_TTL, $this->expiry( '/products/search', array( 'q' => 'nas' ) ), 2 );
	}

	public function test_other_payloads_keep_a_long_hard_ttl() {
		$this->respond( '/products/synology-ds925-plus', $this->fixture( 'product' ) );

		$this->cache->get( '/products/synology-ds925-plus' );

		$this->assertGreaterThanOrEqual( time() + DAY_IN_SECONDS - 5, $this->expiry( '/products/synology-ds925-plus' ) );
	}

	public function test_cache_ttl_filter() {
		add_filter(
			'oha_deals_cache_ttl',
			static function () {
				return 600;
			}
		);
		$this->respond( '/me', $this->fixture( 'me' ) );

		$this->cache->get( '/me' );

		$this->assertSame( 600, get_transient( $this->cache->key( '/me', array() ) )['soft_ttl'] );
	}

	public function test_flush_invalidates_everything() {
		$this->respond( '/me', $this->fixture( 'me' ) );
		$this->cache->get( '/me' );
		$version = Cache::version();

		$this->cache->flush();
		$this->cache->get( '/me' );

		$this->assertSame( $version + 1, Cache::version() );
		$this->assertCount( 2, $this->requests );
	}

	public function test_changing_the_api_key_flushes_the_cache() {
		$version = Cache::version();

		update_option( Options::OPTION, array_merge( Options::defaults(), array( 'api_key' => 'oha_live_ZYXWVUTSRQPONMLKJIHGFEDCBA987654' ) ) );
		$this->assertSame( $version + 1, Cache::version() );

		update_option(
			Options::OPTION,
			array_merge(
				Options::defaults(),
				array(
					'api_key'      => 'oha_live_ZYXWVUTSRQPONMLKJIHGFEDCBA987654',
					'open_new_tab' => false,
				)
			)
		);
		$this->assertSame( $version + 1, Cache::version(), 'Other settings keep the cache.' );
	}

	public function test_peek_reads_the_cache_without_requests_or_refreshes() {
		$this->assertNull( $this->cache->peek( '/me' ) );
		$this->assertCount( 0, $this->requests );

		$this->respond( '/me', $this->fixture( 'me' ) );
		$this->cache->get( '/me' );
		$this->age_entry( '/me' );
		$entry = $this->cache->peek( '/me' );

		$this->assertSame( 'k1', $entry['data']['keyId'] );
		$this->assertEqualsWithDelta( time() - 7200, $entry['fetched_at'], 5 );
		$this->assertCount( 1, $this->requests );
		$this->assertFalse( wp_next_scheduled( Cache::REFRESH_HOOK, array( '/me', array() ) ) );
	}

	public function test_peek_ignores_cached_errors() {
		$this->respond_error( '/me', 401, 'unauthorized' );
		$this->cache->get( '/me' );

		$this->assertNull( $this->cache->peek( '/me' ) );
	}

	public function test_the_newest_price_check_is_remembered() {
		$this->assertSame( '', Cache::last_price_check() );

		$this->respond( '/products/synology-ds925-plus', $this->fixture( 'product' ) );
		$this->cache->get( '/products/synology-ds925-plus' );
		$this->assertSame( '2026-11-20T04:15:00.000Z', Cache::last_price_check() );

		$deals                            = $this->fixture( 'deals' );
		$deals['meta']['pricesCheckedAt'] = '2026-11-19T04:15:00.000Z';
		$this->respond( '/deals', $deals );
		$this->cache->get( '/deals' );
		$this->assertSame( '2026-11-20T04:15:00.000Z', Cache::last_price_check(), 'An older answer does not replace it.' );

		$deals['meta']['pricesCheckedAt'] = '2026-11-21T04:15:00.000Z';
		$this->respond( '/deals', $deals );
		$this->cache->get( '/deals', array( 'limit' => 5 ) );
		$this->routes['/deals'] = array( end( $this->routes['/deals'] ) );
		$this->cache->get( '/deals', array( 'limit' => 6 ) );
		$this->assertSame( '2026-11-21T04:15:00.000Z', Cache::last_price_check() );
	}

	public function test_fresh_flag_bypasses_the_cache() {
		$this->respond( '/me', $this->fixture( 'me' ) );
		$this->cache->get( '/me' );

		$this->cache->get( '/me', array(), true );

		$this->assertCount( 2, $this->requests );
	}

	public function test_contains_amazon_price() {
		$this->assertTrue( Cache::contains_amazon_price( array( 'offers' => array( array( 'priceAt' => '2026-11-20' ) ) ) ) );
		$this->assertTrue(
			Cache::contains_amazon_price(
				array(
					'offers' => array(
						array(
							'shop'    => array( 'id' => 'amazon' ),
							'price'   => 9.99,
							'priceAt' => null,
						),
					),
				)
			),
			'An Amazon shop id with a visible price counts even without a timestamp.'
		);
		$this->assertTrue(
			Cache::contains_amazon_price(
				array(
					'price'    => 9.99,
					'merchant' => array( 'network' => 'amazon' ),
				)
			)
		);
		$this->assertFalse(
			Cache::contains_amazon_price(
				array(
					'shop'  => array( 'id' => 'amazon' ),
					'price' => null,
				)
			),
			'Link-only Amazon offers carry no Amazon price.'
		);
		$this->assertFalse( Cache::contains_amazon_price( array( 'priceAt' => null ) ) );
		$this->assertFalse( Cache::contains_amazon_price( 'string' ) );
	}
}
