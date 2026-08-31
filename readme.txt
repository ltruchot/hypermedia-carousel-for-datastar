=== Hypermedia Carousel for Datastar ===
Contributors: ltruchot
Tags: carousel, slideshow, gallery, images, accessibility
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.5.0
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
  anyway. With JavaScript off there is no carousel at all -- just that one image, which is exactly
  what a plain image block would have given you.

= How you use it =

1. Add the **Hypermedia Carousel** block to a page.
2. Pick your photographs in the Media Library, the way you would for a Gallery. The order you
   choose is the order they rotate in.
3. That is it. There is no third step.

To change the pictures later, select the block and press **Images** in its toolbar — add one,
remove one, reorder them. **Nothing is copied into the page**: the block stores the media IDs and
fetches from the Media Library each time, so replacing a photograph in the library replaces it in
every carousel that uses it, with nothing to re-save.

Two settings, under **Settings → Hypermedia Carousel**, shared by every carousel on the site: how
long each image stays on screen, and how long the cross-fade takes. There is nothing to configure
per carousel except the images themselves and, if you like, the name a screen reader reads out.

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
* `hcfd-track` — wraps the slides and stacks them, so two can be on screen at
  once during a fade.
* `hcfd-slide` — one slide. The ones off screen carry `hidden`.
* `--hcfd-fade` — the length of the cross-fade. The plugin writes your setting
  here, or `0ms` when the transition is off; leave it unset in a theme and it
  falls back to 1000ms.

Only one layer moves, and a theme should keep it that way: the slide that is
leaving carries `hidden` and is painted on top, fading out over an incoming
slide that is already fully opaque. Fading both at once looks like the obvious
way to do it and is not — two half-transparent layers do not add up to an opaque
one, so a quarter of your container shows through in the middle of the swap,
which on a light background reads as a flash of light.

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

Settings → Hypermedia Carousel. Two numbers for the whole site: how long each image stays on
screen (2.5 to 25 seconds, by halves) and how long the cross-fade takes (100 to 2000 ms). A page
already in a cache keeps the cross-fade length it was rendered with; the interval travels in the
stream, which is never cached.

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

= 0.5.0 =
* **Images are one press away.** The block toolbar carries an **Images** button
  showing how many the carousel holds. It used to be in the sidebar only, where
  an author had no reason to look.
* **The plugin says what went wrong, in the console.** A carousel that never
  received its slides names its stream URL and what produces exactly that. Two
  Datastar runtimes on one page are reported too.
* New `hcfd_datastar_src` filter: a site already running Datastar can point this
  plugin at that copy instead of loading a second one.
* **The French translation now reaches the editor.** It never had:
  `wp_set_script_translations()` was called without a path, so WordPress ignored
  the translation this plugin ships and the whole sidebar stayed in English. The
  JSON file the editor actually reads is shipped too.
* The readme now explains how to use the block, which it did not.

Earlier releases: see the repository's history. Only the current release is kept
here, because this file has a 10 KiB budget and a changelog grows for ever.
