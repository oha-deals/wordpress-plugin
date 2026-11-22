<?php
/**
 * Rendering tests for every template, through the shortcodes and oha_deals_render().
 *
 * @package OhaDeals
 */

namespace OhaDeals\Tests;

use OhaDeals\Plugin;
use OhaDeals\Render\Elements;
use OhaDeals\Settings\Options;

/**
 * Tests for Templates.
 *
 * @covers \OhaDeals\Render\Elements
 * @covers \OhaDeals\Render\Helpers
 * @covers \OhaDeals\Render\Renderer
 * @covers \OhaDeals\Render\TemplateLoader
 */
class TemplatesTest extends TestCase {

	/**
	 * Visitors are logged out unless a test logs in.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( 0 );
		update_option( 'date_format', 'Y-m-d' );
		update_option( 'time_format', 'H:i' );
		update_option( 'timezone_string', 'UTC' );
	}

	/**
	 * Mocks the product detail endpoint with a (possibly modified) fixture.
	 *
	 * @param callable|null $modify Receives and returns the fixture array.
	 * @param string        $name   Fixture name.
	 * @return void
	 */
	private function mock_product( $modify = null, $name = 'product' ) {
		$data = $this->fixture( $name );
		if ( $modify ) {
			$data = $modify( $data );
		}
		$this->respond( '/products/' . $data['data']['slug'], $data );
	}

