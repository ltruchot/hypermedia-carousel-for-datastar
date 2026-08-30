# Hypermedia Carousel for Datastar

A WordPress block that ships **one** slide in the page and streams the rest in a single
Server-Sent Events burst, so a carousel costs what a single image costs.

User-facing documentation lives in [`readme.txt`](readme.txt), which is what wordpress.org
publishes. This file is for people reading the source.

## How it works

1. The block's server render emits slide 1, the shell, the controls, and slides 2..n inside a
   `<noscript>` — images in there are never fetched while scripting is on, so they cost nothing
   and still exist for crawlers and reader modes.
2. `data-init__delay.500ms="@get(…)"` opens an SSE stream once the page has settled.
3. The server answers with one `datastar-patch-elements` (the remaining slides), one
   `datastar-patch-signals` (how many, and that the burst landed), and one more
   `datastar-patch-elements` carrying the element that drives the rotation — **then closes**.
   Measured: 3 585 bytes, 44 ms. No loop, no `sleep`, no worker held.
4. The rotation runs in the browser, with a View Transition between slides.

Sending the cadence in the burst rather than in the initial markup is deliberate: the HTML can be
frozen by a caching layer, the burst never is. Change the interval and every visitor gets it,
purge or no purge.

## Layout

| Path | What it is |
|---|---|
| `hypermedia-carousel-for-datastar.php` | Header, version guard, constants. Must parse on ancient PHP — see the comment at the top. |
| `includes/class-hcfd-slides.php` | Ids to HTML, attachment filtering, HMAC token. Shared by the block and the endpoint so they cannot drift. |
| `includes/class-hcfd-sse-endpoint.php` | The REST route and the takeover of its output. |
| `blocks/carousel/` | `block.json`, the server render, the editor script, the styles. |
| `includes/datastar-php/` | The Datastar PHP SDK, namespace-prefixed. See its `UPSTREAM.md`. |
| `assets/vendor/datastar/` | The Datastar browser runtime, verbatim. See its `UPSTREAM.md`. |
| `bin/vendor-datastar.sh` | What produces those two directories. |

## No build step

There is none, on purpose. The editor script is written against `wp.element.createElement`
rather than JSX, and the two `*.asset.php` files are written by hand. What is published is what
runs: easier to review, and one fewer thing that can fall out of sync.

If the editor UI ever outgrows three controls, moving to `@wordpress/scripts` is a reversible
decision.

## Refreshing the vendored dependencies

```sh
bin/vendor-datastar.sh          # then read the diff
```

Bump `HCFD\Assets::DATASTAR_VERSION` when the browser bundle moves: it is what names the file.

## Licence

GPL v2 or later. The two bundled Datastar components are MIT; their licences are kept beside
them.
