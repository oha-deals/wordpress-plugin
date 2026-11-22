<?php
/**
 * Block patterns.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Blocks;

use WP_Block_Type_Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the files in `patterns/` as block patterns, in the format core uses for theme patterns: the metadata is in
 * the file header, the file prints the block markup.
 */
final class Patterns {

	/**
	 * Slug of the pattern category.
	 */
	const CATEGORY = 'oha-deals';

	/**
	 * Header fields.
	 */
	const HEADERS = array(
		'title'         => 'Title',
		'slug'          => 'Slug',
		'description'   => 'Description',
		'categories'    => 'Categories',
		'keywords'      => 'Keywords',
		'viewportWidth' => 'Viewport Width',
		'blockTypes'    => 'Block Types',
	);

	/**
	 * Directory with the pattern files, with trailing slash.
	 *
	 * @var string
	 */
	private string $directory;

	/**
	 * Constructor.
	 *
	 * @param string $directory Directory with the pattern files.
	 */
	public function __construct( string $directory ) {
		$this->directory = trailingslashit( $directory );
	}

	/**
	 * Registers the category and the patterns (on `init`, after the blocks). Without the blocks there are no patterns.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'oha-deals/product' ) ) {
			return;
		}
		$files = glob( $this->directory . '*.php' );
		if ( ! is_array( $files ) || ! $files ) {
			return;
		}

		register_block_pattern_category( self::CATEGORY, array( 'label' => __( 'oha.deals', 'oha-deals' ) ) );
		foreach ( $files as $file ) {
			$this->register_file( $file );
		}
	}

	/**
	 * Registers one pattern. The editor includes the file when it needs the content.
	 *
	 * @param string $file Absolute path.
	 * @return void
	 */
	private function register_file( string $file ): void {
		$data = get_file_data( $file, self::HEADERS );
		if ( '' === $data['slug'] || '' === $data['title'] ) {
			return;
		}

		$texts   = self::texts()[ $data['slug'] ] ?? $data;
		$pattern = array(
			'title'       => $texts['title'],
			'description' => $texts['description'],
			'categories'  => self::words( $data['categories'] ),
			'keywords'    => self::words( $data['keywords'] ),
			'blockTypes'  => self::words( $data['blockTypes'] ),
			'filePath'    => $file,
		);
		if ( (int) $data['viewportWidth'] > 0 ) {
			$pattern['viewportWidth'] = (int) $data['viewportWidth'];
		}

		register_block_pattern( $data['slug'], array_filter( $pattern ) );
	}

	/**
	 * The translated titles and descriptions by slug. Same texts as in the file headers, which make-pot reads for themes
	 * only.
	 *
	 * @return array<string, array{title: string, description: string}>
	 */
	private static function texts(): array {
		return array(
			'oha-deals/comparison-verdict' => array(
				'title'       => _x( 'Comparison with verdict', 'Pattern title', 'oha-deals' ),
				'description' => _x( 'A comparison table of two to six products, followed by a short verdict.', 'Pattern description', 'oha-deals' ),
			),
			'oha-deals/deals-page'         => array(
				'title'       => _x( 'Deals page with coupons', 'Pattern title', 'oha-deals' ),
				'description' => _x( 'Price drops, lowest prices and coupon codes, each with a heading.', 'Pattern description', 'oha-deals' ),
			),
			'oha-deals/product-review'     => array(
				'title'       => _x( 'Product card with price history', 'Pattern title', 'oha-deals' ),
				'description' => _x( 'A product card, the advantages and drawbacks side by side, and the price history for the question when to buy.', 'Pattern description', 'oha-deals' ),
			),
			'oha-deals/top-list'           => array(
				'title'       => _x( 'Top 5 list with intro', 'Pattern title', 'oha-deals' ),
				'description' => _x( 'An introduction, a numbered list of products with live prices and a note on how they were chosen.', 'Pattern description', 'oha-deals' ),
			),
		);
	}

	/**
	 * The entries of a comma-separated header.
	 *
	 * @param string $value Header value.
	 * @return string[]
	 */
	private static function words( string $value ): array {
		return array_values( array_diff( array_map( 'trim', explode( ',', $value ) ), array( '' ) ) );
	}
}
