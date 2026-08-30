<?php
/**
 * Registers the carousel block.
 *
 * @package HypermediaCarouselForDatastar
 */

namespace HCFD;

defined( 'ABSPATH' ) || exit;

/**
 * Block registration and per-request instance numbering.
 */
final class Block {

	/** How many carousels have been rendered so far in this request. */
	private static int $instances = 0;

	/**
	 * Hooks registration.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Registers the block from its metadata.
	 *
	 * Everything the block needs is declared in block.json: the render file,
	 * the editor script, and the two view modules. Nothing is enqueued here --
	 * WordPress puts viewScriptModule in the queue when the block actually
	 * renders, which is more accurate than any has_block() check could be
	 * (has_block sees neither template parts nor synced patterns).
	 */
	public static function register(): void {
		register_block_type_from_metadata( HCFD_PATH . 'blocks/carousel' );
	}

	/**
	 * Returns the ordinal of the carousel about to be rendered.
	 *
	 * Two carousels on one page must not share a DOM id, and must not share a
	 * signal namespace -- Datastar signals are global to the document, so they
	 * would drive each other.
	 *
	 * @return int Zero-based ordinal within the current request.
	 */
	public static function next_instance(): int {
		return self::$instances++;
	}
}
