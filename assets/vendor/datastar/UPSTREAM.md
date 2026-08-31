# Vendored: the Datastar browser runtime

| | |
|---|---|
| Upstream | https://github.com/starfederation/datastar |
| Tag | `v1.0.3` |
| File | `bundles/datastar.js`, renamed to carry its version |
| Licence | MIT (see `LICENSE.md`, kept verbatim) |
| SHA-256 | `5d6b7794a50a83d82da962aec5e382f5ae83ac7afbc751f903f7a9c6bd433c65` |

The file is the official build, **byte for byte unmodified**. It is minified,
so `datastar-1.0.3.js.map` sits beside it: that is what makes the
code readable, as the plugin directory requires of bundled libraries.

It is served from this plugin and never from a CDN — guideline 8 forbids
loading code from third-party CDNs.

This is the free MIT core. The Pro attributes (`data-animate`,
`data-persist`, `data-view-transition`, `data-match-media`,
`data-on-raf`, `data-query-string`, …) are **not** MIT and must never appear
in this plugin's markup.

## Refreshing

Run `bin/vendor-datastar.sh` from the repository root, then bump
`Assets::DATASTAR_VERSION`, which is what names the file.
