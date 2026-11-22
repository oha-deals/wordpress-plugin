<?php
/**
 * Tests for Admin\SiteHealth.
 *
 * @package OhaDeals
 */

namespace OhaDeals\Tests;

use OhaDeals\Admin\SiteHealth;
use OhaDeals\Plugin;
use OhaDeals\Settings\Options;

/**
 * Tests for SiteHealth.
 *
 * @covers \OhaDeals\Admin\SiteHealth
 */
class SiteHealthTest extends TestCase {

	/**
	 * Site Health integration under test.
	 *
	 * @return SiteHealth
	 */
	private function site_health() {
		return new SiteHealth( new Options(), Plugin::instance()->repository() );
	}

	public function test_debug_information_masks_the_key() {
		$info = $this->site_health()->debug_information( array() );

		$this->assertArrayHasKey( 'oha-deals', $info );
		$this->assertSame( 'oha_test_abc****', $info['oha-deals']['fields']['api_key']['value'] );
		$this->assertTrue( $info['oha-deals']['fields']['api_key']['private'] );
		$this->assertStringNotContainsString( self::KEY, wp_json_encode( $info ) );
	}

	public function test_connection_test_is_good_when_the_api_answers() {
		$this->respond( '/me', $this->fixture( 'me' ) );

		$result = $this->site_health()->test_connection();

		$this->assertSame( 'good', $result['status'] );
		$this->assertSame( 'oha_deals_connection', $result['test'] );
	}

	public function test_connection_test_is_critical_when_the_api_fails() {
		$this->respond_error( '/me', 401, 'unauthorized' );

		$result = $this->site_health()->test_connection();

		$this->assertSame( 'critical', $result['status'] );
		$this->assertStringNotContainsString( self::KEY, $result['description'] );
	}

	public function test_connection_test_recommends_a_key() {
		update_option( Options::OPTION, Options::defaults() );

		$result = $this->site_health()->test_connection();

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertCount( 0, $this->requests );
	}

	public function test_test_is_registered() {
		$tests = $this->site_health()->add_tests( array( 'direct' => array() ) );

		$this->assertArrayHasKey( 'oha_deals_connection', $tests['direct'] );
	}
}
