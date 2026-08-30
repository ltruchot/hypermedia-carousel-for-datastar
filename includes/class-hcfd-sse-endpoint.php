<?php
/**
 * The REST route that streams the remaining slides.
 *
 * One burst, then the connection closes. There is no loop and no sleep here,
 * and that is a deliberate architectural choice rather than a simplification:
 * PHP-FPM pins one worker per open connection, so a stream held open for the
 * length of a visit would trade the whole site's capacity for a slideshow.
 *
 * @package HypermediaCarouselForDatastar
 */

namespace HCFD;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Serves slides two and up as a single Datastar burst.
 */
final class Sse_Endpoint {

	/** REST namespace. */
	private const REST_NAMESPACE = 'hcfd/v1';

	/** REST route. */
	private const ROUTE = '/slides';

	/**
	 * Hooks the route up.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Declares the route and everything it will accept.
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'dispatch' ),
				'permission_callback' => array( __CLASS__, 'permission' ),
				'args'                => array(
					'ids'    => array(
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => static function ( $value ) {
							return (bool) preg_match(
								'/^\d+(,\d+){0,' . ( Slides::MAX_SLIDES - 1 ) . '}$/',
								(string) $value
							);
						},
					),
					'size'   => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => Slides::SIZES,
					),
					'target' => array(
						'type'              => 'string',
						'required'          => true,
						/*
						 * This regex is a security boundary, not tidiness: the
						 * value is interpolated into the CSS selector of a
						 * patch, so anything looser would let a caller have the
						 * site inject markup anywhere in the document.
						 */
						'validate_callback' => static function ( $value ) {
							return (bool) preg_match( '/^hcfd-[a-f0-9]{12}$/', (string) $value );
						},
					),
					'token'  => array(
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => static function ( $value ) {
							return (bool) preg_match( '/^[a-f0-9]{32}$/', (string) $value );
						},
					),
				),
			)
		);
	}

	/**
	 * Lets through only parameters this site signed.
	 *
	 * The route is genuinely public -- it feeds a carousel that anonymous
	 * visitors see -- so there is no user to authenticate. What has to be
	 * proven is that these ids were put together by this site, not by whoever
	 * is calling. Without that proof the route would happily render the markup
	 * of any attachment the site holds, drafts and unattached uploads included,
	 * to anyone willing to count from one.
	 *
	 * A REST nonce cannot do this job: it depends on the current user and on
	 * the time, so it would be frozen stale into any cached page.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return true|WP_Error True when the signature holds.
	 */
	public static function permission( WP_REST_Request $request ) {
		$valid = Slides::verify_token(
			(string) $request['ids'],
			(string) $request['size'],
			(string) $request['target'],
			(string) $request['token']
		);

		if ( ! $valid ) {
			return new WP_Error(
				'hcfd_bad_token',
				__( 'This carousel request was not issued by this site.', 'hypermedia-carousel-for-datastar' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Takes over the response and streams the burst.
	 *
	 * The takeover happens on `rest_pre_serve_request`, which the core applies
	 * after it has sent its own headers -- that is the only point at which ours
	 * are guaranteed to have the last word.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response Never serialised; the filter below returns true.
	 */
	public static function dispatch( WP_REST_Request $request ) {
		add_filter(
			'rest_pre_serve_request',
			static function ( $served ) use ( $request ) {
				self::emit( $request );

				// True tells WP_REST_Server the response has been sent. It then
				// returns null and rest_api_loaded() calls die(), which still
				// runs the shutdown hooks.
				return true;
			},
			PHP_INT_MAX
		);

		return new WP_REST_Response( null, 200 );
	}

	/**
	 * Writes the burst.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 */
	private static function emit( WP_REST_Request $request ): void {
		self::clear_the_way();

		require_once HCFD_PATH . 'includes/datastar-php/loader.php';

		$sse = new Datastar\ServerSentEventGenerator();
		$sse->sendHeaders();

		/*
		 * The SDK only sends `Cache-Control: no-cache`. The core would have
		 * sent stronger headers, but rest_send_nocache_headers defaults to
		 * is_user_logged_in() -- so for the anonymous visitor this route exists
		 * for, nothing was sent at all, and a proxy could hand one visitor's
		 * burst to everybody.
		 */
		nocache_headers();

		$target = (string) $request['target'];
		$size   = Slides::sanitize_size( (string) $request['size'] );

		/*
		 * Re-sanitised even though the token proves the site signed these ids:
		 * an image can have been deleted since the page was rendered, and a
		 * cached page can be days old. Count what comes back, never what went
		 * in -- a count that is too high makes the carousel step onto slides
		 * that do not exist and blink on an empty box.
		 */
		$ids   = Slides::sanitize_ids( explode( ',', (string) $request['ids'] ) );
		$total = count( $ids );

		if ( $total > 1 ) {
			$signal = Slides::signal_key( $target );

			$sse->patchElements(
				Slides::render_slides( $ids, $size, 1, $signal ),
				array(
					'selector' => '#' . $target . ' .hcfd-track',
					'mode'     => 'append',
				)
			);

			$sse->patchSignals(
				(string) wp_json_encode(
					array(
						'hcfd' => array(
							substr( $signal, strlen( 'hcfd.' ) ) => array(
								'count'  => $total,
								'loaded' => true,
							),
						),
					)
				)
			);

			// Sent last, and as its own element, so that the cadence only starts
			// once `count` is right. It also means the interval reaches a page
			// a caching layer froze days ago: the HTML is stale, the burst is
			// never cached. data-on-interval parses its duration from the
			// attribute NAME, so no signal could have carried it.
			$sse->patchElements( self::cadence_element( $target, $signal ) );
		}
	}

	/**
	 * Builds the element that drives the rotation.
	 *
	 * @param string $target DOM id of the carousel.
	 * @param string $signal Signal path of the carousel.
	 * @return string HTML for a single element.
	 */
	private static function cadence_element( string $target, string $signal ): string {
		/*
		 * The reduced-motion check lives inside the expression rather than in a
		 * script file, and that is not a shortcut. CSS cannot stop an interval,
		 * so something has to test the query -- and testing it on every tick
		 * means a visitor who changes the system setting mid-visit is obeyed at
		 * once, which a value read at load time could not do.
		 */
		$advance = sprintf(
			'!$%1$s.paused'
				. ' && !matchMedia(\'(prefers-reduced-motion: reduce)\').matches'
				. ' && ($%1$s.view = ($%1$s.view + 1) %% $%1$s.count)',
			$signal
		);

		return sprintf(
			'<div id="%1$s-cadence" hidden data-on-interval__duration.%2$ds__viewtransition="%3$s"></div>',
			esc_attr( $target ),
			Settings::interval(),
			esc_attr( $advance )
		);
	}

	/**
	 * Makes sure nothing but the burst can reach the socket.
	 *
	 * A single stray byte -- a deprecation notice from another plugin, a BOM at
	 * the head of some file, half a gzip frame -- lands before the first
	 * `event:` line and the parser on the other end gives up on the whole
	 * stream. None of it would show up in a log.
	 */
	private static function clear_the_way(): void {
		// phpcs:disable WordPress.PHP.IniSet.Risky -- see the docblock: the alternative is a corrupted stream.
		@ini_set( 'display_errors', '0' );
		@ini_set( 'html_errors', '0' );
		@ini_set( 'zlib.output_compression', '0' );
		// phpcs:enable WordPress.PHP.IniSet.Risky

		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', '1' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		/*
		 * WordPress itself does not use $_SESSION, but plenty of plugins do,
		 * and an open session holds a lock that blocks every other request from
		 * the same visitor until this one ends.
		 */
		if ( PHP_SESSION_ACTIVE === session_status() ) {
			session_write_close();
		}

		/*
		 * Destroy the buffers rather than flush them: their contents would be
		 * written into the stream. This also disarms a defect in the SDK, whose
		 * sendEvent() calls ob_end_flush() where ob_end_clean() was meant --
		 * with no buffer left, that branch is never reached.
		 */
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
	}
}
