=== Hypermedia Carousel for Datastar ===
Contributors: ltruchot
Tags: carousel, slideshow, gallery, images, accessibility
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.2.0
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
icons, because only your theme knows how big the box should be and how an image
that does not match its shape should be cropped or padded.

Give it a container with a size and the images will fill it.

= Styling hooks =

Since the block ships no styles, the names below are its public surface. They
are a **contract**: they will not change without a major version and a changelog
entry saying so, because a theme that styles them has no other way to reach the
markup.

* `hcfd-carousel` — the container. Carries the id, the ARIA region and the
  signals.
* `hcfd-live` — added to the container once the stream has landed. It arms the
  entry half of the cross-fade, and only then: `@starting-style` fires on an
  element's first render, page load included, so arming it unconditionally would
  fade in your first image — usually the largest thing on the page — on every
  visit.
* `hcfd-track` — wraps the slides and stacks them, so two can be on screen at
  once during a fade.
* `hcfd-slide` — one slide. The ones off screen carry `hidden`.
* `--hcfd-fade` — the length of the cross-fade. Defaults to 600ms; set it to
  taste, or leave it alone. The plugin sets it to `0s` when the transition
  setting is off.

= Content-Security-Policy =

It runs under a strict policy, without `unsafe-eval`, provided you hand it your page nonce
through the `hcfd_csp_nonce` filter — see the FAQ. Without that, under such a policy, the
carousel stays on its first image rather than breaking anything.

= Accessibility =

Read this before you install it. **This carousel starts on its own and offers no
way to stop it.** It ships no play, pause or arrow buttons — that is deliberate,
and it is a knowing failure of WCAG 2.2.2 (level A) on any page that carries
other content beside it. If your site has to meet WCAG at level A, this is not
the plugin for you, and no amount of theming will make it one.

What it does do:

* a visitor whose system asks for reduced motion gets **no rotation at all** —
  the photograph they load is the photograph they keep. That is checked on every
  tick, not once, so turning the setting on mid-visit is obeyed;
* slides that are not on screen carry `hidden`, so they are out of the tab order
  and out of the accessibility tree. An opacity-0 slide would be in both;
* nothing is announced. There is no live region, so a screen reader is not read
  a photograph every five seconds — and with nothing to press, there is no
  moment at which announcing one would be a reply to anything the visitor did;
* the carousel is a named ARIA region, every slide says its position and the
  total, and alternative text comes from the media library exactly as written
  there.

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

= 0.2.0 =
* **The controls are gone** — no play, no pause, no arrows. The carousel starts
  on load and does not stop. Breaking change: `hcfd-controls`, `hcfd-button`,
  `hcfd-sr` and `is-paused` no longer exist.
* **The cross-fade no longer goes through the View Transition API, which fixes a
  bug on every site.** `startViewTransition` captures the document element, so
  each swap cross-faded the whole viewport over itself — 597 604 pixels changed
  outside the carousel on one swap, measured. Two stacked images and a CSS
  transition cannot reach outside the block.
* New hooks: `--hcfd-fade` for the length of the fade, and `hcfd-live` on the
  container once the stream has landed.
* Reduced motion now stops the rotation outright — with no pause button, it is
  the only stillness a visitor can ask for.
* The setting is now called "Transition between slides".

= 0.1.5 =
* The French name is now « Diaporama ultraléger via SSE ». "for Datastar" is an
  artefact of the English naming rule and reads, in French, as "aimed at Datastar
  users" — which it is not. "via SSE" says how it works.

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
* All sizing, positioning, colour and icons were removed: they are the theme's
  to decide, and a plugin that guesses forces every theme to out-specify it.

= 0.1.0 =
* First release.
