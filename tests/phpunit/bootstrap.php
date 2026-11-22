<?php
/**
 * PHPUnit bootstrap for the WordPress test suite (wp-env or a local install of the WP test library).
 *
 * @package OhaDeals
 */

$oha_deals_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $oha_deals_tests_dir ) {
	$oha_deals_tests_dir = getenv( 'WP_PHPUNIT__DIR' );
}
if ( ! $oha_deals_tests_dir ) {
	$oha_deals_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $oha_deals_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "Could not find the WordPress test library in {$oha_deals_tests_dir}. Set WP_TESTS_DIR or run the tests through wp-env." . PHP_EOL ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	exit( 1 );
}

$oha_deals_polyfills = dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills';
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) && is_dir( $oha_deals_polyfills ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $oha_deals_polyfills );
}

require_once $oha_deals_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__, 2 ) . '/oha-deals.php';
	}
);

require $oha_deals_tests_dir . '/includes/bootstrap.php';
require __DIR__ . '/TestCase.php';
