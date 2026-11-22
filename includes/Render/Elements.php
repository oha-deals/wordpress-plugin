<?php
/**
 * The front-end elements shared by blocks, shortcodes and oha_deals_render().
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Render;

use OhaDeals\Api\Repository;
use OhaDeals\Model\Coupon;
use OhaDeals\Model\Deal;
use OhaDeals\Model\Offer;
use OhaDeals\Model\Product;
use OhaDeals\Settings\Options;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Normalises attributes, loads data and renders one element.
 *
 * Visitors never see errors: a failure renders nothing, users who can edit posts see a short notice.
 */
final class Elements {

	/**
	 * Most used/refurbished offers appended to the offers table.
	 */
	const USED_OFFERS_LIMIT = 5;

	/**
	 * Errors that the API key causes.
	 */
	const KEY_ERRORS = array( 'oha_deals_no_key', 'oha_deals_unauthorized', 'oha_deals_forbidden' );

	/**
	 * Most specification rows in a comparison.
	 */
	const SPEC_ROWS_LIMIT = 12;

	/**
	 * Script module with the copy button of coupons: the id core generates for `viewScriptModule` of oha-deals/coupons.
	 */
	const COUPONS_MODULE = 'oha-deals-coupons-view-script-module';

	/**
	 * Attribute schema per element (camelCase names, as in block.json).
	 * Each entry: type (string|bool|int|float|enum|list), default, and min/max or options. A list is an array of strings
	 * in blocks and a comma-separated string in shortcodes.
	 */
	const SCHEMA = array(
		'product'       => array(
			'slug'        => array(
				'type'    => 'string',
				'default' => '',
			),
			'ean'         => array(
				'type'    => 'string',
				'default' => '',
			),
			'layout'      => array(
				'type'    => 'enum',
				'default' => 'card',
				'options' => array( 'card', 'compact', 'inline' ),
			),
			'showImage'   => array(
				'type'    => 'bool',
				'default' => true,
			),
			'offersLimit' => array(
				'type'    => 'int',
				'default' => 3,
				'min'     => 0,
				'max'     => 10,
			),
			'showHistory' => array(
				'type'    => 'bool',
				'default' => false,
			),
			'buttonText'  => array(
				'type'    => 'string',
				'default' => '',
			),
		),
		'offers'        => array(
			'slug'         => array(
				'type'    => 'string',
				'default' => '',
			),
			'ean'          => array(
				'type'    => 'string',
				'default' => '',
			),
			'limit'        => array(
				'type'    => 'int',
				'default' => 10,
				'min'     => 1,
				'max'     => 30,
			),
			'showShipping' => array(
				'type'    => 'bool',
				'default' => true,
			),
			'includeUsed'  => array(
				'type'    => 'bool',
				'default' => false,
			),
			'showTitle'    => array(
				'type'    => 'bool',
				'default' => true,
			),
		),
		'deals'         => array(
			'mode'     => array(
				'type'    => 'enum',
				'default' => 'all',
				'options' => array( 'all', 'drop', 'low' ),
			),
			'category' => array(
				'type'    => 'string',
				'default' => '',
			),
			'sort'     => array(
				'type'    => 'enum',
				'default' => 'percent',
				'options' => array( 'percent', 'amount', 'price', 'new' ),
			),
			'limit'    => array(
				'type'    => 'int',
				'default' => 6,
				'min'     => 1,
				'max'     => 24,
			),
			'columns'  => array(
				'type'    => 'int',
				'default' => 3,
				'min'     => 1,
				'max'     => 4,
			),
			'minPrice' => array(
				'type'    => 'float',
				'default' => null,
			),
			'maxPrice' => array(
				'type'    => 'float',
				'default' => null,
			),
		),
		'comparison'    => array(
			'slugs'         => array(
				'type'    => 'list',
				'default' => array(),
				'max'     => Repository::MAX_COMPARED,
			),
			'fields'        => array(
				'type'    => 'list',
				'default' => array( 'image', 'price', 'shop', 'offers', 'lowest', 'specs' ),
				'options' => array( 'image', 'brand', 'price', 'before', 'discount', 'shop', 'offers', 'lowest', 'change', 'specs' ),
			),
			'orientation'   => array(
				'type'    => 'enum',
				'default' => 'columns',
				'options' => array( 'columns', 'rows' ),
			),
			'highlightBest' => array(
				'type'    => 'bool',
				'default' => true,
			),
			'title'         => array(
				'type'    => 'string',
				'default' => '',
			),
			'buttonText'    => array(
				'type'    => 'string',
				'default' => '',
			),
			'numbered'      => array(
				'type'    => 'bool',
				'default' => false,
			),
		),
		'price-history' => array(
			'slug'   => array(
				'type'    => 'string',
				'default' => '',
			),
			'ean'    => array(
				'type'    => 'string',
				'default' => '',
			),
			'days'   => array(
				'type'    => 'enum',
				'default' => 90,
				'options' => array( 30, 90, 180, 365 ),
			),
			'height' => array(
				'type'    => 'int',
				'default' => 220,
				'min'     => 120,
				'max'     => 600,
			),
		),
		'coupons'       => array(
			'host'  => array(
				'type'    => 'string',
				'default' => '',
			),
			'limit' => array(
				'type'    => 'int',
				'default' => 5,
				'min'     => 1,
				'max'     => 20,
			),
		),
		'price'         => array(
			'slug' => array(
				'type'    => 'string',
				'default' => '',
			),
			'ean'  => array(
				'type'    => 'string',
				'default' => '',
			),
		),
	);

