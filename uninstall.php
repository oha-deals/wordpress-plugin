<?php
/**
 * Uninstall: removes the plugin's options, cached responses, user options and scheduled events on every site,
 * unless a site turned off "Delete the settings and cached prices when the plugin is deleted".
 *
 * @package OhaDeals
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Guarded so the file can be loaded more than once in one request (tests).
if ( ! function_exists( 'oha_deals_uninstall_site' ) ) {
	/**
	 * Cleans up the current site.
	 *
	 * @return void
	 */
	function oha_deals_uninstall_site() {
		global $wpdb;

		$settings = get_option( 'oha_deals_settings', array() );
		if ( is_array( $settings ) && isset( $settings['delete_data_on_uninstall'] ) && ! $settings['delete_data_on_uninstall'] ) {
			return;
		}

		wp_unschedule_hook( 'oha_deals_refresh' );
		delete_option( 'oha_deals_settings' );
		delete_option( 'oha_deals_cache_version' );
		delete_metadata( 'user', 0, $wpdb->get_blog_prefix() . 'oha_deals_welcome_dismissed', '', true );

		// Cached responses are transients named oha_deals_<md5>. With a persistent object cache they expire on their own.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off cleanup of the plugin's own transients.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_oha_deals_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_oha_deals_' ) . '%'
			)
		);
	}
}

if ( is_multisite() ) {
	$oha_deals_offset = 0;
	do {
		$oha_deals_sites = get_sites(
			array(
				'fields' => 'ids',
				'number' => 100,
				'offset' => $oha_deals_offset,
			)
		);
		foreach ( $oha_deals_sites as $oha_deals_site_id ) {
			switch_to_blog( (int) $oha_deals_site_id );
			oha_deals_uninstall_site();
			restore_current_blog();
		}
		$oha_deals_offset += 100;
		$oha_deals_more    = 100 === count( $oha_deals_sites );
	} while ( $oha_deals_more );
} else {
	oha_deals_uninstall_site();
}
