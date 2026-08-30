<?php
/**
 * Registers the bundled Datastar runtime as a script module.
 *
 * @package HypermediaCarouselForDatastar
 */

namespace HCFD;

defined( 'ABSPATH' ) || exit;

/**
 * Script module registration.
 */
final class Assets {

	/** Module identifier, referenced by block.json and by view.asset.php. */
	public const MODULE = 'hcfd-datastar';

	/** Version of the bundled Datastar runtime. Must match the file name. */
	public const DATASTAR_VERSION = '1.0.3';

	/**
	 * Hooks registration.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Registers Datastar under our own module id.
	 *
	 * block.json lists this id in viewScriptModule without a "file:" prefix,
	 * which tells WordPress to use it as-is rather than to register a file of
	 * its own. Enqueueing then happens when a carousel really renders, and the
	 * import map deduplicates it if a second block ever asks for it.
	 *
	 * Datastar is served from this plugin and never from a CDN. That is not a
	 * preference: guideline 8 of the plugin directory forbids loading code from
	 * third-party CDNs.
	 */
	public static function register(): void {
		wp_register_script_module(
			self::MODULE,
			HCFD_URL . 'assets/vendor/datastar/datastar-' . self::DATASTAR_VERSION . '.js',
			array(),
			self::DATASTAR_VERSION
		);
	}
}
