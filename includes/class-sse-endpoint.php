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
			static function ( $served ) use ( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- the filter's signature is WordPress's, and we always take over.
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

		self::close_to_other_origins();

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
		 * The whole behaviour, in one expression: unless the visitor asked for
		 * reduced motion, step to the next slide and wrap around at the end.
		 *
		 * There is no __viewtransition modifier here, and its removal is the
		 * fix for a bug rather than a simplification for its own sake.
		 * startViewTransition captures the document element, so every swap
		 * cross-faded the ENTIRE PAGE over itself -- measured on a real page,
		 * one swap: 597 604 pixels changed outside the carousel, across the
		 * whole viewport. Decorative shapes elsewhere on the page flickered on
		 * a five second beat while the photograph appeared not to move.
		 *
		 * What made that flash possible was the PAIRING: the modifier called
		 * startViewTransition whether or not the expression changed anything,
		 * so the reduced-motion guard below -- which skips the assignment --
		 * produced a full-page cross-fade over identical content. With the
		 * modifier gone, a guard that changes nothing changes nothing.
		 *
		 * The guard stays in the expression rather than in a stylesheet because
		 * CSS cannot stop an interval, and it is read on every tick so that a
		 * visitor who turns the system setting on mid-visit is obeyed at once.
		 * It carries more weight than it looks: this carousel ships no
		 * controls, so it is the only stillness a visitor can ask for.
		 */
		$advance = sprintf(
			'!matchMedia(\'(prefers-reduced-motion: reduce)\').matches'
				. ' && ($%1$s.view = ($%1$s.view + 1) %% $%1$s.count)',
			$signal
		);

		return sprintf(
			'<div id="%1$s-cadence" hidden data-on-interval__duration.%2$ds="%3$s"></div>',
			esc_attr( $target ),
			Settings::interval(),
			esc_attr( $advance )
		);
	}

	/**
	 * Refuses to let another origin read this response.
	 *
	 * WordPress answers every REST request with the caller's own origin in
	 * `Access-Control-Allow-Origin`, plus `Access-Control-Allow-Credentials:
	 * true`. That is its documented default and it is not this plugin's place
	 * to change it for other routes -- but this route is only ever called by
	 * the page it was rendered into, so cross-origin reads have nothing to
	 * serve and no reason to be allowed.
	 *
	 * Nothing confidential would leak either way: the burst is the same markup
	 * for every visitor, and it takes a signature this site issued to get it at
	 * all. This narrows the channel to exactly what it is for, which is cheaper
	 * than arguing about whether it matters.
	 *
	 * `header_remove()` and not a filter: `rest_send_cors_headers` has already
	 * run by the time `rest_pre_serve_request` fires, and filtering it would
	 * change the answer for every other route on the site.
	 */
	private static function close_to_other_origins(): void {
		foreach (
			array(
				'Access-Control-Allow-Origin',
				'Access-Control-Allow-Credentials',
				'Access-Control-Allow-Methods',
				'Access-Control-Allow-Headers',
				'Access-Control-Expose-Headers',
			) as $header
		) {
			header_remove( $header );
		}
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
		/*
		 * Three linters object to these, and all three are right in general and
		 * wrong here. Turning error display off for the length of one streaming
		 * response is not "changing production error reporting": it is the only
		 * way a notice raised by somebody else's code does not end up inside a
		 * text/event-stream body. Nothing is turned back on afterwards because
		 * the request ends here.
		 */
		// phpcs:disable WordPress.PHP.IniSet, Squiz.PHP.DiscouragedFunctions.Discouraged, PluginCheck.CodeAnalysis.PHPErrorReporting, WordPress.PHP.NoSilencedErrors.Discouraged
		@ini_set( 'display_errors', '0' );
		@ini_set( 'html_errors', '0' );
		@ini_set( 'zlib.output_compression', '0' );
		// phpcs:enable WordPress.PHP.IniSet, Squiz.PHP.DiscouragedFunctions.Discouraged, PluginCheck.CodeAnalysis.PHPErrorReporting, WordPress.PHP.NoSilencedErrors.Discouraged

		if ( function_exists( 'apache_setenv' ) ) {
			// Apache's own lever for the same job X-Accel-Buffering does on
			// nginx: mod_deflate would otherwise buffer the stream and hold
			// every event until the response ends, which for a stream means
			// forever.
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_apache_setenv
			@apache_setenv( 'no-gzip', '1' );
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
