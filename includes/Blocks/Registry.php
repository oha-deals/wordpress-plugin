<?php
/**
 * Block registration.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Blocks;

use Closure;
use WP_Block_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the dynamic blocks from `build/blocks/<name>/block.json` with PHP render callbacks.
 *
 * From WordPress 6.7 on, the metadata is read from one generated file (`build/blocks-manifest.php`) instead of one
 * JSON file per block.
 */
final class Registry {

	/**
	 * Slug of the block category in the inserter.
	 */
	const CATEGORY = 'oha-deals';

	/**
	 * Block directory names mapped to element names.
	 */
	const BLOCKS = array(
		'product'       => 'product',
		'offers'        => 'offers',
		'deals'         => 'deals',
		'comparison'    => 'comparison',
		'price-history' => 'price-history',
		'coupons'       => 'coupons',
	);

	/**
	 * Returns the element renderer. Resolved per render, so changed settings take effect.
	 *
	 * @var Closure(): \OhaDeals\Render\Elements
	 */
	private Closure $elements;

	/**
	 * Directory that holds the built blocks.
	 *
	 * @var string
	 */
	private string $build_dir;

	/**
	 * Constructor.
	 *
	 * @param Closure $elements  Returns the element renderer.
	 * @param string  $build_dir Directory with one sub-directory per block.
	 */
	public function __construct( Closure $elements, string $build_dir ) {
		$this->elements  = $elements;
		$this->build_dir = trailingslashit( $build_dir );
	}

	/**
	 * Registers the blocks (on `init`). Without a build, nothing is registered and admins get a notice.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! is_dir( $this->build_dir ) ) {
			add_action( 'admin_notices', array( $this, 'missing_build_notice' ) );
			return;
		}

		add_filter( 'block_categories_all', array( $this, 'add_category' ) );
		$this->register_metadata_collection();

		foreach ( self::BLOCKS as $directory => $element ) {
			$path = $this->build_dir . $directory;
			if ( ! is_readable( $path . '/block.json' ) ) {
				continue;
			}
			$block = register_block_type(
				$path,
				array(
					'render_callback' => function ( $attributes ) use ( $element ): string {
						return ( $this->elements )()->render( $element, is_array( $attributes ) ? $attributes : array(), true );
					},
				)
			);
			if ( $block instanceof WP_Block_Type ) {
				$this->set_translations( $block );
			}
		}
	}

	/**
	 * Adds the "oha.deals" category to the block inserter, after the existing ones.
	 *
	 * @param array<int, array<string, mixed>> $categories Block categories.
	 * @return array<int, array<string, mixed>>
	 */
	public function add_category( $categories ): array {
		$categories = is_array( $categories ) ? $categories : array();
		if ( in_array( self::CATEGORY, array_column( $categories, 'slug' ), true ) ) {
			return $categories;
		}
		$categories[] = array(
			'slug'  => self::CATEGORY,
			'title' => __( 'oha.deals', 'oha-deals' ),
			'icon'  => null,
		);

		return $categories;
	}

	/**
	 * Registers the generated metadata manifest (WordPress 6.7+). Without it, core reads each block.json.
	 *
	 * @return void
	 */
	private function register_metadata_collection(): void {
		$manifest = dirname( $this->build_dir ) . '/blocks-manifest.php';
		if ( function_exists( 'wp_register_block_metadata_collection' ) && is_readable( $manifest ) ) {
			wp_register_block_metadata_collection( untrailingslashit( $this->build_dir ), $manifest );
		}
	}

	/**
	 * Lets the editor scripts of a block load the translations bundled in `languages/`. For languages that are not
	 * bundled, the language packs from WordPress.org are used.
	 *
	 * @param WP_Block_Type $block Registered block.
	 * @return void
	 */
	private function set_translations( WP_Block_Type $block ): void {
		foreach ( $block->editor_script_handles as $handle ) {
			wp_set_script_translations( $handle, 'oha-deals', OHA_DEALS_DIR . 'languages' );
		}
	}

	/**
	 * Notice on the Plugins screen: the block editor assets have not been built (a checkout without `npm run build`).
	 *
	 * @return void
	 */
	public function missing_build_notice(): void {
		$screen = get_current_screen();
		if ( null === $screen || 'plugins' !== $screen->id || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'oha.deals: the blocks are not available because the build/ directory is missing. Run "npm install && npm run build" in the plugin directory, or install the plugin from WordPress.org. Shortcodes keep working.', 'oha-deals' )
		);
	}
}
