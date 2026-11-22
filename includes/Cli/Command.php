<?php
/**
 * WP-CLI commands.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Cli;

use OhaDeals\Admin\KeyInfo;
use OhaDeals\Api\Cache;
use OhaDeals\Api\Repository;
use OhaDeals\Render\Formatter;
use OhaDeals\Settings\Options;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Inspect the oha.deals connection, flush the cache and look up products.
 */
final class Command {

	/**
	 * Settings.
	 *
	 * @var Options
	 */
	private Options $options;

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
	 * Constructor.
	 *
	 * @param Options    $options    Settings.
	 * @param Repository $repository Data access.
	 * @param Cache      $cache      Response cache.
	 * @param Formatter  $formatter  Formatter.
	 */
	public function __construct( Options $options, Repository $repository, Cache $cache, Formatter $formatter ) {
		$this->options    = $options;
		$this->repository = $repository;
		$this->cache      = $cache;
		$this->formatter  = $formatter;
	}

	/**
	 * Registers the commands.
	 *
	 * @return void
	 */
	public function register(): void {
		WP_CLI::add_command( 'oha-deals status', array( $this, 'status' ) );
		WP_CLI::add_command( 'oha-deals cache flush', array( $this, 'cache_flush' ) );
		WP_CLI::add_command( 'oha-deals product', array( $this, 'product' ) );
		WP_CLI::add_command( 'oha-deals deals', array( $this, 'deals' ) );
	}

