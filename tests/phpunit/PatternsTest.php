<?php
/**
 * Tests for the block patterns.
 *
 * @package OhaDeals
 */

namespace OhaDeals\Tests;

use OhaDeals\Blocks\Patterns;
use WP_Block_Pattern_Categories_Registry;
use WP_Block_Patterns_Registry;
use WP_Block_Type_Registry;

/**
 * Tests for Patterns.
 *
 * @covers \OhaDeals\Blocks\Patterns
 */
class PatternsTest extends TestCase {

	/**
	 * The slugs of the shipped patterns.
	 */
	const SLUGS = array( 'oha-deals/comparison-verdict', 'oha-deals/deals-page', 'oha-deals/product-review', 'oha-deals/top-list' );

	/**
	 * Skips without the built blocks.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'oha-deals/product' ) ) {
			$this->markTestSkipped( 'build/ is missing; run npm run build.' );
		}
	}

	/**
	 * The registered patterns of the plugin by slug.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function patterns() {
		$patterns = array();
		foreach ( WP_Block_Patterns_Registry::get_instance()->get_all_registered() as $pattern ) {
			if ( 0 === strpos( $pattern['name'], 'oha-deals/' ) ) {
				$patterns[ $pattern['name'] ] = $pattern;
			}
		}

		return $patterns;
	}

	public function test_every_file_is_registered_in_the_category() {
		$patterns = $this->patterns();

		$this->assertCount( count( (array) glob( OHA_DEALS_DIR . 'patterns/*.php' ) ), $patterns );
		$this->assertEqualsCanonicalizing( self::SLUGS, array_keys( $patterns ) );
		$this->assertTrue( WP_Block_Pattern_Categories_Registry::get_instance()->is_registered( 'oha-deals' ) );
		foreach ( $patterns as $slug => $pattern ) {
			$this->assertSame( array( 'oha-deals' ), $pattern['categories'], $slug );
			$this->assertNotEmpty( $pattern['keywords'], $slug );
			$this->assertGreaterThan( 0, $pattern['viewportWidth'], $slug );
		}
		$this->assertSame( array( 'oha-deals/product' ), $patterns['oha-deals/product-review']['blockTypes'] );
	}

	public function test_translatable_texts_match_the_file_headers() {
		$patterns = $this->patterns();
		foreach ( (array) glob( OHA_DEALS_DIR . 'patterns/*.php' ) as $file ) {
			$header = get_file_data(
				$file,
				array(
					'title'       => 'Title',
					'slug'        => 'Slug',
					'description' => 'Description',
				)
			);
			$this->assertSame( $header['title'], $patterns[ $header['slug'] ]['title'] );
			$this->assertSame( $header['description'], $patterns[ $header['slug'] ]['description'] );
		}
	}

	/**
	 * All blocks of a tree that have a name, plus freeform HTML that is not just whitespace.
	 *
	 * @param array $blocks Parsed blocks.
	 * @return array[]
	 */
	private function flatten( array $blocks ) {
		$flat = array();
		foreach ( $blocks as $block ) {
			if ( null !== $block['blockName'] || '' !== trim( $block['innerHTML'] ) ) {
				$flat[] = $block;
			}
			$flat = array_merge( $flat, $this->flatten( $block['innerBlocks'] ) );
		}

		return $flat;
	}

	public function test_contents_are_known_blocks_with_known_attributes() {
		$registry = WP_Block_Type_Registry::get_instance();
		foreach ( $this->patterns() as $slug => $pattern ) {
			$this->assertStringNotContainsString( '<?php', $pattern['content'], $slug );
			$blocks = $this->flatten( parse_blocks( $pattern['content'] ) );
			$this->assertNotEmpty( $blocks, $slug );
			$own = 0;
			foreach ( $blocks as $block ) {
				$this->assertNotNull( $block['blockName'], $slug . ': freeform HTML' );
				$this->assertTrue( $registry->is_registered( $block['blockName'] ), $slug . ': ' . $block['blockName'] );
				if ( 0 === strpos( $block['blockName'], 'oha-deals/' ) ) {
					++$own;
					$known = array_keys( $registry->get_registered( $block['blockName'] )->attributes );
					$this->assertSame( array(), array_diff( array_keys( $block['attrs'] ), $known ), $slug . ': ' . $block['blockName'] );
				}
			}
			$this->assertGreaterThan( 0, $own, $slug );
		}
	}

	public function test_without_the_blocks_nothing_is_registered() {
		$blocks = WP_Block_Type_Registry::get_instance();
		$block  = $blocks->get_registered( 'oha-deals/product' );
		$blocks->unregister( 'oha-deals/product' );
		WP_Block_Patterns_Registry::get_instance()->unregister( self::SLUGS[0] );

		( new Patterns( OHA_DEALS_DIR . 'patterns' ) )->register();
		$without = WP_Block_Patterns_Registry::get_instance()->is_registered( self::SLUGS[0] );

		$blocks->register( $block );
		( new Patterns( OHA_DEALS_DIR . 'patterns' ) )->register();

		$this->assertFalse( $without );
		$this->assertTrue( WP_Block_Patterns_Registry::get_instance()->is_registered( self::SLUGS[0] ) );
	}

	public function test_files_without_title_or_slug_are_ignored() {
		$directory = get_temp_dir() . 'oha-deals-patterns-' . wp_generate_password( 8, false );
		wp_mkdir_p( $directory );
		file_put_contents( $directory . '/x.php', "<?php\n/**\n * Title: No slug\n */\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$before = count( $this->patterns() );
		( new Patterns( $directory ) )->register();

		$this->assertCount( $before, $this->patterns() );
		unlink( $directory . '/x.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		rmdir( $directory ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}
}
