<?php
/**
 * Settings → oha.deals.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Admin;

use OhaDeals\Api\Repository;
use OhaDeals\Render\Formatter;
use OhaDeals\Settings\Options;

defined( 'ABSPATH' ) || exit;

/**
 * The settings page: a connection status card with "Test connection" and "Clear cache", the settings (Settings API)
 * and a short guide.
 *
 * Loading the page never calls the API. The status comes from the cached `/me` answer; without one, the page's script
 * asks for it through the REST route after load.
 */
final class SettingsPage {

	/**
	 * Menu and page slug.
	 */
	const SLUG = 'oha-deals';

	/**
	 * Settings group.
	 */
	const GROUP = 'oha_deals';

	/**
	 * Script and style handle.
	 */
	const HANDLE = 'oha-deals-settings';

	/**
	 * Where site owners learn how to get an API key.
	 */
	const KEY_URL = 'https://oha.deals/kontakt';

	/**
	 * Source code and documentation.
	 */
	const REPOSITORY_URL = 'https://github.com/oha-deals/wordpress-plugin';

	/**
	 * Settings.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Data access (cached key description only).
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Formatter.
	 *
	 * @var Formatter
	 */
	private Formatter $formatter;

	/**
	 * Hook suffix of the page.
	 *
	 * @var string
	 */
	private string $hook_suffix = '';

	/**
	 * Constructor.
	 *
	 * @param Options    $options    Settings.
	 * @param Repository $repository Data access.
	 * @param Formatter  $formatter  Formatter.
	 */
	public function __construct( Options $options, Repository $repository, Formatter $formatter ) {
		$this->options    = $options;
		$this->repository = $repository;
		$this->formatter  = $formatter;
	}

	/**
	 * Address of the settings page.
	 *
	 * @return string
	 */
	public static function url(): string {
		return admin_url( 'options-general.php?page=' . self::SLUG );
	}

