<?php
/**
 * The plugin's only setting: how many seconds a slide stays on screen.
 *
 * @package HypermediaCarouselForDatastar
 */

namespace HCFD;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the rotation interval.
 */
final class Settings {

	/** Option name. Also hard-coded in uninstall.php, which cannot load this class. */
	public const OPTION = 'hcfd_settings';

	/** Settings group, used by register_setting() and settings_fields(). */
	private const GROUP = 'hcfd';

	/** Slug of the settings page, under Settings. */
	private const PAGE = 'hcfd';

	/** Shortest interval a human can follow, in seconds. */
	public const MIN_INTERVAL = 3;

	/** Longest interval that still reads as a carousel rather than a bug. */
	public const MAX_INTERVAL = 60;

	/**
	 * View transitions the block can ask the browser for.
	 *
	 * The slides arrive from the server, so what happens between two of them is
	 * a View Transition and not a CSS animation: the browser takes a snapshot
	 * either side of the patch and interpolates between them.
	 *
	 * Deliberately a list rather than a boolean: `none` and `fade` are what
	 * exists today, and the next one is meant to slot in beside them without
	 * changing the shape of the setting or of what reads it.
	 */
	public const TRANSITIONS = array( 'fade', 'none' );

	/** Shipped defaults. */
	public const DEFAULTS = array(
		'interval'   => 5,
		'transition' => 'fade',
	);

