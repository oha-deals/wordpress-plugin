<?php
/**
 * HTTP client for the oha.deals Partner API.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Api;

use OhaDeals\Settings\Options;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Performs authenticated GET requests and unwraps the response envelope.
 *
 * The base URL and the key are read from the settings at request time, so filters added by a theme apply everywhere.
 * Never puts the API key into a URL, an error message or error data, and never follows redirects (the key would go
 * with them).
 */
final class Client {

	/**
	 * Request timeout in seconds for background work (cron refresh, admin, REST, WP-CLI).
	 */
	const TIMEOUT = 8;

	/**
	 * Request timeout in seconds while a visitor's page is being rendered.
	 */
	const QUICK_TIMEOUT = 4;

	/**
	 * Longest `Retry-After` (seconds) the client waits for before its single retry.
	 * Longer waits are returned as an error so the caller can back off.
	 */
	const MAX_INLINE_WAIT = 2;

	/**
	 * Settings (API key and base URL).
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
	 * Sends a GET request.
	 *
	 * @param string               $path  Path below the base URL, e.g. `/products/search`.
	 * @param array<string, mixed> $query Query arguments.
	 * @param bool                 $quick Visitor page view: short timeout and no retry.
	 * @return array{data: mixed, meta: array<string, mixed>}|WP_Error Unwrapped envelope, or an error whose
	 *         code is `oha_deals_<api error code>` and whose data holds `status` and, when sent, `retry_after`.
	 */
	public function get( string $path, array $query = array(), bool $quick = false ) {
		$key = $this->options->api_key();
		if ( '' === $key ) {
			return new WP_Error( 'oha_deals_no_key', __( 'No oha.deals API key is configured.', 'oha-deals' ), array( 'status' => 0 ) );
		}

		$url  = $this->url( $path, $query );
		$args = array(
			'timeout'     => $quick ? self::QUICK_TIMEOUT : self::TIMEOUT,
			'redirection' => 0,
			'user-agent'  => 'oha-deals-wordpress/' . OHA_DEALS_VERSION,
			'headers'     => array(
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $key,
				'X-Oha-Key'     => $key,
			),
		);

		/**
		 * Filters the arguments passed to wp_remote_get() for Partner API requests.
		 *
		 * Redirects stay disabled whatever this filter returns, so the key cannot be sent to another host.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $args Request arguments. The headers carry the API key.
		 * @param string               $url  Request URL (never contains the key).
		 */
		$args                = (array) apply_filters( 'oha_deals_request_args', $args, $url );
		$args['redirection'] = 0;

		$result = $this->attempt( $url, $args );
		if ( ! $quick && is_wp_error( $result ) && $this->should_retry( $result ) ) {
			$wait = (int) ( ( (array) $result->get_error_data() )['retry_after'] ?? 0 );
			if ( $wait > 0 ) {
				sleep( $wait );
			}
			$result = $this->attempt( $url, $args );
		}

		return $result;
	}

	/**
	 * Builds the request URL.
	 *
	 * @param string               $path  Path.
	 * @param array<string, mixed> $query Query arguments; null and empty-string values are dropped.
	 * @return string
	 */
	public function url( string $path, array $query = array() ): string {
		$query = array_filter(
			$query,
			static function ( $value ): bool {
				return null !== $value && '' !== $value;
			}
		);
		$url   = $this->options->api_base() . '/' . ltrim( $path, '/' );
		if ( $query ) {
			$url .= '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		}

		return $url;
	}

	/**
	 * Whether an error means the API is unreachable or failing (transport error or 5xx), as opposed to an answer
	 * about this request.
	 *
	 * @param WP_Error $error Error from get().
	 * @return bool
	 */
	public static function is_outage( WP_Error $error ): bool {
		if ( 'oha_deals_no_key' === $error->get_error_code() ) {
			return false;
		}
		$status = (int) ( ( (array) $error->get_error_data() )['status'] ?? 0 );

		return 0 === $status || $status >= 500;
	}

	/**
	 * One HTTP round trip.
	 *
	 * @param string               $url  URL.
	 * @param array<string, mixed> $args Request arguments.
	 * @return array{data: mixed, meta: array<string, mixed>}|WP_Error
	 */
	private function attempt( string $url, array $args ) {
		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'oha_deals_http',
				__( 'The oha.deals API could not be reached.', 'oha-deals' ),
				array(
					'status'    => 0,
					'transport' => $response->get_error_code(),
				)
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $status >= 200 && $status < 300 && is_array( $body ) && array_key_exists( 'data', $body ) ) {
			return array(
				'data' => $body['data'],
				'meta' => isset( $body['meta'] ) && is_array( $body['meta'] ) ? $body['meta'] : array(),
			);
		}

		$error = is_array( $body ) && isset( $body['error'] ) && is_array( $body['error'] ) ? $body['error'] : array();
		$code  = isset( $error['code'] ) && is_string( $error['code'] ) ? sanitize_key( $error['code'] ) : self::code_for_status( $status );
		$data  = array(
			'status'     => $status,
			'request_id' => self::header( $response, 'x-request-id' ),
		);
		$retry = self::header( $response, 'retry-after' );
		if ( is_numeric( $retry ) ) {
			$data['retry_after'] = max( 0, (int) $retry );
		}

		if ( isset( $error['message'] ) && is_string( $error['message'] ) && '' !== $error['message'] ) {
			$message = sanitize_text_field( $error['message'] );
		} else {
			/* translators: %d: HTTP status code. */
			$message = sprintf( __( 'The oha.deals API answered with HTTP status %d.', 'oha-deals' ), $status );
		}

		return new WP_Error( 'oha_deals_' . ( '' !== $code ? $code : 'internal' ), $message, $data );
	}

	/**
	 * Whether a failed attempt is worth one retry: transport errors (timeouts), 5xx, and 429 with a short Retry-After.
	 *
	 * @param WP_Error $error Error from attempt().
	 * @return bool
	 */
	private function should_retry( WP_Error $error ): bool {
		$data   = (array) $error->get_error_data();
		$status = (int) ( $data['status'] ?? 0 );
		$wait   = isset( $data['retry_after'] ) ? (int) $data['retry_after'] : null;

		if ( null !== $wait && $wait > self::MAX_INLINE_WAIT ) {
			return false;
		}
		if ( 429 === $status ) {
			return null !== $wait;
		}

		return 0 === $status || $status >= 500;
	}

	/**
	 * A response header as a string (the first value when it was sent more than once).
	 *
	 * @param array<string, mixed> $response Response from wp_remote_get().
	 * @param string               $name     Lowercase header name.
	 * @return string
	 */
	private static function header( array $response, string $name ): string {
		$value = wp_remote_retrieve_header( $response, $name );
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Fallback error code when the body carries none.
	 *
	 * @param int $status HTTP status.
	 * @return string
	 */
	private static function code_for_status( int $status ): string {
		switch ( $status ) {
			case 400:
				return 'invalid_request';
			case 401:
				return 'unauthorized';
			case 403:
				return 'forbidden';
			case 404:
				return 'not_found';
			case 429:
				return 'rate_limited';
			default:
				return 'internal';
		}
	}
}
