=== Hypermedia Carousel for Datastar ===
Contributors: ltruchot
Tags: carousel, slideshow, gallery, images, accessibility
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A carousel that puts one image in the page and streams the rest, so it costs what a single image costs.

== Description ==

A carousel usually makes you pay for every slide on every page view. Five photographs in the
markup are five photographs the browser has to reckon with before it can settle the layout, and
the first one — the one people actually see — waits its turn.

This block ships **one slide**. The rest arrive a moment later, in a single Server-Sent Events
burst, and the rotation then runs in the browser.

= What that buys you =

* **The first paint carries one image.** Nothing else competes with it.
* **A cached page stays correct.** The HTML a caching layer froze last week still shows the right
  first slide; the burst is never cached, so it delivers today's list and today's timing. Add a
  photograph and it appears without purging anything.
* **Nothing breaks when something fails.** No JavaScript, a blocked request, a strict security
  policy: the visitor sees the first image, which is what a carousel shows most of the time
  anyway. Search engines and reader modes get the full set from a `<noscript>` block.

= Honest about what it is =

This plugin trades **one extra HTTP request per page view** for a smaller first payload. On a
page with one carousel that is a good trade. It is not a good trade if you were going to put the
images in the markup anyway and did not care about the first paint.

The stream is a **single burst that closes at once** — a few hundredths of a second of PHP. It
never holds a connection open, because on most PHP hosting one held connection is one worker
taken out of a small pool, and a slideshow is not worth a site's capacity.

= It brings no styles of its own =

The block streams images into a container with an id and fades one into the
next. That is all it does. It ships no sizing, no positioning, no colour and no
icons, because only your theme knows how big the box should be, how an image
that does not match its shape should be cropped or padded, and where the
controls belong.

Give it a container with a size and the images will fill it. Two class names are
there for you to style and are never styled here: `hcfd-sr` on the text inside a
control, and `is-paused` on the play/pause button.

= Styling hooks =

Since the block ships no styles, the class names below are its public surface.
They are a **contract**: they will not change without a major version and a
changelog entry saying so, because a theme that styles them has no other way to
reach the markup.

* `hcfd-carousel` — the container. Carries the id, the ARIA region and, when a
  view transition is on, the `--hcfd-name` custom property.
* `hcfd-controls` — wraps the three buttons, first in the DOM so a keyboard
  reaches the stop button before the movement.
* `hcfd-button` with `hcfd-button--toggle`, `--prev`, `--next`.
* `is-paused` — added to the toggle while the carousel is stopped.
* `hcfd-sr` — the text inside a control. Left visible; hide it and add an icon
  if you prefer, but leave it in the accessibility tree.
* `hcfd-track` — wraps the slides, and carries `aria-live`.
* `hcfd-slide` — one slide. The ones off screen carry `hidden`.

What the block does style, and why it is not a look: a minimum target size (24px,
44px on a coarse pointer) and a focus ring that cannot vanish against a
photograph. A control too small to hit or whose focus cannot be seen is broken,
not unstyled. Both are minimums and one class deep, so a theme overrides either
with a single rule.

= Content-Security-Policy =

It runs under a strict policy, without `unsafe-eval`, provided you hand it your page nonce
through the `hcfd_csp_nonce` filter — see the FAQ. Without that, under such a policy, the
carousel stays on its first image rather than breaking anything.

= Accessibility =

Auto-rotating content is a common way to fail WCAG 2.2.2, so this block does not leave it to you:

* a real pause button, reachable **before** the moving content in the tab order;
* once paused, it never restarts on its own;
* `prefers-reduced-motion: reduce` stops the rotation, and is re-checked on every tick, so
  changing the system setting mid-visit is obeyed;
* slides that are not on screen are removed from the tab order and from the accessibility tree;
* announcements stay quiet while it rotates on its own, and become polite once the visitor takes
  control;
* alternative text comes from the media library, as written there.

== Frequently Asked Questions ==

= Where do I set the speed? =

Settings → Hypermedia Carousel, between 3 and 60 seconds, for the whole site. Everything else
lives in the block: choose the images the way you would in a Gallery block.

= Does it work with a strict Content-Security-Policy? =

Yes, without `unsafe-eval`, if you hand it your page nonce. Datastar 1.0.3 — the version bundled
here — added a CSP mode: it reads a nonce from the `<html>` tag and uses it when it compiles
expressions.

The plugin will not invent that nonce for you. A nonce is worth something only if the same value
appears in the `script-src` directive of the response, and only whatever sends that header can
guarantee the two match. So tell the plugin what it is:

`add_filter( 'hcfd_csp_nonce', fn() => my_csp_nonce() );`

The plugin then adds `data-nonce` to the opening `<html>` tag, and Datastar takes it from there.
Your own `<script>` tags still need whatever your policy requires of them — that part is not this
plugin's to solve.

Return nothing, or install nothing, and the attribute is never added. Under a policy that forbids
`unsafe-eval`, the carousel then stays on its first image: no error a visitor can see, no broken
layout, just a still picture.

= Does it phone home? =

No. The only request it makes is to your own site.

= What happens if I deactivate the plugin? =

The carousel disappears and leaves nothing behind: the block is rendered on the server, so post
content holds a block comment and no stale markup. Uninstalling removes the single option it
stores.

== Development ==

The source lives at
[github.com/ltruchot/hypermedia-carousel-for-datastar](https://github.com/ltruchot/hypermedia-carousel-for-datastar),
and what is published here is that source: there is no build step, no bundler,
and no minified file of our own. The editor script is plain ES5 written against
`wp.element.createElement`, and the two `*.asset.php` files are written by hand.
What you install is what you can read.

The one minified file is the Datastar runtime, which is third party and ships
with its source map beside it — see below.

To run the checks:

`composer install && composer exec -- phpunit` — unit tests
`composer exec -- phpcs` — WordPress coding standards
`cd e2e && BASE_URL=… npm test` — a real browser against a real site

== Third-party code ==

This plugin bundles two pieces of [Datastar](https://data-star.dev/), both MIT licensed, both
served from this plugin and never from a CDN:

* the Datastar browser runtime, `v1.0.3`, the official build byte for byte, with its source map
  alongside it — see `assets/vendor/datastar/UPSTREAM.md`;
* the Datastar PHP SDK, `1.0.1`, with its namespace prefixed so it cannot collide with another
  plugin shipping the same library — see `includes/datastar-php/UPSTREAM.md`.

Both directories carry the upstream licence and a note recording the exact version, its checksum,
and every change made to it. `bin/vendor-datastar.sh` in the source repository is what produces
them.

== Changelog ==

= 0.1.4 =
* French translation. The block is called « Diaporama ultraléger » there, which
  is the word a French editor reaches for; the English keyword `carousel` is kept
  so both searches find it.
* The controls now carry an accessibility floor: a minimum target size and a
  focus ring drawn in black and white so it cannot vanish against a photograph.
  A control too small to hit is broken, not unstyled.

= 0.1.3 =
* The settings page is now built with `add_settings_section()` and
  `add_settings_field()`, as the Plugin Handbook prescribes, instead of a
  hand-written table. Another plugin can now add a field to it.
* Added the Development section the directory guidelines ask for whenever a
  minified file ships.

= 0.1.2 =
* A Settings link now appears under the plugin on the plugins screen, as it does
  for every other plugin. Without it the settings page existed and nothing
  pointed at it.

= 0.1.1 =
* The block now ships only the cross-fade and the reduced-motion guarantee. All
  sizing, positioning, colour and icons were removed: they are the theme's to
  decide, and a plugin that guesses forces every theme to out-specify the guess.

= 0.1.0 =
* First release.
