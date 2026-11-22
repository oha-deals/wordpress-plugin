<?php
/**
 * Tests for attribute normalisation, block and shortcode registration.
 *
 * @package OhaDeals
 */

namespace OhaDeals\Tests;

use OhaDeals\Blocks\Registry as BlockRegistry;
use OhaDeals\Plugin;
use OhaDeals\Render\Elements;
use OhaDeals\Settings\Options;

/**
 * Tests for Elements.
 *
 * @covers \OhaDeals\Render\Elements
 * @covers \OhaDeals\Blocks\Registry
 * @covers \OhaDeals\Shortcodes\Registry
 */
class ElementsTest extends TestCase {

	public function test_defaults() {
		$this->assertSame(
			array(
				'slug'        => '',
				'ean'         => '',
				'layout'      => 'card',
				'showImage'   => true,
				'offersLimit' => 3,
				'showHistory' => false,
				'buttonText'  => '',
			),
			Elements::normalize( 'product', array() )
		);
	}

	public function test_snake_case_shortcode_attributes_map_to_block_attributes() {
		$atts = Elements::normalize(
			'product',
			array(
				'show_image'   => 'false',
				'offers_limit' => '99',
				'show_history' => 'yes',
				'button_text'  => '<b>Buy</b>',
				'layout'       => 'COMPACT',
				'unknown'      => 'x',
			)
		);

		$this->assertFalse( $atts['showImage'] );
		$this->assertSame( 10, $atts['offersLimit'] );
		$this->assertTrue( $atts['showHistory'] );
		$this->assertSame( 'Buy', $atts['buttonText'] );
		$this->assertSame( 'compact', $atts['layout'] );
		$this->assertArrayNotHasKey( 'unknown', $atts );
	}

	public function test_invalid_values_fall_back_to_defaults() {
		$deals = Elements::normalize(
			'deals',
			array(
				'mode'     => 'everything',
				'limit'    => 'many',
				'columns'  => 0,
				'minPrice' => -5,
				'maxPrice' => '250',
			)
		);
		$this->assertSame( 'all', $deals['mode'] );
		$this->assertSame( 6, $deals['limit'] );
		$this->assertSame( 1, $deals['columns'] );
		$this->assertNull( $deals['minPrice'] );
		$this->assertSame( 250.0, $deals['maxPrice'] );

		$history = Elements::normalize( 'price-history', array( 'days' => 45 ) );
		$this->assertSame( 90, $history['days'] );
		$this->assertSame( 365, Elements::normalize( 'price-history', array( 'days' => 365.0 ) )['days'] );
	}

	public function test_block_attributes_pass_through() {
		$atts = Elements::normalize(
			'offers',
			array(
				'slug'        => 'synology-ds925-plus',
				'includeUsed' => true,
				'limit'       => 5,
			)
		);

		$this->assertTrue( $atts['includeUsed'] );
		$this->assertSame( 5, $atts['limit'] );
	}

	public function test_shortcodes_are_registered() {
		foreach ( array( 'oha_deals_product', 'oha_deals_offers', 'oha_deals_list', 'oha_deals_comparison', 'oha_deals_history', 'oha_deals_coupons', 'oha_deals_price' ) as $tag ) {
			$this->assertTrue( shortcode_exists( $tag ), $tag );
		}
	}

	public function test_missing_build_registers_no_blocks_and_warns_admins() {
		$registry = new BlockRegistry( \Closure::fromCallable( array( Plugin::instance(), 'elements' ) ), '/path/that/does/not/exist' );

		$registry->register();

		$this->assertNotFalse( has_action( 'admin_notices', array( $registry, 'missing_build_notice' ) ) );

		$this->assertSame( '', $this->missing_build_notice( $registry, 'subscriber', 'plugins' ) );
		$this->assertSame( '', $this->missing_build_notice( $registry, 'administrator', 'edit-post' ), 'Only on the Plugins screen.' );
		$this->assertStringContainsString( 'notice-warning', $this->missing_build_notice( $registry, 'administrator', 'plugins' ) );
		set_current_screen( 'front' );
	}

