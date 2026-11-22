<?php
/**
 * Plugin Name:       oha.deals – Price Comparison & Deals
 * Plugin URI:        https://github.com/oha-deals/wordpress-plugin
 * Description:       Live price comparisons, deals, coupons and price history from the oha.deals Partner API, as blocks and shortcodes.
 * Version:           1.0.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            oha.deals
 * Author URI:        https://oha.deals
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       oha-deals
 * Domain Path:       /languages
 *
 * @package OhaDeals
 */

// This file must parse on old PHP versions so the requirement notice can be shown: no typed syntax here.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OHA_DEALS_VERSION', '1.0.0' );
define( 'OHA_DEALS_FILE', __FILE__ );
define( 'OHA_DEALS_DIR', plugin_dir_path( __FILE__ ) );
define( 'OHA_DEALS_URL', plugin_dir_url( __FILE__ ) );
define( 'OHA_DEALS_MIN_PHP', '7.4' );

if ( version_compare( PHP_VERSION, OHA_DEALS_MIN_PHP, '<' ) ) {
	add_action( 'admin_notices', 'oha_deals_php_version_notice' );

	/**
	 * Tells administrators that the server's PHP version is too old.
	 *
	 * @return void
	 */
	function oha_deals_php_version_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: required PHP version, 2: current PHP version. */
					__( 'oha.deals needs PHP %1$s or newer. This server runs PHP %2$s, so the plugin is inactive.', 'oha-deals' ),
					OHA_DEALS_MIN_PHP,
					PHP_VERSION
				)
			)
		);
	}
	return;
}

require_once OHA_DEALS_DIR . 'includes/Autoloader.php';
require_once OHA_DEALS_DIR . 'includes/functions.php';

\OhaDeals\Autoloader::register( 'OhaDeals\\', OHA_DEALS_DIR . 'includes/' );

register_deactivation_hook( __FILE__, array( 'OhaDeals\Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( \OhaDeals\Plugin::instance(), 'register' ) );
