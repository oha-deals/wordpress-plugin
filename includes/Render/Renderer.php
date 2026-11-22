<?php
/**
 * Renders templates into strings.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Render;

defined( 'ABSPATH' ) || exit;

/**
 * Runs a template with an `$args` array and returns its output.
 */
final class Renderer {

	/**
	 * Front-end style handle.
	 */
	const STYLE_HANDLE = 'oha-deals-frontend';

	/**
	 * Template loader.
	 *
	 * @var TemplateLoader
	 */
	private TemplateLoader $loader;

	/**
	 * Constructor.
	 *
	 * @param TemplateLoader $loader Template loader.
	 */
	public function __construct( TemplateLoader $loader ) {
		$this->loader = $loader;
	}

	/**
	 * Renders a view.
	 *
	 * @param string               $view View name, e.g. `product-card`.
	 * @param array<string, mixed> $args Template arguments, available in the template as `$args`.
	 * @return string HTML; empty when the template is missing or prints nothing.
	 */
	public function render( string $view, array $args ): string {
		/**
		 * Filters the arguments passed to a template.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $args Template arguments.
		 * @param string               $view View name.
		 */
		$args = (array) apply_filters( 'oha_deals_template_args', $args, $view );

		$template = $this->loader->locate( $view );
		if ( '' === $template ) {
			return '';
		}

		/**
		 * Fires before a view is rendered (inside output buffering).
		 *
		 * @since 1.0.0
		 *
		 * @param string               $view View name.
		 * @param array<string, mixed> $args Template arguments.
		 */
		do_action( 'oha_deals_before_render', $view, $args );

		ob_start();
		self::include_template( $template, $args );

		/**
		 * Fires after a view is rendered (inside output buffering).
		 *
		 * @since 1.0.0
		 *
		 * @param string               $view View name.
		 * @param array<string, mixed> $args Template arguments.
		 */
		do_action( 'oha_deals_after_render', $view, $args );

		$html = trim( (string) ob_get_clean() );
		if ( '' !== $html ) {
			wp_enqueue_style( self::STYLE_HANDLE );
		}

		return $html;
	}

	/**
	 * Includes a template in an isolated scope where only `$args` is defined.
	 *
	 * @param string               $template Absolute path.
	 * @param array<string, mixed> $args     Template arguments.
	 * @return void
	 */
	private static function include_template( string $template, array $args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $args is read by the included template.
		include $template;
	}
}
