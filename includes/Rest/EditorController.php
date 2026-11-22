<?php
/**
 * REST proxy for the block editor and the settings page.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Rest;

use OhaDeals\Admin\KeyInfo;
use OhaDeals\Api\Cache;
use OhaDeals\Api\Repository;
use OhaDeals\Blocks\Bindings;
use OhaDeals\Model\Product;
use OhaDeals\Render\Formatter;
use OhaDeals\Settings\Options;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Routes under `oha-deals/v1`. The API key stays on the server; responses never contain it.
 */
final class EditorController {

	/**
	 * REST namespace.
	 */
	const ROUTE_NAMESPACE = 'oha-deals/v1';

	/**
	 * Data access.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Response cache.
	 *
	 * @var Cache
	 */
	private Cache $cache;

	/**
	 * Formatter.
	 *
	 * @var Formatter
	 */
	private Formatter $formatter;

	/**
	 * Settings.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Block Bindings source (values for the editor).
	 *
	 * @var Bindings
	 */
	private Bindings $bindings;

	/**
	 * Constructor.
	 *
	 * @param Repository $repository Data access.
	 * @param Cache      $cache      Response cache.
	 * @param Formatter  $formatter  Formatter.
	 * @param Options    $options    Settings.
	 * @param Bindings   $bindings   Block Bindings source.
	 */
	public function __construct( Repository $repository, Cache $cache, Formatter $formatter, Options $options, Bindings $bindings ) {
		$this->repository = $repository;
		$this->cache      = $cache;
		$this->formatter  = $formatter;
		$this->options    = $options;
		$this->bindings   = $bindings;
	}

	/**
	 * Registers the routes (on `rest_api_init`).
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'q'        => array(
						'type'              => 'string',
						'required'          => true,
						'minLength'         => 1,
						'maxLength'         => 200,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'limit'    => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 50,
						'default' => 20,
					),
					'category' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => static function ( $value ): string {
							return Repository::sanitize_category( (string) $value );
						},
					),
				),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/product',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'product' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'slug' => array(
						'type'              => 'string',
						'default'           => '',
						'maxLength'         => 300,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'ean'  => array(
						'type'    => 'string',
						'default' => '',
						'pattern' => '^([0-9]{8,14})?$',
					),
				),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/products',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'products' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'slugs' => array(
						'description'       => __( 'Comma-separated slugs, EANs or oha.deals product URLs (at most six are used).', 'oha-deals' ),
						'type'              => 'string',
						'required'          => true,
						'minLength'         => 1,
						'maxLength'         => 2000,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/categories',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'categories' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/connection',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'connection' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/cache/flush',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'flush' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	/**
	 * Permission: editors.
	 *
	 * @return bool
	 */
	public function can_edit(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Permission: administrators.
	 *
	 * @return bool
	 */
	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET /search.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function search( WP_REST_Request $request ) {
		$products = $this->repository->search( (string) $request['q'], (int) $request['limit'], (string) $request['category'] );
		if ( is_wp_error( $products ) ) {
			return self::rest_error( $products );
		}

		return new WP_REST_Response( array_map( array( $this, 'item' ), $products ) );
	}

	/**
	 * GET /product: resolves a slug, an EAN or an oha.deals product URL. The answer also carries the values of the Block
	 * Bindings source, as the front end prints them.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function product( WP_REST_Request $request ) {
		$identifier = '' !== (string) $request['slug'] ? (string) $request['slug'] : (string) $request['ean'];
		if ( '' === $identifier ) {
			return new WP_Error( 'oha_deals_invalid_request', __( 'Pass a slug or an EAN.', 'oha-deals' ), array( 'status' => 400 ) );
		}

		$product = $this->repository->product( $identifier );
		if ( is_wp_error( $product ) ) {
			return self::rest_error( $product );
		}

		return new WP_REST_Response( $this->item( $product ) + array( 'bindings' => $this->bindings->values( $product ) ) );
	}

	/**
	 * GET /products: several products in the requested order; unknown ones are left out.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function products( WP_REST_Request $request ) {
		$identifiers = array_map( 'trim', explode( ',', (string) $request['slugs'] ) );
		$products    = $this->repository->products( array_values( array_diff( $identifiers, array( '' ) ) ) );
		if ( is_wp_error( $products ) ) {
			return self::rest_error( $products );
		}

		return new WP_REST_Response( array_map( array( $this, 'item' ), $products ) );
	}

	/**
	 * GET /categories.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function categories() {
		$categories = $this->repository->categories();
		if ( is_wp_error( $categories ) ) {
			return self::rest_error( $categories );
		}

		return new WP_REST_Response( $categories );
	}

	/**
	 * POST /connection: calls `/me` without the cache.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function connection() {
		$me = $this->repository->me( true );
		if ( is_wp_error( $me ) ) {
			return self::rest_error( $me );
		}

		$capabilities = KeyInfo::active_capabilities( $me['capabilities'] );
		$message      = sprintf(
			/* translators: 1: plan name, 2: list of capabilities, 3: rate limit, e.g. "60 requests per minute, 10,000 per day". */
			__( 'Connected. Plan: %1$s. Included: %2$s. Rate limit: %3$s.', 'oha-deals' ),
			KeyInfo::plan( $me['tier'] ),
			$capabilities ? implode( wp_get_list_item_separator(), $capabilities ) : __( 'none', 'oha-deals' ),
			KeyInfo::rate_limit( $me['rateLimit']['perMinute'], $me['rateLimit']['perDay'] )
		);

