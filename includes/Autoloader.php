<?php
/**
 * PSR-4 autoloader for the plugin's classes.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals;

defined( 'ABSPATH' ) || exit;

/**
 * Maps the `OhaDeals\` namespace onto the `includes/` directory.
 */
final class Autoloader {

	/**
	 * Namespace prefix, with trailing backslash.
	 *
	 * @var string
	 */
	private string $prefix;

	/**
	 * Base directory, with trailing slash.
	 *
	 * @var string
	 */
	private string $base_dir;

	/**
	 * Constructor.
	 *
	 * @param string $prefix   Namespace prefix, e.g. `OhaDeals\`.
	 * @param string $base_dir Directory that holds the classes of that namespace.
	 */
	private function __construct( string $prefix, string $base_dir ) {
		$this->prefix   = trim( $prefix, '\\' ) . '\\';
		$this->base_dir = rtrim( $base_dir, '/\\' ) . '/';
	}

	/**
	 * Registers an autoloader for one namespace prefix.
	 *
	 * @param string $prefix   Namespace prefix.
	 * @param string $base_dir Base directory.
	 * @return void
	 */
	public static function register( string $prefix, string $base_dir ): void {
		spl_autoload_register( array( new self( $prefix, $base_dir ), 'load' ) );
	}

	/**
	 * Loads the file for a class, if it belongs to this namespace.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @return void
	 */
	public function load( string $class_name ): void {
		if ( 0 !== strncmp( $class_name, $this->prefix, strlen( $this->prefix ) ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $this->prefix ) );
		if ( '' === $relative || ! preg_match( '/^[A-Za-z0-9_\\\\]+$/', $relative ) ) {
			return;
		}

		$file = $this->base_dir . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
