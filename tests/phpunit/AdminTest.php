<?php
/**
 * Tests for the settings page, the onboarding notice and the key descriptions.
 *
 * @package OhaDeals
 */

namespace OhaDeals\Tests;

use OhaDeals\Admin\KeyInfo;
use OhaDeals\Admin\OnboardingNotice;
use OhaDeals\Admin\SettingsPage;
use OhaDeals\Plugin;
use OhaDeals\Settings\Options;

/**
 * Tests for the admin screens.
 *
 * @covers \OhaDeals\Admin\KeyInfo
 * @covers \OhaDeals\Admin\OnboardingNotice
 * @covers \OhaDeals\Admin\SettingsPage
 */
class AdminTest extends TestCase {

	/**
	 * Logged in as an administrator.
	 */
	public function set_up() {
		parent::set_up();
		$this->login_as( 'administrator' );
	}

	/**
	 * Leaves the admin screens and forgets the settings sections.
	 */
	public function tear_down() {
		global $wp_settings_sections, $wp_settings_fields;
		unset( $wp_settings_sections[ SettingsPage::SLUG ], $wp_settings_fields[ SettingsPage::SLUG ] );
		unset( $_REQUEST['_wpnonce'] );
		set_current_screen( 'front' );
		wp_dequeue_script( SettingsPage::HANDLE );
		wp_dequeue_style( SettingsPage::HANDLE );
		parent::tear_down();
	}

	/**
	 * Updates settings on top of the test defaults.
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	private function settings( array $settings ) {
		update_option( Options::OPTION, array_merge( Options::defaults(), array( 'api_key' => self::KEY ), $settings ) );
	}

	/**
	 * The settings page.
	 *
	 * @return SettingsPage
	 */
	private function page() {
		return new SettingsPage( Plugin::instance()->options(), Plugin::instance()->repository(), Plugin::instance()->formatter() );
	}

	/**
	 * Rendered settings page.
	 *
	 * @return string
	 */
	private function render_page() {
		$page = $this->page();
		$page->register_settings();
		ob_start();
		$page->render();

		return (string) ob_get_clean();
	}

	public function test_the_page_is_for_administrators_only() {
		$this->login_as( 'editor' );

		$this->assertSame( '', $this->render_page() );
	}

	public function test_loading_the_page_never_calls_the_api() {
		$html = $this->render_page();

		$this->assertCount( 0, $this->requests );
		$this->assertStringContainsString( 'data-state="checking"', $html, 'Without a cached answer the script asks after load.' );
		$this->assertStringNotContainsString( self::KEY, $html );
		$this->assertStringContainsString( 'oha_test_abc****', $html );
	}

	public function test_a_cached_answer_is_shown_in_words() {
		$this->respond( '/me', $this->fixture( 'me' ) );
		Plugin::instance()->repository()->me();

		$html = $this->render_page();

		$this->assertCount( 1, $this->requests, 'Only the request made above.' );
		$this->assertStringContainsString( 'data-state="connected"', $html );
		$this->assertStringContainsString( 'Own affiliate links', $html );
		$this->assertStringContainsString( 'Amazon prices and images', $html );
		$this->assertStringContainsString( '60 requests per minute, 10,000 per day', $html );
		$this->assertStringNotContainsString( 'ownlinks', $html );
	}

	public function test_without_a_key_the_page_explains_how_to_get_one() {
		$this->settings( array( 'api_key' => '' ) );

		$html = $this->render_page();

		$this->assertStringContainsString( 'data-state="none"', $html );
		$this->assertStringContainsString( SettingsPage::KEY_URL, $html );
		$this->assertStringNotContainsString( 'data-oha-deals-action', $html );
	}

	public function test_every_setting_has_a_field_with_its_option_name() {
		$html = $this->render_page();

		foreach ( array_keys( Options::defaults() ) as $key ) {
			$this->assertStringContainsString( 'name="' . Options::OPTION . '[' . $key . ']"', $html, $key );
		}
		$this->assertStringContainsString( 'name="' . Options::OPTION . '[api_key_remove]"', $html );
		$this->assertStringContainsString( "name='option_page' value='" . SettingsPage::GROUP . "'", $html );
		$this->assertStringContainsString( 'name="_wpnonce"', $html );
	}

