<?php
/**
 * Helpers available to templates as `$args['helpers']`.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Render;

use OhaDeals\Affiliate\LinkResolver;
use OhaDeals\Model\Coupon;
use OhaDeals\Model\Offer;
use OhaDeals\Model\Product;
use OhaDeals\Settings\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Formatting, link and markup helpers for templates.
 *
 * Methods named `print_*` echo escaped markup. All other methods return plain text that the template escapes.
 */
final class Helpers {

	/**
	 * Settings.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Formatter.
	 *
	 * @var Formatter
	 */
	private Formatter $formatter;

	/**
	 * Link resolver.
	 *
	 * @var LinkResolver
	 */
	private LinkResolver $links;

	/**
	 * Whether the current element contains the site owner's own affiliate links.
	 *
	 * @var bool
	 */
	private bool $has_own_links = false;

	/**
	 * Whether the current element contains links to oha.deals.
	 *
	 * @var bool
	 */
	private bool $has_oha_links = false;

	/**
	 * Constructor.
	 *
	 * @param Options      $options   Settings.
	 * @param Formatter    $formatter Formatter.
	 * @param LinkResolver $links     Link resolver.
	 */
	public function __construct( Options $options, Formatter $formatter, LinkResolver $links ) {
		$this->options   = $options;
		$this->formatter = $formatter;
		$this->links     = $links;
	}

	/**
	 * The formatter.
	 *
	 * @return Formatter
	 */
	public function format(): Formatter {
		return $this->formatter;
	}

	/**
	 * Starts an element: remembers which kinds of links it contains, for the disclosure in print_footer().
	 *
	 * The URLs are announced up front so that the disclosure is right wherever a template prints it.
	 *
	 * @param string[] $urls Outgoing URLs of the element (empty strings are ignored).
	 * @return void
	 */
	public function start_element( array $urls ): void {
		$this->has_own_links = false;
		$this->has_oha_links = false;
		foreach ( $urls as $url ) {
			$this->note_link( $url );
		}
	}

	/**
	 * Whether a URL is the site owner's own affiliate link, as opposed to a page on oha.deals (or no link at all).
	 *
	 * @param string $url URL from offer_url(), best_url(), product_url() or coupon_url().
	 * @return bool
	 */
	public function is_own_link( string $url ): bool {
		return LinkResolver::KIND_OWN === LinkResolver::kind( $url );
	}

	/**
	 * Outgoing URL for an offer (https or empty): the site's own affiliate link, else the product page on oha.deals.
	 *
	 * @param Offer   $offer   Offer.
	 * @param Product $product Product.
	 * @return string
	 */
	public function offer_url( Offer $offer, Product $product ): string {
		return $this->links->url( $offer, $product );
	}

	/**
	 * The label of an offer's link, as plain text. Says where the link leads: to the shop or to oha.deals.
	 *
	 * @param Offer   $offer   Offer.
	 * @param Product $product Product.
	 * @return string Empty when the offer has no link.
	 */
	public function offer_link_label( Offer $offer, Product $product ): string {
		$url = $this->offer_url( $offer, $product );
		if ( '' === $url ) {
			return '';
		}
		if ( ! $this->is_own_link( $url ) ) {
			return __( 'Compare on oha.deals', 'oha-deals' );
		}

		return $offer->is_price_hidden() ? $this->view_price_at( $offer->shop_name() ) : __( 'To the shop', 'oha-deals' );
	}

	/**
	 * The default label of a product's main button (the link from best_url()), as plain text.
	 *
	 * @param Product $product Product.
	 * @return string
	 */
	public function best_link_label( Product $product ): string {
		return $this->is_own_link( $this->best_url( $product ) )
			? __( 'View best price', 'oha-deals' )
			: __( 'Compare on oha.deals', 'oha-deals' );
	}

	/**
	 * Outgoing URL for a coupon (https or empty): the shop's coupon page on oha.deals, else the coupon overview.
	 *
	 * @param Coupon $coupon Coupon.
	 * @return string
	 */
	public function coupon_url( Coupon $coupon ): string {
		return $this->links->coupon_url( $coupon );
	}

	/**
	 * The label of a coupon's link, as plain text. Says where the link leads: to the shop or to oha.deals.
	 *
	 * @param Coupon $coupon Coupon.
	 * @return string Empty when the coupon has no link.
	 */
	public function coupon_link_label( Coupon $coupon ): string {
		$url = $this->coupon_url( $coupon );
		if ( '' === $url ) {
			return '';
		}
		if ( ! $this->is_own_link( $url ) ) {
			return __( 'View on oha.deals', 'oha-deals' );
		}

		/* translators: %s: shop name. */
		return sprintf( __( 'Redeem at %s', 'oha-deals' ), $coupon->shop() );
	}

