<?php
/**
 * Composition root.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals;

use OhaDeals\Admin\OnboardingNotice;
use OhaDeals\Admin\SettingsPage;
use OhaDeals\Admin\SiteHealth;
use OhaDeals\Affiliate\AmazonProvider;
use OhaDeals\Affiliate\AwinProvider;
use OhaDeals\Affiliate\LinkResolver;
use OhaDeals\Api\Cache;
use OhaDeals\Api\Client;
use OhaDeals\Api\Query;
use OhaDeals\Api\Repository;
use OhaDeals\Blocks\Bindings;
use OhaDeals\Blocks\Patterns;
use OhaDeals\Blocks\Registry as BlockRegistry;
use OhaDeals\Cli\Command;
use OhaDeals\Privacy\Policy;
use OhaDeals\Render\Elements;
use OhaDeals\Render\Formatter;
use OhaDeals\Render\Helpers;
use OhaDeals\Render\HistoryChart;
use OhaDeals\Render\Renderer;
use OhaDeals\Render\StructuredData;
use OhaDeals\Render\TemplateLoader;
use OhaDeals\Rest\EditorController;
use OhaDeals\Settings\Options;
use OhaDeals\Shortcodes\Registry as ShortcodeRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the services lazily and wires them to WordPress hooks.
 */
final class Plugin {

	/**
	 * The instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Settings.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Response cache, built on first use.
	 *
	 * @var Cache|null
	 */
	private ?Cache $cache = null;

	/**
	 * Data access, built on first use.
	 *
	 * @var Repository|null
	 */
	private ?Repository $repository = null;

	/**
	 * Formatter, built on first use.
	 *
	 * @var Formatter|null
	 */
	private ?Formatter $formatter = null;

	/**
	 * Link resolver, built on first use.
	 *
	 * @var LinkResolver|null
	 */
	private ?LinkResolver $links = null;

	/**
	 * Element renderer, built on first use.
	 *
	 * @var Elements|null
	 */
	private ?Elements $elements = null;

	/**
	 * Use instance().
	 */
	private function __construct() {
		$this->options = new Options();
	}

