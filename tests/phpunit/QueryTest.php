<?php
/**
 * Tests for the developer data API.
 *
 * @package OhaDeals
 */

namespace OhaDeals\Tests;

use OhaDeals\Model\Offer;
use OhaDeals\Model\Product;

/**
 * Tests for oha_deals_query() and the to_array() methods.
 *
 * @covers \OhaDeals\Api\Query
 * @covers \OhaDeals\Model\Product
 * @covers \OhaDeals\Model\Offer
 * @covers \OhaDeals\Model\Deal
 * @covers \OhaDeals\Model\Coupon
 */
class QueryTest extends TestCase {

	/**
	 * Every key of a nested array.
	 *
	 * @param array $data Data.
	 * @return string[]
	 */
	private function keys( array $data ) {
		$keys = array();
		foreach ( $data as $key => $value ) {
			if ( is_string( $key ) ) {
				$keys[] = $key;
			}
			if ( is_array( $value ) && 'attrs' !== $key ) {
				$keys = array_merge( $keys, $this->keys( $value ) );
			}
		}

		return array_unique( $keys );
	}

	public function test_product_shape() {
		$this->respond( '/products/synology-ds925-plus', $this->fixture( 'product' ) );

		$product = oha_deals_query( 'product', array( 'slug' => 'synology-ds925-plus' ) );

		$this->assertSame(
			array( 'id', 'slug', 'name', 'brand', 'category', 'ean', 'url', 'image', 'images', 'spec', 'attrs', 'price', 'price_hidden', 'price_at', 'currency', 'shop', 'offer_count', 'before', 'discount_percent', 'change', 'msrp', 'low30', 'all_time_low', 'offers', 'used_offers', 'history', 'prices_checked_at', 'omitted', 'link', 'link_is_own' ),
			array_keys( $product )
		);
		$this->assertSame(
			array( 'shop', 'price', 'price_hidden', 'price_at', 'shipping', 'total', 'currency', 'availability', 'condition', 'is_amazon', 'network', 'asin', 'awin_merchant_id', 'awin_product_id', 'merchant_url', 'link', 'link_is_own' ),
			array_keys( $product['offers'][0] )
		);
		$this->assertSame( 944.99, $product['price'] );
		$this->assertSame( array( 'Art' => 'NAS' ), $product['attrs'] );
		$this->assertSame( 'https://oha.deals/produkt/synology-ds925-plus#angebote', $product['link'] );
		$this->assertFalse( $product['link_is_own'] );
		foreach ( $this->keys( $product ) as $key ) {
			$this->assertMatchesRegularExpression( '/^[a-z0-9_]+$/', $key );
		}
		$this->assertStringNotContainsString( '/go/', wp_json_encode( $product, JSON_UNESCAPED_SLASHES ), 'Redirects are left out.' );
	}

	public function test_hidden_prices_stay_hidden() {
		$data  = $this->fixture( 'product' );
		$offer = $data['data']['offers'][2];

		$offer['price'] = 10.0;
		$array          = Offer::from_array( $offer )->to_array();

		$this->assertNull( $array['price'] );
		$this->assertTrue( $array['price_hidden'] );
	}

	public function test_to_array_survives_empty_input() {
		$array = Product::from_array( array() )->to_array();

		$this->assertSame( '', $array['slug'] );
		$this->assertSame( array(), $array['offers'] );
		$this->assertNotFalse( wp_json_encode( $array ) );
	}

	public function test_malformed_specifications_are_dropped() {
		$product = Product::from_array(
			array(
				'attrs' => array(
					'Typ'   => ' SSD ',
					'Leer'  => ' ',
					'Liste' => array( 'x' ),
					'Ja'    => true,
					'Zahl'  => 4,
				),
			)
		);

		$this->assertSame(
			array(
				'Typ'  => 'SSD',
				'Zahl' => '4',
			),
			$product->attrs()
		);
	}

