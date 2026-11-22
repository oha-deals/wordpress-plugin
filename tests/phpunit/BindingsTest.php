<?php
/**
 * Tests for the Block Bindings source.
 *
 * @package OhaDeals
 */

namespace OhaDeals\Tests;

use OhaDeals\Blocks\Bindings;
use OhaDeals\Settings\Options;

/**
 * Tests for Bindings.
 *
 * @covers \OhaDeals\Blocks\Bindings
 */
class BindingsTest extends TestCase {

	/**
	 * Mocks the fixture products for a visitor.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( 0 );
		update_option( 'date_format', 'Y-m-d' );
		update_option( 'time_format', 'H:i' );
		update_option( 'timezone_string', 'UTC' );
		$this->respond( '/products/synology-ds925-plus', $this->fixture( 'product' ) );
		$this->respond( '/products/echo-dot-5', $this->fixture( 'product-amazon' ) );
	}

	/**
	 * Value of a field.
	 *
	 * @param string $field Field.
	 * @param string $slug  Product slug.
	 * @return string|null
	 */
	private function value( $field, $slug = 'synology-ds925-plus' ) {
		return get_block_bindings_source( Bindings::SOURCE )->get_value(
			array(
				'slug'  => $slug,
				'field' => $field,
			),
			new \WP_Block( array( 'blockName' => 'core/paragraph' ) ),
			'content'
		);
	}

	public function test_the_source_is_registered() {
		$this->assertNotNull( get_block_bindings_source( 'oha-deals/product' ) );
	}

	public function test_fields() {
		$expected = array(
			'name'         => 'Synology DS925+',
			'brand'        => 'Synology',
			'spec'         => '4 Bay · Ryzen V1500B · 4 GB DDR4',
			'category'     => 'NAS',
			'price'        => '€944.99',
			'before_price' => '€989.00',
			'discount'     => "\u{2212}4%",
			'shop'         => 'Alternate DE',
			'offer_count'  => '5',
			'lowest_price' => '€929.00',
			'lowest_date'  => '2026-10-02',
			'image'        => 'https://img.oha.deals/p/synology-ds925-plus.jpg',
			'image_alt'    => 'Synology DS925+',
			'url'          => 'https://oha.deals/produkt/synology-ds925-plus#angebote',
			'rel'          => 'nofollow noopener',
			'link_label'   => 'Compare on oha.deals',
			'disclosure'   => 'Links lead to the price comparison on oha.deals.',
			'checked_at'   => '2026-11-20, 04:15',
		);
		$this->assertSame( Bindings::FIELDS, array_keys( $expected ) );
		foreach ( $expected as $field => $value ) {
			$this->assertSame( $value, $this->value( $field ), $field );
		}
	}

	public function test_own_links_come_with_the_matching_rel_label_and_disclosure() {
		update_option( Options::OPTION, array_merge( Options::defaults(), array( 'api_key' => self::KEY ), array( 'awin_publisher_id' => '555' ) ) );

		$this->assertStringStartsWith( 'https://www.awin1.com/pclick.php', $this->value( 'url' ) );
		$this->assertSame( 'sponsored nofollow noopener', $this->value( 'rel' ) );
		$this->assertSame( 'View best price', $this->value( 'link_label' ) );
		$this->assertSame( 'Links to shops are affiliate links.', $this->value( 'disclosure' ) );
	}

	public function test_an_amazon_price_is_never_returned_without_its_timestamp() {
		foreach ( array( 'price', 'before_price', 'discount', 'lowest_price', 'lowest_date' ) as $field ) {
			$this->assertNull( $this->value( $field, 'echo-dot-5' ), $field );
		}
		$this->assertSame( 'Echo Dot (5. Gen.)', $this->value( 'name', 'echo-dot-5' ) );
		$this->assertSame( 'Amazon', $this->value( 'shop', 'echo-dot-5' ) );
	}

	public function test_unavailable_values_are_null() {
		$this->assertNull( $this->value( 'image', 'echo-dot-5' ), 'No image.' );
		$this->assertNull( $this->value( 'spec', 'echo-dot-5' ) );
		$this->assertNull( $this->value( 'unknown' ) );
		$this->assertNull( $this->value( 'name', 'missing' ) );
		$this->assertNull( $this->value( 'name', '' ) );

		update_option( Options::OPTION, Options::defaults() );
		$this->assertNull( $this->value( 'name' ), 'No key.' );
	}

