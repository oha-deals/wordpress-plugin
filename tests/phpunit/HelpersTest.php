<?php
/**
 * Tests for the link helpers, rel attributes and the disclosure.
 *
 * @package OhaDeals
 */

namespace OhaDeals\Tests;

use OhaDeals\Model\Coupon;
use OhaDeals\Model\Product;
use OhaDeals\Plugin;
use OhaDeals\Render\Helpers;
use OhaDeals\Settings\Options;

/**
 * Tests for Helpers.
 *
 * @covers \OhaDeals\Render\Helpers
 * @covers \OhaDeals\Render\Elements
 */
class HelpersTest extends TestCase {

	/**
	 * Default disclosure with own affiliate links.
	 */
	const OWN = 'Links to shops are affiliate links.';

	/**
	 * Default disclosure with oha.deals links only.
	 */
	const OHA = 'Links lead to the price comparison on oha.deals.';

	/**
	 * Product built from the fixture.
	 *
	 * @var Product
	 */
	private $product;

	/**
	 * Visitor view of the fixture product.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( 0 );
		$data          = $this->fixture( 'product' );
		$this->product = Product::from_array( $data['data'] );
		$this->respond( '/products/synology-ds925-plus', $data );
		$this->respond( '/deals', $this->fixture( 'deals' ) );
		$this->respond( '/coupons', $this->fixture( 'coupons' ) );
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

	/**
	 * Own Amazon and Awin IDs.
	 *
	 * @return void
	 */
	private function own_ids() {
		$this->settings(
			array(
				'amazon_tag'        => 'myblog-21',
				'awin_publisher_id' => '555',
			)
		);
	}

	/**
	 * Helpers with the current settings.
	 *
	 * @return Helpers
	 */
	private function helpers() {
		return new Helpers( Plugin::instance()->options(), Plugin::instance()->formatter(), Plugin::instance()->links() );
	}

	public function test_labels_say_where_the_link_leads() {
		$offers = $this->product->offers();

		foreach ( $offers as $offer ) {
			$this->assertSame( 'Compare on oha.deals', $this->helpers()->offer_link_label( $offer, $this->product ) );
		}
		$this->assertSame( 'Compare on oha.deals', $this->helpers()->best_link_label( $this->product ) );

		$this->own_ids();
		$this->assertSame( 'To the shop', $this->helpers()->offer_link_label( $offers[0], $this->product ) );
		$this->assertSame( 'View price at Amazon', $this->helpers()->offer_link_label( $offers[2], $this->product ) );
		$this->assertSame( 'Compare on oha.deals', $this->helpers()->offer_link_label( $offers[3], $this->product ) );
		$this->assertSame( 'View best price', $this->helpers()->best_link_label( $this->product ) );
	}

	public function test_an_offer_without_a_link_has_no_label() {
		add_filter( 'oha_deals_offer_url', '__return_empty_string' );

		$this->assertSame( '', $this->helpers()->offer_link_label( $this->product->offers()[0], $this->product ) );
	}

	public function test_timestamp_of_an_amazon_best_price() {
		update_option( 'date_format', 'Y-m-d' );
		update_option( 'time_format', 'H:i' );
		update_option( 'timezone_string', 'UTC' );
		$data = $this->fixture( 'product-amazon' )['data'];

		$this->assertSame( 'Price as of 2026-11-20, 07:30', $this->helpers()->product_as_of( Product::from_array( $data ) ) );
		$this->assertSame( '', $this->helpers()->product_as_of( $this->product ) );

		$data['priceAt']              = null;
		$data['offers'][0]['priceAt'] = null;
		$data['pricesCheckedAt']      = '2026-11-21T06:00:00Z';
		$this->assertSame( 'Price as of 2026-11-21, 06:00', $this->helpers()->product_as_of( Product::from_array( $data ) ), 'Falls back to the last price check.' );

		$data['price']       = null;
		$data['priceHidden'] = true;
		$this->assertSame( '', $this->helpers()->product_as_of( Product::from_array( $data ) ) );
	}

	public function test_is_own_link() {
		$helpers = $this->helpers();

		$this->assertTrue( $helpers->is_own_link( 'https://www.amazon.de/dp/B0DXYZ1234?tag=myblog-21' ) );
		$this->assertTrue( $helpers->is_own_link( 'https://www.awin1.com/pclick.php?p=1' ) );
		$this->assertFalse( $helpers->is_own_link( 'https://oha.deals/produkt/x#angebote' ) );
		$this->assertFalse( $helpers->is_own_link( '' ) );
		$this->assertFalse( $helpers->is_own_link( 'http://www.amazon.de/' ) );
	}

	public function test_coupon_link_helpers() {
		$coupon = Coupon::from_array(
			array(
				'id'      => 'c1',
				'shop'    => 'Alternate DE',
				'pageUrl' => 'https://oha.deals/gutscheine/alternate',
			)
		);

		$this->assertSame( 'https://oha.deals/gutscheine/alternate', $this->helpers()->coupon_url( $coupon ) );
		$this->assertSame( 'View on oha.deals', $this->helpers()->coupon_link_label( $coupon ) );

		add_filter(
			'oha_deals_coupon_url',
			static function () {
				return 'https://www.awin1.com/cread.php?x=1';
			}
		);
		$this->assertSame( 'Redeem at Alternate DE', $this->helpers()->coupon_link_label( $coupon ) );
	}

