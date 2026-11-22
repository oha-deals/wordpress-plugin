<?php
/**
 * Plugin settings: defaults, typed getters and the sanitize callback.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and sanitises the `oha_deals_settings` option.
 */
final class Options {

	/**
	 * Option name.
	 */
	const OPTION = 'oha_deals_settings';

	/**
	 * Default Partner API base URL.
	 */
	const DEFAULT_API_BASE = 'https://oha.deals/api/partner/v1';

	/**
	 * Default cache lifetime in seconds.
	 */
	const DEFAULT_CACHE_TTL = 3600;

	/**
	 * Smallest allowed cache lifetime in seconds.
	 */
	const MIN_CACHE_TTL = 300;

	/**
	 * Largest allowed cache lifetime in seconds.
	 */
	const MAX_CACHE_TTL = 86400;

	/**
	 * Keys whose value is a boolean, used by the sanitize callback.
	 */
	const BOOLEAN_KEYS = array(
		'show_images',
		'link_rel_sponsored',
		'open_new_tab',
		'show_disclosure',
		'show_attribution',
		'structured_data',
		'delete_data_on_uninstall',
	);

	/**
	 * Default values.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'api_key'                  => '',
			'cache_ttl'                => self::DEFAULT_CACHE_TTL,
			'amazon_tag'               => '',
			'awin_publisher_id'        => '',
			'show_images'              => true,
			'link_rel_sponsored'       => true,
			'open_new_tab'             => true,
			'show_disclosure'          => true,
			'disclosure_text'          => '',
			'show_attribution'         => false,
			'structured_data'          => false,
			'delete_data_on_uninstall' => true,
		);
	}

	/**
	 * All stored settings merged over the defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION, array() );

		return array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
	}

	/**
	 * One raw setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public function get( string $key ) {
		$all = $this->all();

		return $all[ $key ] ?? null;
	}

	/**
	 * One setting as a string; anything that is not scalar reads as empty.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	public function text( string $key ): string {
		$value = $this->get( $key );

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Whether the API key is defined as the `OHA_DEALS_API_KEY` constant.
	 *
	 * @return bool
	 */
	public function is_api_key_constant(): bool {
		return '' !== self::constant_string( 'OHA_DEALS_API_KEY' );
	}

	/**
	 * The API key. The constant wins over the stored value.
	 *
	 * @return string
	 */
	public function api_key(): string {
		$constant = self::constant_string( 'OHA_DEALS_API_KEY' );
		if ( '' !== $constant ) {
			return $constant;
		}

		return trim( $this->text( 'api_key' ) );
	}

	/**
	 * Whether an API key is configured.
	 *
	 * @return bool
	 */
	public function has_api_key(): bool {
		return '' !== $this->api_key();
	}

	/**
	 * The API base URL without trailing slash: the `OHA_DEALS_API_BASE` constant or the default, then the filter.
	 *
	 * Deliberately not a stored setting: on multisite a site administrator could otherwise point a network-wide key
	 * at a host of their own. Anything that is not an https URL falls back to the default.
	 *
	 * @return string
	 */
	public function api_base(): string {
		$base = self::constant_string( 'OHA_DEALS_API_BASE' );
		if ( '' === $base ) {
			$base = self::DEFAULT_API_BASE;
		}

		/**
		 * Filters the Partner API base URL.
		 *
		 * @since 1.0.0
		 *
		 * @param string $base Base URL, e.g. `https://oha.deals/api/partner/v1`.
		 */
		$base = (string) apply_filters( 'oha_deals_api_base', $base );

		return self::is_valid_api_base( $base ) ? untrailingslashit( $base ) : self::DEFAULT_API_BASE;
	}

	/**
	 * Soft cache lifetime in seconds, clamped to the allowed range.
	 *
	 * @return int
	 */
	public function cache_ttl(): int {
		$ttl = $this->get( 'cache_ttl' );

		return self::clamp_ttl( is_numeric( $ttl ) ? (int) $ttl : self::DEFAULT_CACHE_TTL );
	}