	public function test_the_commission_rule_is_stated() {
		$html = $this->render_page();

		$this->assertStringContainsString( 'Without your own IDs, links go to the product page on oha.deals, where visitors can continue to the shops and oha.deals may earn a commission.', $html );
		$this->assertStringContainsString( 'With your own IDs, the link goes straight to Amazon or Awin and the commission is yours.', $html );
	}

	/**
	 * Values and labels of the cache lifetime options, and the selected value.
	 *
	 * @param string $html Page.
	 * @return array{values: string[], selected: string}
	 */
	private function ttl_select( $html ) {
		preg_match( '#<select id="oha_deals_cache_ttl".*?</select>#s', $html, $select );
		preg_match_all( '#<option value="(\d+)"([^>]*)>#', $select[0], $options );
		$selected = array();
		foreach ( $options[2] as $index => $attributes ) {
			if ( false !== strpos( $attributes, 'selected' ) ) {
				$selected[] = $options[1][ $index ];
			}
		}
		$this->assertCount( 1, $selected );

		return array(
			'values'   => $options[1],
			'selected' => $selected[0],
		);
	}

	public function test_cache_lifetime_is_a_select() {
		$select = $this->ttl_select( $this->render_page() );

		$this->assertSame( array( '300', '900', '1800', '3600', '10800', '21600', '43200', '86400' ), $select['values'] );
		$this->assertSame( '3600', $select['selected'] );
	}

	public function test_a_stored_custom_lifetime_stays_selectable() {
		$this->settings( array( 'cache_ttl' => 2400 ) );

		$html   = $this->render_page();
		$select = $this->ttl_select( $html );

		$this->assertSame( array( '300', '900', '1800', '2400', '3600', '10800', '21600', '43200', '86400' ), $select['values'] );
		$this->assertSame( '2400', $select['selected'] );
		$this->assertStringContainsString( 'Custom: 2,400 seconds', $html );
	}

	public function test_assets_load_on_the_settings_page_only_and_without_inline_script() {
		$page = $this->page();
		set_current_screen( 'dashboard' );
		$page->add_page();

		$page->enqueue( 'index.php' );
		$this->assertFalse( wp_script_is( SettingsPage::HANDLE, 'enqueued' ) );

		$page->enqueue( (string) get_plugin_page_hook( SettingsPage::SLUG, 'options-general.php' ) );
		$this->assertTrue( wp_script_is( SettingsPage::HANDLE, 'enqueued' ) );
		$this->assertTrue( wp_style_is( SettingsPage::HANDLE, 'enqueued' ) );
		$this->assertFalse( wp_scripts()->get_data( SettingsPage::HANDLE, 'before' ) );
		$this->assertFalse( wp_scripts()->get_data( SettingsPage::HANDLE, 'after' ) );
		$this->assertStringNotContainsString( '<script', $this->render_page() );
		foreach ( array( 'settings.css', 'settings.js', 'mark.svg' ) as $file ) {
			$this->assertFileExists( OHA_DEALS_DIR . 'assets/admin/' . $file );
		}
	}

	public function test_help_tabs() {
		set_current_screen( 'settings_page_oha-deals' );

		$this->page()->add_help();

		$this->assertSame( array( 'oha_deals_overview', 'oha_deals_links' ), array_keys( get_current_screen()->get_help_tabs() ) );
	}

	/**
	 * Output of the onboarding notice on a screen.
	 *
	 * @param string $screen Screen ID.
	 * @return string
	 */
	private function notice( $screen ) {
		set_current_screen( $screen );
		ob_start();
		( new OnboardingNotice( Plugin::instance()->options() ) )->render();

		return (string) ob_get_clean();
	}

