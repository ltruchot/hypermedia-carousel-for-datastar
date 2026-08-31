<?php
/**
 * Server render of the carousel block.
 *
 * Emits ONE slide plus the shell. Slides two and up arrive later, in a single
 * SSE burst, and the rotation then runs in the browser. That is the whole point
 * of the plugin: a carousel that costs what one image costs.
 *
 * In scope, provided by WordPress: $attributes, $content, $block.
 *
 * @package HypermediaCarouselForDatastar
 */

use HCFD\Block;
use HCFD\Settings;
use HCFD\Slides;

defined( 'ABSPATH' ) || exit;

$hcfd_ids  = Slides::sanitize_ids( (array) ( $attributes['ids'] ?? array() ) );
$hcfd_size = Slides::sanitize_size( (string) ( $attributes['sizeSlug'] ?? 'large' ) );
$hcfd_n    = count( $hcfd_ids );

// No images, nothing at all. Not an empty box, not a placeholder: a carousel
// with no photographs has nothing to say on a live site.
if ( 0 === $hcfd_n ) {
	return '';
}

$hcfd_label = trim( (string) ( $attributes['ariaLabel'] ?? '' ) );

if ( '' === $hcfd_label ) {
	$hcfd_label = __( 'Image carousel', 'hypermedia-carousel-for-datastar' );
}

$hcfd_dom_id = Slides::dom_id( $hcfd_ids, $hcfd_size, Block::next_instance() );
$hcfd_signal = Slides::signal_key( $hcfd_dom_id );

/*
 * The cross-fade, or the absence of one.
 *
 * The stylesheet owns the fade and reads its length from --hcfd-fade, so
 * turning the setting off is one declaration rather than a second code path:
 * zero is a cut. A theme that wants another length sets the same property.
 *
 * The length rides in the rendered HTML rather than in the burst, unlike the
 * interval. That is a real limitation and it is stated in the settings page:
 * a page already in a cache keeps the length it was rendered with. The interval
 * had to travel in the burst because data-on-interval parses its duration from
 * the attribute NAME; a custom property has no such constraint, and putting it
 * here keeps the swap correct even if the burst never arrives.
 */
$hcfd_fade = sprintf(
	' style="--hcfd-fade:%dms"',
	'fade' === Settings::transition() ? Settings::duration() : 0
);

/*
 * The editor previews a dynamic block by rendering it through the REST block
 * renderer, where Datastar is not loaded -- viewScriptModule belongs to the
 * front end. Left alone, the author would see one image and no way to tell
 * whether the other six were saved. So the preview shows the whole selection,
 * flat, with no behaviour attached.
 */
$hcfd_is_preview = defined( 'REST_REQUEST' ) && REST_REQUEST;

// One image never rotates, so it needs no shell and no stream.
$hcfd_is_static = $hcfd_is_preview || 1 === $hcfd_n;

$hcfd_wrapper = get_block_wrapper_attributes(
	array( 'class' => 'hcfd' . ( $hcfd_is_preview ? ' hcfd--preview' : '' ) )
);

if ( $hcfd_is_static ) {
	printf(
		'<div %1$s><div class="hcfd-track">%2$s</div></div>',
		$hcfd_wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by get_block_wrapper_attributes().
		Slides::render_slides( $hcfd_ids, $hcfd_size ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by Slides, which escapes.
	);
	return;
}

$hcfd_ids_csv = implode( ',', $hcfd_ids );

/*
 * Not esc_url(): it turns "&" into "&#038;", and the single esc_attr() applied
 * to the whole expression below would then encode the ampersand a second time.
 * The URL is our own, built from rest_url(), and carries nothing a user typed.
 */
$hcfd_stream = add_query_arg(
	array(
		'ids'    => $hcfd_ids_csv,
		'size'   => $hcfd_size,
		'target' => $hcfd_dom_id,
		'token'  => Slides::token( $hcfd_ids_csv, $hcfd_size, $hcfd_dom_id ),
	),
	rest_url( 'hcfd/v1/slides' )
);

/*
 * `count` starts at 1 because exactly one slide exists right now. The burst
 * corrects it. Getting this wrong would make the rotation step through slides
 * that are not there yet, and the carousel would blink on an empty box.
 *
 * `loaded` guards against a second burst: were data-init to run again, an
 * append would duplicate every slide.
 */
$hcfd_signals = wp_json_encode(
	array(
		'hcfd' => array(
			substr( $hcfd_signal, strlen( 'hcfd.' ) ) => array(
				'view'   => 0,
				'count'  => 1,
				'loaded' => false,
			),
		),
	)
);

$hcfd_init = sprintf(
	'!$%1$s.loaded && @get(\'%2$s\')',
	$hcfd_signal,
	$hcfd_stream
);
?>
<div <?php echo $hcfd_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by get_block_wrapper_attributes(). ?>>
	<div
		id="<?php echo esc_attr( $hcfd_dom_id ); ?>"
		class="hcfd-carousel"
		role="region"
		aria-roledescription="<?php esc_attr_e( 'carousel', 'hypermedia-carousel-for-datastar' ); ?>"
		aria-label="<?php echo esc_attr( $hcfd_label ); ?>"
		<?php echo $hcfd_fade; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a literal chosen above, not data. ?>
		data-signals="<?php echo esc_attr( (string) $hcfd_signals ); ?>"
		data-init__delay.500ms="<?php echo esc_attr( $hcfd_init ); ?>"
	>
		<div class="hcfd-track">
			<?php
			echo Slides::render_slide( $hcfd_ids[0], $hcfd_size, 0, $hcfd_n, $hcfd_signal ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by Slides, which escapes.
			?>
			<noscript>
				<?php
				/*
				 * Images inside <noscript> are not fetched while scripting is
				 * on, so this costs nothing to a normal visit. It is what a
				 * crawler, a reader mode, and a visitor without JavaScript get
				 * instead of six slides that exist nowhere in the page.
				 */
				echo Slides::render_slides( $hcfd_ids, $hcfd_size, 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by Slides, which escapes.
				?>
			</noscript>
		</div>

		<?php
		/*
		 * Placeholder for the element that carries the rotation cadence. The
		 * burst replaces it whole, which is how the interval reaches a page
		 * that a caching layer froze days ago: the HTML is stale, the burst
		 * never is. data-on-interval parses its duration from the attribute
		 * NAME, so no signal could have carried it.
		 */
		?>
		<div id="<?php echo esc_attr( $hcfd_dom_id ); ?>-cadence" hidden></div>
	</div>
</div>
