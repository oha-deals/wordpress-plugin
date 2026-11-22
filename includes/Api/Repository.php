<?php
/**
 * Typed access to the Partner API endpoints.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Api;

use OhaDeals\Model\Coupon;
use OhaDeals\Model\Deal;
use OhaDeals\Model\Product;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Turns cached API responses into models.
 */
final class Repository {

	/**
	 * Allowed history ranges in days.
	 */
	const HISTORY_DAYS = array( 30, 90, 180, 365 );

	/**
	 * Most products that are loaded for one comparison (block, shortcode, editor route).
	 */
	const MAX_COMPARED = 6;

	/**
	 * Response cache.
	 *
	 * @var Cache
	 */
	private Cache $cache;

	/**
	 * Constructor.
	 *
	 * @param Cache $cache Response cache.
	 */
	public function __construct( Cache $cache ) {
		$this->cache = $cache;
	}

	/**
	 * Splits what an editor typed into an identifier: an oha.deals product URL, an EAN or a slug.
	 *
	 * @param string $input Slug, EAN or `https://oha.deals/produkt/<slug>` URL.
	 * @return array{type: string, value: string}|null Type `ean` or `slug`; null when unusable.
	 */
	public static function parse_identifier( string $input ): ?array {
		$input = trim( $input );

		if ( preg_match( '#^https?://(?:www\.)?oha\.deals/produkt/([a-z0-9][a-z0-9-]*)#i', $input, $match ) ) {
			return array(
				'type'  => 'slug',
				'value' => strtolower( $match[1] ),
			);
		}
		if ( preg_match( '/^[0-9]{8,14}$/', $input ) ) {
			return array(
				'type'  => 'ean',
				'value' => $input,
			);
		}
		$slug = sanitize_title( $input );
		if ( '' !== $slug && preg_match( '/^[a-z0-9][a-z0-9-]*$/', $slug ) ) {
			return array(
				'type'  => 'slug',
				'value' => $slug,
			);
		}

		return null;
	}

	/**
	 * Loads a product by slug or EAN (an EAN is resolved through `/products/lookup` first).
	 *
	 * @param string   $slug_or_ean Slug, EAN or oha.deals product URL.
	 * @param string[] $parts       Optional parts to include: `history`, `similar`, `variants`.
	 * @param int      $days        History range in days (30, 90, 180 or 365).
	 * @return Product|WP_Error
	 */
	public function product( string $slug_or_ean, array $parts = array(), int $days = 90 ) {
		$id = self::parse_identifier( $slug_or_ean );
		if ( null === $id ) {
			return new WP_Error( 'oha_deals_invalid_request', __( 'Enter a product slug, an EAN or an oha.deals product URL.', 'oha-deals' ), array( 'status' => 400 ) );
		}

		$slug = $id['value'];
		if ( 'ean' === $id['type'] ) {
			$summary = $this->lookup_ean( $id['value'] );
			if ( is_wp_error( $summary ) ) {
				return $summary;
			}
			$slug = $summary->slug();
		}

		$parts = array_values( array_intersect( array( 'history', 'similar', 'variants' ), $parts ) );
		$query = array( 'include' => implode( ',', $parts ) );
		if ( in_array( 'history', $parts, true ) ) {
			$query['days'] = in_array( $days, self::HISTORY_DAYS, true ) ? $days : 90;
		}

		$response = $this->cache->get( '/products/' . rawurlencode( $slug ), $query );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->single( $response );
	}

	/**
	 * Loads several products in the given order. Each one is cached on its own.
	 *
	 * Products that are not listed are left out, and a product that is named twice (by slug and by EAN, say) appears
	 * once.
	 *
	 * @param string[] $identifiers Slugs, EANs or oha.deals product URLs.
	 * @param int      $max         Most products to load.
	 * @return Product[]|WP_Error An error only when no product could be loaded for a reason other than "not listed".
	 */
	public function products( array $identifiers, int $max = self::MAX_COMPARED ) {
		$products = array();
		$failure  = null;
		foreach ( array_slice( array_values( array_unique( $identifiers ) ), 0, max( 0, $max ) ) as $identifier ) {
			$product = $this->product( $identifier );
			if ( ! is_wp_error( $product ) ) {
				$products[ $product->slug() ] = $product;
			} elseif ( ! in_array( $product->get_error_code(), array( 'oha_deals_not_found', 'oha_deals_invalid_request' ), true ) ) {
				$failure = $product;
			}
		}

		return ! $products && null !== $failure ? $failure : array_values( $products );
	}