	public function test_the_notice_shows_without_a_key_on_two_screens_only() {
		$this->assertSame( '', $this->notice( 'plugins' ), 'Not with a key.' );

		$this->settings( array( 'api_key' => '' ) );
		foreach ( array( 'plugins', 'dashboard' ) as $screen ) {
			$html = $this->notice( $screen );
			$this->assertStringContainsString( 'notice-info', $html );
			$this->assertStringContainsString( 'options-general.php?page=oha-deals', $html );
		}
		foreach ( array( 'edit-post', 'options-general', 'settings_page_oha-deals', 'plugins-network' ) as $screen ) {
			$this->assertSame( '', $this->notice( $screen ), $screen );
		}

		$this->login_as( 'editor' );
		$this->assertSame( '', $this->notice( 'dashboard' ) );
	}

	public function test_dismissing_the_notice_is_remembered_per_user() {
		$this->settings( array( 'api_key' => '' ) );
		$redirect = '';
		add_filter(
			'wp_redirect',
			static function ( $location ) use ( &$redirect ) {
				$redirect = $location;
				throw new \RuntimeException( 'redirected' );
			}
		);
		$_REQUEST['_wpnonce'] = wp_create_nonce( OnboardingNotice::ACTION );

		try {
			( new OnboardingNotice( Plugin::instance()->options() ) )->dismiss();
			$this->fail( 'No redirect.' );
		} catch ( \RuntimeException $exception ) {
			$this->assertSame( 'redirected', $exception->getMessage() );
		}

		$this->assertSame( admin_url( 'plugins.php' ), $redirect );
		$this->assertSame( '', $this->notice( 'plugins' ) );
		$this->login_as( 'administrator' );
		$this->assertNotSame( '', $this->notice( 'plugins' ), 'Other administrators still see it.' );
	}

	public function test_dismissing_needs_the_nonce() {
		$_REQUEST['_wpnonce'] = 'wrong';

		$this->expectException( \WPDieException::class );
		( new OnboardingNotice( Plugin::instance()->options() ) )->dismiss();
	}

	public function test_dismissing_needs_the_capability() {
		$this->login_as( 'editor' );
		$_REQUEST['_wpnonce'] = wp_create_nonce( OnboardingNotice::ACTION );

		try {
			( new OnboardingNotice( Plugin::instance()->options() ) )->dismiss();
			$this->fail( 'Not stopped.' );
		} catch ( \WPDieException $exception ) {
			$this->assertFalse( get_user_option( OnboardingNotice::USER_OPTION ) );
		}
	}

	public function test_capability_labels() {
		$list = KeyInfo::capabilities(
			array(
				'ownLinks'   => true,
				'bulkExport' => true,
			)
		);

		$this->assertSame( array( 'Own affiliate links', 'Price history', 'Amazon prices and images', 'Bulk export' ), wp_list_pluck( $list, 'label' ) );
		$this->assertSame( array( true, false, false, true ), wp_list_pluck( $list, 'enabled' ) );
		$this->assertSame( array( 'Own affiliate links' ), KeyInfo::active_capabilities( array( 'ownLinks' => true ) ) );
	}

	public function test_plan_labels() {
		$this->assertSame( 'Pro', KeyInfo::plan( 'pro' ) );
		$this->assertSame( 'Unknown', KeyInfo::plan( '' ) );
		$this->assertSame( 'Enterprise', KeyInfo::plan( 'enterprise' ) );
	}

	public function test_the_summary_names_the_last_price_check() {
		update_option( 'date_format', 'Y-m-d' );
		update_option( 'time_format', 'H:i' );
		update_option( 'timezone_string', 'UTC' );
		$this->respond( '/me', $this->fixture( 'me' ) );
		$this->respond( '/products/synology-ds925-plus', $this->fixture( 'product' ) );
		$me = Plugin::instance()->repository()->me();
		oha_deals_get_product( 'synology-ds925-plus' );

		$summary = KeyInfo::summary( $me, (int) strtotime( '2026-11-22T10:00:00Z' ), Plugin::instance()->formatter() );

		$this->assertSame( '2026-11-20, 04:15', $summary['pricesCheckedAt'] );
		$this->assertSame( '2026-11-22, 10:00', $summary['checkedAt'] );
	}
}