	/**
	 * Data access.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Template renderer.
	 *
	 * @var Renderer
	 */
	private Renderer $renderer;

	/**
	 * Template helpers.
	 *
	 * @var Helpers
	 */
	private Helpers $helpers;

	/**
	 * History chart.
	 *
	 * @var HistoryChart
	 */
	private HistoryChart $chart;

	/**
	 * JSON-LD builder.
	 *
	 * @var StructuredData
	 */
	private StructuredData $structured_data;

	/**
	 * Settings.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * HTML anchor of the block being rendered, or an empty string.
	 *
	 * @var string
	 */
	private string $anchor = '';

	/**
	 * Constructor.
	 *
	 * @param Repository     $repository      Data access.
	 * @param Renderer       $renderer        Template renderer.
	 * @param Helpers        $helpers         Template helpers.
	 * @param HistoryChart   $chart           History chart.
	 * @param StructuredData $structured_data JSON-LD builder.
	 * @param Options        $options         Settings.
	 */
	public function __construct( Repository $repository, Renderer $renderer, Helpers $helpers, HistoryChart $chart, StructuredData $structured_data, Options $options ) {
		$this->repository      = $repository;
		$this->renderer        = $renderer;
		$this->helpers         = $helpers;
		$this->chart           = $chart;
		$this->structured_data = $structured_data;
		$this->options         = $options;
	}

	/**
	 * Renders an element by name.
	 *
	 * @param string               $element    One of the SCHEMA keys.
	 * @param array<string, mixed> $attributes Attributes, camelCase or snake_case.
	 * @param bool                 $is_block   Whether rendering as a block (uses get_block_wrapper_attributes()).
	 * @return string
	 */
	public function render( string $element, array $attributes, bool $is_block = false ): string {
		if ( ! isset( self::SCHEMA[ $element ] ) ) {
			return '';
		}

		$anchor       = $attributes['anchor'] ?? '';
		$this->anchor = $is_block && is_string( $anchor ) ? (string) preg_replace( '/[\s"\'<>&]+/', '', $anchor ) : '';
		$html         = $this->element( $element, self::normalize( $element, $attributes ), $is_block );
		$this->anchor = '';

		return $is_block ? self::neutralize_shortcodes( $html ) : $html;
	}

