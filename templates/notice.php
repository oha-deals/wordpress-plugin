<?php
/**
 * Notice for editors when an element cannot be shown. Visitors never see it.
 *
 * Override by copying to `<theme>/oha-deals/notice.php`.
 *
 * @package OhaDeals
 *
 * @var array<string, mixed> $args {
 *     @type string $message            Plain-text message.
 *     @type string $tag                "div" or "span".
 *     @type string $wrapper_attributes Escaped wrapper attributes.
 * }
 */

defined( 'ABSPATH' ) || exit;

$oha_deals_tag = 'span' === $args['tag'] ? 'span' : 'div';
?>
<<?php echo tag_escape( $oha_deals_tag ); ?> <?php echo $args['wrapper_attributes']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by get_block_wrapper_attributes() or esc_attr(). ?> role="note"><strong>oha.deals:</strong> <?php echo esc_html( $args['message'] ); ?> <em class="oha-deals-notice__hint"><?php esc_html_e( '(Only visible to editors.)', 'oha-deals' ); ?></em></<?php echo tag_escape( $oha_deals_tag ); ?>>
