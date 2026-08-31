<?php
/**
 * Plugin Name:       Hypermedia Carousel for Datastar
 * Plugin URI:        https://github.com/ltruchot/hypermedia-carousel-for-datastar
 * Description:       An image carousel that ships one slide in the page and streams the rest over Server-Sent Events.
 * Version:           0.1.3
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Loïc Truchot
 * Author URI:        https://github.com/ltruchot
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hypermedia-carousel-for-datastar
 * Domain Path:       /languages
 *
 * @package HypermediaCarouselForDatastar
 */

/*
 * THIS FILE MUST PARSE ON ANCIENT PHP.
 *
 * The bundled Datastar SDK uses enums, which are a PARSE error below PHP 8.1 --
 * not a runtime error. A file containing one kills the process at `require`,
 * before any version check can run. So this entry point stays deliberately
 * plain: no return types, no union types, no arrow functions, no enums. It
 * checks the version first, and only then loads anything else.
 */

defined( 'ABSPATH' ) || exit;

define( 'HCFD_VERSION', '0.1.3' );
define( 'HCFD_FILE', __FILE__ );
define( 'HCFD_PATH', plugin_dir_path( __FILE__ ) );
define( 'HCFD_URL', plugin_dir_url( __FILE__ ) );

/** Minimum versions, mirrored in the plugin header and in readme.txt. */
define( 'HCFD_MIN_PHP', '8.1' );
define( 'HCFD_MIN_WP', '6.5' );

/**
 * Refuses activation on an unsupported stack.
 *
 * WordPress honours the `Requires PHP` header since 5.1, but a site that
 * downgrades PHP after activation never goes through activation again -- hence
 * the runtime guard in hcfd_boot() as well.
 */
function hcfd_activate() {
	if ( version_compare( PHP_VERSION, HCFD_MIN_PHP, '<' )
		|| version_compare( get_bloginfo( 'version' ), HCFD_MIN_WP, '<' ) ) {
		deactivate_plugins( plugin_basename( HCFD_FILE ) );
		wp_die(
			esc_html(
				sprintf(
					/* translators: 1: required PHP version, 2: required WordPress version. */
					__( 'Hypermedia Carousel for Datastar requires PHP %1$s and WordPress %2$s or later.', 'hypermedia-carousel-for-datastar' ),
					HCFD_MIN_PHP,
					HCFD_MIN_WP
				)
			),
			'',
			array( 'back_link' => true )
		);
	}
}
register_activation_hook( __FILE__, 'hcfd_activate' );

/**
 * Loads the plugin, or stays silent on an unsupported stack.
 *
 * Silence is deliberate: an admin notice here would need a translated string
 * evaluated before `init`, which trips _load_textdomain_just_in_time on
 * WordPress 6.7 and later. The activation guard above is where the user is
 * told; this one only has to avoid a fatal.
 */
function hcfd_boot() {
	if ( version_compare( PHP_VERSION, HCFD_MIN_PHP, '<' ) ) {
		return;
	}

	require_once HCFD_PATH . 'includes/class-settings.php';
	require_once HCFD_PATH . 'includes/class-slides.php';
	require_once HCFD_PATH . 'includes/class-assets.php';
	require_once HCFD_PATH . 'includes/class-csp.php';
	require_once HCFD_PATH . 'includes/class-block.php';
	require_once HCFD_PATH . 'includes/class-sse-endpoint.php';

	HCFD\Settings::init();
	HCFD\Assets::init();
	HCFD\Csp::init();
	HCFD\Block::init();
	HCFD\Sse_Endpoint::init();
}
hcfd_boot();