	/**
	 * Encodes the opening bracket of registered shortcodes.
	 *
	 * The content filter runs shortcodes after blocks, so text from the API that happens to look like a shortcode would
	 * be executed in block output. (The output of shortcodes is not run again.)
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function neutralize_shortcodes( string $html ): string {
		global $shortcode_tags;
		if ( false === strpos( $html, '[' ) || ! is_array( $shortcode_tags ) || ! $shortcode_tags ) {
			return $html;
		}
		$tags = implode(
			'|',
			array_map(
				static function ( $tag ): string {
					return preg_quote( (string) $tag, '/' );
				},
				array_keys( $shortcode_tags )
			)
		);

		return (string) preg_replace( '/\[(?=[\[\/]?(?:' . $tags . ')(?![\w-]))/', '&#91;', $html );
	}

	/**
	 * Renders an element from normalised attributes.
	 *
	 * @param string               $element    Element name.
	 * @param array<string, mixed> $attributes Normalised attributes.
	 * @param bool                 $is_block   Block context.
	 * @return string
	 */
	private function element( string $element, array $attributes, bool $is_block ): string {
		if ( ! $this->options->has_api_key() ) {
			return $this->notice( $element, $is_block, $this->no_key_message(), true );
		}

		switch ( $element ) {
			case 'product':
				return $this->product( $attributes, $is_block );
			case 'offers':
				return $this->offers( $attributes, $is_block );
			case 'deals':
				return $this->deals( $attributes, $is_block );
			case 'comparison':
				return $this->comparison( $attributes, $is_block );
			case 'price-history':
				return $this->price_history( $attributes, $is_block );
			case 'coupons':
				return $this->coupons( $attributes, $is_block );
			default:
				return $this->price( $attributes, $is_block );
		}
	}

	/**
	 * Coerces raw attributes to the schema: unknown keys are dropped, invalid values fall back to defaults.
	 * Keys match case- and underscore-insensitively, so `show_image`, `showimage` and `showImage` are the same.
	 *
	 * @param string               $element Element name.
	 * @param array<string, mixed> $raw     Raw attributes.
	 * @return array<string, mixed>
	 */
	public static function normalize( string $element, array $raw ): array {
		$schema = self::SCHEMA[ $element ] ?? array();
		$lookup = array();
		$clean  = array();
		foreach ( $schema as $name => $rule ) {
			$lookup[ strtolower( $name ) ] = array( $name, $rule );
			$clean[ $name ]                = $rule['default'];
		}

		foreach ( $raw as $key => $value ) {
			$match = $lookup[ strtolower( str_replace( array( '_', '-' ), '', (string) $key ) ) ] ?? null;
			if ( null === $match ) {
				continue;
			}
			list( $name, $rule ) = $match;
			$clean[ $name ]      = self::coerce( $value, $rule );
		}

		return $clean;
	}

