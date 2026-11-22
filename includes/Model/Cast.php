<?php
/**
 * Tolerant type coercion for API payloads.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Model;

defined( 'ABSPATH' ) || exit;

/**
 * Reads typed values from decoded JSON arrays without trusting their shape.
 */
final class Cast {

	/**
	 * A string field, or the fallback when missing or not scalar.
	 *
	 * @param array<string, mixed> $data     Source.
	 * @param string               $field    Field name.
	 * @param string               $fallback Fallback.
	 * @return string
	 */
	public static function text( array $data, string $field, string $fallback = '' ): string {
		$value = $data[ $field ] ?? null;

		return is_scalar( $value ) && ! is_bool( $value ) ? trim( (string) $value ) : $fallback;
	}

	/**
	 * A non-empty string field, or null.
	 *
	 * @param array<string, mixed> $data  Source.
	 * @param string               $field Field name.
	 * @return string|null
	 */
	public static function nullable_text( array $data, string $field ): ?string {
		$value = self::text( $data, $field );

		return '' === $value ? null : $value;
	}

	/**
	 * An https URL field, or null. Anything else (http, javascript:, relative) is dropped.
	 *
	 * @param array<string, mixed> $data  Source.
	 * @param string               $field Field name.
	 * @return string|null
	 */
	public static function https_url( array $data, string $field ): ?string {
		$value = self::text( $data, $field );

		return 0 === stripos( $value, 'https://' ) ? $value : null;
	}

	/**
	 * A finite number field, or null.
	 *
	 * @param array<string, mixed> $data  Source.
	 * @param string               $field Field name.
	 * @return float|null
	 */
	public static function nullable_float( array $data, string $field ): ?float {
		$value = $data[ $field ] ?? null;
		if ( is_bool( $value ) || ! is_numeric( $value ) ) {
			return null;
		}
		$value = (float) $value;

		return is_finite( $value ) ? $value : null;
	}

	/**
	 * An integer field, or the fallback.
	 *
	 * @param array<string, mixed> $data     Source.
	 * @param string               $field    Field name.
	 * @param int                  $fallback Fallback.
	 * @return int
	 */
	public static function integer( array $data, string $field, int $fallback = 0 ): int {
		$value = $data[ $field ] ?? null;

		return is_numeric( $value ) ? (int) $value : $fallback;
	}

	/**
	 * A boolean field. Only `true` (or 1 / "1" / "true") counts as true.
	 *
	 * @param array<string, mixed> $data  Source.
	 * @param string               $field Field name.
	 * @return bool
	 */
	public static function flag( array $data, string $field ): bool {
		$value = $data[ $field ] ?? false;

		return true === $value || 1 === $value || '1' === $value || 'true' === $value;
	}

	/**
	 * An array field, or an empty array.
	 *
	 * @param array<string, mixed> $data  Source.
	 * @param string               $field Field name.
	 * @return array<mixed>
	 */
	public static function items( array $data, string $field ): array {
		$value = $data[ $field ] ?? null;

		return is_array( $value ) ? $value : array();
	}
}
