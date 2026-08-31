# Vendored: starfederation/datastar-php

| | |
|---|---|
| Upstream | https://github.com/starfederation/datastar-php |
| Tag | `1.0.1` |
| Licence | MIT (see `LICENSE.md`, kept verbatim) |
| Combined SHA-256 of the vendored PHP files | `808074c0858d5de5b3096cd87ddf8d3795effb5e21a80148ef92cd7a715a1f75` |

## What was changed, and nothing else

1. `namespace starfederation\datastar` became `namespace HCFD\Datastar`
   (and the matching `use` statements). PHP does not isolate namespaces, so
   two plugins shipping the same unprefixed classes at different versions
   collide — either fatally, or silently, which is worse.
2. `defined( 'ABSPATH' ) || exit;` was added under the opening tag of each
   file, as the plugin directory expects of every PHP file.
3. `readSignals()` was replaced by a stub that throws. The original reads
   `$_GET` and `$_SERVER` with no guards; on a request without signals PHP 8
   raises *Undefined array key*, and with `WP_DEBUG_DISPLAY` on that warning
   prints **into the event stream** and makes it unparseable. This plugin reads
   its parameters from `WP_REST_Request`, which validates them declaratively.
   A throwing stub rather than a deletion, so the class keeps its shape and a
   future caller gets a sentence instead of a fatal.
4. `loader.php` was generated. There is no Composer autoloader.

The MIT headers are untouched. `readme.txt` credits the project.

## What is deliberately NOT vendored

`events/ExecuteScript.php`, `events/Location.php` and `events/RemoveElements.php` are
removed: this plugin emits element and signal patches only, and shipping code that
never runs only gives a reviewer more to read. Upstream's `.gitattributes` is
removed too — a hidden file inside a plugin is an error for Plugin Check.

## Findings Plugin Check reports here, and why they stand

Three come from this library and are inherent to what it does. They are **not**
patched, because patching vendored code is how a fork starts:

| Where | Finding | Why it stands |
|---|---|---|
| `ServerSentEventGenerator::sendEvent()` | `EscapeOutput.OutputNotEscaped` on `echo $output` | That echo **is** the SSE frame. Escaping happens where the markup is composed, in `HCFD\Slides`, which is the only place that knows what is data and what is markup. |
| `PatchElements::getMode()` / `getNamespace()` | `EscapeOutput.ExceptionNotEscaped` (x2) | Exception messages built from a hard-coded enum. No user input reaches them. |
| `ServerSentEventGenerator::headers()` | `MissingUnslash`, `InputNotSanitized` on `$_SERVER['SERVER_PROTOCOL']` | Compared against the literal `'HTTP/1.1'` to decide whether a `Connection` header is legal. The value is never echoed, stored, or used to build anything. |
| `ServerSentEventGenerator::readSignals()` | four `ValidatedSanitizedInput` warnings and a nonce warning | **This plugin never calls that method.** It reads `$_GET` and `$_SERVER` without guards; parameters come from `WP_REST_Request` instead, which validates them declaratively. |

## Refreshing

Run `bin/vendor-datastar.sh` from the repository root, then read the diff.
Two upstream defects are worked around in `includes/class-sse-endpoint.php`
rather than patched here — see the comments there before assuming a new release
fixed them.
