<?php
/**
 * Block Bindings source.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Blocks;

use Closure;
use OhaDeals\Api\Repository;
use OhaDeals\Model\Product;
use OhaDeals\Render\Elements;
use OhaDeals\Render\Helpers;
use OhaDeals\Settings\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Registers `oha-deals/product`, so that core blocks (paragraph, heading, button, image) can show live values.
 *
 * Values are plain text in the site language. A value that is not available is null, so the block's own content stays.
 */
final class Bindings {

	/**
	 * Source name.
	 */
	const SOURCE = 'oha-deals/product';

	/**
	 * Available fields.
	 */
	const FIELDS = array(
		'name',
		'brand',
		'spec',
		'category',
		'price',
		'before_price',
		'discount',
		'shop',
		'offer_count',
		'lowest_price',
		'lowest_date',
		'image',
		'image_alt',
		'url',
		'rel',
		'link_label',
		'disclosure',
		'checked_at',
	);

	/**
	 * Settings.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Returns the data access. Resolved per value, so changed settings take effect.
	 *
	 * @var Closure(): Repository
	 */
	private Closure $repository;

	/**
	 * Returns the helpers.
	 *
	 * @var Closure(): Helpers
	 */
	private Closure $helpers;

	/**
	 * Constructor.
	 *
	 * @param Options $options    Settings.
	 * @param Closure $repository Returns the data access.
	 * @param Closure $helpers    Returns the helpers.
	 */
	public function __construct( Options $options, Closure $repository, Closure $helpers ) {
		$this->options    = $options;
		$this->repository = $repository;
		$this->helpers    = $helpers;
	}

	/**
	 * Registers the source (on `init`).
	 *
	 * @return void
	 */
	public function register(): void {
		if ( null !== get_block_bindings_source( self::SOURCE ) ) {
			return;
		}
		register_block_bindings_source(
			self::SOURCE,
			array(
				'label'              => _x( 'oha.deals product', 'block bindings source', 'oha-deals' ),
				'get_value_callback' => array( $this, 'value' ),
			)
		);
	}

	/**
	 * The value for a bound attribute.
	 *
	 * @param array<string, mixed> $args      Source arguments: `slug` or `ean`, and `field`.
	 * @param mixed                $block     Block instance.
	 * @param mixed                $attribute Attribute name.
	 * @return string|null
	 */
	public function value( $args, $block = null, $attribute = '' ): ?string {
		$args  = is_array( $args ) ? $args : array();
		$field = self::text( $args, 'field' );
		if ( ! in_array( $field, self::FIELDS, true ) || ! $this->options->has_api_key() ) {
			return null;
		}

		$identifier = self::text( $args, 'slug' );
		if ( '' === $identifier ) {
			$identifier = self::text( $args, 'ean' );
		}
		if ( '' === $identifier ) {
			return null;
		}

		$product = ( $this->repository )()->product( $identifier );
		if ( is_wp_error( $product ) ) {
			return null;
		}

		$value = $this->field( $field, $product );
		if ( '' === $value ) {
			return null;
		}

		// Core inserts rich text as HTML (filtered by kses) and encodes attribute values itself. The content filter runs
		// shortcodes afterwards; text from the API must not become one.
		return self::is_rich_text( $block, (string) $attribute )
			? Elements::neutralize_shortcodes( esc_html( $value ) )
			: strip_shortcodes( $value );
	}

	/**
	 * The values of all fields, for the editor.
	 *
	 * @param Product $product Product.
	 * @return array<string, string|null> Plain text; null when a value is not available.
	 */
	public function values( Product $product ): array {
		$values = array();
		foreach ( self::FIELDS as $field ) {
			$value            = $this->field( $field, $product );
			$values[ $field ] = '' === $value ? null : $value;
		}

		return $values;
	}

	/**
	 * One field of a product as plain text; an empty string when it is not available.
	 *
	 * An Amazon best price may only be shown with its timestamp, which a single bound value cannot carry. For such
	 * products the price and everything derived from it is not available.
	 *
	 * @param string  $field   Field.
	 * @param Product $product Product.
	 * @return string
	 */
	private function field( string $field, Product $product ): string {
		$helpers = ( $this->helpers )();
		$amazon  = $product->is_best_price_amazon();
		$low     = $product->all_time_low();

		switch ( $field ) {
			case 'name':
				return $product->name();
			case 'brand':
				return $product->brand();
			case 'spec':
				return $product->spec();
			case 'category':
				return $product->category_name();
			case 'price':
				return $amazon ? '' : $helpers->product_price( $product );
			case 'before_price':
				return $amazon ? '' : $helpers->before_price( $product );
			case 'discount':
				return $amazon ? '' : $helpers->discount( $product );
			case 'shop':
				return (string) $product->shop();
			case 'offer_count':
				return $product->offer_count() > 0 ? number_format_i18n( $product->offer_count() ) : '';
			case 'lowest_price':
				return $amazon || null === $low ? '' : $helpers->format()->money( $low['price'], $product->currency() );
			case 'lowest_date':
				return $amazon || null === $low ? '' : $helpers->format()->date( $low['date'] );
			case 'image':
				return $helpers->shows_images() ? (string) $product->image() : '';
			case 'image_alt':
				return $helpers->shows_images() && null !== $product->image() ? $helpers->image_alt( $product ) : '';
			case 'url':
				return $helpers->best_url( $product );
			case 'rel':
				return $this->options->link_rel( $helpers->is_own_link( $helpers->best_url( $product ) ) );
			case 'link_label':
				return $helpers->best_link_label( $product );
			case 'disclosure':
				return $this->options->disclosure_text( $helpers->is_own_link( $helpers->best_url( $product ) ) );
			case 'checked_at':
				return null !== $product->prices_checked_at() ? $helpers->format()->datetime( $product->prices_checked_at() ) : '';
			default:
				return '';
		}
	}

	/**
	 * Whether a block attribute holds rich text (HTML) rather than an attribute value.
	 *
	 * @param mixed  $block     Block instance.
	 * @param string $attribute Attribute name.
	 * @return bool
	 */
	private static function is_rich_text( $block, string $attribute ): bool {
		if ( ! $block instanceof \WP_Block || ! $block->block_type instanceof \WP_Block_Type ) {
			return false;
		}
		$definition = ( $block->block_type->attributes ?? array() )[ $attribute ] ?? array();

		return in_array( $definition['source'] ?? '', array( 'html', 'rich-text' ), true );
	}

	/**
	 * A text argument.
	 *
	 * @param array<string, mixed> $args Arguments.
	 * @param string               $name Name.
	 * @return string
	 */
	private static function text( array $args, string $name ): string {
		$value = $args[ $name ] ?? '';

		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}
}
