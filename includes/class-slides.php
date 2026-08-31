<?php
/**
 * Turns a list of attachment ids into carousel slides, and guards the route
 * that serves them.
 *
 * Everything about "what a slide looks like" lives here, so the block and the
 * SSE endpoint cannot drift apart.
 *
 * @package HypermediaCarouselForDatastar
 */

namespace HCFD;

defined( 'ABSPATH' ) || exit;

/**
 * Slide source of truth.
 */
final class Slides {

	/** Ceiling on how many images one carousel may hold. Mirrored in the route's regex. */
	public const MAX_SLIDES = 50;

	/** Image sizes the block offers. Mirrored in block.json and in the route's enum. */
	public const SIZES = array( 'thumbnail', 'medium', 'large', 'full' );

	/**
	 * Keeps only ids that are really images this site can show.
	 *
	 * An id can name a post that was deleted, a PDF someone dropped into the
	 * gallery, or an attachment whose parent went to the trash. Left alone,
	 * each of those renders as an empty slide: the carousel then blanks out for
	 * a few seconds at a time with nothing on screen and no error anywhere.
	 * Callers must count what comes back, never what went in.
	 *
	 * @param array<int|string> $ids Raw attachment ids.
	 * @return array<int> Existing image attachments, in the given order, deduplicated.
	 */
	public static function sanitize_ids( array $ids ): array {
		$clean = array();

		foreach ( $ids as $id ) {
			$id = absint( $id );

			if ( 0 === $id || in_array( $id, $clean, true ) ) {
				continue;
			}

			$post = get_post( $id );

			if ( ! $post
				|| 'attachment' !== $post->post_type
				|| 'inherit' !== $post->post_status
				|| ! str_starts_with( (string) get_post_mime_type( $post ), 'image/' ) ) {
				continue;
			}

			$clean[] = $id;

			if ( count( $clean ) >= self::MAX_SLIDES ) {
				break;
			}
		}

		return $clean;
	}

	/**
	 * Normalises a requested image size.
	 *
	 * @param string $size Requested size slug.
	 * @return string A size this plugin is willing to render.
	 */
	public static function sanitize_size( string $size ): string {
		return in_array( $size, self::SIZES, true ) ? $size : 'large';
	}

	/**
	 * Builds the DOM id of one carousel instance.
	 *
	 * Deliberately not wp_unique_id(): that counter depends on how many other
	 * things were rendered first, so the same block can get a different id on a
	 * page assembled from partially cached fragments -- and the id is baked
	 * into the HMAC below.
	 *
	 * @param array<int> $ids      Sanitised attachment ids.
	 * @param string     $size     Sanitised size slug.
	 * @param int        $instance Ordinal of this carousel within the request.
	 * @return string DOM id, matching /^hcfd-[a-f0-9]{12}$/.
	 */
	public static function dom_id( array $ids, string $size, int $instance ): string {
		return 'hcfd-' . substr( md5( implode( ',', $ids ) . '|' . $size . '|' . $instance ), 0, 12 );
	}

	/**
	 * Builds the signal namespace for one instance.
	 *
	 * Datastar signals are global to the page, so two carousels would otherwise
	 * drive each other. The leading "k" is not decoration: a DOM id hash can
	 * start with a digit, which would make `$hcfd.0a1b2c.view` a syntax error
	 * in a Datastar expression.
	 *
	 * @param string $dom_id Value returned by dom_id().
	 * @return string Signal path, without the leading `$`, e.g. "hcfd.ka1b2c3d4e5f6".
	 */
	public static function signal_key( string $dom_id ): string {
		return 'hcfd.k' . substr( $dom_id, strlen( 'hcfd-' ) );
	}