	public function test_product_by_ean_with_history() {
		$product = $this->fixture( 'product' );
		$this->respond( '/products/lookup', array( 'data' => $product['data'] ) );
		$this->respond( '/products/synology-ds925-plus', $product );

		$result = oha_deals_query(
			'product',
			array(
				'ean'     => '4711174725762',
				'include' => array( 'history' ),
				'days'    => 30,
			)
		);

		$this->assertCount( 3, $result['history'] );
		$query = $this->requests_to( '/products/synology-ds925-plus' )[0]['query'];
		$this->assertSame( 'history', $query['include'] );
		$this->assertSame( '30', $query['days'] );
	}

	public function test_products_search_and_categories() {
		$this->respond( '/products/synology-ds925-plus', $this->fixture( 'product' ) );
		$this->respond( '/products/echo-dot-5', $this->fixture( 'product-amazon' ) );
		$this->respond( '/products/search', $this->fixture( 'search' ) );
		$this->respond( '/categories', $this->fixture( 'categories' ) );

		$products = oha_deals_query( 'products', array( 'slugs' => 'echo-dot-5,synology-ds925-plus' ) );
		$this->assertSame( array( 'echo-dot-5', 'synology-ds925-plus' ), wp_list_pluck( $products, 'slug' ) );
		$this->assertCount( 2, oha_deals_query( 'products', array( 'slugs' => array( 'echo-dot-5', 'synology-ds925-plus', 'missing' ) ) ) );
		$this->assertSame( array(), oha_deals_query( 'products' ) );

		$this->assertCount(
			2,
			oha_deals_query(
				'search',
				array(
					'q'     => 'synology',
					'limit' => 5,
				)
			)
		);
		$this->assertSame( '5', $this->requests_to( '/products/search' )[0]['query']['limit'] );
		$this->assertSame( 'nas', oha_deals_query( 'categories' )[0]['slug'] );
	}

	public function test_deals_and_coupons() {
		$this->respond( '/deals', $this->fixture( 'deals' ) );
		$this->respond( '/coupons', $this->fixture( 'coupons' ) );

		$deals = oha_deals_query( 'deals', array( 'limit' => 2 ) );
		$this->assertSame( array( 'kind', 'is_all_time_low', 'last_seen', 'product' ), array_keys( $deals[0] ) );
		$this->assertStringStartsWith( 'https://oha.deals/produkt/', $deals[0]['product']['link'] );

		$coupons = oha_deals_query( 'coupons' );
		$this->assertSame( array( 'id', 'shop', 'host', 'title', 'description', 'code', 'code_hidden', 'url', 'valid_until', 'link', 'link_is_own' ), array_keys( $coupons[0] ) );
		$this->assertSame( 'https://oha.deals/gutscheine', $coupons[0]['link'] );
		$this->assertNull( $coupons[1]['code'], 'Hidden codes stay hidden.' );
		$this->assertSame( array( '101', 'c2' ), wp_list_pluck( $coupons, 'id' ), 'The API sends numeric coupon ids; here they are text.' );
	}

	public function test_errors_are_wp_errors() {
		$this->assertWPError( oha_deals_query( 'nonsense' ) );
		$this->assertWPError( oha_deals_query( 'product' ) );
		$this->assertWPError( oha_deals_query( 'product', array( 'slug' => 'missing' ) ) );
		$this->assertWPError( oha_deals_query( 'coupons', array( 'host' => 'not a host' ) ) );
	}

	public function test_no_public_data_route_exists() {
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		do_action( 'rest_api_init', $wp_rest_server );
		wp_set_current_user( 0 );

		foreach ( $wp_rest_server->get_routes( 'oha-deals/v1' ) as $route => $handlers ) {
			if ( '/oha-deals/v1' === $route ) {
				continue;
			}
			foreach ( $handlers as $handler ) {
				$this->assertFalse( call_user_func( $handler['permission_callback'] ), $route );
			}
		}
		$wp_rest_server = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}
}
