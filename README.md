# Hypermedia Carousel for Datastar

A WordPress block that ships **one** slide in the page and streams the rest in a single
Server-Sent Events burst, so a carousel costs what a single image costs.

User-facing documentation lives in [`readme.txt`](readme.txt), which is what wordpress.org
publishes. This file is for people reading the source.

## Written by an agent

**This plugin was coded end to end by Claude Opus 5, agentically** — design,
implementation, tests, hardening and documentation — under the direction of
[@ltruchot](https://github.com/ltruchot), who set the constraints, arbitrated
the trade-offs and rejected the first answer more than once.

That claim is only worth something if you can check it, so here is what it
actually meant in practice:

- **Nothing was asserted that had not been measured.** The burst is 3 585 bytes
  and closes in 44 ms because that was measured on a running site, not
  estimated. The security boundary is described by the responses the endpoint
  actually gave to forged requests.
- **Every test was qualified by breaking what it tests.** Eleven deliberate
  sabotages of the code, ten caught on the first pass — and the eleventh
  revealed a genuine gap, now covered. Four sabotages of `.distignore`, all
  caught, but only after the first version of that check came back green twice
  and had to be rewritten: it modelled different rules from the ones `rsync`
  applies.
- **The mistakes are in the git history rather than tidied out of it.** A lint
  script that reported success while thirteen fatal errors scrolled past. A
  documented limitation about Content-Security-Policy that the bundled version
  had already fixed. A settings sanitiser that turned a submitted `-10` into ten
  seconds. Each was found, fixed, and written down where the next person will
  read it.

If you want the reasoning behind a decision rather than the result, the commit
messages carry it: they say what changed, what was measured, and what was
rejected.

## It brings no styles of its own

The block streams images into a container with an id and fades one into the
next. That is its whole job.

It ships **no sizing, no positioning, no colour and no icons**. Only the theme
knows how big the box should be, how an image that does not match its shape
should be cropped or padded or blurred at the edges, and where the controls
belong — so those decisions are left where the knowledge is. A plugin that
guessed would force every theme to out-specify the guess.

`blocks/carousel/style.css` is forty lines and contains exactly two things: the
`view-transition-name` that makes the browser cross-fade one slide into the
next, and the promise that `prefers-reduced-motion` means no motion. Two class
names are provided as hooks and never styled: `hcfd-sr` on the text inside a
control, and `is-paused` on the play/pause button.

`editor.css` is the one exception, and a narrow one: it lays the slides out flat
in the editor so an author can see what they picked. None of it reaches a
visitor.

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
| `includes/class-slides.php` | Ids to HTML, attachment filtering, HMAC token. Shared by the block and the endpoint so they cannot drift. |
| `includes/class-sse-endpoint.php` | The REST route and the takeover of its output. |
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