	/**
	 * Hooks the setting up.
	 *
	 * On `init`, not `admin_init`. register_setting() installs the default
	 * through the `default_option_{$option}` filter, and only for the request
	 * that called it -- registering on `admin_init` alone would make
	 * get_option() return false on the front end until someone opened the
	 * settings page. Reading through defaults() covers the same ground twice,
	 * on purpose.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );

		// The "Settings" link every other plugin shows under its own row on the
		// plugins screen. WordPress does not infer it: without this filter the
		// page exists but nothing on that screen points at it, and the only way
		// to find it is to already know where it is.
		add_filter(
			'plugin_action_links_' . plugin_basename( HCFD_FILE ),
			array( __CLASS__, 'add_settings_link' )
		);
	}

	/**
	 * Puts a Settings link at the front of the plugin's row actions.
	 *
	 * @param array<int|string, string> $links Links already there.
	 * @return array<int|string, string> Links, with ours first.
	 */
	public static function add_settings_link( $links ): array {
		$link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ),
			esc_html__( 'Settings', 'hypermedia-carousel-for-datastar' )
		);

		// Prepended, not appended: "Settings" belongs before "Deactivate", which
		// is where every core and well-behaved plugin puts it.
		array_unshift( $links, $link );

		return $links;
	}

	/**
	 * Declares the option.
	 */
	public static function register(): void {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'object',
				'default'           => self::DEFAULTS,
				'show_in_rest'      => false,
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			)
		);
	}

	/**
	 * Clamps the submitted interval into range and says so when it had to.
	 *
	 * @param mixed $input Raw value from the settings form.
	 * @return array<string, int> Sanitised settings.
	 */
	public static function sanitize( $input ): array {
		$raw      = is_array( $input ) && isset( $input['interval'] ) ? $input['interval'] : null;
		$interval = self::to_interval( $raw );

		if ( is_numeric( $raw ) && ( (int) $raw < self::MIN_INTERVAL || (int) $raw > self::MAX_INTERVAL ) ) {
			add_settings_error(
				self::OPTION,
				'hcfd_interval_range',
				sprintf(
					/* translators: 1: shortest allowed interval, 2: longest allowed interval, both in seconds. */
					__( 'The rotation interval must be between %1$d and %2$d seconds. Your value was adjusted.', 'hypermedia-carousel-for-datastar' ),
					self::MIN_INTERVAL,
					self::MAX_INTERVAL
				),
				'warning'
			);
		}

		return array(
			'interval'   => $interval,
			'transition' => self::to_transition(
				is_array( $input ) && isset( $input['transition'] ) ? $input['transition'] : null
			),
		);
	}

	/**
	 * Keeps only a transition this version knows how to play.
	 *
	 * An unknown value means the option was written by another version or by
	 * hand. Falling back to the shipped default is the only safe reading: a
	 * name that reaches the markup unchecked would set a view-transition-name
	 * nobody defined, and the browser would cross-fade the whole page.
	 *
	 * @param mixed $value Raw value.
	 * @return string One of self::TRANSITIONS.
	 */
	private static function to_transition( $value ): string {
		return in_array( $value, self::TRANSITIONS, true ) ? (string) $value : self::DEFAULTS['transition'];
	}

	/**
	 * Returns the transition to play between two slides.
	 *
	 * @return string One of self::TRANSITIONS.
	 */
	public static function transition(): string {
		$stored = wp_parse_args( (array) get_option( self::OPTION, array() ), self::DEFAULTS );

		return self::to_transition( $stored['transition'] );
	}

	/**
	 * Turns whatever was submitted or stored into a usable number of seconds.
	 *
	 * Deliberately NOT absint(): it mirrors a negative instead of refusing it,
	 * so someone posting -10 would silently get a ten-second carousel. A value
	 * that is not a number at all falls back to the shipped default rather than
	 * to the floor, because the floor is a boundary, not a preference.
	 *
	 * @param mixed $value Raw value.
	 * @return int Seconds, within range.
	 */
	private static function to_interval( $value ): int {
		if ( ! is_numeric( $value ) ) {
			return self::DEFAULTS['interval'];
		}

		return max( self::MIN_INTERVAL, min( self::MAX_INTERVAL, (int) $value ) );
	}

	/**
	 * Returns the rotation interval in seconds, always within range.
	 *
	 * Never call get_option() for this directly: a site whose option row was
	 * written by an older version, or by hand, can hold anything at all.
	 */
	public static function interval(): int {
		$stored = wp_parse_args( (array) get_option( self::OPTION, array() ), self::DEFAULTS );

		return self::to_interval( $stored['interval'] );
	}

	/**
	 * Adds the settings page under Settings.
	 *
	 * Not a top-level menu: one plugin, one option, no business being in the
	 * admin sidebar next to Posts and Media.
	 */
	public static function add_page(): void {
		add_options_page(
			__( 'Hypermedia Carousel', 'hypermedia-carousel-for-datastar' ),
			__( 'Hypermedia Carousel', 'hypermedia-carousel-for-datastar' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Renders the settings page.
	 *
	 * Hand-written rather than run through add_settings_section() and
	 * add_settings_field(): for a single number, those add three callbacks and
	 * a layer of indirection without adding a thing a reader can use. The nonce
	 * and the capability check still come from settings_fields() and
	 * options.php, which is the part that actually matters.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<p>
				<?php esc_html_e( 'Pick the images inside the Hypermedia Carousel block. This page holds the one setting shared by every carousel on the site.', 'hypermedia-carousel-for-datastar' ); ?>
			</p>

			<form action="options.php" method="post">
				<?php settings_fields( self::GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="hcfd-interval">
								<?php esc_html_e( 'Seconds per slide', 'hypermedia-carousel-for-datastar' ); ?>
							</label>
						</th>
						<td>
							<input
								type="number"
								id="hcfd-interval"
								name="<?php echo esc_attr( self::OPTION ); ?>[interval]"
								value="<?php echo esc_attr( (string) self::interval() ); ?>"
								min="<?php echo esc_attr( (string) self::MIN_INTERVAL ); ?>"
								max="<?php echo esc_attr( (string) self::MAX_INTERVAL ); ?>"
								step="1"
								required
								class="small-text"
							>
							<p class="description">
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: shortest allowed interval, 2: longest allowed interval, both in seconds. */
										__( 'Between %1$d and %2$d. A carousel of a single image never rotates, whatever this says.', 'hypermedia-carousel-for-datastar' ),
										self::MIN_INTERVAL,
										self::MAX_INTERVAL
									)
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="hcfd-transition">
								<?php esc_html_e( 'View transition', 'hypermedia-carousel-for-datastar' ); ?>
							</label>
						</th>
						<td>
							<select id="hcfd-transition" name="<?php echo esc_attr( self::OPTION ); ?>[transition]">
								<?php
								$hcfd_labels  = array(
									'fade' => __( 'Cross-fade — the browser fades one slide into the next', 'hypermedia-carousel-for-datastar' ),
									'none' => __( 'None — the slide is simply replaced', 'hypermedia-carousel-for-datastar' ),
								);
								$hcfd_current = self::transition();

								foreach ( self::TRANSITIONS as $hcfd_transition ) {
									printf(
										'<option value="%1$s"%2$s>%3$s</option>',
										esc_attr( $hcfd_transition ),
										selected( $hcfd_transition, $hcfd_current, false ),
										esc_html( $hcfd_labels[ $hcfd_transition ] ?? $hcfd_transition )
									);
								}
								?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Visitors who asked their system for reduced motion never get a transition, whatever this says.', 'hypermedia-carousel-for-datastar' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