	/**
	 * Signs the parameters the SSE route will be asked to honour.
	 *
	 * The route is public and takes attachment ids. Without this, anyone could
	 * walk it and have the site render the markup of any attachment it holds --
	 * drafts and unattached uploads included. A REST nonce cannot do this job:
	 * it depends on the current user and on the time, so it would be baked
	 * stale into any cached page. An HMAC over the parameters depends on
	 * neither.
	 *
	 * @param string $ids_csv Comma-separated sanitised ids, in render order.
	 * @param string $size    Sanitised size slug.
	 * @param string $target  DOM id of the instance.
	 * @return string 32 hex characters.
	 */
	public static function token( string $ids_csv, string $size, string $target ): string {
		return substr(
			hash_hmac( 'sha256', $ids_csv . '|' . $size . '|' . $target, wp_salt( 'hcfd_slides' ) ),
			0,
			32
		);
	}

	/**
	 * Checks a token in constant time.
	 *
	 * @param string $ids_csv Comma-separated ids as received.
	 * @param string $size    Size slug as received.
	 * @param string $target  DOM id as received.
	 * @param string $token   Token as received.
	 * @return bool Whether this site signed exactly these parameters.
	 */
	public static function verify_token( string $ids_csv, string $size, string $target, string $token ): bool {
		return hash_equals( self::token( $ids_csv, $size, $target ), $token );
	}

	/**
	 * Renders one slide.
	 *
	 * @param int    $id      Attachment id, already sanitised.
	 * @param string $size    Size slug, already sanitised.
	 * @param int    $index   Zero-based position.
	 * @param int    $total   Number of slides in the carousel.
	 * @param string $signal  Signal namespace, or '' for a static render.
	 * @return string HTML, safe to print.
	 */
	public static function render_slide( int $id, string $size, int $index, int $total, string $signal = '' ): string {
		$classes = 'hcfd-slide';
		$attrs   = array( 'class' => 'hcfd-image' );

		if ( 0 === $index ) {
			// The first slide is almost always the LCP element: it must not be
			// deferred by the browser or by a lazy-loading plugin. The two extra
			// class names are the opt-out markers the common ones agree on.
			$attrs['loading']       = 'eager';
			$attrs['fetchpriority'] = 'high';
			$attrs['class']        .= ' skip-lazy no-lazyload';
		}

		$image = wp_get_attachment_image( $id, $size, false, $attrs );

		if ( '' === $image ) {
			return '';
		}

		$hidden = '';
		$state  = '';

		if ( '' !== $signal ) {
			// Hidden, not transparent. An opacity-0 slide stays focusable and
			// stays in the accessibility tree: a screen reader would read all
			// seven slides and the tab order would wander into what nobody can
			// see.
			$hidden = 0 === $index ? '' : ' hidden';
			$state  = sprintf(
				' data-attr:hidden="$%s.view !== %d"',
				$signal,
				$index
			);
		}

		return sprintf(
			'<div class="%1$s" role="group" aria-roledescription="%2$s" aria-label="%3$s"%4$s%5$s>%6$s</div>',
			esc_attr( $classes ),
			esc_attr__( 'slide', 'hypermedia-carousel-for-datastar' ),
			esc_attr(
				sprintf(
					/* translators: 1: slide number, 2: total number of slides. */
					__( '%1$d of %2$d', 'hypermedia-carousel-for-datastar' ),
					$index + 1,
					$total
				)
			),
			$hidden,
			$state,
			$image
		);
	}

	/**
	 * Renders a run of slides.
	 *
	 * @param array<int> $ids    Sanitised ids for the whole carousel.
	 * @param string     $size   Sanitised size slug.
	 * @param int        $from   Zero-based index of the first slide to render.
	 * @param string     $signal Signal namespace, or '' for a static render.
	 * @return string HTML, safe to print.
	 */
	public static function render_slides( array $ids, string $size, int $from = 0, string $signal = '' ): string {
		$total = count( $ids );
		$html  = '';

		for ( $index = $from; $index < $total; $index++ ) {
			$html .= self::render_slide( $ids[ $index ], $size, $index, $total, $signal );
		}

		return $html;
	}
}
