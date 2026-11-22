<?php
/**
 * A shop coupon or voucher.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Model;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable coupon built from a Partner API coupon object.
 */
final class Coupon {

	/**
	 * The coupon overview on oha.deals, used when the API names no page for the shop.
	 */
	const OVERVIEW_PAGE = 'https://oha.deals/gutscheine';

	/**
	 * Coupon ID.
	 *
	 * @var string
	 */
	private string $id;

	/**
	 * Shop name.
	 *
	 * @var string
	 */
	private string $shop;

	/**
	 * Shop host.
	 *
	 * @var string|null
	 */
	private ?string $host;

	/**
	 * Title.
	 *
	 * @var string
	 */
	private string $title;

	/**
	 * Description.
	 *
	 * @var string
	 */
	private string $description;

	/**
	 * Voucher code, or null when there is none or it is hidden.
	 *
	 * @var string|null
	 */
	private ?string $code;

	/**
	 * Whether the code is only revealed at the shop.
	 *
	 * @var bool
	 */
	private bool $code_hidden;

	/**
	 * The shop's coupon page on oha.deals (https), else the coupon overview.
	 *
	 * The API's `url` field (a redirect to the shop) is deliberately not read: redirects must not be printed.
	 *
	 * @var string
	 */
	private string $url;

	/**
	 * Expiry date (ISO), or null.
	 *
	 * @var string|null
	 */
	private ?string $valid_until;

	/**
	 * Use from_array().
	 */
	private function __construct() {}

	/**
	 * Builds a coupon from a decoded API object.
	 *
	 * @param array<string, mixed> $data Coupon object.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		$coupon = new self();

		$coupon->id          = Cast::text( $data, 'id' );
		$coupon->host        = Cast::nullable_text( $data, 'host' );
		$coupon->shop        = Cast::text( $data, 'shop', (string) $coupon->host );
		$coupon->title       = Cast::text( $data, 'title' );
		$coupon->description = Cast::text( $data, 'description' );
		$coupon->code_hidden = Cast::flag( $data, 'codeHidden' );
		$coupon->code        = $coupon->code_hidden ? null : Cast::nullable_text( $data, 'code' );
		$coupon->url         = Cast::https_url( $data, 'pageUrl' ) ?? self::OVERVIEW_PAGE;
		$coupon->valid_until = Cast::nullable_text( $data, 'validUntil' );

		return $coupon;
	}

	/**
	 * Coupon ID.
	 *
	 * @return string
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * Shop name.
	 *
	 * @return string
	 */
	public function shop(): string {
		return $this->shop;
	}

	/**
	 * Shop host, or null.
	 *
	 * @return string|null
	 */
	public function host(): ?string {
		return $this->host;
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function title(): string {
		return $this->title;
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description(): string {
		return $this->description;
	}

	/**
	 * Voucher code, or null.
	 *
	 * @return string|null
	 */
	public function code(): ?string {
		return $this->code;
	}

	/**
	 * Whether the code is revealed only at the shop.
	 *
	 * @return bool
	 */
	public function is_code_hidden(): bool {
		return $this->code_hidden;
	}

	/**
	 * The page on oha.deals that shows this coupon: the shop's coupon page, else the coupon overview.
	 *
	 * @return string https URL.
	 */
	public function url(): string {
		return $this->url;
	}

	/**
	 * Expiry date, or null.
	 *
	 * @return string|null
	 */
	public function valid_until(): ?string {
		return $this->valid_until;
	}

	/**
	 * The coupon as a plain, JSON-ready array with stable snake_case keys.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'          => $this->id,
			'shop'        => $this->shop,
			'host'        => $this->host,
			'title'       => $this->title,
			'description' => $this->description,
			'code'        => $this->code,
			'code_hidden' => $this->code_hidden,
			'url'         => $this->url,
			'valid_until' => $this->valid_until,
		);
	}
}
