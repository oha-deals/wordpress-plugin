<?php
/**
 * Tests for the comparison element.
 *
 * @package OhaDeals
 */

namespace OhaDeals\Tests;

use OhaDeals\Render\Elements;

/**
 * Tests for the data the comparison hands to its template, and a smoke test of the template itself.
 *
 * @covers \OhaDeals\Render\Elements
 * @covers \OhaDeals\Api\Repository
 */
class ComparisonTest extends TestCase {

	/**
	 * Template arguments of the last render.
	 *
	 * @var array<string, mixed>
	 */
	private $args = array();

	/**
	 * Three products: a and b share specifications, c is Amazon's.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( 0 );
		$this->args = array();
		$this->product(
			'product-a',
			200.0,
			array(
				'Typ'       => 'SSD',
				'Kapazität' => '1 TB',
				'Farbe'     => 'Schwarz',
			)
		);
		$this->product(
			'product-b',
			150.0,
			array(
				'Kapazität' => '2 TB',
				'Typ'       => 'SSD',
				'Bauform'   => 'M.2',
			)
		);
		$this->respond( '/products/echo-dot-5', $this->fixture( 'product-amazon' ) );
		add_filter(
			'oha_deals_template_args',
			function ( $args, $view ) {
				if ( 'comparison-table' === $view ) {
					$this->args = $args;
				}
				return $args;
			},
			10,
			2
		);
	}

	/**
	 * Mocks a product.
	 *
	 * @param string     $slug  Slug.
	 * @param float|null $price Best price.
	 * @param array      $attrs Specifications.
	 * @return void
	 */
	private function product( $slug, $price, array $attrs = array() ) {
		$data                        = $this->fixture( 'product' );
		$data['data']['slug']        = $slug;
		$data['data']['name']        = 'Name of ' . $slug;
		$data['data']['url']         = 'https://oha.deals/produkt/' . $slug;
		$data['data']['price']       = $price;
		$data['data']['priceHidden'] = null === $price;
		$data['data']['attrs']       = $attrs ? $attrs : null;
		$this->respond( '/products/' . $slug, $data );
	}

	/**
	 * Slugs of the compared products.
	 *
	 * @return string[]
	 */
	private function slugs() {
		return array_map(
			static function ( $product ) {
				return $product->slug();
			},
			$this->args['products']
		);
	}

	public function test_attribute_defaults() {
		$this->assertSame(
			array(
				'slugs'         => array(),
				'fields'        => array( 'image', 'price', 'shop', 'offers', 'lowest', 'specs' ),
				'orientation'   => 'columns',
				'highlightBest' => true,
				'title'         => '',
				'buttonText'    => '',
				'numbered'      => false,
			),
			Elements::normalize( 'comparison', array() )
		);
	}

	public function test_lists_from_shortcodes_and_blocks() {
		$shortcode = Elements::normalize(
			'comparison',
			array(
				'slugs'          => ' a , b,a,, c ,d,e,f,g',
				'fields'         => 'specs, PRICE,unknown,price',
				'orientation'    => 'rows',
				'highlight_best' => 'no',
				'numbered'       => 'yes',
			)
		);
		$this->assertSame( array( 'a', 'b', 'c', 'd', 'e', 'f' ), $shortcode['slugs'] );
		$this->assertSame( array( 'specs', 'price' ), $shortcode['fields'] );
		$this->assertSame( 'rows', $shortcode['orientation'] );
		$this->assertFalse( $shortcode['highlightBest'] );
		$this->assertTrue( $shortcode['numbered'] );

		$block = Elements::normalize(
			'comparison',
			array(
				'slugs'  => array( 'a', array( 'x' ), null, 'b', true ),
				'fields' => array( 'nothing' ),
			)
		);
		$this->assertSame( array( 'a', 'b' ), $block['slugs'] );
		$this->assertSame( Elements::SCHEMA['comparison']['fields']['default'], $block['fields'] );
	}

	public function test_products_keep_their_order_and_unknown_or_repeated_ones_are_dropped() {
		do_shortcode( '[oha_deals_comparison slugs="product-b, missing, https://oha.deals/produkt/product-a?x=1, PRODUCT-B"]' );

		$this->assertSame( array( 'product-b', 'product-a' ), $this->slugs() );
	}

	public function test_best_index() {
		do_shortcode( '[oha_deals_comparison slugs="product-a,product-b"]' );
		$this->assertSame( 1, $this->args['best_index'] );

		do_shortcode( '[oha_deals_comparison slugs="product-a,product-b" highlight_best="false"]' );
		$this->assertNull( $this->args['best_index'] );

		do_shortcode( '[oha_deals_comparison slugs="product-a"]' );
		$this->assertNull( $this->args['best_index'], 'One product is not a comparison.' );

		$this->product( 'tie', 150.0 );
		do_shortcode( '[oha_deals_comparison slugs="product-a,product-b,tie"]' );
		$this->assertNull( $this->args['best_index'], 'A tie has no winner.' );

		$this->product( 'hidden', null );
		do_shortcode( '[oha_deals_comparison slugs="product-a,hidden"]' );
		$this->assertNull( $this->args['best_index'], 'A hidden price is not compared.' );
	}

