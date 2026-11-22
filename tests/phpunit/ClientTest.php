<?php
/**
 * Tests for Api\Client.
 *
 * @package OhaDeals
 */

namespace OhaDeals\Tests;

use OhaDeals\Api\Client;
use OhaDeals\Settings\Options;

/**
 * Tests for Client.
 *
 * @covers \OhaDeals\Api\Client
 */
class ClientTest extends TestCase {

	/**
	 * Client with the test key.
	 *
	 * @return Client
	 */
	private function client() {
		return new Client( new Options() );
	}

	public function test_success_unwraps_the_envelope_and_sends_both_auth_headers() {
		$this->respond( '/me', $this->fixture( 'me' ) );

		$result = $this->client()->get( '/me' );

		$this->assertIsArray( $result );
		$this->assertSame( 'pro', $result['data']['tier'] );
		$this->assertSame( array(), $result['meta'] );

		$headers = $this->requests[0]['args']['headers'];
		$this->assertSame( 'Bearer ' . self::KEY, $headers['Authorization'] );
		$this->assertSame( self::KEY, $headers['X-Oha-Key'] );
		$this->assertSame( Client::TIMEOUT, $this->requests[0]['args']['timeout'] );
		$this->assertSame( 0, $this->requests[0]['args']['redirection'], 'Redirects would carry the key to another host.' );
		$this->assertStringNotContainsString( self::KEY, $this->requests[0]['url'], 'The key never goes into the URL.' );
	}

	public function test_missing_key_fails_without_a_request() {
		update_option( Options::OPTION, Options::defaults() );

		$result = $this->client()->get( '/me' );

		$this->assertWPError( $result );
		$this->assertSame( 'oha_deals_no_key', $result->get_error_code() );
		$this->assertCount( 0, $this->requests );
	}

	public function test_api_errors_map_to_prefixed_codes_without_retry() {
		$this->respond_error( '/products/nope', 404, 'not_found' );

		$result = $this->client()->get( '/products/nope' );

		$this->assertWPError( $result );
		$this->assertSame( 'oha_deals_not_found', $result->get_error_code() );
		$this->assertSame( 404, $result->get_error_data()['status'] );
		$this->assertCount( 1, $this->requests );
	}

	public function test_server_error_is_retried_once() {
		$this->respond_error( '/me', 500, 'internal' );
		$this->respond( '/me', $this->fixture( 'me' ) );

		$result = $this->client()->get( '/me' );

		$this->assertIsArray( $result );
		$this->assertCount( 2, $this->requests );
	}

	public function test_persistent_server_error_gives_up_after_one_retry() {
		$this->respond_error( '/me', 503, 'internal' );

		$result = $this->client()->get( '/me' );

		$this->assertWPError( $result );
		$this->assertCount( 2, $this->requests );
	}

	public function test_quick_mode_uses_a_short_timeout_and_no_retry() {
		$this->respond_error( '/me', 503, 'internal' );

		$result = $this->client()->get( '/me', array(), true );

		$this->assertWPError( $result );
		$this->assertCount( 1, $this->requests );
		$this->assertSame( Client::QUICK_TIMEOUT, $this->requests[0]['args']['timeout'] );
	}

	public function test_redirects_stay_disabled_whatever_the_filter_says() {
		add_filter(
			'oha_deals_request_args',
			static function ( $args ) {
				$args['redirection'] = 5;
				return $args;
			}
		);
		$this->respond( '/me', $this->fixture( 'me' ) );

		$this->client()->get( '/me' );

		$this->assertSame( 0, $this->requests[0]['args']['redirection'] );
	}

	public function test_is_outage() {
		$this->assertTrue( Client::is_outage( new \WP_Error( 'oha_deals_http', '', array( 'status' => 0 ) ) ) );
		$this->assertTrue( Client::is_outage( new \WP_Error( 'oha_deals_internal', '', array( 'status' => 502 ) ) ) );
		$this->assertFalse( Client::is_outage( new \WP_Error( 'oha_deals_not_found', '', array( 'status' => 404 ) ) ) );
		$this->assertFalse( Client::is_outage( new \WP_Error( 'oha_deals_no_key', '', array( 'status' => 0 ) ) ) );
	}

	public function test_transport_error_is_retried_once() {
		$this->respond( '/me', array( '__transport_error' => true ) );

		$result = $this->client()->get( '/me' );

		$this->assertWPError( $result );
		$this->assertSame( 'oha_deals_http', $result->get_error_code() );
		$this->assertCount( 2, $this->requests );
	}

	public function test_long_retry_after_is_not_waited_for() {
		$this->respond_error( '/me', 429, 'rate_limited', array( 'retry-after' => '30' ) );

		$result = $this->client()->get( '/me' );

		$this->assertWPError( $result );
		$this->assertSame( 'oha_deals_rate_limited', $result->get_error_code() );
		$this->assertSame( 30, $result->get_error_data()['retry_after'] );
		$this->assertCount( 1, $this->requests );
	}

	public function test_short_retry_after_is_honoured_with_one_retry() {
		$this->respond_error( '/me', 429, 'rate_limited', array( 'retry-after' => '0' ) );
		$this->respond( '/me', $this->fixture( 'me' ) );

		$result = $this->client()->get( '/me' );

		$this->assertIsArray( $result );
		$this->assertCount( 2, $this->requests );
	}

	public function test_errors_never_contain_the_key() {
		$this->respond_error( '/me', 401, 'unauthorized' );

		$result = $this->client()->get( '/me' );

		$this->assertStringNotContainsString( self::KEY, $result->get_error_message() );
		$this->assertStringNotContainsString( self::KEY, wp_json_encode( $result->get_error_data() ) );
	}

	public function test_invalid_json_is_an_error() {
		add_filter(
			'pre_http_request',
			static function () {
				return array(
					'headers'  => array(),
					'body'     => '<html>Bad gateway</html>',
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			5
		);

		$result = $this->client()->get( '/me' );

		$this->assertWPError( $result );
	}

	public function test_url_drops_empty_query_values_and_encodes() {
		$url = $this->client()->url(
			'/products/search',
			array(
				'q'        => 'ssd 2 tb & more',
				'category' => '',
				'limit'    => 20,
				'x'        => null,
			)
		);

		$this->assertSame( Options::DEFAULT_API_BASE . '/products/search?q=ssd%202%20tb%20%26%20more&limit=20', $url );
	}

	public function test_request_args_filter() {
		add_filter(
			'oha_deals_request_args',
			static function ( $args ) {
				$args['timeout'] = 3;
				return $args;
			}
		);
		$this->respond( '/me', $this->fixture( 'me' ) );

		$this->client()->get( '/me' );

		$this->assertSame( 3, $this->requests[0]['args']['timeout'] );
	}
}
