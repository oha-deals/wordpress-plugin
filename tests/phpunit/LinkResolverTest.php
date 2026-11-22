<?php
/**
 * Tests for the affiliate link providers and Affiliate\LinkResolver.
 *
 * @package OhaDeals
 */

namespace OhaDeals\Tests;

use OhaDeals\Affiliate\AmazonProvider;
use OhaDeals\Affiliate\AwinProvider;
use OhaDeals\Affiliate\LinkResolver;
use OhaDeals\Affiliate\Provider;
use OhaDeals\Model\Coupon;
use OhaDeals\Model\Offer;
use OhaDeals\Model\Product;
use OhaDeals\Plugin;

/**
 * Tests for LinkResolver.
 *
 * @covers \OhaDeals\Affiliate\LinkResolver
 * @covers \OhaDeals\Affiliate\AmazonProvider
 * @covers \OhaDeals\Affiliate\AwinProvider
 */
class LinkResolverTest extends TestCase {

	/**
	 * Product built from the fixture.
	 *
	 * @var Product
	 */
	private $product;

	/**
	 * Offers of the fixture product, in fixture order: Awin product link, Awin deep link, Amazon, plain shop.
	 *
	 * @var Offer[]
	 */
	private $offers;

	/**
	 * Loads the fixture product.
	 */
	public function set_up() {
		parent::set_up();
		$data          = $this->fixture( 'product' );
		$this->product = Product::from_array( $data['data'] );
		$this->offers  = $this->product->offers();
	}

	/**
	 * Resolver with both built-in providers.
	 *
	 * @param string $tag  Amazon tag.
	 * @param string $awin Awin publisher ID.
	 * @return LinkResolver
	 */
	private function resolver( $tag = 'myblog-21', $awin = '555' ) {
		return new LinkResolver( array( new AmazonProvider( $tag ), new AwinProvider( $awin ) ) );
	}

	public function test_awin_product_link_is_preferred() {
		$this->assertSame(
			'https://www.awin1.com/pclick.php?p=38512345&a=555&m=11731',
			$this->resolver()->url( $this->offers[0], $this->product )
		);
	}

	public function test_awin_deep_link_without_product_id() {
		$this->assertSame(
			'https://www.awin1.com/cread.php?awinmid=20000&awinaffid=555&ued=' . rawurlencode( 'https://www.deeplink.example/p/ds925?ref=1&x=2' ),
			$this->resolver()->url( $this->offers[1], $this->product )
		);
	}

	/**
	 * The offers section of the fixture product's page on oha.deals.
	 */
	const PAGE = 'https://oha.deals/produkt/synology-ds925-plus#angebote';

	public function test_awin_without_product_id_or_url_falls_back_to_oha_deals() {
		$offer = Offer::from_array(
			array(
				'shop'     => array(
					'id'   => 'awin-1',
					'name' => 'Shop',
				),
				'url'      => 'https://oha.deals/api/partner/v1/go/k1/x/awin-1',
				'merchant' => array(
					'network'        => 'awin',
					'awinMerchantId' => 1,
					'awinProductId'  => null,
					'url'            => null,
				),
			)
		);

		$this->assertSame( self::PAGE, $this->resolver()->url( $offer, $this->product ) );
	}

	public function test_non_numeric_awin_product_id_is_ignored() {
		$offer = Offer::from_array(
			array(
				'url'      => 'https://oha.deals/go/1',
				'merchant' => array(
					'awinMerchantId' => 1,
					'awinProductId'  => '12&a=evil',
				),
			)
		);

		$this->assertNull( $offer->awin_product_id() );
		$this->assertSame( self::PAGE, $this->resolver()->url( $offer, $this->product ) );
	}

	public function test_amazon_link_with_own_tag() {
		$this->assertSame( 'https://www.amazon.de/dp/B0DXYZ1234?tag=myblog-21', $this->resolver()->url( $this->offers[2], $this->product ) );
	}

	public function test_without_own_ids_the_product_page_is_linked_never_the_redirect() {
		$resolver = $this->resolver( '', '' );

		foreach ( $this->offers as $offer ) {
			$this->assertStringContainsString( '/go/', $offer->url(), 'The redirect stays available to the filter.' );
			$this->assertSame( self::PAGE, $resolver->url( $offer, $this->product ) );
		}
	}

	public function test_the_anchor_replaces_an_existing_fragment() {
		$product = Product::from_array(
			array(
				'slug' => 'x',
				'url'  => 'https://oha.deals/produkt/x?v=1#top',
			)
		);

		$this->assertSame( 'https://oha.deals/produkt/x?v=1#angebote', LinkResolver::offers_page( $product ) );
	}