	/**
	 * Shows the configuration and tests the API key (bypasses the cache).
	 *
	 * ## EXAMPLES
	 *
	 *     wp oha-deals status
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function status( $args, $assoc_args ): void {
		unset( $args, $assoc_args );

		$key = $this->options->api_key();
		WP_CLI::log( 'API base:      ' . $this->options->api_base() );
		WP_CLI::log( 'API key:       ' . ( '' !== $key ? Options::mask_key( $key ) : 'not set' ) . ( $this->options->is_api_key_constant() ? ' (OHA_DEALS_API_KEY)' : '' ) );
		WP_CLI::log( 'Cache TTL:     ' . $this->options->cache_ttl() . ' s' );
		WP_CLI::log( 'Cache version: ' . Cache::version() );

		if ( '' === $key ) {
			WP_CLI::error( 'No API key configured.' );
		}

		$me = $this->repository->me( true );
		if ( is_wp_error( $me ) ) {
			WP_CLI::error( $me->get_error_message() );
			return;
		}

		$capabilities = KeyInfo::active_capabilities( $me['capabilities'] );
		WP_CLI::log( 'Plan:          ' . KeyInfo::plan( $me['tier'] ) );
		WP_CLI::log( 'Capabilities:  ' . ( $capabilities ? implode( ', ', $capabilities ) : 'none' ) );
		WP_CLI::log( 'Rate limit:    ' . $me['rateLimit']['perMinute'] . '/min, ' . $me['rateLimit']['perDay'] . '/day' );
		WP_CLI::success( 'Connected.' );
	}

	/**
	 * Invalidates all cached API responses.
	 *
	 * ## EXAMPLES
	 *
	 *     wp oha-deals cache flush
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function cache_flush( $args, $assoc_args ): void {
		unset( $args, $assoc_args );

		$this->cache->flush();
		WP_CLI::success( 'Cache flushed (version ' . Cache::version() . ').' );
	}

	/**
	 * Shows a product and its offers.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : Product slug, EAN or oha.deals product URL.
	 *
	 * [--format=<format>]
	 * : `table` lists the offers, `json` prints the whole product as oha_deals_query() returns it.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp oha-deals product synology-ds925-plus
	 *     wp oha-deals product 4711174725762 --format=json
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function product( $args, $assoc_args ): void {
		$product = $this->repository->product( (string) ( $args[0] ?? '' ) );
		if ( is_wp_error( $product ) ) {
			WP_CLI::error( $product->get_error_message() );
			return;
		}

		if ( 'json' === ( $assoc_args['format'] ?? 'table' ) ) {
			WP_CLI::log( self::json( oha_deals_query( 'product', array( 'slug' => $product->slug() ) ) ) );
			return;
		}

		$price = $product->price();
		WP_CLI::log( $product->name() . ' (' . $product->slug() . ')' );
		WP_CLI::log( 'Best price: ' . ( null !== $price ? $this->formatter->money( $price, $product->currency() ) . ' at ' . (string) $product->shop() : 'hidden or unavailable' ) );

		$rows = array();
		foreach ( array_merge( $product->offers(), $product->used_offers() ) as $offer ) {
			$offer_price = $offer->price();
			$shipping    = $offer->shipping();
			$rows[]      = array(
				'shop'      => $offer->shop_name(),
				'price'     => null !== $offer_price ? number_format( $offer_price, 2, '.', '' ) : 'hidden',
				'shipping'  => null !== $shipping ? number_format( $shipping, 2, '.', '' ) : 'unknown',
				'condition' => $offer->condition(),
				'price_at'  => (string) $offer->price_at(),
			);
		}

		if ( $rows ) {
			\WP_CLI\Utils\format_items( 'table', $rows, array_keys( $rows[0] ) );
		} else {
			WP_CLI::log( 'No offers.' );
		}
	}

	/**
	 * Lists current deals.
	 *
	 * ## OPTIONS
	 *
	 * [--mode=<mode>]
	 * : Kind of deals.
	 * ---
	 * default: all
	 * options:
	 *   - all
	 *   - drop
	 *   - low
	 * ---
	 *
	 * [--sort=<sort>]
	 * : Order.
	 * ---
	 * default: percent
	 * options:
	 *   - percent
	 *   - amount
	 *   - price
	 *   - new
	 * ---
	 *
	 * [--category=<slug>]
	 * : Category slug.
	 *
	 * [--min-price=<price>]
	 * : Lowest price in EUR.
	 *
	 * [--max-price=<price>]
	 * : Highest price in EUR.
	 *
	 * [--limit=<number>]
	 * : Number of deals (1–100).
	 * ---
	 * default: 20
	 * ---
	 *
	 * [--format=<format>]
	 * : `table` and `csv` list the main columns, `json` prints the deals as oha_deals_query() returns them.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp oha-deals deals --mode=drop --limit=5
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function deals( $args, $assoc_args ): void {
		unset( $args );

		$query = array(
			'mode'     => $assoc_args['mode'] ?? 'all',
			'sort'     => $assoc_args['sort'] ?? 'percent',
			'category' => $assoc_args['category'] ?? '',
			'minPrice' => $assoc_args['min-price'] ?? null,
			'maxPrice' => $assoc_args['max-price'] ?? null,
			'limit'    => $assoc_args['limit'] ?? 20,
		);
		$deals = $this->repository->deals( $query );
		if ( is_wp_error( $deals ) ) {
			WP_CLI::error( $deals->get_error_message() );
			return;
		}

		$format = $assoc_args['format'] ?? 'table';
		if ( 'json' === $format ) {
			WP_CLI::log( self::json( oha_deals_query( 'deals', $query ) ) );
			return;
		}

		$rows = array();
		foreach ( $deals as $deal ) {
			$product = $deal->product();
			$price   = $product->price();
			$rows[]  = array(
				'slug'     => $product->slug(),
				'name'     => $product->name(),
				'price'    => null !== $price ? number_format( $price, 2, '.', '' ) : 'hidden',
				'discount' => null !== $product->discount_percent() ? $product->discount_percent() . '%' : '',
				'shop'     => (string) $product->shop(),
				'kind'     => $deal->kind(),
			);
		}
		if ( $rows ) {
			\WP_CLI\Utils\format_items( $format, $rows, array_keys( $rows[0] ) );
		} else {
			WP_CLI::log( 'No deals.' );
		}
	}

	/**
	 * Data as readable JSON.
	 *
	 * @param mixed $data Data.
	 * @return string
	 */
	private static function json( $data ): string {
		return (string) wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}
}