	/**
	 * Coerces one value.
	 *
	 * @param mixed                $value Raw value.
	 * @param array<string, mixed> $rule  Schema rule.
	 * @return mixed
	 */
	private static function coerce( $value, array $rule ) {
		switch ( $rule['type'] ) {
			case 'bool':
				if ( is_bool( $value ) ) {
					return $value;
				}
				$bool = is_scalar( $value ) ? filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) : null;

				return null === $bool ? $rule['default'] : $bool;

			case 'int':
				if ( ! is_numeric( $value ) ) {
					return $rule['default'];
				}

				return max( (int) $rule['min'], min( (int) $rule['max'], (int) round( (float) $value ) ) );

			case 'float':
				return is_numeric( $value ) && (float) $value > 0 ? (float) $value : $rule['default'];

			case 'enum':
				foreach ( $rule['options'] as $option ) {
					if ( is_scalar( $value ) && strtolower( trim( (string) $value ) ) === (string) $option ) {
						return $option;
					}
				}

				return $rule['default'];

			case 'list':
				return self::coerce_list( $value, $rule );

			default:
				return is_scalar( $value ) && ! is_bool( $value ) ? sanitize_text_field( (string) $value ) : $rule['default'];
		}
	}

	/**
	 * Coerces a list: keeps the order, drops empty and repeated entries and anything outside the allowed options.
	 *
	 * @param mixed                $value Array of strings or comma-separated string.
	 * @param array<string, mixed> $rule  Schema rule.
	 * @return string[]
	 */
	private static function coerce_list( $value, array $rule ): array {
		$items = is_array( $value ) ? $value : explode( ',', is_scalar( $value ) ? (string) $value : '' );
		$clean = array();
		foreach ( $items as $item ) {
			$item = is_scalar( $item ) && ! is_bool( $item ) ? trim( sanitize_text_field( (string) $item ) ) : '';
			if ( isset( $rule['options'] ) ) {
				$item = strtolower( $item );
				$item = in_array( $item, (array) $rule['options'], true ) ? $item : '';
			}
			if ( '' !== $item && ! in_array( $item, $clean, true ) ) {
				$clean[] = $item;
			}
		}
		if ( ! $clean ) {
			return (array) $rule['default'];
		}

		return isset( $rule['max'] ) ? array_slice( $clean, 0, (int) $rule['max'] ) : $clean;
	}

	/**
	 * Product card, compact card or inline mention.
	 *
	 * @param array<string, mixed> $attributes Normalised attributes.
	 * @param bool                 $is_block   Block context.
	 * @return string
	 */
	private function product( array $attributes, bool $is_block ): string {
		$identifier = self::identifier( $attributes );
		if ( '' === $identifier ) {
			return $this->notice( 'product', $is_block, __( 'Choose a product (slug or EAN).', 'oha-deals' ) );
		}

		$product = $this->repository->product( $identifier, $attributes['showHistory'] ? array( 'history' ) : array() );
		if ( is_wp_error( $product ) ) {
			return $this->error( 'product', $is_block, $product );
		}

		$layout = (string) $attributes['layout'];
		$offers = 'card' === $layout ? array_slice( $product->offers(), 0, (int) $attributes['offersLimit'] ) : array();
		$best   = $product->best_offer();
		$chart  = '';
		if ( $attributes['showHistory'] && 'card' === $layout ) {
			$chart = $this->chart->render( $product->history(), $product->name(), 160, $product->currency(), 90 );
		}

		$button_text = '' !== $attributes['buttonText'] ? (string) $attributes['buttonText'] : $this->helpers->best_link_label( $product );
		$button_url  = $this->helpers->best_url( $product );
		$this->helpers->start_element( array_merge( array( $button_url ), $this->offer_urls( $offers, $product ) ) );

		return $this->renderer->render(
			'inline' === $layout ? 'product-inline' : 'product-card',
			array(
				'product'            => $product,
				'offers'             => $offers,
				'attributes'         => $attributes,
				'layout'             => $layout,
				'button_text'        => $button_text,
				'button_url'         => $button_url,
				'chart'              => $chart,
				'amazon_prices'      => $product->has_amazon_price(),
				'amazon_links'       => $this->helpers->links_to_amazon( null !== $best ? array_merge( array( $best ), $offers ) : $offers, $product ),
				'structured_data'    => $this->options->enabled( 'structured_data' ) && 'inline' !== $layout ? $this->structured_data->script( $product ) : '',
				'tag'                => 'inline' === $layout && ! $is_block ? 'span' : 'div',
				'helpers'            => $this->helpers,
				'wrapper_attributes' => $this->wrapper( 'product', $is_block, array( 'oha-deals-product--' . $layout ) ),
			)
		);
	}

	/**
	 * Offers table.
	 *
	 * @param array<string, mixed> $attributes Normalised attributes.
	 * @param bool                 $is_block   Block context.
	 * @return string
	 */
	private function offers( array $attributes, bool $is_block ): string {
		$identifier = self::identifier( $attributes );
		if ( '' === $identifier ) {
			return $this->notice( 'offers', $is_block, __( 'Choose a product (slug or EAN).', 'oha-deals' ) );
		}

		$product = $this->repository->product( $identifier );
		if ( is_wp_error( $product ) ) {
			return $this->error( 'offers', $is_block, $product );
		}

		// The limit applies to new goods; used and refurbished offers are appended with their own cap.
		$offers = array_slice( $product->offers(), 0, (int) $attributes['limit'] );
		if ( $attributes['includeUsed'] ) {
			$offers = array_merge( $offers, array_slice( $product->used_offers(), 0, self::USED_OFFERS_LIMIT ) );
		}
		if ( ! $offers ) {
			return $this->notice( 'offers', $is_block, __( 'There are no current offers for this product.', 'oha-deals' ) );
		}

		$amazon_prices = false;
		foreach ( $offers as $offer ) {
			if ( $offer->is_amazon() && ! $offer->is_price_hidden() ) {
				$amazon_prices = true;
				break;
			}
		}

		$this->helpers->start_element( $this->offer_urls( $offers, $product ) );

		return $this->renderer->render(
			'offers-table',
			array(
				'product'            => $product,
				'offers'             => $offers,
				'attributes'         => $attributes,
				'show_shipping'      => (bool) $attributes['showShipping'],
				'show_title'         => (bool) $attributes['showTitle'],
				'amazon_prices'      => $amazon_prices,
				'amazon_links'       => $this->helpers->links_to_amazon( $offers, $product ),
				'helpers'            => $this->helpers,
				'wrapper_attributes' => $this->wrapper( 'offers', $is_block ),
			)
		);
	}

	/**
	 * Deals grid.
	 *
	 * @param array<string, mixed> $attributes Normalised attributes.
	 * @param bool                 $is_block   Block context.
	 * @return string
	 */
	private function deals( array $attributes, bool $is_block ): string {
		$deals = $this->repository->deals( $attributes );
		if ( is_wp_error( $deals ) ) {
			return $this->error( 'deals', $is_block, $deals );
		}
		// The request asks for priced rows only; drop any that still arrive without a price.
		$deals = array_values(
			array_filter(
				$deals,
				static function ( Deal $deal ): bool {
					return ! $deal->product()->is_price_hidden();
				}
			)
		);
		if ( ! $deals ) {
			return $this->notice( 'deals', $is_block, __( 'No deals match these settings right now.', 'oha-deals' ) );
		}

		$amazon_prices = false;
		foreach ( $deals as $deal ) {
			if ( $deal->product()->is_best_price_amazon() ) {
				$amazon_prices = true;
				break;
			}
		}

		$this->helpers->start_element(
			array_map(
				function ( Deal $deal ): string {
					return $this->helpers->product_url( $deal->product() );
				},
				$deals
			)
		);

		return $this->renderer->render(
			'deals-grid',
			array(
				'deals'              => $deals,
				'attributes'         => $attributes,
				'columns'            => (int) $attributes['columns'],
				'amazon_prices'      => $amazon_prices,
				'helpers'            => $this->helpers,
				'wrapper_attributes' => $this->wrapper( 'deals', $is_block, array( 'oha-deals-deals--columns-' . (int) $attributes['columns'] ) ),
			)
		);
	}

	/**
	 * Product comparison: products side by side, or one per row ("Top 5").
	 *
	 * @param array<string, mixed> $attributes Normalised attributes.
	 * @param bool                 $is_block   Block context.
	 * @return string
	 */
	private function comparison( array $attributes, bool $is_block ): string {
		if ( ! $attributes['slugs'] ) {
			return $this->notice( 'comparison', $is_block, __( 'Choose the products to compare.', 'oha-deals' ) );
		}

		$products = $this->repository->products( $attributes['slugs'] );
		if ( is_wp_error( $products ) ) {
			return $this->error( 'comparison', $is_block, $products );
		}
		if ( ! $products ) {
			return $this->notice( 'comparison', $is_block, __( 'None of these products is listed on oha.deals.', 'oha-deals' ) );
		}

		$urls          = array();
		$amazon_prices = false;
		$amazon_links  = false;
		foreach ( $products as $product ) {
			$best          = $product->best_offer();
			$urls[]        = $this->helpers->best_url( $product );
			$amazon_prices = $amazon_prices || $product->is_best_price_amazon();
			$amazon_links  = $amazon_links || $this->helpers->links_to_amazon( null !== $best ? array( $best ) : array(), $product );
		}
		$this->helpers->start_element( $urls );

		return $this->renderer->render(
			'comparison-table',
			array(
				'products'           => $products,
				'attributes'         => $attributes,
				'fields'             => $attributes['fields'],
				'orientation'        => $attributes['orientation'],
				'best_index'         => $attributes['highlightBest'] ? self::best_index( $products ) : null,
				'spec_rows'          => in_array( 'specs', $attributes['fields'], true ) ? self::spec_rows( $products ) : array(),
				'title'              => $attributes['title'],
				'show_numbers'       => $attributes['numbered'] && 'rows' === $attributes['orientation'],
				'button_text'        => $attributes['buttonText'],
				'amazon_prices'      => $amazon_prices,
				'amazon_links'       => $amazon_links,
				'helpers'            => $this->helpers,
				'wrapper_attributes' => $this->wrapper( 'comparison', $is_block, array( 'oha-deals-comparison--' . $attributes['orientation'] ) ),
			)
		);
	}

	/**
	 * The index of the product with the lowest visible price.
	 *
	 * @param Product[] $products Products.
	 * @return int|null Null when fewer than two products have a visible price or the lowest price is a tie.
	 */
	private static function best_index( array $products ): ?int {
		$prices = array();
		foreach ( $products as $index => $product ) {
			$price = $product->price();
			if ( null !== $price && ! $product->is_price_hidden() ) {
				$prices[ $index ] = $price;
			}
		}
		if ( count( $prices ) < 2 ) {
			return null;
		}
		$cheapest = array_keys( $prices, min( $prices ), true );

		return 1 === count( $cheapest ) ? (int) $cheapest[0] : null;
	}

	/**
	 * Specification rows: labels that at least two products share, in the order of the products' own lists.
	 *
	 * @param Product[] $products Products.
	 * @return array<int, array{label: string, values: list<string>}> Values line up with the products; '' when missing.
	 */
	private static function spec_rows( array $products ): array {
		$rows   = array();
		$counts = array();
		foreach ( $products as $product ) {
			foreach ( array_keys( $product->attrs() ) as $label ) {
				$counts[ $label ] = ( $counts[ $label ] ?? 0 ) + 1;
			}
		}
		foreach ( $counts as $label => $count ) {
			if ( $count < 2 ) {
				continue;
			}
			$values = array();
			foreach ( $products as $product ) {
				$values[] = $product->attrs()[ $label ] ?? '';
			}
			$rows[] = array(
				'label'  => (string) $label,
				'values' => $values,
			);
			if ( count( $rows ) >= self::SPEC_ROWS_LIMIT ) {
				break;
			}
		}

		return $rows;
	}

	/**
	 * Price history chart.
	 *
	 * @param array<string, mixed> $attributes Normalised attributes.
	 * @param bool                 $is_block   Block context.
	 * @return string
	 */
	private function price_history( array $attributes, bool $is_block ): string {
		$identifier = self::identifier( $attributes );
		if ( '' === $identifier ) {
			return $this->notice( 'price-history', $is_block, __( 'Choose a product (slug or EAN).', 'oha-deals' ) );
		}

		$product = $this->repository->product( $identifier, array( 'history' ), (int) $attributes['days'] );
		if ( is_wp_error( $product ) ) {
			return $this->error( 'price-history', $is_block, $product );
		}
		if ( $product->is_omitted( 'history' ) ) {
			return $this->notice( 'price-history', $is_block, __( 'Price history needs an oha.deals plan with the history capability.', 'oha-deals' ) );
		}

		$this->helpers->start_element( array() );

		return $this->renderer->render(
			'price-history',
			array(
				'product'            => $product,
				'attributes'         => $attributes,
				'days'               => (int) $attributes['days'],
				'chart'              => $this->chart->render( $product->history(), $product->name(), (int) $attributes['height'], $product->currency(), (int) $attributes['days'] ),
				'helpers'            => $this->helpers,
				'wrapper_attributes' => $this->wrapper( 'price-history', $is_block ),
			)
		);
	}

	/**
	 * Coupon list.
	 *
	 * @param array<string, mixed> $attributes Normalised attributes.
	 * @param bool                 $is_block   Block context.
	 * @return string
	 */
	private function coupons( array $attributes, bool $is_block ): string {
		$coupons = $this->repository->coupons( (string) $attributes['host'], (int) $attributes['limit'] );
		if ( is_wp_error( $coupons ) ) {
			return $this->error( 'coupons', $is_block, $coupons );
		}
		$today   = wp_date( 'Y-m-d' );
		$coupons = array_values(
			array_filter(
				$coupons,
				static function ( Coupon $coupon ) use ( $today ): bool {
					$until = $coupon->valid_until();

					return null === $until || substr( $until, 0, 10 ) >= $today;
				}
			)
		);
		if ( ! $coupons ) {
			return $this->notice( 'coupons', $is_block, __( 'There are no coupons for this shop right now.', 'oha-deals' ) );
		}

		$this->helpers->start_element( array_map( array( $this->helpers, 'coupon_url' ), $coupons ) );
		// The copy button. Enqueued here, so that shortcodes and theme overrides of the template get it as well.
		wp_enqueue_script_module( self::COUPONS_MODULE );

		return $this->renderer->render(
			'coupons-list',
			array(
				'coupons'            => $coupons,
				'attributes'         => $attributes,
				'helpers'            => $this->helpers,
				'wrapper_attributes' => $this->wrapper( 'coupons', $is_block ),
			)
		);
	}

	/**
	 * The best price as inline text.
	 *
	 * @param array<string, mixed> $attributes Normalised attributes.
	 * @param bool                 $is_block   Block context (not used by a block in 1.0, kept for oha_deals_render()).
	 * @return string
	 */
	private function price( array $attributes, bool $is_block ): string {
		$identifier = self::identifier( $attributes );
		if ( '' === $identifier ) {
			return $this->notice( 'price', $is_block, __( 'Choose a product (slug or EAN).', 'oha-deals' ) );
		}

		$product = $this->repository->product( $identifier );
		if ( is_wp_error( $product ) ) {
			return $this->error( 'price', $is_block, $product );
		}
		if ( $product->is_price_hidden() ) {
			return $this->notice( 'price', $is_block, __( 'This product has no price that may be shown here.', 'oha-deals' ) );
		}

		$this->helpers->start_element( array() );

		return $this->renderer->render(
			'price',
			array(
				'product'            => $product,
				'attributes'         => $attributes,
				'amazon_prices'      => $product->is_best_price_amazon(),
				'helpers'            => $this->helpers,
				'wrapper_attributes' => 'class="oha-deals oha-deals-price"',
			)
		);
	}

	/**
	 * The outgoing URLs of some offers.
	 *
	 * @param Offer[] $offers  Offers.
	 * @param Product $product Product.
	 * @return string[]
	 */
	private function offer_urls( array $offers, Product $product ): array {
		return array_map(
			function ( Offer $offer ) use ( $product ): string {
				return $this->helpers->offer_url( $offer, $product );
			},
			$offers
		);
	}

	/**
	 * The product identifier: slug wins over EAN.
	 *
	 * @param array<string, mixed> $attributes Normalised attributes.
	 * @return string
	 */
	private static function identifier( array $attributes ): string {
		$slug = trim( (string) ( $attributes['slug'] ?? '' ) );

		return '' !== $slug ? $slug : trim( (string) ( $attributes['ean'] ?? '' ) );
	}

	/**
	 * The wrapper attribute string, already escaped.
	 *
	 * @param string   $element  Element name.
	 * @param bool     $is_block Block context.
	 * @param string[] $extra    Extra classes.
	 * @return string
	 */
	private function wrapper( string $element, bool $is_block, array $extra = array() ): string {
		$classes = implode( ' ', array_filter( array_map( 'sanitize_html_class', array_merge( array( 'oha-deals', 'oha-deals-' . $element ), $extra ) ) ) );

		if ( $is_block ) {
			$extra_attributes = array( 'class' => $classes );
			// WordPress 7.0 and newer add the anchor themselves; passing it as well would print it twice there.
			if ( '' !== $this->anchor && ! function_exists( 'wp_apply_anchor_support' ) ) {
				$extra_attributes['id'] = $this->anchor;
			}
			$attributes = get_block_wrapper_attributes( $extra_attributes );
			if ( '' !== $attributes ) {
				return $attributes;
			}
		}

		return 'class="' . esc_attr( $classes ) . '"';
	}

	/**
	 * A notice for users who can edit posts; nothing for everyone else.
	 *
	 * @param string $element  Element name.
	 * @param bool   $is_block Block context.
	 * @param string $message  Plain-text message.
	 * @param bool   $key      Whether a missing or rejected API key is the reason. The editor then offers the settings.
	 * @return string
	 */
	private function notice( string $element, bool $is_block, string $message, bool $key = false ): string {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}

		return $this->renderer->render(
			'notice',
			array(
				'message'            => $message,
				'tag'                => 'price' === $element || ( ! $is_block && 'product' === $element ) ? 'span' : 'div',
				'wrapper_attributes' => $this->wrapper( $element, $is_block, $key ? array( 'oha-deals-notice', 'oha-deals-notice--key' ) : array( 'oha-deals-notice' ) ),
			)
		);
	}

	/**
	 * A notice for an API error.
	 *
	 * @param string   $element  Element name.
	 * @param bool     $is_block Block context.
	 * @param WP_Error $error    Error.
	 * @return string
	 */
	private function error( string $element, bool $is_block, WP_Error $error ): string {
		switch ( $error->get_error_code() ) {
			case 'oha_deals_no_key':
				$message = $this->no_key_message();
				break;
			case 'oha_deals_not_found':
				$message = __( 'This product is not listed on oha.deals.', 'oha-deals' );
				break;
			case 'oha_deals_unauthorized':
			case 'oha_deals_forbidden':
				$message = __( 'The oha.deals API key was rejected. Check it under Settings → oha.deals.', 'oha-deals' );
				break;
			case 'oha_deals_rate_limited':
				$message = __( 'The oha.deals rate limit was reached. Prices will show again shortly.', 'oha-deals' );
				break;
			case 'oha_deals_invalid_request':
				$message = $error->get_error_message();
				break;
			default:
				$message = __( 'Prices could not be loaded from oha.deals right now.', 'oha-deals' );
		}

		return $this->notice( $element, $is_block, $message, in_array( $error->get_error_code(), self::KEY_ERRORS, true ) );
	}

	/**
	 * The "no API key" message.
	 *
	 * @return string
	 */
	private function no_key_message(): string {
		return __( 'Add your oha.deals API key under Settings → oha.deals.', 'oha-deals' );
	}
}
