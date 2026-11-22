<?php
/**
 * Tests for uninstall.php.
 *
 * @package OhaDeals
 */

namespace OhaDeals\Tests;

use OhaDeals\Admin\OnboardingNotice;
use OhaDeals\Api\Cache;
use OhaDeals\Settings\Options;

/**
 * Tests for Uninstall.
 *
 * @coversNothing
 */
class UninstallTest extends TestCase {

	/**
	 * Stores data the uninstaller should remove, runs uninstall.php and flushes the object cache.
	 *
	 * @return void
	 */
	private function seed_and_uninstall() {
		update_option( Cache::VERSION_OPTION, '3' );
		update_user_option( 1, OnboardingNotice::USER_OPTION, '1' );
		set_transient( 'oha_deals_' . md5( 'x' ), array( 'data' => 1 ), HOUR_IN_SECONDS );
		set_transient( 'unrelated_transient', 'keep', HOUR_IN_SECONDS );
		wp_schedule_single_event( time() + 60, Cache::REFRESH_HOOK, array( '/me', array() ) );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'oha-deals/oha-deals.php' );
		}
		require dirname( __DIR__, 2 ) . '/uninstall.php';
		wp_cache_flush();
	}

	public function test_uninstall_removes_options_transients_and_cron() {
		$this->seed_and_uninstall();

		$this->assertFalse( get_option( Options::OPTION ) );
		$this->assertFalse( get_option( Cache::VERSION_OPTION ) );
		$this->assertFalse( get_user_option( OnboardingNotice::USER_OPTION, 1 ) );
		$this->assertFalse( get_transient( 'oha_deals_' . md5( 'x' ) ) );
		$this->assertSame( 'keep', get_transient( 'unrelated_transient' ) );
		$this->assertFalse( wp_next_scheduled( Cache::REFRESH_HOOK, array( '/me', array() ) ) );
	}

	public function test_uninstall_keeps_everything_when_delete_data_is_off() {
		update_option(
			Options::OPTION,
			array_merge(
				Options::defaults(),
				array(
					'api_key'                  => self::KEY,
					'delete_data_on_uninstall' => false,
				)
			)
		);

		$this->seed_and_uninstall();

		$this->assertIsArray( get_option( Options::OPTION ) );
		$this->assertSame( '3', get_option( Cache::VERSION_OPTION ) );
		$this->assertSame( '1', get_user_option( OnboardingNotice::USER_OPTION, 1 ) );
		$this->assertSame( array( 'data' => 1 ), get_transient( 'oha_deals_' . md5( 'x' ) ) );
	}
}