		return new WP_REST_Response(
			array(
				'ok'           => true,
				'message'      => $message,
				'key'          => Options::mask_key( $this->options->api_key() ),
				'tier'         => $me['tier'],
				'capabilities' => $me['capabilities'],
				'rateLimit'    => $me['rateLimit'],
				'summary'      => KeyInfo::summary( $me, time(), $this->formatter ),
			)
		);
	}

	/**
	 * POST /cache/flush.
	 *
	 * @return WP_REST_Response
	 */
	public function flush(): WP_REST_Response {
		$this->cache->flush();

		return new WP_REST_Response(
			array(
				'ok'      => true,
				'message' => __( 'The cache was cleared. Prices are fetched again on the next page view.', 'oha-deals' ),
			)
		);
	}

	/**
	 * The picker item shape: slug, ean, name, brand, image, price, priceFormatted, shop, url.
	 *
	 * @param Product $product Product.
	 * @return array<string, mixed>
	 */
	public function item( Product $product ): array {
		$price = $product->is_price_hidden() ? null : $product->price();

		return array(
			'slug'           => $product->slug(),
			'ean'            => $product->ean(),
			'name'           => $product->name(),
			'brand'          => $product->brand(),
			'image'          => $product->image(),
			'price'          => $price,
			'priceFormatted' => null !== $price ? $this->formatter->money( $price, $product->currency() ) : null,
			'shop'           => $product->shop(),
			'url'            => $product->url(),
		);
	}

	/**
	 * Maps an API error onto a REST error. Upstream failures become 502; the message never contains the key.
	 *
	 * @param WP_Error $error API error.
	 * @return WP_Error
	 */
	private static function rest_error( WP_Error $error ): WP_Error {
		$upstream = (int) ( ( (array) $error->get_error_data() )['status'] ?? 0 );
		switch ( $error->get_error_code() ) {
			case 'oha_deals_not_found':
				$status = 404;
				break;
			case 'oha_deals_invalid_request':
				$status = 400;
				break;
			case 'oha_deals_no_key':
				$status = 503;
				break;
			default:
				$status = 502;
		}

		return new WP_Error(
			$error->get_error_code(),
			$error->get_error_message(),
			array(
				'status'   => $status,
				'upstream' => $upstream,
			)
		);
	}
}