	/**
	 * Hooks the page into the admin.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( OHA_DEALS_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Adds the page under Settings.
	 *
	 * @return void
	 */
	public function add_page(): void {
		$hook = add_options_page(
			__( 'oha.deals', 'oha-deals' ),
			__( 'oha.deals', 'oha-deals' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);

		$this->hook_suffix = is_string( $hook ) ? $hook : '';
		if ( '' !== $this->hook_suffix ) {
			add_action( 'load-' . $this->hook_suffix, array( $this, 'add_help' ) );
		}
	}

	/**
	 * Registers the setting, sections and fields.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::GROUP,
			Options::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this->options, 'sanitize' ),
				'default'           => Options::defaults(),
				'show_in_rest'      => false,
			)
		);

		$card = array(
			'before_section' => '<div class="oha-deals-card %s">',
			'after_section'  => '</div>',
		);
		add_settings_section( 'oha_deals_connection', __( 'Connection', 'oha-deals' ), array( $this, 'section_connection' ), self::SLUG, $card + array( 'section_class' => 'oha-deals-card--connection' ) );
		add_settings_section( 'oha_deals_affiliate', __( 'Your affiliate IDs', 'oha-deals' ), array( $this, 'section_affiliate' ), self::SLUG, $card + array( 'section_class' => 'oha-deals-card--affiliate' ) );
		add_settings_section( 'oha_deals_display', __( 'Display', 'oha-deals' ), '__return_null', self::SLUG, $card + array( 'section_class' => 'oha-deals-card--display' ) );
		add_settings_section( 'oha_deals_advanced', __( 'Cache and data', 'oha-deals' ), '__return_null', self::SLUG, $card + array( 'section_class' => 'oha-deals-card--data' ) );

		add_settings_field( 'oha_deals_api_key', __( 'API key', 'oha-deals' ), array( $this, 'field_api_key' ), self::SLUG, 'oha_deals_connection', array( 'label_for' => 'oha_deals_api_key' ) );

		$this->text_field( 'amazon_tag', __( 'Amazon tag', 'oha-deals' ), 'oha_deals_affiliate', __( 'Your Amazon Associates tag for amazon.de, for example example-21. Amazon offers then link straight to Amazon with your tag.', 'oha-deals' ), 'example-21' );
		$this->text_field( 'awin_publisher_id', __( 'Awin publisher ID', 'oha-deals' ), 'oha_deals_affiliate', __( 'Your numeric Awin publisher ID. Offers from Awin shops then link through your Awin account. You need to be approved by each advertiser to earn a commission.', 'oha-deals' ), '123456', 'numeric' );

		$this->checkbox_field( 'show_images', __( 'Product images', 'oha-deals' ), 'oha_deals_display', __( 'Show product images. They are loaded by the visitor\'s browser from the oha.deals image server and shop image servers.', 'oha-deals' ) );
		$this->checkbox_field( 'link_rel_sponsored', __( 'Link attributes', 'oha-deals' ), 'oha_deals_display', __( 'Mark your affiliate links as rel="sponsored nofollow" and links to oha.deals as rel="nofollow" (recommended by search engines).', 'oha-deals' ) );
		$this->checkbox_field( 'open_new_tab', __( 'New tab', 'oha-deals' ), 'oha_deals_display', __( 'Open outgoing links in a new tab.', 'oha-deals' ) );
		$this->checkbox_field( 'show_disclosure', __( 'Disclosure', 'oha-deals' ), 'oha_deals_display', __( 'Show under each element where its links lead.', 'oha-deals' ) );
		$this->text_field( 'disclosure_text', __( 'Disclosure text', 'oha-deals' ), 'oha_deals_display', __( 'Leave empty for the default text: "Links to shops are affiliate links." when an element contains your own affiliate links, otherwise "Links lead to the price comparison on oha.deals."', 'oha-deals' ), '', 'text', 'large-text' );
		$this->checkbox_field( 'show_attribution', __( 'Credit', 'oha-deals' ), 'oha_deals_display', __( 'Show a small "Prices by oha.deals" link.', 'oha-deals' ) );
		$this->checkbox_field( 'structured_data', __( 'Structured data', 'oha-deals' ), 'oha_deals_display', __( 'Add schema.org Product data (JSON-LD) to product cards. Amazon prices are never included.', 'oha-deals' ) );

		add_settings_field( 'oha_deals_cache_ttl', __( 'Cache lifetime', 'oha-deals' ), array( $this, 'field_cache_ttl' ), self::SLUG, 'oha_deals_advanced', array( 'label_for' => 'oha_deals_cache_ttl' ) );
		$this->checkbox_field( 'delete_data_on_uninstall', __( 'Uninstall', 'oha-deals' ), 'oha_deals_advanced', __( 'Delete the settings and cached prices when the plugin is deleted.', 'oha-deals' ) );
	}

	/**
	 * Loads the stylesheet and the script on this page only.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public function enqueue( $hook_suffix ): void {
		if ( '' === $this->hook_suffix || $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_style( self::HANDLE, OHA_DEALS_URL . 'assets/admin/settings.css', array(), OHA_DEALS_VERSION );
		wp_enqueue_script( self::HANDLE, OHA_DEALS_URL . 'assets/admin/settings.js', array( 'wp-a11y', 'wp-api-fetch' ), OHA_DEALS_VERSION, array( 'in_footer' => true ) );
	}

	/**
	 * Adds the contextual help (on `load-<page>`).
	 *
	 * @return void
	 */
	public function add_help(): void {
		$screen = get_current_screen();
		if ( null === $screen ) {
			return;
		}

		$screen->add_help_tab(
			array(
				'id'      => 'oha_deals_overview',
				'title'   => __( 'Overview', 'oha-deals' ),
				'content' => self::paragraphs(
					__( 'oha.deals shows live prices, shop offers, deals, coupons and price history. Your server asks the oha.deals Partner API with your API key and keeps the answers in a cache, so visitors never contact the API.', 'oha-deals' ),
					__( 'If the API cannot be reached, cached prices keep showing for a while. After that the blocks show nothing to visitors; users who can edit posts see a short notice.', 'oha-deals' )
				),
			)
		);
		$screen->add_help_tab(
			array(
				'id'      => 'oha_deals_links',
				'title'   => __( 'Links and commission', 'oha-deals' ),
				'content' => self::paragraphs( ...self::link_explanation() ),
			)
		);
		$screen->set_help_sidebar(
			'<p><strong>' . esc_html__( 'For more information:', 'oha-deals' ) . '</strong></p>'
			. '<p><a href="' . esc_url( self::REPOSITORY_URL ) . '">' . esc_html__( 'Documentation', 'oha-deals' ) . '</a></p>'
			. '<p><a href="' . esc_url( self::KEY_URL ) . '">' . esc_html__( 'Request an API key', 'oha-deals' ) . '</a></p>'
		);
	}

	/**
	 * Adds a "Settings" link on the Plugins screen.
	 *
	 * @param array<int|string, string> $links Action links.
	 * @return array<int|string, string>
	 */
	public function action_links( $links ): array {
		$links = is_array( $links ) ? $links : array();
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( self::url() ), esc_html__( 'Settings', 'oha-deals' ) )
		);