	/**
	 * Amazon Associates tag, or an empty string.
	 *
	 * @return string
	 */
	public function amazon_tag(): string {
		return $this->text( 'amazon_tag' );
	}

	/**
	 * Awin publisher ID, or an empty string.
	 *
	 * @return string
	 */
	public function awin_publisher_id(): string {
		return $this->text( 'awin_publisher_id' );
	}

	/**
	 * Whether a boolean setting is on.
	 *
	 * @param string $key One of the BOOLEAN_KEYS.
	 * @return bool
	 */
	public function enabled( string $key ): bool {
		return (bool) $this->get( $key );
	}

	/**
	 * The `rel` attribute value for outgoing links.
	 *
	 * `sponsored` is for the site's own affiliate links only; links to oha.deals are marked `nofollow`.
	 *
	 * @param bool $own Whether the link is the site owner's own affiliate link.
	 * @return string
	 */
	public function link_rel( bool $own = true ): string {
		$rel = array();
		if ( $this->enabled( 'link_rel_sponsored' ) ) {
			if ( $own ) {
				$rel[] = 'sponsored';
			}
			$rel[] = 'nofollow';
		}
		if ( $this->enabled( 'open_new_tab' ) || $this->enabled( 'link_rel_sponsored' ) ) {
			$rel[] = 'noopener';
		}

		return implode( ' ', $rel );
	}

	/**
	 * The link disclosure shown under each element. A custom text from the settings replaces both defaults.
	 *
	 * @param bool $own Whether the element contains the site owner's own affiliate links (as opposed to links to
	 *                  oha.deals only).
	 * @return string Plain text.
	 */
	public function disclosure_text( bool $own = true ): string {
		$text = trim( $this->text( 'disclosure_text' ) );
		if ( '' === $text ) {
			$text = $own
				? __( 'Links to shops are affiliate links.', 'oha-deals' )
				: __( 'Links lead to the price comparison on oha.deals.', 'oha-deals' );
		}

		/**
		 * Filters the link disclosure text (plain text, escaped on output).
		 *
		 * @since 1.0.0
		 *
		 * @param string $text Disclosure text.
		 * @param bool   $own  Whether the element contains the site owner's own affiliate links.
		 */
		return (string) apply_filters( 'oha_deals_disclosure_text', $text, $own );
	}

	/**
	 * Masks an API key for display: the first 12 characters followed by `****`.
	 *
	 * @param string $key API key.
	 * @return string
	 */
	public static function mask_key( string $key ): string {
		$key = trim( $key );
		if ( '' === $key ) {
			return '';
		}
		$visible = strlen( $key ) > 16 ? 12 : min( 4, (int) floor( strlen( $key ) / 2 ) );

		return substr( $key, 0, $visible ) . '****';
	}

