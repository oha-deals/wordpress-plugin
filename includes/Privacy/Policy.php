<?php
/**
 * Suggested privacy policy text.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Privacy;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a section to Settings → Privacy → Policy Guide.
 */
final class Policy {

	/**
	 * Hooks into the admin.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'add_content' ) );
	}

	/**
	 * Registers the suggested text.
	 *
	 * @return void
	 */
	public function add_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$paragraphs = array(
			__( 'This site shows prices from the oha.deals price comparison. The prices are loaded by this site\'s server, so viewing a page sends no data about you to oha.deals for the prices themselves.', 'oha-deals' ),
			__( 'Product images are loaded by your browser directly from the image hosts named by oha.deals (the oha.deals image server and shop image servers). Those hosts receive your IP address and browser details, as with any embedded image. The site owner can turn product images off.', 'oha-deals' ),
			__( 'Links either lead to the price comparison on oha.deals or, where the site owner has entered affiliate IDs, directly to Amazon or through the affiliate network Awin to the shop. When you follow a link, you leave this site, and the site you reach may process data such as cookies under its own privacy policy.', 'oha-deals' ),
			__( 'From oha.deals you can continue to the shops; oha.deals may earn a commission on purchases. Direct links to Amazon and to Awin shops are affiliate links of this site, which may earn a commission on purchases.', 'oha-deals' ),
		);

		$content = '';
		foreach ( $paragraphs as $paragraph ) {
			$content .= '<p>' . esc_html( $paragraph ) . '</p>';
		}

		wp_add_privacy_policy_content( __( 'oha.deals – Price Comparison & Deals', 'oha-deals' ), wp_kses_post( $content ) );
	}
}