		return $links;
	}

	/**
	 * Renders the page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap oha-deals-settings">
			<div class="oha-deals-settings__header">
				<img src="<?php echo esc_url( OHA_DEALS_URL . 'assets/admin/mark.svg' ); ?>" width="44" height="44" alt="" />
				<div>
					<h1><?php esc_html_e( 'oha.deals', 'oha-deals' ); ?></h1>
					<p><?php esc_html_e( 'Live prices, deals, coupons and price history for your posts and pages.', 'oha-deals' ); ?></p>
				</div>
			</div>
			<hr class="wp-header-end" />

			<div class="oha-deals-settings__layout">
				<div class="oha-deals-settings__main">
					<?php $this->render_status(); ?>
					<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
						<?php
						settings_fields( self::GROUP );
						do_settings_sections( self::SLUG );
						submit_button( '', 'primary large' );
						?>
					</form>
				</div>
				<div class="oha-deals-settings__side">
					<?php $this->render_guide(); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * The connection status card.
	 *
	 * @return void
	 */
	private function render_status(): void {
		if ( ! $this->options->has_api_key() ) {
			$this->render_not_connected();
			return;
		}

		$cached  = $this->repository->cached_me();
		$summary = null !== $cached ? KeyInfo::summary( $cached['me'], $cached['fetched_at'], $this->formatter ) : null;
		$strings = array(
			'connected'    => __( 'Connected', 'oha-deals' ),
			'checking'     => __( 'Checking the connection…', 'oha-deals' ),
			'problem'      => __( 'Connection problem', 'oha-deals' ),
			'tested'       => __( 'The connection works.', 'oha-deals' ),
			'clearing'     => __( 'Clearing the cache…', 'oha-deals' ),
			'failed'       => __( 'The request failed. Reload the page and try again.', 'oha-deals' ),
			'included'     => __( 'included', 'oha-deals' ),
			'not_included' => __( 'not included', 'oha-deals' ),
		);
		$empty   = array(
			'plan'            => '',
			'capabilities'    => array(),
			'rateLimit'       => '',
			'pricesCheckedAt' => '',
			'checkedAt'       => '',
		);
		$facts   = $summary ?? $empty;
		?>
		<div
			id="oha-deals-status"
			class="oha-deals-card oha-deals-status"
			role="region"
			aria-labelledby="oha-deals-status-title"
			data-state="<?php echo esc_attr( null !== $summary ? 'connected' : 'checking' ); ?>"
			data-strings="<?php echo esc_attr( (string) wp_json_encode( $strings ) ); ?>"
		>
			<h2 id="oha-deals-status-title" class="oha-deals-status__title">
				<span class="oha-deals-status__dot" aria-hidden="true"></span>
				<span data-field="title"><?php echo esc_html( null !== $summary ? $strings['connected'] : $strings['checking'] ); ?></span>
				<?php if ( null !== $summary ) : ?>
					<span class="oha-deals-status__plan" data-field="plan"><?php echo esc_html( $facts['plan'] ); ?></span>
				<?php else : ?>
					<span class="oha-deals-status__plan" data-field="plan" hidden></span>
				<?php endif; ?>
			</h2>
			<p class="oha-deals-status__problem" data-field="problem" hidden></p>
			<noscript><p><?php esc_html_e( 'Testing the connection and clearing the cache need JavaScript.', 'oha-deals' ); ?></p></noscript>

			<dl class="oha-deals-status__facts" data-field="facts" <?php echo null === $summary ? 'hidden' : ''; ?>>
				<div>
					<dt><?php esc_html_e( 'Your key includes', 'oha-deals' ); ?></dt>
					<dd>
						<ul class="oha-deals-status__capabilities" data-field="capabilities">
							<?php foreach ( $facts['capabilities'] as $capability ) : ?>
								<li class="<?php echo esc_attr( $capability['enabled'] ? 'is-on' : 'is-off' ); ?>">
									<?php echo esc_html( $capability['label'] ); ?>
									<span class="screen-reader-text"><?php echo esc_html( $capability['enabled'] ? $strings['included'] : $strings['not_included'] ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					</dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Rate limit', 'oha-deals' ); ?></dt>
					<dd data-field="rateLimit"><?php echo esc_html( $facts['rateLimit'] ); ?></dd>
				</div>
				<div <?php echo '' === $facts['pricesCheckedAt'] ? 'hidden' : ''; ?>>
					<dt><?php esc_html_e( 'Last price check', 'oha-deals' ); ?></dt>
					<dd data-field="pricesCheckedAt"><?php echo esc_html( $facts['pricesCheckedAt'] ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Connection tested', 'oha-deals' ); ?></dt>
					<dd data-field="checkedAt"><?php echo esc_html( $facts['checkedAt'] ); ?></dd>
				</div>
			</dl>

			<div class="oha-deals-status__actions hide-if-no-js">
				<button type="button" class="button" data-oha-deals-action="connection"><?php esc_html_e( 'Test connection', 'oha-deals' ); ?></button>
				<button type="button" class="button" data-oha-deals-action="cache/flush"><?php esc_html_e( 'Clear cache', 'oha-deals' ); ?></button>
				<span class="spinner" aria-hidden="true"></span>
				<span class="oha-deals-status__result" role="status" aria-live="polite"></span>
			</div>
		</div>
		<?php
	}

	/**
	 * The status card of a site without an API key: how to get one.
	 *
	 * @return void
	 */
	private function render_not_connected(): void {
		?>
		<div class="oha-deals-card oha-deals-status" role="region" aria-labelledby="oha-deals-status-title" data-state="none">
			<h2 id="oha-deals-status-title" class="oha-deals-status__title">
				<span class="oha-deals-status__dot" aria-hidden="true"></span>
				<?php esc_html_e( 'Not connected yet', 'oha-deals' ); ?>
			</h2>
			<p><?php esc_html_e( 'The blocks need an API key from oha.deals. Until then they show nothing to visitors.', 'oha-deals' ); ?></p>
			<ol class="oha-deals-steps">
				<li><?php esc_html_e( 'Request an API key on oha.deals. It starts with oha_live_.', 'oha-deals' ); ?></li>
				<li><?php esc_html_e( 'Paste the key into the field below and save.', 'oha-deals' ); ?></li>
				<li><?php esc_html_e( 'Add an oha.deals block to a post or page.', 'oha-deals' ); ?></li>
			</ol>
			<p class="oha-deals-status__actions">
				<a class="button button-primary" href="<?php echo esc_url( self::KEY_URL ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'Request an API key', 'oha-deals' ); ?>
					<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'oha-deals' ); ?></span>
				</a>
				<a class="button" href="#oha_deals_api_key"><?php esc_html_e( 'I have a key', 'oha-deals' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * The "Getting started" card with the blocks and shortcodes.
	 *
	 * @return void
	 */
	private function render_guide(): void {
		$elements = array(
			array(
				'title'     => __( 'Product', 'oha-deals' ),
				'text'      => __( 'One product with its best price.', 'oha-deals' ),
				'shortcode' => '[oha_deals_product slug="product-slug"]',
			),
			array(
				'title'     => __( 'Offers', 'oha-deals' ),
				'text'      => __( 'All shop offers for a product.', 'oha-deals' ),
				'shortcode' => '[oha_deals_offers slug="product-slug"]',
			),
			array(
				'title'     => __( 'Deals', 'oha-deals' ),
				'text'      => __( 'Current price drops and low prices.', 'oha-deals' ),
				'shortcode' => '[oha_deals_list limit="6"]',
			),
			array(
				'title'     => __( 'Comparison', 'oha-deals' ),
				'text'      => __( 'Up to six products side by side.', 'oha-deals' ),
				'shortcode' => '[oha_deals_comparison slugs="slug-one, slug-two"]',
			),
			array(
				'title'     => __( 'Price history', 'oha-deals' ),
				'text'      => __( 'A chart of a product\'s price.', 'oha-deals' ),
				'shortcode' => '[oha_deals_history slug="product-slug" days="90"]',
			),
			array(
				'title'     => __( 'Coupons', 'oha-deals' ),
				'text'      => __( 'Current voucher codes.', 'oha-deals' ),
				'shortcode' => '[oha_deals_coupons limit="5"]',
			),
			array(
				'title'     => __( 'Price in a sentence', 'oha-deals' ),
				'text'      => __( 'Only the best price, as text.', 'oha-deals' ),
				'shortcode' => '[oha_deals_price slug="product-slug"]',
			),
		);
		?>
		<div class="oha-deals-card oha-deals-guide">
			<h2><?php esc_html_e( 'Getting started', 'oha-deals' ); ?></h2>
			<p><?php esc_html_e( 'In the block editor, open the inserter and look for the oha.deals category. Everywhere else, use a shortcode:', 'oha-deals' ); ?></p>
			<ul class="oha-deals-guide__list" data-copied="<?php esc_attr_e( 'Copied', 'oha-deals' ); ?>">
				<?php foreach ( $elements as $element ) : ?>
					<li>
						<strong><?php echo esc_html( $element['title'] ); ?></strong>
						<span class="oha-deals-guide__text"><?php echo esc_html( $element['text'] ); ?></span>
						<span class="oha-deals-guide__shortcode">
							<code><?php echo esc_html( $element['shortcode'] ); ?></code>
							<button
								type="button"
								class="button button-small hide-if-no-js"
								data-oha-deals-copy="<?php echo esc_attr( $element['shortcode'] ); ?>"
								aria-label="<?php echo esc_attr( /* translators: %s: element name, e.g. "Product". */ sprintf( __( 'Copy shortcode: %s', 'oha-deals' ), $element['title'] ) ); ?>"
							><?php esc_html_e( 'Copy', 'oha-deals' ); ?></button>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="description"><?php esc_html_e( 'The slug is the last part of a product address on oha.deals. You can also write ean="…" with the EAN from the package.', 'oha-deals' ); ?></p>
		</div>
		<div class="oha-deals-card oha-deals-links">
			<h2><?php esc_html_e( 'Links', 'oha-deals' ); ?></h2>
			<ul>
				<li><a href="<?php echo esc_url( self::REPOSITORY_URL ); ?>"><?php esc_html_e( 'Documentation and source code', 'oha-deals' ); ?></a></li>
				<li><a href="<?php echo esc_url( self::KEY_URL ); ?>"><?php esc_html_e( 'Request an API key', 'oha-deals' ); ?></a></li>
				<li><a href="https://oha.deals/nutzungsbedingungen"><?php esc_html_e( 'Terms of use', 'oha-deals' ); ?></a></li>
				<li><a href="https://oha.deals/datenschutz"><?php esc_html_e( 'Privacy policy', 'oha-deals' ); ?></a></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Intro of the connection section.
	 *
	 * @return void
	 */
	public function section_connection(): void {
		echo '<p>' . esc_html__( 'Your server fetches prices with this key and caches them. The key never reaches the visitor\'s browser.', 'oha-deals' ) . '</p>';
	}

	/**
	 * Intro of the affiliate section.
	 *
	 * @return void
	 */
	public function section_affiliate(): void {
		echo wp_kses( self::paragraphs( ...self::link_explanation() ), array( 'p' => array() ) );
	}

	/**
	 * Where links lead and who earns the commission.
	 *
	 * @return string[]
	 */
	private static function link_explanation(): array {
		return array(
			__( 'Optional. Without your own IDs, links go to the product page on oha.deals, where visitors can continue to the shops and oha.deals may earn a commission.', 'oha-deals' ),
			__( 'With your own IDs, the link goes straight to Amazon or Awin and the commission is yours. All other offers keep linking to oha.deals. Use only IDs of accounts in which this website is registered.', 'oha-deals' ),
		);
	}

	/**
	 * Plain texts as escaped paragraphs.
	 *
	 * @param string ...$texts Texts.
	 * @return string
	 */
	private static function paragraphs( string ...$texts ): string {
		return '<p>' . implode( '</p><p>', array_map( 'esc_html', $texts ) ) . '</p>';
	}

	/**
	 * The API key field: never prints the stored key, only its masked prefix.
	 *
	 * @return void
	 */
	public function field_api_key(): void {
		$locked = $this->options->is_api_key_constant();
		$masked = Options::mask_key( $this->options->api_key() );
		$name   = Options::OPTION . '[api_key]';
		?>
		<input
			type="password"
			id="oha_deals_api_key"
			name="<?php echo esc_attr( $name ); ?>"
			value=""
			class="regular-text code"
			autocomplete="off"
			spellcheck="false"
			placeholder="<?php echo esc_attr( '' !== $masked ? $masked : 'oha_live_…' ); ?>"
			aria-describedby="oha_deals_api_key_description"
			<?php disabled( $locked ); ?>
		/>
		<p class="description" id="oha_deals_api_key_description">
			<?php
			if ( $locked ) {
				esc_html_e( 'The key is set with the OHA_DEALS_API_KEY constant in wp-config.php.', 'oha-deals' );
			} elseif ( '' !== $masked ) {
				/* translators: %s: masked API key. */
				echo esc_html( sprintf( __( 'A key is saved (%s). Leave the field empty to keep it.', 'oha-deals' ), $masked ) );
			} else {
				esc_html_e( 'Paste the key you received from oha.deals. It starts with oha_live_.', 'oha-deals' );
			}
			?>
		</p>
		<?php if ( ! $locked && '' !== $masked ) : ?>
			<p>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( Options::OPTION . '[api_key_remove]' ); ?>" value="1" />
					<?php esc_html_e( 'Remove the saved key', 'oha-deals' ); ?>
				</label>
			</p>
			<?php
		endif;
	}

	/**
	 * The cache lifetime field: a list of sensible values plus the stored one if it is not among them.
	 *
	 * @return void
	 */
	public function field_cache_ttl(): void {
		$current = $this->options->cache_ttl();
		$choices = self::cache_ttl_choices();
		if ( ! isset( $choices[ $current ] ) ) {
			/* translators: %s: number of seconds. */
			$choices[ $current ] = sprintf( _n( 'Custom: %s second', 'Custom: %s seconds', $current, 'oha-deals' ), number_format_i18n( $current ) );
			ksort( $choices );
		}
		?>
		<select id="oha_deals_cache_ttl" name="<?php echo esc_attr( Options::OPTION . '[cache_ttl]' ); ?>" aria-describedby="oha_deals_cache_ttl_description">
			<?php foreach ( $choices as $seconds => $label ) : ?>
				<option value="<?php echo esc_attr( (string) $seconds ); ?>" <?php selected( $current, $seconds ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="description" id="oha_deals_cache_ttl_description">
			<?php esc_html_e( 'How long prices are reused before they are refreshed in the background. Amazon prices are never kept longer than one hour.', 'oha-deals' ); ?>
		</p>
		<?php
	}

	/**
	 * The offered cache lifetimes: seconds mapped to labels.
	 *
	 * @return array<int, string>
	 */
	private static function cache_ttl_choices(): array {
		$choices = array();
		foreach ( array( 5, 15, 30 ) as $minutes ) {
			/* translators: %s: number of minutes. */
			$choices[ $minutes * MINUTE_IN_SECONDS ] = sprintf( _n( '%s minute', '%s minutes', $minutes, 'oha-deals' ), number_format_i18n( $minutes ) );
		}
		foreach ( array( 1, 3, 6, 12, 24 ) as $hours ) {
			/* translators: %s: number of hours. */
			$choices[ $hours * HOUR_IN_SECONDS ] = sprintf( _n( '%s hour', '%s hours', $hours, 'oha-deals' ), number_format_i18n( $hours ) );
		}

		return $choices;
	}

	/**
	 * Renders a text field (callback for add_settings_field()).
	 *
	 * @param array<string, string> $args Field arguments.
	 * @return void
	 */
	public function render_text_field( array $args ): void {
		$key = $args['key'];
		printf(
			'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="%4$s" placeholder="%5$s" inputmode="%6$s" aria-describedby="%1$s_description" />',
			esc_attr( 'oha_deals_' . $key ),
			esc_attr( Options::OPTION . '[' . $key . ']' ),
			esc_attr( $this->options->text( $key ) ),
			esc_attr( $args['class'] ),
			esc_attr( $args['placeholder'] ),
			esc_attr( $args['inputmode'] )
		);
		printf( '<p class="description" id="%1$s_description">%2$s</p>', esc_attr( 'oha_deals_' . $key ), esc_html( $args['description'] ) );
	}

	/**
	 * Renders a checkbox field (callback for add_settings_field()).
	 *
	 * @param array<string, string> $args Field arguments.
	 * @return void
	 */
	public function render_checkbox_field( array $args ): void {
		$key = $args['key'];
		printf(
			'<fieldset><legend class="screen-reader-text"><span>%1$s</span></legend><label for="%2$s"><input type="checkbox" id="%2$s" name="%3$s" value="1" %4$s /> %5$s</label></fieldset>',
			esc_html( $args['title'] ),
			esc_attr( 'oha_deals_' . $key ),
			esc_attr( Options::OPTION . '[' . $key . ']' ),
			checked( $this->options->enabled( $key ), true, false ),
			esc_html( $args['description'] )
		);
	}

	/**
	 * Adds a text field.
	 *
	 * @param string $key         Setting key.
	 * @param string $title       Label.
	 * @param string $section     Section ID.
	 * @param string $description Help text.
	 * @param string $placeholder Placeholder.
	 * @param string $inputmode   inputmode attribute.
	 * @param string $css_class   CSS class.
	 * @return void
	 */
	private function text_field( string $key, string $title, string $section, string $description, string $placeholder, string $inputmode = 'text', string $css_class = 'regular-text' ): void {
		add_settings_field(
			'oha_deals_' . $key,
			$title,
			array( $this, 'render_text_field' ),
			self::SLUG,
			$section,
			array(
				'label_for'   => 'oha_deals_' . $key,
				'key'         => $key,
				'description' => $description,
				'placeholder' => $placeholder,
				'inputmode'   => $inputmode,
				'class'       => $css_class,
			)
		);
	}

	/**
	 * Adds a checkbox field.
	 *
	 * @param string $key         Setting key.
	 * @param string $title       Row title.
	 * @param string $section     Section ID.
	 * @param string $description Checkbox label.
	 * @return void
	 */
	private function checkbox_field( string $key, string $title, string $section, string $description ): void {
		add_settings_field(
			'oha_deals_' . $key,
			$title,
			array( $this, 'render_checkbox_field' ),
			self::SLUG,
			$section,
			array(
				'key'         => $key,
				'title'       => $title,
				'description' => $description,
			)
		);
	}
}
