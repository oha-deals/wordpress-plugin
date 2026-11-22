<?php
/**
 * Tests for Settings\Options.
 *
 * @package OhaDeals
 */

namespace OhaDeals\Tests;

use OhaDeals\Settings\Options;

/**
 * Tests for Options.
 *
 * @covers \OhaDeals\Settings\Options
 */
class OptionsTest extends TestCase {

	/**
	 * Settings under test.
	 *
	 * @var Options
	 */
	private $options;

	/**
	 * Fresh Options instance.
	 */
	public function set_up() {
		parent::set_up();
		$this->options = new Options();
	}

	public function test_defaults_are_merged_over_stored_values() {
		delete_option( Options::OPTION );

		$this->assertSame( 3600, $this->options->cache_ttl() );
		$this->assertFalse( $this->options->enabled( 'show_attribution' ) );
		$this->assertTrue( $this->options->enabled( 'link_rel_sponsored' ) );
		$this->assertFalse( $this->options->has_api_key() );
		$this->assertSame( Options::DEFAULT_API_BASE, $this->options->api_base() );
	}

	public function test_valid_key_is_stored() {
		$clean = $this->options->sanitize( array( 'api_key' => '  oha_live_ABCdef0123456789ABCdef0123456789 ' ) );

		$this->assertSame( 'oha_live_ABCdef0123456789ABCdef0123456789', $clean['api_key'] );
	}

	public function test_empty_key_field_keeps_the_stored_key() {
		$clean = $this->options->sanitize( array( 'api_key' => '' ) );

		$this->assertSame( self::KEY, $clean['api_key'] );
	}

	public function test_invalid_key_keeps_the_stored_key() {
		$clean = $this->options->sanitize( array( 'api_key' => 'not-a-key' ) );

		$this->assertSame( self::KEY, $clean['api_key'] );
	}

	public function test_remove_checkbox_clears_the_key() {
		$clean = $this->options->sanitize(
			array(
				'api_key'        => '',
				'api_key_remove' => '1',
			)
		);

		$this->assertSame( '', $clean['api_key'] );
	}

	public function test_cache_ttl_is_clamped() {
		$this->assertSame( 300, $this->options->sanitize( array( 'cache_ttl' => '5' ) )['cache_ttl'] );
		$this->assertSame( 86400, $this->options->sanitize( array( 'cache_ttl' => '999999' ) )['cache_ttl'] );
		$this->assertSame( 1800, $this->options->sanitize( array( 'cache_ttl' => '1800' ) )['cache_ttl'] );
		$this->assertSame( 3600, $this->options->sanitize( array( 'cache_ttl' => 'abc' ) )['cache_ttl'] );
	}

	public function test_amazon_tag_is_lowercased_and_validated() {
		$this->assertSame( 'myblog-21', $this->options->sanitize( array( 'amazon_tag' => ' MyBlog-21 ' ) )['amazon_tag'] );
		$this->assertSame( 'myblog', $this->options->sanitize( array( 'amazon_tag' => 'myblog' ) )['amazon_tag'], 'Lenient: a tag without suffix is kept.' );
		$this->assertSame( '', $this->options->sanitize( array( 'amazon_tag' => 'bad tag!<script>' ) )['amazon_tag'], 'Invalid input keeps the previous (empty) tag.' );
	}

	public function test_awin_publisher_id_accepts_digits_only() {
		$this->assertSame( '123456', $this->options->sanitize( array( 'awin_publisher_id' => '123456' ) )['awin_publisher_id'] );
		$this->assertSame( '', $this->options->sanitize( array( 'awin_publisher_id' => '12a' ) )['awin_publisher_id'] );
	}

	public function test_missing_booleans_mean_off() {
		$clean = $this->options->sanitize( array( 'open_new_tab' => '1' ) );

		$this->assertTrue( $clean['open_new_tab'] );
		$this->assertFalse( $clean['link_rel_sponsored'] );
		$this->assertFalse( $clean['delete_data_on_uninstall'] );
	}

	public function test_disclosure_text_is_sanitized() {
		$clean = $this->options->sanitize( array( 'disclosure_text' => '<b>Ad</b> links' ) );

		$this->assertSame( 'Ad links', $clean['disclosure_text'] );
	}