	public function test_images_respect_the_setting() {
		update_option( Options::OPTION, array_merge( Options::defaults(), array( 'api_key' => self::KEY ), array( 'show_images' => false ) ) );

		$this->assertNull( $this->value( 'image' ) );
		$this->assertNull( $this->value( 'image_alt' ) );
	}

	/**
	 * A bound block.
	 *
	 * @param string $name     Block name.
	 * @param array  $bindings Attribute => field.
	 * @param string $html     Inner HTML.
	 * @param string $slug     Product slug.
	 * @return string Rendered block.
	 */
	private function bound( $name, array $bindings, $html, $slug = 'synology-ds925-plus' ) {
		$attributes = array( 'metadata' => array( 'bindings' => array() ) );
		foreach ( $bindings as $attribute => $field ) {
			$attributes['metadata']['bindings'][ $attribute ] = array(
				'source' => Bindings::SOURCE,
				'args'   => array(
					'slug'  => $slug,
					'field' => $field,
				),
			);
		}

		return do_blocks( '<!-- wp:' . $name . ' ' . wp_json_encode( $attributes ) . ' -->' . $html . '<!-- /wp:' . $name . ' -->' );
	}

	public function test_core_blocks_show_live_values() {
		$this->assertStringContainsString( '>€944.99</p>', $this->bound( 'paragraph', array( 'content' => 'price' ), '<p>Preis</p>' ) );
		$this->assertStringContainsString( '>Synology DS925+</h2>', $this->bound( 'heading', array( 'content' => 'name' ), '<h2 class="wp-block-heading">Name</h2>' ) );

		$button = $this->bound(
			'button',
			array(
				'url'  => 'url',
				'rel'  => 'rel',
				'text' => 'link_label',
			),
			'<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#" rel="x">Shop</a></div>'
		);
		$this->assertStringContainsString( 'href="https://oha.deals/produkt/synology-ds925-plus#angebote"', $button );
		$this->assertStringContainsString( 'rel="nofollow noopener"', $button );
		$this->assertStringContainsString( '>Compare on oha.deals</a>', $button );

		$image = $this->bound(
			'image',
			array(
				'url' => 'image',
				'alt' => 'image_alt',
			),
			'<figure class="wp-block-image"><img src="https://example.org/x.jpg" alt=""/></figure>'
		);
		$this->assertStringContainsString( 'src="https://img.oha.deals/p/synology-ds925-plus.jpg"', $image );
		$this->assertStringContainsString( 'alt="Synology DS925+"', $image );
	}

	public function test_the_blocks_own_content_stays_when_a_value_is_not_available() {
		$this->assertStringContainsString( '>Preis bei Amazon</p>', $this->bound( 'paragraph', array( 'content' => 'price' ), '<p>Preis bei Amazon</p>', 'echo-dot-5' ) );
	}

	public function test_values_never_become_shortcodes() {
		add_shortcode(
			'oha_deals_test_tag',
			static function () {
				return 'EXECUTED';
			}
		);
		$data                 = $this->fixture( 'product' );
		$data['data']['slug'] = 'tagged';
		$data['data']['name'] = 'A [oha_deals_test_tag] B';
		$this->respond( '/products/tagged', $data );

		$text  = do_shortcode( $this->bound( 'paragraph', array( 'content' => 'name' ), '<p>x</p>', 'tagged' ) );
		$image = do_shortcode( $this->bound( 'image', array( 'alt' => 'image_alt' ), '<figure class="wp-block-image"><img src="https://example.org/x.jpg" alt=""/></figure>', 'tagged' ) );
		remove_shortcode( 'oha_deals_test_tag' );

		$this->assertStringNotContainsString( 'EXECUTED', $text . $image );
		$this->assertStringContainsString( 'oha_deals_test_tag', $text, 'The text itself is still shown.' );
	}

	public function test_hostile_names_are_escaped_once() {
		$data                 = $this->fixture( 'product' );
		$data['data']['slug'] = 'evil';
		$data['data']['name'] = 'A & B <img src=x onerror=alert(1)>';
		$this->respond( '/products/evil', $data );

		$html = $this->bound( 'paragraph', array( 'content' => 'name' ), '<p>x</p>', 'evil' );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringContainsString( 'A &amp; B &lt;img', $html );

		$image = $this->bound( 'image', array( 'alt' => 'image_alt' ), '<figure class="wp-block-image"><img src="https://example.org/x.jpg" alt=""/></figure>', 'evil' );
		$this->assertStringNotContainsString( '&amp;amp;', $image );
	}
}