	/**
	 * Updates settings on top of the test defaults.
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	private function settings( array $settings ) {
		update_option( Options::OPTION, array_merge( Options::defaults(), array( 'api_key' => self::KEY ), $settings ) );
	}

	public function test_product_card_renders_escaped_data() {
		$this->mock_product(
			static function ( $data ) {
				$data['data']['name'] = 'Evil <script>alert("x")</script> "NAS"';
				return $data;
			}
		);

		$html = do_shortcode( '[oha_deals_product slug="synology-ds925-plus"]' );

		$this->assertStringContainsString( 'class="oha-deals oha-deals-product oha-deals-product--card"', $html );
		$this->assertStringNotContainsString( '<script>alert', $html );
		$this->assertStringContainsString( 'Evil &lt;script&gt;', $html );
		$this->assertStringNotContainsString( self::KEY, $html );
		$this->assertTrue( wp_style_is( 'oha-deals-frontend', 'enqueued' ) );
	}

	public function test_before_price_only_when_the_api_sends_it() {
		$this->mock_product();
		$with = do_shortcode( '[oha_deals_product slug="synology-ds925-plus"]' );
		$this->assertStringContainsString( '<s>', $with );
		$this->assertStringContainsString( 'oha-deals-badge--discount', $with );

		$this->routes = array();
		Plugin::instance()->cache()->flush();
		$this->mock_product(
			static function ( $data ) {
				$data['data']['before'] = null;
				return $data;
			}
		);
		$without = do_shortcode( '[oha_deals_product slug="synology-ds925-plus"]' );
		$this->assertStringNotContainsString( '<s>', $without );
		$this->assertStringNotContainsString( 'oha-deals-badge--discount', $without );
	}

	public function test_offer_list_rules() {
		$this->mock_product();

		$html = do_shortcode( '[oha_deals_product slug="synology-ds925-plus" offers_limit="4"]' );

		$this->assertStringContainsString( 'Free shipping', $html );
		$this->assertStringContainsString( 'Shipping unknown', $html, 'shipping: null is never printed as free.' );
		$this->assertStringContainsString( 'Compare on oha.deals', $html, 'Without own IDs, offers link to oha.deals.' );
		$this->assertSame( 4, substr_count( $html, 'oha-deals-offer-list__item' ) );
	}

	public function test_links_follow_the_settings() {
		$this->mock_product();

		$html = do_shortcode( '[oha_deals_product slug="synology-ds925-plus"]' );
		$this->assertStringContainsString( 'rel="nofollow noopener"', $html );
		$this->assertStringContainsString( 'target="_blank"', $html );
		$this->assertStringContainsString( '(opens in a new tab)', $html );

		$this->settings(
			array(
				'link_rel_sponsored' => false,
				'open_new_tab'       => false,
			)
		);
		$html = do_shortcode( '[oha_deals_product slug="synology-ds925-plus"]' );
		$this->assertStringNotContainsString( 'rel="', $html );
		$this->assertStringNotContainsString( 'target="_blank"', $html );
	}

	public function test_own_affiliate_ids_are_used() {
		$this->settings(
			array(
				'amazon_tag'        => 'myblog-21',
				'awin_publisher_id' => '555',
			)
		);
		$this->mock_product();

		$html = do_shortcode( '[oha_deals_product slug="synology-ds925-plus" offers_limit="4"]' );

		$this->assertStringContainsString( esc_url( 'https://www.awin1.com/pclick.php?p=38512345&a=555&m=11731' ), $html );
		$this->assertStringContainsString( esc_url( 'https://www.amazon.de/dp/B0DXYZ1234?tag=myblog-21' ), $html );
		$this->assertStringContainsString( 'As an Amazon Associate', $html );
	}

	public function test_only_https_urls_are_printed() {
		$this->mock_product(
			static function ( $data ) {
				$data['data']['image']                 = 'http://insecure.example/i.jpg';
				$data['data']['images']                = array();
				$data['data']['offers'][0]['url']      = 'javascript:alert(1)';
				$data['data']['offers'][0]['merchant'] = null;
				return $data;
			}
		);

		$html = do_shortcode( '[oha_deals_product slug="synology-ds925-plus"]' );

		$this->assertStringNotContainsString( 'javascript:', $html );
		$this->assertStringNotContainsString( 'http://insecure.example', $html );
		$this->assertStringNotContainsString( '<img', $html );
	}

	public function test_images_are_lazy_and_sized() {
		$this->mock_product();

		$html = do_shortcode( '[oha_deals_product slug="synology-ds925-plus"]' );

		$this->assertMatchesRegularExpression( '/<img class="oha-deals-image" src="https:[^"]+" alt="Synology DS925\+" width="160" height="160" loading="lazy" decoding="async" \/>/', $html );
	}

	public function test_amazon_price_shows_timestamp_and_disclaimer() {
		$this->mock_product( null, 'product-amazon' );

		$html = do_shortcode( '[oha_deals_product slug="echo-dot-5"]' );

		$this->assertStringContainsString( 'Price as of 2026-11-20, 07:30', $html );
		$this->assertStringContainsString( 'Price as of 2026-11-20<', $html, 'A bare-date priceAt prints the date only.' );
		$this->assertStringContainsString( 'oha-deals-note--amazon', $html );
	}

	/**
	 * Puts the main query on a single post and enters the loop.
	 *
	 * @return void
	 */
	private function enter_main_loop() {
		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );
		the_post();
	}

	public function test_structured_data_is_opt_in_and_skips_amazon_prices() {
		$this->mock_product();
		$this->enter_main_loop();
		$this->assertStringNotContainsString( 'application/ld+json', do_shortcode( '[oha_deals_product slug="synology-ds925-plus"]' ) );

		$this->settings( array( 'structured_data' => true ) );
		$html = do_shortcode( '[oha_deals_product slug="synology-ds925-plus"]' );
		$this->assertStringContainsString( '<script type="application/ld+json">', $html );
		$this->assertStringContainsString( '"lowPrice":944.99', $html, 'The hidden Amazon offer is not part of the range.' );
		$this->assertStringContainsString( '"offerCount":3', $html );
		$this->assertStringNotContainsString( 'application/ld+json', do_shortcode( '[oha_deals_product slug="synology-ds925-plus"]' ), 'Once per product.' );
	}

	public function test_structured_data_is_not_used_up_outside_the_main_loop() {
		$this->settings( array( 'structured_data' => true ) );
		$this->mock_product();

		$outside = do_shortcode( '[oha_deals_product slug="synology-ds925-plus"]' );
		$this->enter_main_loop();
		$inside = do_shortcode( '[oha_deals_product slug="synology-ds925-plus"]' );

		$this->assertStringNotContainsString( 'application/ld+json', $outside, 'Renders outside the main loop (excerpts, previews) print none.' );
		$this->assertStringContainsString( 'application/ld+json', $inside, 'They do not use up the once-per-product slot.' );
	}

	public function test_amazon_offer_without_timestamp_is_still_treated_as_amazon() {
		$this->mock_product(
			static function ( $data ) {
				$data['data']['priceAt']               = null;
				$data['data']['offers'][0]['priceAt']  = null;
				$data['data']['offers'][0]['merchant'] = array( 'network' => 'amazon' );
				return $data;
			},
			'product-amazon'
		);

		$html = do_shortcode( '[oha_deals_product slug="echo-dot-5"]' );

		$this->assertStringContainsString( 'Price as of 2026-11-20, 07:30', $html, 'Falls back to pricesCheckedAt.' );
		$this->assertStringContainsString( 'oha-deals-note--amazon', $html );
	}

	public function test_images_can_be_turned_off() {
		$this->settings( array( 'show_images' => false ) );
		$this->mock_product();
		$this->respond( '/deals', $this->fixture( 'deals' ) );

		$this->assertStringNotContainsString( '<img', do_shortcode( '[oha_deals_product slug="synology-ds925-plus"]' ) );
		$this->assertStringNotContainsString( '<img', do_shortcode( '[oha_deals_list]' ) );
	}

	public function test_hostile_strings_are_escaped() {
		$evil = '"><script>alert(1)</script><img src=x onerror=alert(2)>';
		$this->mock_product(
			static function ( $data ) use ( $evil ) {
				$data['data']['name']                      = 'Synology ' . $evil;
				$data['data']['currency']                  = $evil;
				$data['data']['offers'][0]['shop']['name'] = $evil;
				$data['data']['offers'][0]['currency']     = $evil;
				$data['data']['offers'][1]['availability'] = $evil;
				return $data;
			}
		);
		$coupons                           = $this->fixture( 'coupons' );
		$coupons['data'][0]['shop']        = $evil;
		$coupons['data'][0]['title']       = $evil;
		$coupons['data'][0]['description'] = $evil;
		$coupons['data'][0]['code']        = $evil;
		$coupons['data'][0]['validUntil']  = null;
		$this->respond( '/coupons', $coupons );

		$html = do_shortcode( '[oha_deals_product slug="synology-ds925-plus" offers_limit="4"]' )
			. do_shortcode( '[oha_deals_offers slug="synology-ds925-plus"]' )
			. do_shortcode( '[oha_deals_coupons]' );

		$this->assertStringNotContainsString( '<script>alert(1)', $html );
		$this->assertStringNotContainsString( '<img src=x', $html );
		$this->assertStringNotContainsString( 'onerror=alert(2)>', $html );
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $html );
		$this->assertMatchesRegularExpression( '/alt="Synology &quot;&gt;&lt;script&gt;/', $html, 'The image alt is escaped.' );
	}

	public function test_coupons_with_an_invalid_host_show_a_notice_instead_of_all_shops() {
		$this->respond( '/coupons', $this->fixture( 'coupons' ) );

		$this->assertSame( '', do_shortcode( '[oha_deals_coupons host="not a host"]' ) );
		$this->login_as( 'editor' );
		$this->assertStringContainsString( 'oha-deals-notice', do_shortcode( '[oha_deals_coupons host="not a host"]' ) );
		$this->assertCount( 0, $this->requests_to( '/coupons' ) );
	}

	public function test_expired_coupons_are_hidden() {
		$coupons                          = $this->fixture( 'coupons' );
		$coupons['data'][0]['validUntil'] = '2020-01-01';
		$this->respond( '/coupons', $coupons );

		$html = do_shortcode( '[oha_deals_coupons]' );

		$this->assertStringNotContainsString( 'ALT10', $html );
		$this->assertStringContainsString( 'Exclusive voucher', $html );
	}

	public function test_errors_are_hidden_from_visitors_and_shown_to_editors() {
		$this->respond_error( '/products/missing', 404, 'not_found' );

		$this->assertSame( '', do_shortcode( '[oha_deals_product slug="missing"]' ) );

		$this->login_as( 'editor' );
		$html = do_shortcode( '[oha_deals_product slug="missing"]' );
		$this->assertStringContainsString( 'oha-deals-notice', $html );
		$this->assertStringContainsString( 'not listed', $html );
	}

	public function test_missing_key_renders_nothing_for_visitors() {
		$this->settings( array( 'api_key' => '' ) );

		$this->assertSame( '', do_shortcode( '[oha_deals_product slug="synology-ds925-plus"]' ) );
		$this->assertCount( 0, $this->requests );
	}

	public function test_inline_layout_and_price_shortcode() {
		$this->mock_product();

		$inline = do_shortcode( '[oha_deals_product slug="synology-ds925-plus" layout="inline"]' );
		$this->assertStringStartsWith( '<span class="oha-deals oha-deals-product oha-deals-product--inline"', $inline );
		$this->assertStringContainsString( 'at Alternate DE', $inline );

		$price = do_shortcode( '[oha_deals_price slug="synology-ds925-plus"]' );
		$this->assertStringStartsWith( '<span class="oha-deals oha-deals-price">', $price );
		$this->assertStringContainsString( '944.99', $price );
		$this->assertStringNotContainsString( '<div', $price );
		$this->assertStringNotContainsString( '<p', $price );
	}

	public function test_ean_is_resolved_through_lookup() {
		$product = $this->fixture( 'product' );
		$this->respond( '/products/lookup', array( 'data' => $product['data'] ) );
		$this->mock_product();

		$html = oha_deals_render( 'product', array( 'ean' => '4711174725762' ) );

		$this->assertStringContainsString( 'Synology DS925+', $html );
		$this->assertSame( '4711174725762', $this->requests_to( '/products/lookup' )[0]['query']['ean'] );
	}

	public function test_offers_table_is_an_accessible_table() {
		$this->mock_product();

		$html = do_shortcode( '[oha_deals_offers slug="synology-ds925-plus" include_used="true" show_title="false"]' );

		$this->assertStringContainsString( '<table class="oha-deals-table oha-deals-offers-table" role="table">', $html );
		$this->assertStringContainsString( '<caption class="oha-deals-visually-hidden">', $html );
		$this->assertSame( 3, substr_count( $html, '<th scope="col" role="columnheader"' ) );
		$this->assertSame( 5, substr_count( $html, '<th scope="row" role="rowheader"' ) );
		$this->assertSame( 6, substr_count( $html, '<tr role="row"' ), 'Rows keep their role when CSS lays them out as grids.' );
		$this->assertStringContainsString( 'Used', $html );

		$limited = do_shortcode( '[oha_deals_offers slug="synology-ds925-plus" include_used="true" limit="1"]' );
		$this->assertSame( 2, substr_count( $limited, '<th scope="row"' ), 'The limit applies to new offers; used ones are appended.' );
		$this->assertStringContainsString( 'Shipping unknown', $html );
	}

	public function test_offers_table_without_shipping_column() {
		$this->mock_product();

		$html = do_shortcode( '[oha_deals_offers slug="synology-ds925-plus" show_shipping="false" limit="1"]' );

		$this->assertStringNotContainsString( 'shipping', strtolower( wp_strip_all_tags( $html ) ) );
		$this->assertSame( 1, substr_count( $html, '<th scope="row"' ) );
	}

	public function test_best_offer_is_marked_in_text_once_and_never_on_used_goods() {
		$this->mock_product();

		$html = do_shortcode( '[oha_deals_offers slug="synology-ds925-plus" include_used="true"]' );

		$this->assertSame( 1, substr_count( $html, 'oha-deals-offers-table__row--best' ) );
		$this->assertSame( 1, substr_count( $html, 'oha-deals-badge--best' ) );
		$this->assertMatchesRegularExpression(
			'/row--best">\s*<th[^>]*>\s*<span class="oha-deals-offers-table__name">Alternate DE<\/span>\s*<span class="oha-deals-badge oha-deals-badge--best">Best price<\/span>/',
			$html,
			'The label is text, not only a colour; the cheaper used offer is not the best price.'
		);
	}

	public function test_no_best_offer_without_a_visible_price() {
		$this->mock_product(
			static function ( $data ) {
				foreach ( $data['data']['offers'] as $i => $offer ) {
					$data['data']['offers'][ $i ]['price']       = null;
					$data['data']['offers'][ $i ]['priceHidden'] = true;
				}
				return $data;
			}
		);

		$html = do_shortcode( '[oha_deals_offers slug="synology-ds925-plus"]' );

		$this->assertStringNotContainsString( '--best', $html );
		$this->assertStringContainsString( 'Price at the shop', $html );
		$this->assertStringNotContainsString( 'oha-deals-amount', $html );
	}

	public function test_offer_availability_is_text() {
		$this->mock_product();

		$html = do_shortcode( '[oha_deals_offers slug="synology-ds925-plus"]' );

		$this->assertStringContainsString( '<span class="oha-deals-stock oha-deals-stock--in_stock">In stock</span>', $html );
		$this->assertStringContainsString( '<span class="oha-deals-stock oha-deals-stock--long">Longer delivery time</span>', $html );
	}

	public function test_product_card_names_the_brand_only_when_the_title_does_not() {
		$this->mock_product();
		$this->assertStringNotContainsString( 'oha-deals-product__brand', do_shortcode( '[oha_deals_product slug="synology-ds925-plus"]' ), 'The title starts with the brand.' );

		$this->routes = array();
		Plugin::instance()->cache()->flush();
		$this->mock_product(
			static function ( $data ) {
				$data['data']['name'] = 'DS925+';
				return $data;
			}
		);
		$this->assertStringContainsString( '<p class="oha-deals-product__brand">Synology</p>', do_shortcode( '[oha_deals_product slug="synology-ds925-plus"]' ) );
	}

	public function test_product_card_without_image_uses_the_whole_width() {
		$this->mock_product();

		$html = do_shortcode( '[oha_deals_product slug="synology-ds925-plus" show_image="false"]' );

		$this->assertStringContainsString( 'oha-deals-product__body--no-media', $html );
		$this->assertStringNotContainsString( 'oha-deals-product__media', $html );
	}

	public function test_deals_grid() {
		$this->respond( '/deals', $this->fixture( 'deals' ) );

		$html = do_shortcode( '[oha_deals_list mode="drop" limit="2" columns="2" min_price="10"]' );

		$this->assertStringContainsString( '--oha-deals-columns: 2', $html );
		$this->assertStringContainsString( 'Lowest price ever', $html );
		$this->assertStringContainsString( "\u{2212}17%", $html );
		$this->assertSame( 1, substr_count( $html, 'oha-deals-grid__item' ), 'A row without a price is never shown as a price-less card.' );
		$this->assertStringNotContainsString( 'Kindle', $html );

		$query = $this->requests_to( '/deals' )[0]['query'];
		$this->assertSame( 'drop', $query['mode'] );
		$this->assertSame( '1', $query['priced'] );
		$this->assertSame( '10.00', $query['minPrice'] );
		$this->assertArrayNotHasKey( 'maxPrice', $query );
	}

	public function test_deals_request_hygiene() {
		$this->respond( '/deals', $this->fixture( 'deals' ) );

		do_shortcode( '[oha_deals_list min_price="500" max_price="100" category="Bad Category!"]' );
		do_shortcode( '[oha_deals_list min_price="12.345" max_price="99999999"]' );

		$first = $this->requests_to( '/deals' )[0]['query'];
		$this->assertArrayNotHasKey( 'minPrice', $first, 'An inverted range is dropped.' );
		$this->assertArrayNotHasKey( 'maxPrice', $first );
		$this->assertArrayNotHasKey( 'category', $first, 'A category that is not a slug is omitted.' );

		$second = $this->requests_to( '/deals' )[1]['query'];
		$this->assertSame( '12.35', $second['minPrice'] );
		$this->assertSame( '9999999.00', $second['maxPrice'] );
	}

	public function test_coupons_list_hides_hidden_codes_and_unsafe_links() {
		$this->respond( '/coupons', $this->fixture( 'coupons' ) );

		$html = do_shortcode( '[oha_deals_coupons host="https://www.alternate.de/" limit="2"]' );

		$this->assertStringContainsString( '<code class="oha-deals-coupon__code">ALT10</code>', $html );
		$this->assertSame( 1, substr_count( $html, 'class="oha-deals-coupon__copy"' ), 'Only a visible code has a copy button.' );
		$this->assertStringNotContainsString( 'SECRET', $html );
		$this->assertStringContainsString( 'The code is shown at the shop.', $html );
		$this->assertStringNotContainsString( 'javascript:', $html );
		$this->assertSame( 'alternate.de', $this->requests_to( '/coupons' )[0]['query']['host'] );
	}

	public function test_coupon_copy_button_is_a_progressive_enhancement() {
		$this->respond( '/coupons', $this->fixture( 'coupons' ) );

		$html = do_shortcode( '[oha_deals_coupons]' );

		$this->assertMatchesRegularExpression( '/<button type="button" class="oha-deals-coupon__copy" data-wp-on--click="actions\.copy">/', $html );
		$this->assertStringContainsString( 'Copy code ALT10', $html, 'The button names the code.' );
		$this->assertStringContainsString( 'data-wp-interactive="oha-deals/coupon"', $html );
		$this->assertStringNotContainsString( 'is-ready', str_replace( 'data-wp-class--is-ready', '', $html ), 'The button is invisible until the module runs.' );
		$this->assertStringContainsString( 'role="status" data-wp-text="context.status"></span>', $html, 'The live region starts empty.' );

		$context = $this->context( $html, 'oha-deals/coupon' );
		$this->assertFalse( $context['ready'] );
		$this->assertSame( '', $context['status'] );
		$this->assertNotSame( '', $context['done'] );
	}

	public function test_coupon_context_survives_hostile_translations() {
		$this->respond( '/coupons', $this->fixture( 'coupons' ) );
		add_filter(
			'gettext_oha-deals',
			static function ( $translation, $text ) {
				return 'Copied' === $text ? "'\"><script>alert(1)</script>&amp;" : $translation;
			},
			10,
			2
		);

		$html = do_shortcode( '[oha_deals_coupons]' );

		$this->assertStringNotContainsString( '<script>alert', $html );
		$this->assertSame( "'\"><script>alert(1)</script>&amp;", $this->context( $html, 'oha-deals/coupon' )['done'] );
	}

	/**
	 * The decoded Interactivity API context of the first element with a namespace.
	 *
	 * @param string $html      Markup.
	 * @param string $store     Store namespace.
	 * @return array
	 */
	private function context( $html, $store ) {
		$tags = new \WP_HTML_Tag_Processor( $html );
		while ( $tags->next_tag() ) {
			if ( $store === $tags->get_attribute( 'data-wp-interactive' ) ) {
				return json_decode( (string) $tags->get_attribute( 'data-wp-context' ), true );
			}
		}
		$this->fail( 'No element with the namespace ' . $store );
	}

	public function test_price_history_renders_the_chart() {
		$this->mock_product();

		$html = do_shortcode( '[oha_deals_history slug="synology-ds925-plus" days="30" height="200"]' );

		$this->assertStringContainsString( '<svg', $html );
		$this->assertStringContainsString( 'Last 30 days', $html );
		$this->assertStringContainsString( 'Lowest price so far: €929.00 on 2026-10-02', $html );
		$query = $this->requests_to( '/products/synology-ds925-plus' )[0]['query'];
		$this->assertSame( 'history', $query['include'] );
		$this->assertSame( '30', $query['days'] );
	}

	public function test_price_history_without_capability_is_editor_only_hint() {
		$this->mock_product( null, 'product-amazon' );

		$this->assertSame( '', do_shortcode( '[oha_deals_history slug="echo-dot-5"]' ) );

		$this->login_as( 'editor' );
		$this->assertStringContainsString( 'history capability', do_shortcode( '[oha_deals_history slug="echo-dot-5"]' ) );
	}

	/**
	 * Mocks two products for a comparison: `cheap` and `dear`, the second one with the given changes.
	 *
	 * @param array $changes Data of the second product.
	 * @return void
	 */
	private function mock_comparison( array $changes = array() ) {
		$cheap = $this->fixture( 'product' );
		$dear  = $cheap;

		$cheap['data']['slug']  = 'cheap';
		$cheap['data']['name']  = 'Cheap NAS';
		$cheap['data']['attrs'] = array( 'Bays' => '2' );
		$dear['data']           = array_merge(
			$dear['data'],
			array(
				'slug'       => 'dear',
				'name'       => 'Dear NAS',
				'price'      => 1200.0,
				'before'     => null,
				'allTimeLow' => null,
				'attrs'      => array( 'Bays' => '4' ),
			),
			$changes
		);
		$this->respond( '/products/cheap', $cheap );
		$this->respond( '/products/dear', $dear );
	}

	public function test_comparison_columns_are_an_accessible_table() {
		$this->mock_comparison();

		$html = do_shortcode( '[oha_deals_comparison slugs="cheap,dear" title="Two NAS & more" fields="image,price,before,discount,shop,lowest,specs"]' );

		$this->assertStringContainsString( 'Two NAS &amp; more', $html );
		$this->assertSame( 1, preg_match( '/role="region" aria-labelledby="(oha-deals-comparison-\d+)" tabindex="0"/', $html, $match ), 'The scroll area is named and focusable.' );
		$this->assertStringContainsString( '<caption id="' . $match[1] . '"', $html );
		$this->assertSame( 2, substr_count( $html, '<th scope="col"' ) );
		foreach ( array( 'Best price', 'Shop', 'Lowest price so far', 'Bays' ) as $label ) {
			$this->assertStringContainsString( 'class="oha-deals-comparison__attribute">' . $label . '</th>', $html );
		}
		$this->assertStringNotContainsString( '__attribute">30-day low', $html, 'Printed with the price.' );
		$this->assertStringContainsString( '<s>€989.00</s>', $html );
		$this->assertSame( 1, substr_count( $html, 'oha-deals-badge--best">Best price' ), 'The best price is marked in text, once.' );
		$this->assertStringContainsString( 'not available', $html, 'Empty cells say so.' );
	}

	public function test_comparison_rows_have_labels_numbers_and_one_best_row() {
		$this->mock_comparison();

		$html = do_shortcode( '[oha_deals_comparison slugs="cheap,dear" orientation="rows" numbered="true" fields="price,shop,specs"]' );

		$this->assertSame( 2, substr_count( $html, '<th scope="row"' ) );
		$this->assertStringContainsString( '<span class="oha-deals-comparison__number">2</span>', $html );
		$this->assertSame( 1, substr_count( $html, 'oha-deals-comparison__row--best' ) );
		$this->assertStringContainsString( '<span class="oha-deals-comparison__label" aria-hidden="true">Shop</span>', $html, 'Cards repeat the column label.' );
		$this->assertStringContainsString( '<span class="oha-deals-comparison__spec">Bays: 2</span>', $html );
		$this->assertStringNotContainsString( '__label" aria-hidden="true">Bays', $html, 'Specifications are no columns in a list.' );
	}

	public function test_comparison_follows_the_display_rules() {
		$this->mock_comparison(
			array(
				'price'       => null,
				'priceHidden' => true,
			)
		);

		$html = do_shortcode( '[oha_deals_comparison slugs="cheap,dear" button_text="<b>Go</b>"]' );

		$this->assertStringContainsString( 'Price at the shop', $html );
		$this->assertStringNotContainsString( '--best', $html, 'One visible price is nothing to compare.' );
		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertStringContainsString( ': Dear NAS', $html, 'The links are told apart.' );
	}

	public function test_comparison_prints_the_amazon_timestamp() {
		$this->respond( '/products/echo-dot-5', $this->fixture( 'product-amazon' ) );

		$html = do_shortcode( '[oha_deals_comparison slugs="echo-dot-5"]' );

		$this->assertStringContainsString( 'Price as of 2026-11-20', $html );
		$this->assertStringContainsString( 'oha-deals-note--amazon', $html );
	}

	public function test_the_copy_module_is_enqueued_for_theme_overrides_too() {
		global $wp_script_modules;
		$real              = wp_script_modules();
		$wp_script_modules = new \WP_Script_Modules(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Printed modules are not printed again.
		wp_register_script_module( Elements::COUPONS_MODULE, 'https://example.org/copy.js' );
		$this->respond( '/coupons', $this->fixture( 'coupons' ) );
		add_filter( 'oha_deals_template_path', '__return_empty_string' );

		do_shortcode( '[oha_deals_coupons]' );
		ob_start();
		wp_script_modules()->print_enqueued_script_modules();
		$printed           = (string) ob_get_clean();
		$wp_script_modules = $real; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->assertStringContainsString( 'https://example.org/copy.js', $printed );
		$this->assertSame( Elements::COUPONS_MODULE, generate_block_asset_handle( 'oha-deals/coupons', 'viewScriptModule' ) );
	}

	public function test_offers_without_a_shop_name_render_without_warnings() {
		$this->mock_product(
			static function ( $data ) {
				$data['data']['offers'][0]['shop'] = null;
				return $data;
			}
		);

		$html = do_shortcode( '[oha_deals_product slug="synology-ds925-plus"]' ) . do_shortcode( '[oha_deals_offers slug="synology-ds925-plus"]' );

		$this->assertStringNotContainsString( '>: </span>', $html );
	}

	public function test_impossible_history_points_are_dropped() {
		$this->mock_product(
			static function ( $data ) {
				$data['data']['history'][] = array(
					'date'  => '2026-11-20',
					'price' => -5,
					'avg'   => null,
				);
				$data['data']['history'][] = array(
					'date'  => 'yesterday',
					'price' => 5,
				);
				$data['data']['history'][] = null;
				return $data;
			}
		);

		$html = do_shortcode( '[oha_deals_history slug="synology-ds925-plus" days="365"]' );

		$this->assertStringContainsString( '<svg', $html );
		$this->assertStringNotContainsString( 'NAN', $html );
		$this->assertStringNotContainsString( '-€5', $html );
	}

	public function test_attribution_is_opt_in() {
		$this->mock_product();
		$this->assertStringNotContainsString( 'Prices by', do_shortcode( '[oha_deals_product slug="synology-ds925-plus"]' ) );

		$this->settings( array( 'show_attribution' => true ) );
		$this->assertStringContainsString( 'Prices by <a href="https://oha.deals">oha.deals</a>', do_shortcode( '[oha_deals_product slug="synology-ds925-plus"]' ) );
	}

	public function test_template_override_and_args_filter() {
		$override = get_temp_dir() . 'oha-deals-notice-override.php';
		file_put_contents( $override, '<?php echo "OVERRIDE:" . esc_html( $args["message"] );' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		add_filter(
			'oha_deals_template_path',
			static function ( $path, $view ) use ( $override ) {
				return 'notice' === $view ? $override : $path;
			},
			10,
			2
		);
		add_filter(
			'oha_deals_template_args',
			static function ( $args, $view ) {
				if ( 'notice' === $view ) {
					$args['message'] = 'filtered';
				}
				return $args;
			},
			10,
			2
		);
		$this->login_as( 'editor' );

		$this->assertSame( 'OVERRIDE:filtered', do_shortcode( '[oha_deals_product]' ) );
		unlink( $override ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
	}
}
