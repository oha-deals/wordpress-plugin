<?php
/**
 * Site Health test and debug information.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Admin;

use OhaDeals\Api\Cache;
use OhaDeals\Api\Repository;
use OhaDeals\Settings\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a "oha.deals connection" test and an "oha.deals" section to Site Health info. The API key is masked.
 */
final class SiteHealth {

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
	 * Constructor.
	 *
	 * @param Options    $options    Settings.
	 * @param Repository $repository Data access.
	 */
	public function __construct( Options $options, Repository $repository ) {
		$this->options    = $options;
		$this->repository = $repository;
	}

	/**
	 * Hooks into Site Health.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'site_status_tests', array( $this, 'add_tests' ) );
		add_filter( 'debug_information', array( $this, 'debug_information' ) );
	}

	/**
	 * Registers the direct test.
	 *
	 * @param array<string, mixed> $tests Tests.
	 * @return array<string, mixed>
	 */
	public function add_tests( $tests ): array {
		$tests = is_array( $tests ) ? $tests : array();

		$tests['direct']['oha_deals_connection'] = array(
			'label' => __( 'oha.deals connection', 'oha-deals' ),
			'test'  => array( $this, 'test_connection' ),
		);

		return $tests;
	}

	/**
	 * The test: a key is configured and `/me` answers. Uses the cache, so it does not count against the rate limit on
	 * every Site Health visit.
	 *
	 * @return array<string, mixed>
	 */
	public function test_connection(): array {
		$result = array(
			'label'       => __( 'oha.deals is connected', 'oha-deals' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'oha.deals', 'oha-deals' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( 'The API key is set and the oha.deals Partner API answers.', 'oha-deals' ) . '</p>',
			'actions'     => '',
			'test'        => 'oha_deals_connection',
		);

		$settings_link = sprintf(
			'<p><a href="%s">%s</a></p>',
			esc_url( SettingsPage::url() ),
			esc_html__( 'Open the oha.deals settings', 'oha-deals' )
		);

		if ( ! $this->options->has_api_key() ) {
			$result['label']       = __( 'oha.deals has no API key', 'oha-deals' );
			$result['status']      = 'recommended';
			$result['description'] = '<p>' . esc_html__( 'Without an API key the oha.deals blocks and shortcodes show nothing.', 'oha-deals' ) . '</p>';
			$result['actions']     = $settings_link;

			return $result;
		}

		$me = $this->repository->me();
		if ( is_wp_error( $me ) ) {
			$result['label']       = __( 'oha.deals cannot be reached', 'oha-deals' );
			$result['status']      = 'critical';
			$result['description'] = '<p>' . esc_html( $me->get_error_message() ) . '</p>'
				. '<p>' . esc_html__( 'Cached prices keep showing for a while; new products and expired prices cannot be loaded.', 'oha-deals' ) . '</p>';
			$result['actions']     = $settings_link;

			$result['badge']['color'] = 'red';
		}

		return $result;
	}

	/**
	 * Adds the plugin's section to Tools → Site Health → Info.
	 *
	 * @param array<string, mixed> $info Debug information.
	 * @return array<string, mixed>
	 */
	public function debug_information( $info ): array {
		$info = is_array( $info ) ? $info : array();
		$key  = $this->options->api_key();

		$info['oha-deals'] = array(
			'label'  => __( 'oha.deals', 'oha-deals' ),
			'fields' => array(
				'version'      => array(
					'label' => __( 'Version', 'oha-deals' ),
					'value' => OHA_DEALS_VERSION,
				),
				'api_key'      => array(
					'label'   => __( 'API key', 'oha-deals' ),
					'value'   => '' !== $key ? Options::mask_key( $key ) : __( 'Not set', 'oha-deals' ),
					'debug'   => '' !== $key ? 'set' : 'not set',
					'private' => true,
				),
				'key_source'   => array(
					'label' => __( 'API key source', 'oha-deals' ),
					'value' => $this->options->is_api_key_constant() ? 'OHA_DEALS_API_KEY' : __( 'Settings', 'oha-deals' ),
				),
				'api_base'     => array(
					'label' => __( 'API base URL', 'oha-deals' ),
					'value' => $this->options->api_base(),
				),
				'cache_ttl'    => array(
					'label' => __( 'Cache lifetime (seconds)', 'oha-deals' ),
					'value' => $this->options->cache_ttl(),
				),
				'cache'        => array(
					'label' => __( 'Cache version', 'oha-deals' ),
					'value' => Cache::version(),
				),
				'object_cache' => array(
					'label' => __( 'Persistent object cache', 'oha-deals' ),
					'value' => wp_using_ext_object_cache() ? __( 'Yes', 'oha-deals' ) : __( 'No', 'oha-deals' ),
				),
				'amazon_tag'   => array(
					'label' => __( 'Amazon tag', 'oha-deals' ),
					'value' => '' !== $this->options->amazon_tag() ? $this->options->amazon_tag() : __( 'Not set', 'oha-deals' ),
				),
				'awin'         => array(
					'label' => __( 'Awin publisher ID', 'oha-deals' ),
					'value' => '' !== $this->options->awin_publisher_id() ? $this->options->awin_publisher_id() : __( 'Not set', 'oha-deals' ),
				),
				'cron'         => array(
					'label' => __( 'Background refresh', 'oha-deals' ),
					'value' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? __( 'WP-Cron is disabled; a system cron must run wp-cron.php', 'oha-deals' ) : __( 'WP-Cron', 'oha-deals' ),
				),
			),
		);

		return $info;
	}
}