	public function test_spec_rows_are_shared_labels_in_product_order() {
		do_shortcode( '[oha_deals_comparison slugs="product-a,product-b,echo-dot-5"]' );

		$this->assertSame(
			array(
				array(
					'label'  => 'Typ',
					'values' => array( 'SSD', 'SSD', '' ),
				),
				array(
					'label'  => 'Kapazität',
					'values' => array( '1 TB', '2 TB', '' ),
				),
			),
			$this->args['spec_rows']
		);

		do_shortcode( '[oha_deals_comparison slugs="product-a,product-b" fields="price"]' );
		$this->assertSame( array(), $this->args['spec_rows'] );
	}

	public function test_spec_rows_are_capped() {
		$attrs = array();
		for ( $i = 1; $i <= 20; $i++ ) {
			$attrs[ 'Merkmal ' . $i ] = (string) $i;
		}
		$this->product( 'many-a', 1.0, $attrs );
		$this->product( 'many-b', 2.0, $attrs );

		do_shortcode( '[oha_deals_comparison slugs="many-a,many-b"]' );

		$this->assertCount( 12, $this->args['spec_rows'] );
		$this->assertSame( 'Merkmal 1', $this->args['spec_rows'][0]['label'] );
	}

	public function test_numbers_are_for_rows_only() {
		do_shortcode( '[oha_deals_comparison slugs="product-a,product-b" numbered="true"]' );
		$this->assertFalse( $this->args['show_numbers'] );

		do_shortcode( '[oha_deals_comparison slugs="product-a,product-b" numbered="true" orientation="rows"]' );
		$this->assertTrue( $this->args['show_numbers'] );
	}

	public function test_amazon_flag_follows_the_best_prices() {
		do_shortcode( '[oha_deals_comparison slugs="product-a,product-b"]' );
		$this->assertFalse( $this->args['amazon_prices'] );

		do_shortcode( '[oha_deals_comparison slugs="product-a,echo-dot-5"]' );
		$this->assertTrue( $this->args['amazon_prices'] );
	}

	public function test_the_template_renders_both_orientations() {
		$columns = do_shortcode( '[oha_deals_comparison slugs="product-a,product-b" title="SSDs <b>im Vergleich</b>"]' );
		$this->assertStringContainsString( 'class="oha-deals oha-deals-comparison oha-deals-comparison--columns"', $columns );
		$this->assertStringContainsString( 'Name of product-a', $columns );
		$this->assertStringContainsString( 'https://oha.deals/produkt/product-b#angebote', $columns );
		$this->assertStringContainsString( 'Links lead to the price comparison on oha.deals.', $columns );
		$this->assertStringContainsString( '2 TB', $columns );
		$this->assertStringNotContainsString( '<b>', $columns );

		$rows = do_shortcode( '[oha_deals_comparison slugs="product-a,product-b" orientation="rows" numbered="true"]' );
		$this->assertStringContainsString( 'oha-deals-comparison--rows', $rows );
		$this->assertStringContainsString( '<table', $rows );
	}

	public function test_the_template_escapes_specifications() {
		$evil = '"><script>alert(1)</script>';
		$this->product( 'evil-a', 1.0, array( $evil => $evil ) );
		$this->product( 'evil-b', 2.0, array( $evil => $evil ) );

		$html = do_shortcode( '[oha_deals_comparison slugs="evil-a,evil-b"]' ) . do_shortcode( '[oha_deals_comparison slugs="evil-a,evil-b" orientation="rows"]' );

		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_nothing_to_compare_is_an_editor_notice() {
		$this->assertSame( '', do_shortcode( '[oha_deals_comparison]' ) );
		$this->assertSame( '', do_shortcode( '[oha_deals_comparison slugs="missing"]' ) );

		$this->login_as( 'editor' );
		$this->assertStringContainsString( 'Choose the products', do_shortcode( '[oha_deals_comparison]' ) );
		$this->assertStringContainsString( 'None of these products', do_shortcode( '[oha_deals_comparison slugs="missing"]' ) );
	}

	public function test_an_outage_is_reported_to_editors() {
		$this->respond_error( '/products/down', 401, 'unauthorized' );
		$this->login_as( 'editor' );

		$this->assertStringContainsString( 'API key was rejected', do_shortcode( '[oha_deals_comparison slugs="down"]' ) );
		do_shortcode( '[oha_deals_comparison slugs="down,product-a"]' );
		$this->assertSame( array( 'product-a' ), $this->slugs(), 'The rest is still shown.' );
	}
}