	/**
	 * Attributes printed for a link.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private function attributes( $url ) {
		ob_start();
		$this->helpers()->print_link_attributes( $url );

		return (string) ob_get_clean();
	}

	public function test_rel_depends_on_the_kind_of_link() {
		$this->assertStringContainsString( ' rel="sponsored nofollow noopener"', $this->attributes( 'https://www.amazon.de/dp/B0DXYZ1234?tag=myblog-21' ) );
		$this->assertStringContainsString( ' rel="nofollow noopener"', $this->attributes( 'https://oha.deals/produkt/x#angebote' ) );
		$this->assertStringContainsString( ' href="https://oha.deals/produkt/x#angebote"', $this->attributes( 'https://oha.deals/produkt/x#angebote' ) );
		$this->assertSame( '', $this->attributes( '' ) );
	}

	/**
	 * Footer printed by a helpers instance.
	 *
	 * @param Helpers $helpers Helpers.
	 * @return string
	 */
	private function footer( Helpers $helpers ) {
		ob_start();
		$helpers->print_footer( false, false );

		return (string) ob_get_clean();
	}

	public function test_disclosure_follows_the_links_of_the_element() {
		$helpers = $this->helpers();

		$helpers->start_element( array( 'https://oha.deals/produkt/x', '' ) );
		$this->assertStringContainsString( self::OHA, $this->footer( $helpers ) );

		$helpers->start_element( array( 'https://oha.deals/produkt/x', 'https://www.amazon.de/dp/B0DXYZ1234?tag=a-21' ) );
		$this->assertStringContainsString( self::OWN, $this->footer( $helpers ) );
		$this->assertStringNotContainsString( self::OHA, $this->footer( $helpers ) );

		$helpers->start_element( array() );
		$this->assertSame( '', $this->footer( $helpers ), 'An element without links needs no disclosure.' );

		ob_start();
		$helpers->print_link_attributes( 'https://www.awin1.com/pclick.php?p=1' );
		ob_end_clean();
		$this->assertStringContainsString( self::OWN, $this->footer( $helpers ), 'A link a template adds on its own counts too.' );
	}

	public function test_elements_without_own_ids_disclose_oha_deals_and_print_no_redirects() {
		foreach ( array( '[oha_deals_product slug="synology-ds925-plus" offers_limit="4"]', '[oha_deals_product slug="synology-ds925-plus" layout="inline"]', '[oha_deals_offers slug="synology-ds925-plus" include_used="true"]', '[oha_deals_list]', '[oha_deals_coupons]' ) as $shortcode ) {
			$html = do_shortcode( $shortcode );

			$this->assertStringContainsString( self::OHA, $html, $shortcode );
			$this->assertStringNotContainsString( self::OWN, $html, $shortcode );
			$this->assertStringNotContainsString( 'sponsored', $html, $shortcode );
			$this->assertStringNotContainsString( '/go/', $html, $shortcode );
			$this->assertStringNotContainsString( 'oha.deals/g/', $html, $shortcode );
			$this->assertStringContainsString( ' rel="nofollow noopener"', $html, $shortcode );
		}
	}

	public function test_elements_with_own_ids_disclose_affiliate_links() {
		$this->own_ids();

		$html = do_shortcode( '[oha_deals_offers slug="synology-ds925-plus"]' );
		$this->assertStringContainsString( self::OWN . ' As an Amazon Associate', $html );
		$this->assertStringNotContainsString( self::OHA, $html );
		$this->assertStringContainsString( ' rel="sponsored nofollow noopener"', $html );
		$this->assertStringContainsString( ' rel="nofollow noopener"', $html, 'The offer without a matching ID links to oha.deals.' );
		$this->assertStringNotContainsString( '/go/', $html );

		$this->assertStringContainsString( self::OHA, do_shortcode( '[oha_deals_list]' ), 'Deals always link to oha.deals.' );
	}

	public function test_the_link_kinds_of_one_element_do_not_leak_into_the_next() {
		$this->own_ids();

		$this->assertStringContainsString( self::OWN, do_shortcode( '[oha_deals_offers slug="synology-ds925-plus"]' ) );
		$this->assertStringContainsString( self::OHA, do_shortcode( '[oha_deals_coupons]' ) );
	}

	public function test_a_custom_disclosure_wins_in_both_cases() {
		$this->settings( array( 'disclosure_text' => 'Anzeige' ) );
		$this->assertStringContainsString( 'Anzeige', do_shortcode( '[oha_deals_list]' ) );
		$this->assertStringNotContainsString( self::OHA, do_shortcode( '[oha_deals_list]' ) );

		$this->settings(
			array(
				'disclosure_text'   => 'Anzeige',
				'awin_publisher_id' => '555',
			)
		);
		$html = do_shortcode( '[oha_deals_offers slug="synology-ds925-plus"]' );
		$this->assertStringContainsString( 'Anzeige', $html );
		$this->assertStringNotContainsString( self::OWN, $html );
	}

	public function test_the_disclosure_filter_receives_the_kind() {
		add_filter(
			'oha_deals_disclosure_text',
			static function ( $text, $own ) {
				return $own ? 'own' : 'oha only';
			},
			10,
			2
		);

		$this->assertStringContainsString( '>oha only<', do_shortcode( '[oha_deals_list]' ) );
	}

	public function test_the_main_button_is_labelled_by_where_it_leads() {
		$this->assertStringContainsString( 'Compare on oha.deals', do_shortcode( '[oha_deals_product slug="synology-ds925-plus" offers_limit="0"]' ) );

		$this->own_ids();
		$this->assertStringContainsString( 'View best price', do_shortcode( '[oha_deals_product slug="synology-ds925-plus" offers_limit="0"]' ) );
		$this->assertStringContainsString( 'Jetzt ansehen', do_shortcode( '[oha_deals_product slug="synology-ds925-plus" offers_limit="0" button_text="Jetzt ansehen"]' ) );
	}
}