	/**
	 * Output of the "build missing" notice.
	 *
	 * @param BlockRegistry $registry Registry.
	 * @param string        $role     Role of the current user.
	 * @param string        $screen   Screen ID.
	 * @return string
	 */
	private function missing_build_notice( BlockRegistry $registry, $role, $screen ) {
		$this->login_as( $role );
		if ( is_multisite() && 'administrator' === $role ) {
			grant_super_admin( get_current_user_id() ); // Only super admins manage plugins there by default.
		}
		set_current_screen( $screen );
		ob_start();
		$registry->missing_build_notice();

		return (string) ob_get_clean();
	}

	/**
	 * Writes a minimal build directory with one block and registers it.
	 *
	 * @param bool $manifest Whether to write a manifest next to the blocks.
	 * @return array{dir: string, registry: BlockRegistry}
	 */
	private function temporary_build( $manifest ) {
		$root     = get_temp_dir() . 'oha-deals-build-' . wp_generate_password( 8, false );
		$metadata = array(
			'apiVersion'   => 3,
			'name'         => 'oha-deals/product',
			'title'        => 'From block.json',
			'editorScript' => 'oha-deals-test-editor',
			'attributes'   => array( 'slug' => array( 'type' => 'string' ) ),
		);
		wp_mkdir_p( $root . '/blocks/product' );
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $root . '/blocks/product/block.json', wp_json_encode( $metadata ) );
		if ( $manifest ) {
			$metadata['title'] = 'From the manifest';
			file_put_contents( $root . '/blocks-manifest.php', '<?php return ' . var_export( array( 'product' => $metadata ), true ) . ';' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		}
		// phpcs:enable

		return array(
			'dir'      => $root,
			'registry' => new BlockRegistry( \Closure::fromCallable( array( Plugin::instance(), 'elements' ) ), $root . '/blocks' ),
		);
	}

	/**
	 * Registers a temporary build in place of the real product block and returns the block type.
	 *
	 * @param bool $manifest Whether to write a manifest.
	 * @return \WP_Block_Type
	 */
	private function register_temporary_build( $manifest ) {
		$blocks = \WP_Block_Type_Registry::get_instance();
		$real   = $blocks->get_registered( 'oha-deals/product' );
		if ( $real ) {
			$blocks->unregister( 'oha-deals/product' );
		}
		wp_register_script( 'oha-deals-test-editor', 'https://example.org/editor.js', array( 'wp-i18n' ), '1', true );
		$build = $this->temporary_build( $manifest );

		$build['registry']->register();

		$type = $blocks->get_registered( 'oha-deals/product' );
		$blocks->unregister( 'oha-deals/product' );
		if ( $real ) {
			$blocks->register( $real );
		}
		// phpcs:disable WordPress.WP.AlternativeFunctions
		unlink( $build['dir'] . '/blocks/product/block.json' );
		rmdir( $build['dir'] . '/blocks/product' );
		rmdir( $build['dir'] . '/blocks' );
		if ( $manifest ) {
			unlink( $build['dir'] . '/blocks-manifest.php' );
		}
		rmdir( $build['dir'] );
		// phpcs:enable

		return $type;
	}

	public function test_blocks_register_without_a_manifest_and_with_one() {
		$this->assertSame( 'From block.json', $this->register_temporary_build( false )->title );

		$expected = function_exists( 'wp_register_block_metadata_collection' ) ? 'From the manifest' : 'From block.json';
		$this->assertSame( $expected, $this->register_temporary_build( true )->title );
	}

	public function test_editor_scripts_load_bundled_translations() {
		$type = $this->register_temporary_build( false );

		$this->assertContains( 'oha-deals-test-editor', $type->editor_script_handles );
		$script = wp_scripts()->registered['oha-deals-test-editor'];
		$this->assertSame( 'oha-deals', $script->textdomain );
		$this->assertSame( OHA_DEALS_DIR . 'languages', $script->translations_path );
	}

