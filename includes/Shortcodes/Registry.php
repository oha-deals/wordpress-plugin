<?php
/**
 * Shortcode registration.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Shortcodes;

use Closure;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the shortcodes. Each one renders the same element as its block; attributes use snake_case
 * (`show_image="false"`), which Elements::normalize() maps onto the block's camelCase names.
 */
final class Registry {

	/**
	 * Shortcode tags mapped to element names.
	 */
	const SHORTCODES = array(
		'oha_deals_product'    => 'product',
		'oha_deals_offers'     => 'offers',
		'oha_deals_list'       => 'deals',
		'oha_deals_comparison' => 'comparison',
		'oha_deals_history'    => 'price-history',
		'oha_deals_coupons'    => 'coupons',
		'oha_deals_price'      => 'price',
	);

	/**
	 * Returns the element renderer. Resolved per render, so changed settings take effect.
	 *
	 * @var Closure(): \OhaDeals\Render\Elements
	 */
	private Closure $elements;

	/**
	 * Constructor.
	 *
	 * @param Closure $elements Returns the element renderer.
	 */
	public function __construct( Closure $elements ) {
		$this->elements = $elements;
	}

	/**
	 * Registers the shortcodes (on `init`).
	 *
	 * @return void
	 */
	public function register(): void {
		foreach ( array_keys( self::SHORTCODES ) as $tag ) {
			add_shortcode( $tag, array( $this, 'render' ) );
		}
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array<string, mixed>|string $atts Attributes; an empty string when none were given.
	 * @param string|null                 $content Enclosed content (unused).
	 * @param string                      $tag     Shortcode tag.
	 * @return string
	 */
	public function render( $atts, $content = null, $tag = '' ): string {
		unset( $content );
		$element = self::SHORTCODES[ (string) $tag ] ?? '';
		if ( '' === $element ) {
			return '';
		}

		return ( $this->elements )()->render( $element, is_array( $atts ) ? $atts : array(), false );
	}
}
