<?php
/**
 * A deal: a product with a price drop or a new low.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Model;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable deal built from a Partner API `Deal` object (ProductSummary plus deal fields).
 */
final class Deal {

	/**
	 * The product summary.
	 *
	 * @var Product
	 */
	private Product $product;

	/**
	 * Kind: `drop` or `low`.
	 *
	 * @var string
	 */
	private string $kind;

	/**
	 * Whether the price is the lowest ever recorded.
	 *
	 * @var bool
	 */
	private bool $all_time_low;

	/**
	 * Date the deal was last seen (ISO), or null.
	 *
	 * @var string|null
	 */
	private ?string $last_seen;

	/**
	 * Use from_array().
	 */
	private function __construct() {}

	/**
	 * Builds a deal from a decoded API object.
	 *
	 * @param array<string, mixed> $data Deal object.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		$deal = new self();
		$kind = sanitize_key( Cast::text( $data, 'kind' ) );

		// In a Deal, `allTimeLow` is a boolean; the Product model ignores it there.
		$deal->product      = Product::from_array( $data );
		$deal->kind         = in_array( $kind, array( 'drop', 'low' ), true ) ? $kind : 'drop';
		$deal->all_time_low = Cast::flag( $data, 'allTimeLow' );
		$deal->last_seen    = Cast::nullable_text( $data, 'lastSeen' );

		return $deal;
	}

	/**
	 * The product.
	 *
	 * @return Product
	 */
	public function product(): Product {
		return $this->product;
	}

	/**
	 * Deal kind: `drop` or `low`.
	 *
	 * @return string
	 */
	public function kind(): string {
		return $this->kind;
	}

	/**
	 * Whether the price is an all-time low.
	 *
	 * @return bool
	 */
	public function is_all_time_low(): bool {
		return $this->all_time_low;
	}

	/**
	 * Date last seen, or null.
	 *
	 * @return string|null
	 */
	public function last_seen(): ?string {
		return $this->last_seen;
	}

	/**
	 * The deal as a plain, JSON-ready array with stable snake_case keys.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'kind'            => $this->kind,
			'is_all_time_low' => $this->all_time_low,
			'last_seen'       => $this->last_seen,
			'product'         => $this->product->to_array(),
		);
	}
}