	public function test_the_real_editor_scripts_load_bundled_translations() {
		foreach ( $this->built_blocks() as $dir ) {
			$type = \WP_Block_Type_Registry::get_instance()->get_registered( 'oha-deals/' . basename( $dir ) );
			$this->assertNotEmpty( $type->editor_script_handles );
			foreach ( $type->editor_script_handles as $handle ) {
				$this->assertSame( OHA_DEALS_DIR . 'languages', wp_scripts()->registered[ $handle ]->translations_path, $handle );
			}
		}
	}

	public function test_the_block_category_is_added_once_at_the_end() {
		$registry   = new BlockRegistry( \Closure::fromCallable( array( Plugin::instance(), 'elements' ) ), '/path/that/does/not/exist' );
		$categories = $registry->add_category( array( array( 'slug' => 'text' ) ) );

		$this->assertSame( array( 'text', 'oha-deals' ), wp_list_pluck( $categories, 'slug' ) );
		$this->assertSame( 'oha.deals', $categories[1]['title'] );
		$this->assertSame( $categories, $registry->add_category( $categories ) );
	}

	public function test_block_render_uses_wrapper_attributes() {
		$this->respond( '/products/synology-ds925-plus', $this->fixture( 'product' ) );
		$temp = '';
		if ( ! \WP_Block_Type_Registry::get_instance()->is_registered( 'oha-deals/product' ) ) {
			// No build: register a minimal block.json so the render callback can still be exercised.
			$temp = get_temp_dir() . 'oha-deals-build-' . wp_generate_password( 6, false );
			wp_mkdir_p( $temp . '/product' );
			file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				$temp . '/product/block.json',
				wp_json_encode(
					array(
						'apiVersion' => 3,
						'name'       => 'oha-deals/product',
						'title'      => 'Product',
						'attributes' => array( 'slug' => array( 'type' => 'string' ) ),
						'supports'   => array( 'html' => false ),
					)
				)
			);
			( new BlockRegistry( \Closure::fromCallable( array( Plugin::instance(), 'elements' ) ), $temp ) )->register();
		}

		$html = do_blocks( '<!-- wp:oha-deals/product {"slug":"synology-ds925-plus"} /-->' );

		$this->assertStringContainsString( 'wp-block-oha-deals-product', $html );
		$this->assertStringContainsString( 'oha-deals oha-deals-product oha-deals-product--card', $html );

