<?php
/**
 * Finds template files, honouring theme overrides.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Render;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves `<view>.php` from the child theme, the parent theme (`oha-deals/<view>.php`) or the plugin.
 */
final class TemplateLoader {

	/**
	 * Plugin template directory, with trailing slash.
	 *
	 * @var string
	 */
	private string $plugin_dir;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_dir Plugin template directory.
	 */
	public function __construct( string $plugin_dir ) {
		$this->plugin_dir = trailingslashit( $plugin_dir );
	}

	/**
	 * Absolute path of a view's template, or an empty string when none exists.
	 *
	 * @param string $view View name, e.g. `product-card`.
	 * @return string
	 */
	public function locate( string $view ): string {
		if ( ! preg_match( '/^[a-z0-9-]+$/', $view ) ) {
			return '';
		}

		$path = locate_template( array( 'oha-deals/' . $view . '.php' ) );
		if ( '' === $path ) {
			$path = $this->plugin_dir . $view . '.php';
		}

		/**
		 * Filters the template file used for a view.
		 *
		 * @since 1.0.0
		 *
		 * @param string $path Absolute path of the template file.
		 * @param string $view View name, e.g. `product-card`.
		 */
		$path = (string) apply_filters( 'oha_deals_template_path', $path, $view );

		return '' !== $path && is_readable( $path ) ? $path : '';
	}
}