	/**
	 * The product page on oha.deals (https or empty).
	 *
	 * @param Product $product Product.
	 * @return string
	 */
	public function product_url( Product $product ): string {
		$url = (string) $product->url();

		return LinkResolver::is_https( $url ) ? $url : '';
	}

	/**
	 * The URL for the product's main button: the best offer, else the product page.
	 *
	 * @param Product $product Product.
	 * @return string
	 */
	public function best_url( Product $product ): string {
		$offer = $product->best_offer();
		$url   = null !== $offer ? $this->offer_url( $offer, $product ) : '';

		return '' !== $url ? $url : $this->product_url( $product );
	}

	/**
	 * The price of an offer as text, or an empty string when hidden or unknown.
	 *
	 * @param Offer $offer Offer.
	 * @return string
	 */
	public function offer_price( Offer $offer ): string {
		$price = $offer->price();

		return $offer->is_price_hidden() || null === $price ? '' : $this->formatter->money( $price, $offer->currency() );
	}

	/**
	 * The best price of a product as text, or an empty string when hidden or unknown.
	 *
	 * @param Product $product Product.
	 * @return string
	 */
	public function product_price( Product $product ): string {
		$price = $product->price();

		return $product->is_price_hidden() || null === $price ? '' : $this->formatter->money( $price, $product->currency() );
	}

	/**
	 * The struck "before" price as text, only when the API sent one.
	 *
	 * @param Product $product Product.
	 * @return string
	 */
	public function before_price( Product $product ): string {
		$before = $product->before();

		return null === $before ? '' : $this->formatter->money( $before, $product->currency() );
	}

	/**
	 * The discount badge text, e.g. `−12 %`, only when the API sent a reference price.
	 *
	 * @param Product $product Product.
	 * @return string
	 */
	public function discount( Product $product ): string {
		$percent = $product->discount_percent();

		return null === $percent ? '' : $this->formatter->percent( -1 * $percent );
	}

	/**
	 * "Price as of …" for Amazon prices, else an empty string.
	 *
	 * @param string|null $price_at ISO timestamp.
	 * @return string
	 */
	public function price_as_of( ?string $price_at ): string {
		if ( null === $price_at ) {
			return '';
		}
		$when = $this->formatter->datetime( $price_at );
		if ( '' === $when ) {
			return '';
		}

		/* translators: %s: date and time. */
		return sprintf( __( 'Price as of %s', 'oha-deals' ), $when );
	}

	/**
	 * "Price as of …" for a visible Amazon offer price: its own timestamp, else the product's last price check.
	 *
	 * @param Offer   $offer   Offer.
	 * @param Product $product Product the offer belongs to.
	 * @return string Empty for other offers and hidden prices.
	 */
	public function offer_as_of( Offer $offer, Product $product ): string {
		if ( $offer->is_price_hidden() || ! $offer->is_amazon() ) {
			return '';
		}

		return $this->price_as_of( $offer->price_at() ?? $product->prices_checked_at() );
	}

	/**
	 * "Price as of …" for a product's visible best price when it is Amazon's: its own timestamp, else the last price check.
	 *
	 * @param Product $product Product.
	 * @return string Empty for other products and hidden prices.
	 */
	public function product_as_of( Product $product ): string {
		return $product->is_best_price_amazon() ? $this->price_as_of( $product->price_at() ?? $product->prices_checked_at() ) : '';
	}

	/**
	 * "View price at <shop>" for link-only offers.
	 *
	 * @param string $shop Shop name.
	 * @return string
	 */
	public function view_price_at( string $shop ): string {
		/* translators: %s: shop name. */
		return sprintf( __( 'View price at %s', 'oha-deals' ), $shop );
	}

	/**
	 * Whether outgoing links open in a new tab.
	 *
	 * @return bool
	 */
	public function opens_new_tab(): bool {
		return $this->options->enabled( 'open_new_tab' );
	}

	/**
	 * Echoes ` href="…"` plus `rel` and `target` per the settings. Prints nothing for an empty URL.
	 *
	 * Own affiliate links get `rel="sponsored nofollow noopener"`, links to oha.deals `rel="nofollow noopener"`.
	 *
	 * @param string $url Outgoing https URL.
	 * @return void
	 */
	public function print_link_attributes( string $url ): void {
		if ( '' === $url ) {
			return;
		}
		$this->note_link( $url );
		echo ' href="' . esc_url( $url, array( 'https' ) ) . '"';

		$rel = $this->options->link_rel( $this->is_own_link( $url ) );
		if ( '' !== $rel ) {
			echo ' rel="' . esc_attr( $rel ) . '"';
		}
		if ( $this->opens_new_tab() ) {
			echo ' target="_blank"';
		}
	}

	/**
	 * Echoes a visually hidden "(opens in a new tab)" when links open in a new tab.
	 *
	 * @return void
	 */
	public function print_new_tab_hint(): void {
		if ( $this->opens_new_tab() ) {
			echo '<span class="oha-deals-visually-hidden"> ' . esc_html__( '(opens in a new tab)', 'oha-deals' ) . '</span>';
		}
	}

