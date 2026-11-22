<?php
/**
 * Tests for Rest\EditorController.
 *
 * @package OhaDeals
 */

namespace OhaDeals\Tests;

use OhaDeals\Api\Cache;
use OhaDeals\Blocks\Bindings;
use WP_REST_Request;

/**
 * Tests for Rest.
 *
 * @covers \OhaDeals\Rest\EditorController
 */
class RestTest extends TestCase {

	/**
	 * Boots the REST server so the routes are registered.
	 */
	public function set_up() {
		parent::set_up();
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		do_action( 'rest_api_init', $wp_rest_server );
	}

	/**
	 * Resets the REST server.
	 */
	public function tear_down() {
		global $wp_rest_server;
		$wp_rest_server = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		parent::tear_down();
	}

	/**
	 * Dispatches a request.
	 *
	 * @param string $method Method.
	 * @param string $route  Route below the namespace.
	 * @param array  $params Query parameters.
	 * @return \WP_REST_Response
	 */
	private function dispatch( $method, $route, array $params = array() ) {
		$request = new WP_REST_Request( $method, '/oha-deals/v1' . $route );
		$request->set_query_params( $params );

		return rest_get_server()->dispatch( $request );
	}

	public function test_routes_are_registered() {
		$routes = rest_get_server()->get_routes();

		foreach ( array( '/search', '/product', '/products', '/categories', '/connection', '/cache/flush' ) as $route ) {
			$this->assertArrayHasKey( '/oha-deals/v1' . $route, $routes );
		}
	}

	public function test_search_requires_edit_posts() {
		$this->login_as( 'subscriber' );

		$response = $this->dispatch( 'GET', '/search', array( 'q' => 'nas' ) );

		$this->assertSame( 403, $response->get_status() );
		$this->assertCount( 0, $this->requests );
	}