	/**
	 * Sanitize callback for the option.
	 *
	 * The input is treated as a complete form submission: a missing boolean key means "off".
	 * An empty API key field keeps the stored key; the `api_key_remove` checkbox clears it.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ): array {
		// options.php has already unslashed the submitted values.
		$input = is_array( $input ) ? $input : array();
		$clean = array();

		// API key.
		$key = self::input_text( $input, 'api_key' );
		if ( ! empty( $input['api_key_remove'] ) ) {
			$clean['api_key'] = '';
		} elseif ( '' === $key ) {
			$clean['api_key'] = $this->text( 'api_key' );
		} elseif ( self::is_valid_key_format( $key ) ) {
			$clean['api_key'] = $key;
		} else {
			$clean['api_key'] = $this->text( 'api_key' );
			self::add_error( 'api_key', __( 'The API key looks wrong. It starts with oha_live_ or oha_test_. The previous key was kept.', 'oha-deals' ) );
		}

		// Cache lifetime.
		$clean['cache_ttl'] = isset( $input['cache_ttl'] ) && is_numeric( $input['cache_ttl'] )
			? self::clamp_ttl( (int) $input['cache_ttl'] )
			: self::DEFAULT_CACHE_TTL;

		// Amazon tag: lowercase letters, digits and hyphens. Lenient about the marketplace suffix.
		$tag = strtolower( self::input_text( $input, 'amazon_tag' ) );
		if ( '' === $tag || preg_match( '/^[a-z0-9][a-z0-9-]{0,39}$/', $tag ) ) {
			$clean['amazon_tag'] = $tag;
			if ( '' !== $tag && ! preg_match( '/-2[0-9]$/', $tag ) ) {
				self::add_error( 'amazon_tag', __( 'Amazon tags usually end in -21 (or another -2x number). The tag was saved anyway.', 'oha-deals' ), 'warning' );
			}
		} else {
			$clean['amazon_tag'] = $this->amazon_tag();
			self::add_error( 'amazon_tag', __( 'The Amazon tag may only contain lowercase letters, digits and hyphens. The previous tag was kept.', 'oha-deals' ) );
		}

		// Awin publisher ID: digits only.
		$awin = self::input_text( $input, 'awin_publisher_id' );
		if ( '' === $awin || preg_match( '/^[0-9]{1,12}$/', $awin ) ) {
			$clean['awin_publisher_id'] = $awin;
		} else {
			$clean['awin_publisher_id'] = $this->awin_publisher_id();
			self::add_error( 'awin_publisher_id', __( 'The Awin publisher ID may only contain digits. The previous ID was kept.', 'oha-deals' ) );
		}

		foreach ( self::BOOLEAN_KEYS as $bool_key ) {
			$clean[ $bool_key ] = ! empty( $input[ $bool_key ] );
		}

		$clean['disclosure_text'] = self::input_text( $input, 'disclosure_text' );

		return $clean;
	}

	/**
	 * Whether a string has the shape of an oha.deals API key.
	 *
	 * @param string $key Candidate key.
	 * @return bool
	 */
	public static function is_valid_key_format( string $key ): bool {
		return 1 === preg_match( '/^oha_(live|test)_[A-Za-z0-9]{16,64}$/', $key );
	}

	/**
	 * Whether a URL may be used as API base: an absolute https URL.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_valid_api_base( string $url ): bool {
		$parts = wp_parse_url( $url );

		return is_array( $parts )
			&& isset( $parts['scheme'], $parts['host'] )
			&& 'https' === strtolower( $parts['scheme'] )
			&& '' !== $parts['host'];
	}

	/**
	 * The trimmed value of an optional string constant from wp-config.php, or an empty string.
	 *
	 * @param string $name Constant name.
	 * @return string
	 */
	private static function constant_string( string $name ): string {
		if ( ! defined( $name ) ) {
			return '';
		}
		$value = constant( $name );

		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * A sanitised, trimmed text value from submitted input; missing and non-scalar values read as empty.
	 *
	 * @param array<mixed> $input Submitted input.
	 * @param string       $key   Field name.
	 * @return string
	 */
	private static function input_text( array $input, string $key ): string {
		$value = $input[ $key ] ?? '';

		return is_scalar( $value ) ? trim( sanitize_text_field( (string) $value ) ) : '';
	}

	/**
	 * Clamps a cache lifetime to the allowed range.
	 *
	 * @param int $ttl Seconds.
	 * @return int
	 */
	private static function clamp_ttl( int $ttl ): int {
		return max( self::MIN_CACHE_TTL, min( self::MAX_CACHE_TTL, $ttl ) );
	}

	/**
	 * Registers a settings error when the Settings API is loaded (it is not on the front end or in REST requests).
	 *
	 * @param string $code    Error code / field.
	 * @param string $message Message.
	 * @param string $type    `error` or `warning`.
	 * @return void
	 */
	private static function add_error( string $code, string $message, string $type = 'error' ): void {
		if ( function_exists( 'add_settings_error' ) ) {
			add_settings_error( self::OPTION, 'oha_deals_' . $code, $message, $type );
		}
	}
}
