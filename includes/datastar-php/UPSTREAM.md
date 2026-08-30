# Vendored: starfederation/datastar-php

| | |
|---|---|
| Upstream | https://github.com/starfederation/datastar-php |
| Tag | `1.0.1` |
| Licence | MIT (see `LICENSE.md`, kept verbatim) |
| Combined SHA-256 of the vendored PHP files | `60c1f63b95d3600ca3d9c2209c6790f8cfdc722d167e16a2d13285dee14c7138` |

## What was changed, and nothing else

1. `namespace starfederation\datastar` became `namespace HCFD\Datastar`
   (and the matching `use` statements). PHP does not isolate namespaces, so
   two plugins shipping the same unprefixed classes at different versions
   collide — either fatally, or silently, which is worse.
2. `defined( 'ABSPATH' ) || exit;` was added under the opening tag of each
   file, as the plugin directory expects of every PHP file.
3. `loader.php` was generated. There is no Composer autoloader.

The MIT headers are untouched. `readme.txt` credits the project.

## Refreshing

Run `bin/vendor-datastar.sh` from the repository root, then read the diff.
Two upstream defects are worked around in `includes/class-hcfd-sse-endpoint.php`
rather than patched here — see the comments there before assuming a new release
fixed them.