	public function test_search_returns_picker_items() {
		$this->login_as( 'editor' );
		$this->respond( '/products/search', $this->fixture( 'search' ) );

		$response = $this->dispatch( 'GET', '/search', array( 'q' => 'synology' ) );
		$items    = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 2, $items );
		$this->assertSame( array( 'slug', 'ean', 'name', 'brand', 'image', 'price', 'priceFormatted', 'shop', 'url' ), array_keys( $items[0] ) );
		$this->assertSame( 944.99, $items[0]['price'] );
		$this->assertNotEmpty( $items[0]['priceFormatted'] );
		$this->assertNull( $items[1]['price'], 'Hidden prices stay hidden.' );
		$this->assertNull( $items[1]['priceFormatted'] );
		$this->assertNull( $items[1]['image'], 'Insecure image URLs are dropped.' );
		$this->assertStringNotContainsString( self::KEY, wp_json_encode( $items ) );
	}

	public function test_product_resolves_an_oha_deals_url() {
		$this->login_as( 'editor' );
		$this->respond( '/products/synology-ds925-plus', $this->fixture( 'product' ) );

		$response = $this->dispatch( 'GET', '/product', array( 'slug' => 'https://oha.deals/produkt/synology-ds925-plus?ref=x' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'synology-ds925-plus', $response->get_data()['slug'] );
	}

	public function test_product_carries_the_binding_values() {
		$this->login_as( 'editor' );
		$this->respond( '/products/synology-ds925-plus', $this->fixture( 'product' ) );
		$this->respond( '/products/echo-dot-5', $this->fixture( 'product-amazon' ) );

		$bindings = $this->dispatch( 'GET', '/product', array( 'slug' => 'synology-ds925-plus' ) )->get_data()['bindings'];
		$this->assertSame( Bindings::FIELDS, array_keys( $bindings ) );
		$this->assertSame( '€944.99', $bindings['price'] );
		$this->assertSame( 'https://oha.deals/produkt/synology-ds925-plus#angebote', $bindings['url'] );

		$amazon = $this->dispatch( 'GET', '/product', array( 'slug' => 'echo-dot-5' ) )->get_data()['bindings'];
		$this->assertNull( $amazon['price'], 'An Amazon price needs its timestamp.' );
		$this->assertNull( $amazon['image'] );
	}

	public function test_product_not_found_is_a_404() {
		$this->login_as( 'editor' );
		$this->respond_error( '/products/nope', 404, 'not_found' );

		$response = $this->dispatch( 'GET', '/product', array( 'slug' => 'nope' ) );

		$this->assertSame( 404, $response->get_status() );
	}

	public function test_products_returns_picker_items_in_order() {
		$this->login_as( 'editor' );
		$this->respond( '/products/synology-ds925-plus', $this->fixture( 'product' ) );
		$this->respond( '/products/echo-dot-5', $this->fixture( 'product-amazon' ) );

		$response = $this->dispatch( 'GET', '/products', array( 'slugs' => 'echo-dot-5, ,missing,https://oha.deals/produkt/synology-ds925-plus,echo-dot-5' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'echo-dot-5', 'synology-ds925-plus' ), wp_list_pluck( $response->get_data(), 'slug' ) );
		$this->assertArrayHasKey( 'priceFormatted', $response->get_data()[0] );
	}

	public function test_products_loads_at_most_six_and_needs_edit_posts() {
		$this->login_as( 'editor' );
		$this->dispatch( 'GET', '/products', array( 'slugs' => 'a1,a2,a3,a4,a5,a6,a7,a8' ) );
		$this->assertCount( 6, $this->requests );

		$this->login_as( 'subscriber' );
		$this->assertSame( 403, $this->dispatch( 'GET', '/products', array( 'slugs' => 'a1' ) )->get_status() );
	}

	public function test_products_reports_an_outage() {
		$this->login_as( 'editor' );
		$this->respond_error( '/products/synology-ds925-plus', 401, 'unauthorized' );

		$this->assertSame( 502, $this->dispatch( 'GET', '/products', array( 'slugs' => 'synology-ds925-plus' ) )->get_status() );
	}

	public function test_upstream_errors_are_502_without_the_key() {
		$this->login_as( 'editor' );
		$this->respond_error( '/categories', 401, 'unauthorized' );

		$response = $this->dispatch( 'GET', '/categories' );

		$this->assertSame( 502, $response->get_status() );
		$this->assertStringNotContainsString( self::KEY, wp_json_encode( $response->get_data() ) );
	}

	public function test_categories_skip_malformed_entries() {
		$this->login_as( 'editor' );
		$this->respond( '/categories', $this->fixture( 'categories' ) );

		$data = $this->dispatch( 'GET', '/categories' )->get_data();

		$this->assertCount( 2, $data );
		$this->assertSame(
			array(
				'slug'  => 'nas',
				'name'  => 'NAS',
				'count' => 120,
			),
			$data[0]
		);
	}

	public function test_connection_requires_manage_options() {
		$this->login_as( 'editor' );

		$this->assertSame( 403, $this->dispatch( 'POST', '/connection' )->get_status() );
		$this->assertSame( 403, $this->dispatch( 'POST', '/cache/flush' )->get_status() );
	}

	public function test_connection_reports_the_plan_and_masks_the_key() {
		$this->login_as( 'administrator' );
		$this->respond( '/me', $this->fixture( 'me' ) );

		$response = $this->dispatch( 'POST', '/connection' );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['ok'] );
		$this->assertSame( 'pro', $data['tier'] );
		$this->assertSame( 'oha_test_abc****', $data['key'] );
		$this->assertStringContainsString( 'Own affiliate links, Price history', $data['message'] );
		$this->assertStringContainsString( 'Pro', $data['message'] );
		$this->assertTrue( $data['capabilities']['ownLinks'], 'The API\'s capability names are kept as they are.' );
		$this->assertSame( 'Pro', $data['summary']['plan'] );
		$this->assertSame( array( true, true, false ), wp_list_pluck( $data['summary']['capabilities'], 'enabled' ) );
		$this->assertSame( '60 requests per minute, 10,000 per day', $data['summary']['rateLimit'] );
		$this->assertNotSame( '', $data['summary']['checkedAt'] );
		$this->assertSame( '', $data['summary']['pricesCheckedAt'] );
		$this->assertStringNotContainsString( self::KEY, wp_json_encode( $data ) );
	}

	public function test_connection_bypasses_the_cache() {
		$this->login_as( 'administrator' );
		$this->respond( '/me', $this->fixture( 'me' ) );

		$this->dispatch( 'POST', '/connection' );
		$this->dispatch( 'POST', '/connection' );

		$this->assertCount( 2, $this->requests_to( '/me' ) );
	}

	public function test_cache_flush_bumps_the_version() {
		$this->login_as( 'administrator' );
		$version = Cache::version();

		$response = $this->dispatch( 'POST', '/cache/flush' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $version + 1, Cache::version() );
	}
}