	/**
	 * Resolves an EAN to a product summary.
	 *
	 * @param string $ean EAN / GTIN.
	 * @return Product|WP_Error
	 */
	public function lookup_ean( string $ean ) {
		$response = $this->cache->get( '/products/lookup', array( 'ean' => $ean ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->single( $response );
	}

	/**
	 * Searches products.
	 *
	 * @param string $query    Search term (at most 200 characters are sent).
	 * @param int    $limit    1–50.
	 * @param string $category Optional category slug.
	 * @return Product[]|WP_Error
	 */
	public function search( string $query, int $limit = 20, string $category = '' ) {
		$response = $this->cache->get(
			'/products/search',
			array(
				'q'        => mb_substr( trim( $query ), 0, 200 ),
				'limit'    => max( 1, min( 50, $limit ) ),
				'category' => self::sanitize_category( $category ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array_map(
			static function ( array $item ): Product {
				return Product::from_array( $item );
			},
			self::objects( $response['data'] )
		);
	}

	/**
	 * Lists deals.
	 *
	 * @param array<string, mixed> $args `mode`, `sort`, `category`, `minPrice`, `maxPrice`, `limit` (1–100), `offset`.
	 * @return Deal[]|WP_Error
	 */
	public function deals( array $args = array() ) {
		$query = array(
			'mode'     => in_array( $args['mode'] ?? '', array( 'all', 'drop', 'low' ), true ) ? $args['mode'] : 'all',
			'sort'     => in_array( $args['sort'] ?? '', array( 'percent', 'amount', 'price', 'new' ), true ) ? $args['sort'] : 'percent',
			'category' => self::sanitize_category( (string) ( $args['category'] ?? '' ) ),
			'limit'    => max( 1, min( 100, (int) ( $args['limit'] ?? 20 ) ) ),
			'offset'   => max( 0, (int) ( $args['offset'] ?? 0 ) ),
			// Only rows with a price this key may show; filtered by the server before paging.
			'priced'   => 1,
		);

		$min = self::price_bound( $args['minPrice'] ?? null );
		$max = self::price_bound( $args['maxPrice'] ?? null );
		if ( null !== $min && null !== $max && $min > $max ) {
			$min = null;
			$max = null;
		}
		if ( null !== $min ) {
			$query['minPrice'] = number_format( $min, 2, '.', '' );
		}
		if ( null !== $max ) {
			$query['maxPrice'] = number_format( $max, 2, '.', '' );
		}
		if ( 0 === $query['offset'] ) {
			unset( $query['offset'] );
		}

		$response = $this->cache->get( '/deals', $query );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array_map(
			static function ( array $item ): Deal {
				return Deal::from_array( $item );
			},
			self::objects( $response['data'] )
		);
	}

	/**
	 * Lists coupons.
	 *
	 * @param string $host  Optional shop host, e.g. `alternate.de`. A host that is not valid is an error, never "all shops".
	 * @param int    $limit 1–100.
	 * @return Coupon[]|WP_Error
	 */
	public function coupons( string $host = '', int $limit = 40 ) {
		$clean_host = self::sanitize_host( $host );
		if ( '' !== trim( $host ) && '' === $clean_host ) {
			return new WP_Error( 'oha_deals_invalid_request', __( 'The shop host is not valid. Use a domain such as alternate.de.', 'oha-deals' ), array( 'status' => 400 ) );
		}

		$response = $this->cache->get(
			'/coupons',
			array(
				'host'  => $clean_host,
				'limit' => max( 1, min( 100, $limit ) ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array_map(
			static function ( array $item ): Coupon {
				return Coupon::from_array( $item );
			},
			self::objects( $response['data'] )
		);
	}

	/**
	 * Lists categories.
	 *
	 * @return array<int, array{slug: string, name: string, count: int}>|WP_Error
	 */
	public function categories() {
		$response = $this->cache->get( '/categories' );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$categories = array();
		foreach ( self::objects( $response['data'] ) as $item ) {
			$slug = isset( $item['slug'] ) && is_string( $item['slug'] ) ? sanitize_title( $item['slug'] ) : '';
			if ( '' === $slug ) {
				continue;
			}
			$categories[] = array(
				'slug'  => $slug,
				'name'  => isset( $item['name'] ) && is_scalar( $item['name'] ) ? (string) $item['name'] : $slug,
				'count' => isset( $item['count'] ) && is_numeric( $item['count'] ) ? (int) $item['count'] : 0,
			);
		}

		return $categories;
	}

	/**
	 * Describes the API key: tier, capabilities and rate limits.
	 *
	 * @param bool $fresh Bypass the cache (used by "Test connection").
	 * @return array{keyId: string, tier: string, name: string, site: string, capabilities: array<string, bool>, rateLimit: array{perMinute: int, perDay: int}}|WP_Error
	 */
	public function me( bool $fresh = false ) {
		$response = $this->cache->get( '/me', array(), $fresh );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return self::key_data( $response['data'] );
	}

	/**
	 * The last known description of the API key, without any request.
	 *
	 * @return array{fetched_at: int, me: array{keyId: string, tier: string, name: string, site: string, capabilities: array<string, bool>, rateLimit: array{perMinute: int, perDay: int}}}|null Null when nothing is cached.
	 */
	public function cached_me(): ?array {
		$entry = $this->cache->peek( '/me' );
		if ( null === $entry ) {
			return null;
		}

		return array(
			'fetched_at' => $entry['fetched_at'],
			'me'         => self::key_data( $entry['data'] ),
		);
	}

	/**
	 * Reads a `/me` payload.
	 *
	 * @param mixed $data Payload.
	 * @return array{keyId: string, tier: string, name: string, site: string, capabilities: array<string, bool>, rateLimit: array{perMinute: int, perDay: int}}
	 */
	private static function key_data( $data ): array {
		$data         = is_array( $data ) ? $data : array();
		$capabilities = array();
		foreach ( (array) ( $data['capabilities'] ?? array() ) as $name => $value ) {
			if ( is_string( $name ) ) {
				// Keeps the API's camelCase names (`ownLinks`); sanitize_key() would lower-case them.
				$capabilities[ (string) preg_replace( '/[^A-Za-z0-9_-]/', '', $name ) ] = true === $value;
			}
		}
		$rate = isset( $data['rateLimit'] ) && is_array( $data['rateLimit'] ) ? $data['rateLimit'] : array();

		return array(
			'keyId'        => isset( $data['keyId'] ) && is_scalar( $data['keyId'] ) ? (string) $data['keyId'] : '',
			'tier'         => isset( $data['tier'] ) && is_string( $data['tier'] ) ? sanitize_key( $data['tier'] ) : '',
			'name'         => isset( $data['name'] ) && is_string( $data['name'] ) ? $data['name'] : '',
			'site'         => isset( $data['site'] ) && is_string( $data['site'] ) ? $data['site'] : '',
			'capabilities' => $capabilities,
			'rateLimit'    => array(
				'perMinute' => isset( $rate['perMinute'] ) ? (int) $rate['perMinute'] : 0,
				'perDay'    => isset( $rate['perDay'] ) ? (int) $rate['perDay'] : 0,
			),
		);
	}

	/**
	 * Lowercase host name without scheme or path, or an empty string.
	 *
	 * @param string $host Raw host or URL.
	 * @return string
	 */
	public static function sanitize_host( string $host ): string {
		$host = strtolower( trim( $host ) );
		if ( false !== strpos( $host, '://' ) ) {
			$host = (string) wp_parse_url( $host, PHP_URL_HOST );
		}
		$host = (string) preg_replace( '/^www\./', '', $host );

		return preg_match( '/^[a-z0-9.-]+\.[a-z]{2,}$/', $host ) ? $host : '';
	}

	/**
	 * A category slug in the server's format (lowercase words joined by hyphens), or an empty string.
	 *
	 * @param string $category Raw slug.
	 * @return string
	 */
	public static function sanitize_category( string $category ): string {
		$category = strtolower( trim( $category ) );

		return preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $category ) ? $category : '';
	}

	/**
	 * A price filter value rounded to cents and clamped to 0–9,999,999, or null when not set.
	 *
	 * @param mixed $value Raw value.
	 * @return float|null
	 */
	private static function price_bound( $value ): ?float {
		if ( ! is_numeric( $value ) || (float) $value <= 0 ) {
			return null;
		}

		return min( 9999999.0, round( (float) $value, 2 ) );
	}

	/**
	 * Builds a product from a single-object response.
	 *
	 * @param array{data: mixed, meta: array<string, mixed>} $response Response.
	 * @return Product|WP_Error
	 */
	private function single( array $response ) {
		if ( ! is_array( $response['data'] ) || empty( $response['data']['slug'] ) ) {
			return new WP_Error( 'oha_deals_internal', __( 'The oha.deals API sent an unexpected response.', 'oha-deals' ), array( 'status' => 502 ) );
		}

		return Product::from_array( $response['data'], $response['meta'] );
	}

	/**
	 * The array entries of a list payload; anything that is not an object is skipped.
	 *
	 * @param mixed $data Payload.
	 * @return array<int, array<string, mixed>>
	 */
	private static function objects( $data ): array {
		if ( ! is_array( $data ) ) {
			return array();
		}

		return array_values( array_filter( $data, 'is_array' ) );
	}
}