	/**
	 * The plugin instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Adds the hooks (on `plugins_loaded`).
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_assets' ), 5 );
		add_action( 'init', array( $this, 'register_content' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest' ) );
		add_action( Cache::REFRESH_HOOK, array( $this, 'refresh' ), 10, 2 );
		add_action( 'add_option_' . Options::OPTION, array( $this, 'disable_autoload' ) );
		add_action( 'add_option_' . Options::OPTION, array( $this, 'reset' ) );
		add_action( 'update_option_' . Options::OPTION, array( $this, 'settings_updated' ), 10, 2 );

		if ( is_admin() ) {
			( new SettingsPage( $this->options, $this->repository(), $this->formatter() ) )->register();
			( new OnboardingNotice( $this->options ) )->register();
			( new SiteHealth( $this->options, $this->repository() ) )->register();
			( new Policy() )->register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			( new Command( $this->options, $this->repository(), $this->cache(), $this->formatter() ) )->register();
		}

		/**
		 * Fires when oha.deals has added its hooks.
		 *
		 * @since 1.0.0
		 *
		 * @param Plugin $plugin The plugin instance.
		 */
		do_action( 'oha_deals_loaded', $this );
	}

	/**
	 * Deactivation: removes scheduled refreshes. Settings and cache stay until uninstall.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		wp_unschedule_hook( Cache::REFRESH_HOOK );
	}

	/**
	 * Registers the front-end stylesheet (referenced by handle from block.json).
	 *
	 * @return void
	 */
	public function register_assets(): void {
		wp_register_style( Renderer::STYLE_HANDLE, OHA_DEALS_URL . 'assets/css/frontend.css', array(), OHA_DEALS_VERSION );
		wp_style_add_data( Renderer::STYLE_HANDLE, 'path', OHA_DEALS_DIR . 'assets/css/frontend.css' );
	}

	/**
	 * Registers blocks, shortcodes, the bindings source and the block patterns.
	 *
	 * @return void
	 */
	public function register_content(): void {
		$elements = function (): Elements {
			return $this->elements();
		};
		( new BlockRegistry( $elements, OHA_DEALS_DIR . 'build/blocks' ) )->register();
		( new ShortcodeRegistry( $elements ) )->register();
		$this->bindings()->register();
		( new Patterns( OHA_DEALS_DIR . 'patterns' ) )->register();
	}

	/**
	 * Registers the REST routes.
	 *
	 * @return void
	 */
	public function register_rest(): void {
		( new EditorController( $this->repository(), $this->cache(), $this->formatter(), $this->options, $this->bindings() ) )->register_routes();
	}

	/**
	 * Cron callback for background refreshes.
	 *
	 * @param mixed $path  API path.
	 * @param mixed $query Query arguments.
	 * @return void
	 */
	public function refresh( $path, $query = array() ): void {
		if ( is_string( $path ) && '' !== $path ) {
			$this->cache()->refresh( $path, is_array( $query ) ? $query : array() );
		}
	}

	/**
	 * Keeps the settings option out of autoload when it is first created.
	 *
	 * @return void
	 */
	public function disable_autoload(): void {
		wp_set_option_autoload( Options::OPTION, false );
	}

	/**
	 * Drops the built services so the next use picks up changed settings (affiliate IDs, link options).
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->cache      = null;
		$this->repository = null;
		$this->links      = null;
		$this->elements   = null;
	}

	/**
	 * After the settings change: rebuild the services and, when the API key changed, invalidate the cache
	 * (responses depend on the key's capabilities, e.g. Amazon prices and history).
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $value     New option value.
	 * @return void
	 */
	public function settings_updated( $old_value, $value ): void {
		$old_key = is_array( $old_value ) ? (string) ( $old_value['api_key'] ?? '' ) : '';
		$new_key = is_array( $value ) ? (string) ( $value['api_key'] ?? '' ) : '';

		$this->reset();
		if ( $old_key !== $new_key ) {
			$this->cache()->flush();
		}
	}

	/**
	 * Settings.
	 *
	 * @return Options
	 */
	public function options(): Options {
		return $this->options;
	}

	/**
	 * Response cache (with its API client).
	 *
	 * @return Cache
	 */
	public function cache(): Cache {
		if ( null === $this->cache ) {
			$this->cache = new Cache( new Client( $this->options ), $this->options );
		}

		return $this->cache;
	}

	/**
	 * Data access.
	 *
	 * @return Repository
	 */
	public function repository(): Repository {
		if ( null === $this->repository ) {
			$this->repository = new Repository( $this->cache() );
		}

		return $this->repository;
	}

	/**
	 * Formatter for the current locale.
	 *
	 * @return Formatter
	 */
	public function formatter(): Formatter {
		if ( null === $this->formatter ) {
			$this->formatter = new Formatter();
		}

		return $this->formatter;
	}

	/**
	 * Link resolver with the configured affiliate providers.
	 *
	 * @return LinkResolver
	 */
	public function links(): LinkResolver {
		if ( null === $this->links ) {
			$providers = array(
				new AmazonProvider( $this->options->amazon_tag() ),
				new AwinProvider( $this->options->awin_publisher_id() ),
			);

			/**
			 * Filters the affiliate link providers, tried in order. Each must implement OhaDeals\Affiliate\Provider.
			 *
			 * @since 1.0.0
			 *
			 * @param \OhaDeals\Affiliate\Provider[] $providers Providers.
			 */
			$providers = (array) apply_filters( 'oha_deals_affiliate_providers', $providers );

			$this->links = new LinkResolver( $providers );
		}

		return $this->links;
	}

	/**
	 * Template helpers.
	 *
	 * @return Helpers
	 */
	private function helpers(): Helpers {
		return new Helpers( $this->options, $this->formatter(), $this->links() );
	}

	/**
	 * Block Bindings source.
	 *
	 * @return Bindings
	 */
	private function bindings(): Bindings {
		return new Bindings(
			$this->options,
			function (): Repository {
				return $this->repository();
			},
			function (): Helpers {
				return $this->helpers();
			}
		);
	}

	/**
	 * Data queries behind oha_deals_query().
	 *
	 * @return Query
	 */
	public function query(): Query {
		return new Query( $this->repository(), $this->helpers() );
	}

	/**
	 * Element renderer shared by blocks, shortcodes and oha_deals_render().
	 *
	 * @return Elements
	 */
	public function elements(): Elements {
		if ( null === $this->elements ) {
			$this->elements = new Elements(
				$this->repository(),
				new Renderer( new TemplateLoader( OHA_DEALS_DIR . 'templates' ) ),
				$this->helpers(),
				new HistoryChart( $this->formatter() ),
				new StructuredData(),
				$this->options
			);
		}

		return $this->elements;
	}
}