		if ( '' !== $temp ) {
			unregister_block_type( 'oha-deals/product' );
			unlink( $temp . '/product/block.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			rmdir( $temp . '/product' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
			rmdir( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		}
	}

	/**
	 * The built block directories, or a skipped test when build/ is missing.
	 *
	 * @return string[]
	 */
	private function built_blocks() {
		$dirs = glob( OHA_DEALS_DIR . 'build/blocks/*', GLOB_ONLYDIR );
		if ( ! $dirs ) {
			$this->markTestSkipped( 'build/ is missing; run npm run build.' );
		}

		return $dirs;
	}

	public function test_real_block_metadata_matches_the_attribute_schema() {
		foreach ( $this->built_blocks() as $dir ) {
			$element  = basename( $dir );
			$metadata = json_decode( (string) file_get_contents( $dir . '/block.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

			$this->assertSame( 'oha-deals/' . $element, $metadata['name'] );
			$this->assertSame( 'oha-deals-frontend', $metadata['style'] );
			$this->assertEqualsCanonicalizing( array_keys( Elements::SCHEMA[ $element ] ), array_keys( $metadata['attributes'] ), $element );
			$this->assertTrue( \WP_Block_Type_Registry::get_instance()->is_registered( 'oha-deals/' . $element ), $element . ' is registered' );
		}
	}

	public function test_every_editor_script_dependency_is_registered_on_this_wordpress() {
		wp_scripts();
		foreach ( $this->built_blocks() as $dir ) {
			$asset = require $dir . '/index.asset.php';
			foreach ( $asset['dependencies'] as $handle ) {
				$this->assertTrue( wp_script_is( $handle, 'registered' ), basename( $dir ) . ' needs the script handle ' . $handle );
			}
		}
	}

	/**
	 * Renders the product block with attributes.
	 *
	 * @param array $attributes Attributes.
	 * @param array $data       Changes to the product data.
	 * @return string
	 */
	private function product_block( array $attributes = array(), array $data = array() ) {
		$this->built_blocks();
		$fixture         = $this->fixture( 'product' );
		$fixture['data'] = array_merge( $fixture['data'], $data );
		$this->respond( '/products/synology-ds925-plus', $fixture );

		return do_blocks( '<!-- wp:oha-deals/product ' . wp_json_encode( array( 'slug' => 'synology-ds925-plus' ) + $attributes ) . ' /-->' );
	}

	public function test_the_anchor_is_printed_once() {
		$html = $this->product_block( array( 'anchor' => 'mein-nas' ) );

		$this->assertSame( 1, substr_count( $html, ' id="mein-nas"' ) );
		$this->assertStringNotContainsString( 'mein-nas mein-nas', $html );
		$this->assertStringNotContainsString( ' id=', $this->product_block(), 'No anchor, no id.' );
	}

	public function test_the_anchor_is_escaped() {
		$html = $this->product_block( array( 'anchor' => 'a"><script>alert(1)</script>' ) );

		$this->assertStringNotContainsString( '<script>alert', $html );
	}

	public function test_shortcodes_have_no_anchor() {
		$this->respond( '/products/synology-ds925-plus', $this->fixture( 'product' ) );

		$this->assertStringNotContainsString( ' id=', do_shortcode( '[oha_deals_product slug="synology-ds925-plus" anchor="x"]' ) );
	}

	public function test_api_text_never_becomes_a_shortcode() {
		add_shortcode(
			'oha_deals_test_tag',
			static function () {
				return 'EXECUTED';
			}
		);
		$name = 'NAS [oha_deals_test_tag] [[oha_deals_test_tag]] [/oha_deals_test_tag] [2 Stück]';

		$block = do_shortcode( $this->product_block( array(), array( 'name' => $name ) ) );
		Plugin::instance()->cache()->flush();
		$shortcode = do_shortcode( '[oha_deals_product slug="synology-ds925-plus"]' );
		remove_shortcode( 'oha_deals_test_tag' );

		$this->assertStringNotContainsString( 'EXECUTED', $block . $shortcode );
		$this->assertStringContainsString( '&#91;oha_deals_test_tag]', $block );
		$this->assertStringContainsString( '[2 Stück]', $block, 'Other brackets stay as they are.' );
	}

	public function test_notices_about_the_key_are_marked_for_the_editor() {
		$this->login_as( 'editor' );
		$this->respond_error( '/products/rejected', 401, 'unauthorized' );
		$this->respond_error( '/products/missing', 404, 'not_found' );

		$this->assertStringContainsString( 'oha-deals-notice--key', do_shortcode( '[oha_deals_product slug="rejected"]' ) );
		$this->assertStringNotContainsString( 'oha-deals-notice--key', do_shortcode( '[oha_deals_product slug="missing"]' ) );
		$this->assertStringNotContainsString( 'oha-deals-notice--key', do_shortcode( '[oha_deals_product]' ) );

		update_option( Options::OPTION, Options::defaults() );
		$this->assertStringContainsString( 'oha-deals-notice--key', do_shortcode( '[oha_deals_product slug="missing"]' ) );
	}

	public function test_frontend_style_is_registered() {
		$this->assertTrue( wp_style_is( 'oha-deals-frontend', 'registered' ) );
	}
}