	/**
	 * Echoes a lazy-loaded product image with fixed dimensions. Prints nothing without an https URL.
	 *
	 * @param string|null $url  Image URL.
	 * @param string      $alt  Alternative text.
	 * @param int         $size Width and height of the image box in pixels.
	 * @return void
	 */
	public function print_image( ?string $url, string $alt, int $size ): void {
		if ( null === $url || ! $this->shows_images() || ! LinkResolver::is_https( $url ) ) {
			return;
		}
		printf(
			'<img class="oha-deals-image" src="%1$s" alt="%2$s" width="%3$d" height="%3$d" loading="lazy" decoding="async" />',
			esc_url( $url, array( 'https' ) ),
			esc_attr( $alt ),
			(int) $size
		);
	}

	/**
	 * Whether product images are shown (the "Product images" setting). Images are loaded by the visitor's browser
	 * from the hosts the API names.
	 *
	 * @return bool
	 */
	public function shows_images(): bool {
		return $this->options->enabled( 'show_images' );
	}

	/**
	 * The alternative text for a product image.
	 *
	 * @param Product $product Product.
	 * @return string
	 */
	public function image_alt( Product $product ): string {
		$name = $product->name();
		if ( '' !== $product->brand() && 0 !== stripos( $name, $product->brand() ) ) {
			$name = $product->brand() . ' ' . $name;
		}

		return $name;
	}

	/**
	 * Echoes the notes under an element: Amazon disclaimer, link disclosure and the optional credit link.
	 *
	 * The disclosure says "affiliate links" when the element contains at least one own affiliate link, and that the
	 * links lead to oha.deals when it contains only those. An element without links gets none.
	 *
	 * @param bool $amazon_prices Whether the element shows Amazon prices (adds the Amazon disclaimer).
	 * @param bool $amazon_links  Whether the element links to Amazon with the site's own tag (adds the Associates line).
	 * @param bool $inline        Inline context: use <small> instead of paragraphs.
	 * @param bool $has_links     Whether the disclosure may be printed (false for elements that never link out).
	 * @return void
	 */
	public function print_footer( bool $amazon_prices, bool $amazon_links, bool $inline = false, bool $has_links = true ): void {
		$notes = array();

		if ( $amazon_prices ) {
			$notes['amazon'] = esc_html__( 'Amazon prices as of the time shown. Prices and availability may change; the price shown on Amazon at the time of purchase applies.', 'oha-deals' );
		}
		if ( $has_links && ( $this->has_own_links || $this->has_oha_links ) && $this->options->enabled( 'show_disclosure' ) ) {
			$text = $this->options->disclosure_text( $this->has_own_links );
			if ( $amazon_links ) {
				$text .= ' ' . __( 'As an Amazon Associate, this site earns from qualifying purchases.', 'oha-deals' );
			}
			if ( '' !== trim( $text ) ) {
				$notes['disclosure'] = esc_html( trim( $text ) );
			}
		}
		if ( $this->options->enabled( 'show_attribution' ) ) {
			$notes['attribution'] = sprintf(
				/* translators: %s: link to oha.deals. */
				esc_html__( 'Prices by %s', 'oha-deals' ),
				'<a href="' . esc_url( 'https://oha.deals', array( 'https' ) ) . '">oha.deals</a>'
			);
		}
		if ( ! $notes ) {
			return;
		}

		$tag   = $inline ? 'small' : 'p';
		$outer = $inline ? 'span' : 'div';
		echo '<' . tag_escape( $outer ) . ' class="oha-deals-footer">';
		foreach ( $notes as $type => $html ) {
			// Each note is escaped above; the attribution holds one escaped link.
			echo '<' . tag_escape( $tag ) . ' class="' . esc_attr( 'oha-deals-note oha-deals-note--' . $type ) . '">' . $html . '</' . tag_escape( $tag ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</' . tag_escape( $outer ) . '>';
	}

	/**
	 * Whether any of the offers links to Amazon with the site's own Associates tag.
	 *
	 * @param Offer[] $offers  Offers.
	 * @param Product $product Product.
	 * @return bool
	 */
	public function links_to_amazon( array $offers, Product $product ): bool {
		if ( '' === $this->options->amazon_tag() ) {
			return false;
		}
		foreach ( $offers as $offer ) {
			if ( null !== $offer->asin() && 0 === strpos( $this->offer_url( $offer, $product ), 'https://www.amazon.' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Remembers the kind of a link in the current element.
	 *
	 * @param string $url URL.
	 * @return void
	 */
	private function note_link( string $url ): void {
		$kind                = LinkResolver::kind( $url );
		$this->has_own_links = $this->has_own_links || LinkResolver::KIND_OWN === $kind;
		$this->has_oha_links = $this->has_oha_links || LinkResolver::KIND_OHA_DEALS === $kind;
	}
}
