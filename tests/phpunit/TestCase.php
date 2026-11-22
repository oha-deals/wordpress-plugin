<?php
/**
 * Base test case: configured API key and a mocked Partner API.
 *
 * @package OhaDeals
 */

namespace OhaDeals\Tests;

use OhaDeals\Plugin;
use OhaDeals\Settings\Options;
use WP_UnitTestCase;

/**
 * Routes Partner API requests to fixtures through the `pre_http_request` filter and records every request.
 */
abstract class TestCase extends WP_UnitTestCase {

	/**
	 * A syntactically valid test key.
	 */
	const KEY = 'oha_test_abcdefghijklmnopqrstuvwxyz012345';

	/**
	 * Mocked responses keyed by API path (without the base), each a list consumed in order; the last one repeats.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	protected $routes = array();

	/**
	 * Recorded requests: `url`, `path`, `query`, `args`.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	protected $requests = array();

	/**
	 * Sets the API key and installs the HTTP mock.
	 */
	public function set_up() {
		parent::set_up();

		$this->routes   = array();
		$this->requests = array();
		update_option( Options::OPTION, array_merge( Options::defaults(), array( 'api_key' => self::KEY ) ) );
		Plugin::instance()->reset();
		add_filter( 'pre_http_request', array( $this, 'mock_http' ), 10, 3 );
	}

	/**
	 * Resets the services so the next test starts clean.
	 */
	public function tear_down() {
		Plugin::instance()->reset();
		parent::tear_down();
	}

	/**
	 * Loads a JSON fixture as an array.
	 *
	 * @param string $name File name without extension.
	 * @return array<string, mixed>
	 */
	protected function fixture( $name ) {
		$json = file_get_contents( __DIR__ . '/fixtures/' . $name . '.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		return json_decode( $json, true );
	}

	/**
	 * Adds a mocked response.
	 *
	 * @param string               $path    API path, e.g. `/products/synology-ds925-plus`.
	 * @param array<string, mixed> $body    Decoded body.
	 * @param int                  $status  HTTP status.
	 * @param array<string, mixed> $headers Response headers (lowercase names).
	 * @return void
	 */
	protected function respond( $path, array $body, $status = 200, array $headers = array() ) {
		$this->routes[ $path ][] = array(
			'body'    => $body,
			'status'  => $status,
			'headers' => $headers,
		);
	}

	/**
	 * Adds an API error response.
	 *
	 * @param string $path   API path.
	 * @param int    $status HTTP status.
	 * @param string $code   API error code.
	 * @param array  $headers Response headers.
	 * @return void
	 */
	protected function respond_error( $path, $status, $code, array $headers = array() ) {
		$this->respond(
			$path,
			array(
				'error' => array(
					'code'    => $code,
					'message' => 'Mocked ' . $code,
					'status'  => $status,
				),
			),
			$status,
			$headers
		);
	}

	/**
	 * Requests sent to a path.
	 *
	 * @param string $path API path.
	 * @return array<int, array<string, mixed>>
	 */
	protected function requests_to( $path ) {
		return array_values(
			array_filter(
				$this->requests,
				static function ( $request ) use ( $path ) {
					return $request['path'] === $path;
				}
			)
		);
	}

	/**
	 * `pre_http_request` callback.
	 *
	 * @param false|array|\WP_Error $pre  Short-circuit value.
	 * @param array                 $args Request arguments.
	 * @param string                $url  URL.
	 * @return array|\WP_Error
	 */
	public function mock_http( $pre, $args, $url ) {
		if ( false !== $pre ) {
			return $pre;
		}
		$base  = Options::DEFAULT_API_BASE;
		$path  = (string) wp_parse_url( $url, PHP_URL_PATH );
		$path  = substr( $path, strlen( (string) wp_parse_url( $base, PHP_URL_PATH ) ) );
		$query = array();
		wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );

		$this->requests[] = array(
			'url'   => $url,
			'path'  => $path,
			'query' => $query,
			'args'  => $args,
		);

		if ( empty( $this->routes[ $path ] ) ) {
			return $this->http_response(
				404,
				array(
					'error' => array(
						'code'    => 'not_found',
						'message' => 'Not found',
						'status'  => 404,
					),
				),
				array()
			);
		}

		$response = count( $this->routes[ $path ] ) > 1 ? array_shift( $this->routes[ $path ] ) : $this->routes[ $path ][0];
		if ( isset( $response['body']['__transport_error'] ) ) {
			return new \WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );
		}

		return $this->http_response( $response['status'], $response['body'], $response['headers'] );
	}

	/**
	 * Builds a response array in the shape of wp_remote_get().
	 *
	 * @param int                  $status  Status.
	 * @param array<string, mixed> $body    Body.
	 * @param array<string, mixed> $headers Headers.
	 * @return array<string, mixed>
	 */
	private function http_response( $status, array $body, array $headers ) {
		return array(
			'headers'  => array_merge( array( 'x-request-id' => 'req_test' ), $headers ),
			'body'     => wp_json_encode( $body ),
			'response' => array(
				'code'    => $status,
				'message' => get_status_header_desc( $status ),
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Logs in a user with a role.
	 *
	 * @param string $role Role.
	 * @return void
	 */
	protected function login_as( $role ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}
}
