<?php
/**
 * The "add your API key" notice.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Admin;

use OhaDeals\Settings\Options;

defined( 'ABSPATH' ) || exit;

/**
 * While no API key is set, shows one notice on the Dashboard and the Plugins screen to administrators, until each of
 * them dismisses it. Works without JavaScript.
 */
final class OnboardingNotice {

	/**
	 * `admin-post.php` action and nonce action.
	 */
	const ACTION = 'oha_deals_dismiss_welcome';

	/**
	 * User option (per site) that remembers the dismissal. Removed by uninstall.php.
	 */
	const USER_OPTION = 'oha_deals_welcome_dismissed';

	/**
	 * Screens that show the notice.
	 */
	const SCREENS = array( 'dashboard', 'plugins' );

	/**
	 * Settings.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Constructor.
	 *
	 * @param Options $options Settings.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Hooks into the admin.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'dismiss' ) );
	}

	/**
	 * Prints the notice where it is due.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! $this->is_due() ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p><strong>%1$s</strong> %2$s</p><p><a class="button button-primary" href="%3$s">%4$s</a> <a class="button" href="%5$s">%6$s</a></p></div>',
			esc_html__( 'oha.deals is active.', 'oha-deals' ),
			esc_html__( 'Add your API key to show prices, deals and coupons on your site.', 'oha-deals' ),
			esc_url( SettingsPage::url() ),
			esc_html__( 'Open the oha.deals settings', 'oha-deals' ),
			esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION ), self::ACTION ) ),
			esc_html__( 'Dismiss', 'oha-deals' )
		);
	}

	/**
	 * Handles the "Dismiss" link: remembers it for the current user and goes back.
	 *
	 * @return void
	 */
	public function dismiss(): void {
		check_admin_referer( self::ACTION );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'oha-deals' ), '', array( 'response' => 403 ) );
		}

		update_user_option( get_current_user_id(), self::USER_OPTION, '1' );

		$referer = wp_get_referer();
		wp_safe_redirect( false !== $referer ? $referer : admin_url( 'plugins.php' ) );
		exit;
	}

	/**
	 * Whether the notice is due: an administrator without dismissal, on one of the screens, and no API key.
	 *
	 * @return bool
	 */
	private function is_due(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return null !== $screen
			&& in_array( $screen->id, self::SCREENS, true )
			&& current_user_can( 'manage_options' )
			&& ! $this->options->has_api_key()
			&& ! get_user_option( self::USER_OPTION );
	}
}