	public function test_api_base_cannot_be_set_through_the_option() {
		$clean = $this->options->sanitize( array( 'api_base' => 'https://evil.example/api' ) );
		$this->assertArrayNotHasKey( 'api_base', $clean );

		update_option( Options::OPTION, array_merge( Options::defaults(), array( 'api_base' => 'https://evil.example/api' ) ) );
		$this->assertSame( Options::DEFAULT_API_BASE, $this->options->api_base() );
	}

	public function test_api_base_filter_accepts_https_only() {
		add_filter(
			'oha_deals_api_base',
			static function () {
				return 'http://localhost:3000/api/partner/v1';
			}
		);
		$this->assertSame( Options::DEFAULT_API_BASE, $this->options->api_base() );

		add_filter(
			'oha_deals_api_base',
			static function () {
				return 'https://staging.oha.deals/api/partner/v1/';
			},
			20
		);
		$this->assertSame( 'https://staging.oha.deals/api/partner/v1', $this->options->api_base() );
	}

	public function test_api_base_filter_applies_to_an_existing_client() {
		$client = new \OhaDeals\Api\Client( $this->options );
		add_filter(
			'oha_deals_api_base',
			static function () {
				return 'https://staging.oha.deals/api/partner/v1';
			}
		);

		$this->assertStringStartsWith( 'https://staging.oha.deals/api/partner/v1/me', $client->url( '/me' ) );
	}

	public function test_show_images_is_on_by_default() {
		$this->assertTrue( $this->options->enabled( 'show_images' ) );
		$this->assertFalse( $this->options->sanitize( array() )['show_images'] );
	}

	public function test_mask_key_shows_only_the_prefix() {
		$masked = Options::mask_key( self::KEY );

		$this->assertSame( 'oha_test_abc****', $masked );
		$this->assertStringNotContainsString( 'xyz012345', $masked );
		$this->assertSame( '', Options::mask_key( '' ) );
	}

	public function test_link_rel_follows_settings() {
		$this->assertSame( 'sponsored nofollow noopener', $this->options->link_rel() );

		update_option( Options::OPTION, array_merge( Options::defaults(), array( 'link_rel_sponsored' => false ) ) );
		$this->assertSame( 'noopener', $this->options->link_rel() );

		update_option(
			Options::OPTION,
			array_merge(
				Options::defaults(),
				array(
					'link_rel_sponsored' => false,
					'open_new_tab'       => false,
				)
			)
		);
		$this->assertSame( '', $this->options->link_rel() );
	}

	public function test_link_rel_is_sponsored_for_own_links_only() {
		$this->assertSame( 'sponsored nofollow noopener', $this->options->link_rel( true ) );
		$this->assertSame( 'nofollow noopener', $this->options->link_rel( false ) );
	}

	public function test_a_custom_disclosure_text_replaces_both_defaults() {
		update_option( Options::OPTION, array_merge( Options::defaults(), array( 'disclosure_text' => 'Anzeige' ) ) );

		$this->assertSame( 'Anzeige', $this->options->disclosure_text( true ) );
		$this->assertSame( 'Anzeige', $this->options->disclosure_text( false ) );
	}

	public function test_malformed_input_and_stored_values_are_tolerated() {
		$clean = $this->options->sanitize(
			array(
				'api_key'           => array( 'x' ),
				'amazon_tag'        => array( 'x' ),
				'awin_publisher_id' => array( '1' ),
				'cache_ttl'         => array( '1' ),
				'disclosure_text'   => array( 'x' ),
			)
		);
		$this->assertSame( self::KEY, $clean['api_key'] );
		$this->assertSame( '', $clean['amazon_tag'] );
		$this->assertSame( 3600, $clean['cache_ttl'] );
		$this->assertSame( '', $clean['disclosure_text'] );

		update_option(
			Options::OPTION,
			array(
				'amazon_tag' => array(),
				'cache_ttl'  => 'soon',
			)
		);
		$this->assertSame( '', $this->options->amazon_tag() );
		$this->assertSame( 3600, $this->options->cache_ttl() );
	}

	public function test_disclosure_text_default_and_filter() {
		$this->assertSame( 'Links to shops are affiliate links.', $this->options->disclosure_text() );
		$this->assertSame( 'Links lead to the price comparison on oha.deals.', $this->options->disclosure_text( false ) );

		add_filter(
			'oha_deals_disclosure_text',
			static function () {
				return 'Custom disclosure';
			}
		);
		$this->assertSame( 'Custom disclosure', $this->options->disclosure_text() );
	}
}