	public function test_without_a_product_url_there_is_no_link() {
		$product = Product::from_array(
			array(
				'slug' => 'x',
				'url'  => 'javascript:alert(1)',
			)
		);

		$this->assertSame( '', $this->resolver( '', '' )->url( $this->offers[0], $product ) );
	}

	public function test_kind_tells_own_links_from_oha_deals_links() {
		$resolver = $this->resolver();

		$this->assertSame( LinkResolver::KIND_OWN, LinkResolver::kind( $resolver->url( $this->offers[0], $this->product ) ) );
		$this->assertSame( LinkResolver::KIND_OWN, LinkResolver::kind( $resolver->url( $this->offers[2], $this->product ) ) );
		$this->assertSame( LinkResolver::KIND_OHA_DEALS, LinkResolver::kind( $resolver->url( $this->offers[3], $this->product ) ) );
		$this->assertSame( LinkResolver::KIND_OHA_DEALS, LinkResolver::kind( 'https://WWW.OHA.DEALS/x' ) );
		$this->assertSame( LinkResolver::KIND_OWN, LinkResolver::kind( 'https://notoha.deals/x' ) );
		$this->assertSame( LinkResolver::KIND_OWN, LinkResolver::kind( 'https://oha.deals.example/x' ) );
		$this->assertSame( LinkResolver::KIND_NONE, LinkResolver::kind( '' ) );
		$this->assertSame( LinkResolver::KIND_NONE, LinkResolver::kind( 'http://oha.deals/' ) );
	}

	public function test_the_offer_url_filter_can_use_the_redirect() {
		add_filter(
			'oha_deals_offer_url',
			static function ( $url, $offer ) {
				return (string) $offer->url();
			},
			10,
			2
		);

		$this->assertSame( $this->offers[0]->url(), $this->resolver()->url( $this->offers[0], $this->product ) );
	}

	public function test_coupons_link_to_the_shop_page_or_the_overview_never_to_the_redirect() {
		$with    = Coupon::from_array(
			array(
				'id'      => 'c1',
				'url'     => 'https://oha.deals/g/c1',
				'pageUrl' => 'https://oha.deals/gutscheine/alternate',
			)
		);
		$without = Coupon::from_array(
			array(
				'id'      => 'c2',
				'url'     => 'https://oha.deals/g/c2',
				'pageUrl' => null,
			)
		);
		$unsafe  = Coupon::from_array( array( 'pageUrl' => 'javascript:alert(1)' ) );

		$resolver = $this->resolver();
		$this->assertSame( 'https://oha.deals/gutscheine/alternate', $resolver->coupon_url( $with ) );
		$this->assertSame( 'https://oha.deals/gutscheine', $resolver->coupon_url( $without ) );
		$this->assertSame( 'https://oha.deals/gutscheine', $resolver->coupon_url( $unsafe ) );

		add_filter(
			'oha_deals_coupon_url',
			static function ( $url, $coupon ) {
				return 'c1' === $coupon->id() ? 'https://partner.example/voucher' : 'http://insecure.example/';
			},
			10,
			2
		);
		$this->assertSame( 'https://partner.example/voucher', $resolver->coupon_url( $with ) );
		$this->assertSame( '', $resolver->coupon_url( $without ) );
	}

	public function test_offer_url_filter_runs_last_and_only_https_survives() {
		add_filter(
			'oha_deals_offer_url',
			static function ( $url, $offer ) {
				return 'other-shop' === $offer->shop_id() ? 'http://insecure.example/' : $url . '&sub=blog';
			},
			10,
			2
		);
		$resolver = $this->resolver();

		$this->assertSame( '', $resolver->url( $this->offers[3], $this->product ) );
		$this->assertStringEndsWith( '&sub=blog', $resolver->url( $this->offers[0], $this->product ) );
	}

	public function test_non_providers_are_ignored() {
		$resolver = new LinkResolver( array( 'not a provider', new AmazonProvider( 'myblog-21' ) ) );

		$this->assertSame( 'https://www.amazon.de/dp/B0DXYZ1234?tag=myblog-21', $resolver->url( $this->offers[2], $this->product ) );
	}

	public function test_providers_filter_adds_a_custom_provider() {
		$custom = new class() implements Provider {
			public function supports( Offer $offer ): bool {
				return 'other-shop' === $offer->shop_id();
			}
			public function url( Offer $offer ): ?string {
				return 'https://partner.example/?to=' . rawurlencode( (string) $offer->url() );
			}
		};
		add_filter(
			'oha_deals_affiliate_providers',
			static function ( $providers ) use ( $custom ) {
				array_unshift( $providers, $custom );
				return $providers;
			}
		);
		Plugin::instance()->reset();

		$this->assertStringStartsWith( 'https://partner.example/', Plugin::instance()->links()->url( $this->offers[3], $this->product ) );
	}
}
