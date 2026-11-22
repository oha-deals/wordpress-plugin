<?php
/**
 * Data queries for developers.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Api;

use OhaDeals\Model\Coupon;
use OhaDeals\Model\Deal;
use OhaDeals\Model\Offer;
use OhaDeals\Model\Product;
use OhaDeals\Render\Helpers;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Behind oha_deals_query(): returns cached API data as plain arrays.
 *
 * Adds `link` and `link_is_own` to products, offers and coupons, so that custom code links the same way the plugin's
 * own elements do.
 */
final class Query {

	/**
	 * Most products one `products` query loads.
	 */
	const MAX_PRODUCTS = 20;

	/**
	 * Data access.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Template helpers (links).
	 *
	 * @var Helpers
	 */
	private Helpers $helpers;

	/**
	 * Constructor.
	 *
	 * @param Repository $repository Data access.
	 * @param Helpers    $helpers    Template helpers.
	 */
	public function __construct( Repository $repository, Helpers $helpers ) {
		$this->repository = $repository;
		$this->helpers    = $helpers;
	}

	/**
	 * Runs a query.
	 *
	 * @param string               $type One of `product`, `products`, `search`, `deals`, `coupons`, `categories`.
	 * @param array<string, mixed> $args Arguments, see oha_deals_query().
	 * @return array<int|string, mixed>|WP_Error
	 */
	public function run( string $type, array $args = array() ) {
		switch ( $type ) {
			case 'product':
				$parts  = array_filter( (array) ( $args['include'] ?? array() ), 'is_string' );
				$result = $this->repository->product( self::identifier( $args ), $parts, self::integer( $args, 'days', 90 ) );

				return is_wp_error( $result ) ? $result : $this->product( $result );

			case 'products':
				$result = $this->repository->products( self::words( $args['slugs'] ?? array() ), self::MAX_PRODUCTS );

				return is_wp_error( $result ) ? $result : array_map( array( $this, 'product' ), $result );

			case 'search':
				$result = $this->repository->search( self::text( $args, 'q' ), self::integer( $args, 'limit', 20 ), self::text( $args, 'category' ) );

				return is_wp_error( $result ) ? $result : array_map( array( $this, 'product' ), $result );

			case 'deals':
				$result = $this->repository->deals( $args );

				return is_wp_error( $result ) ? $result : array_map( array( $this, 'deal' ), $result );

			case 'coupons':
				$result = $this->repository->coupons( self::text( $args, 'host' ), self::integer( $args, 'limit', 40 ) );

				return is_wp_error( $result ) ? $result : array_map( array( $this, 'coupon' ), $result );

			case 'categories':
				return $this->repository->categories();
		}

		return new WP_Error(
			'oha_deals_invalid_request',
			__( 'Unknown query type. Use product, products, search, deals, coupons or categories.', 'oha-deals' ),
			array( 'status' => 400 )
		);
	}

	/**
	 * A product with links.
	 *
	 * @param Product $product Product.
	 * @return array<string, mixed>
	 */
	private function product( Product $product ): array {
		$with_link = function ( Offer $offer ) use ( $product ): array {
			return $offer->to_array() + $this->link( $this->helpers->offer_url( $offer, $product ) );
		};

		$data                = $product->to_array();
		$data['offers']      = array_map( $with_link, $product->offers() );
		$data['used_offers'] = array_map( $with_link, $product->used_offers() );

		return $data + $this->link( $this->helpers->best_url( $product ) );
	}

	/**
	 * A deal with links.
	 *
	 * @param Deal $deal Deal.
	 * @return array<string, mixed>
	 */
	private function deal( Deal $deal ): array {
		$data            = $deal->to_array();
		$data['product'] = $this->product( $deal->product() );

		return $data;
	}

	/**
	 * A coupon with its link.
	 *
	 * @param Coupon $coupon Coupon.
	 * @return array<string, mixed>
	 */
	private function coupon( Coupon $coupon ): array {
		return $coupon->to_array() + $this->link( $this->helpers->coupon_url( $coupon ) );
	}

	/**
	 * Link fields.
	 *
	 * @param string $url URL, or an empty string.
	 * @return array{link: string, link_is_own: bool}
	 */
	private function link( string $url ): array {
		return array(
			'link'        => $url,
			'link_is_own' => $this->helpers->is_own_link( $url ),
		);
	}

	/**
	 * The product identifier: slug wins over EAN.
	 *
	 * @param array<string, mixed> $args Arguments.
	 * @return string
	 */
	private static function identifier( array $args ): string {
		$slug = self::text( $args, 'slug' );

		return '' !== $slug ? $slug : self::text( $args, 'ean' );
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

	/**
	 * An integer argument.
	 *
	 * @param array<string, mixed> $args     Arguments.
	 * @param string               $name     Name.
	 * @param int                  $fallback Fallback.
	 * @return int
	 */
	private static function integer( array $args, string $name, int $fallback ): int {
		return isset( $args[ $name ] ) && is_numeric( $args[ $name ] ) ? (int) $args[ $name ] : $fallback;
	}

	/**
	 * A list argument: an array or a comma-separated string.
	 *
	 * @param mixed $value Value.
	 * @return string[]
	 */
	private static function words( $value ): array {
		$words = array();
		foreach ( is_array( $value ) ? $value : explode( ',', is_scalar( $value ) ? (string) $value : '' ) as $word ) {
			$word = is_scalar( $word ) ? trim( (string) $word ) : '';
			if ( '' !== $word ) {
				$words[] = $word;
			}
		}

		return $words;
	}
}
