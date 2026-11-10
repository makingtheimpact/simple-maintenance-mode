# MTI framework integration (unreleased)

Source of truth: owner-supplied `simple-maintenance-mode-v2.zip`, matching repository branch `feature/v2-maintenance-refactor` (main entrypoint Git blob `acd63b16c84f7cb913ba742b3abdcc7107f306f8`).

## What changed

- Vendored isolated MTI framework Core/Update PHP classes under `includes/framework/src`.
- Added `UpdaterModule` and `framework-bootstrap.php`, which register only during `plugins_loaded`.
- Added `Update URI` plugin header, versioned manifest, local release builder and validator, CI smoke tests.
- No changes to `smm_*` settings, existing global functions, activation, view templates, bypass token/cookie, or maintenance-mode response behavior.
- Plugin folder/slug stays `simple-maintenance-mode`. Version stays **2.0.0** until an approved subsequent release.

## Existing data must remain intact

**Do not rename or migrate these keys**: `simple_maintenance_mode_status`, `simple_maintenance_mode_page`, `simple_maintenance_mode_bypass_token`, and `smm_*` options.

A clean install and in-place upgrade must retain active mode, chosen page, custom content, exempt paths, bypass configuration, current token, and duration. Do not reset or rotate the bypass token on upgrade.

## Update endpoint gating — do not release yet

`https://plugins.makingtheimpact.com/api/v1/plugins/simple-maintenance-mode/update` is **not deployed**. With the `Update URI` header active, WordPress treats this as an external-update plugin; the update-client fails safely, but **no updates can be delivered** until a valid endpoint and approved release asset exist. Do not ship or merge this integration into a distributed release until the endpoint has been implemented and a real staging WordPress upgrade has succeeded.

For testing, intercept the HTTP request in staging with a synthetic response or deploy a protected test endpoint. Verify update info, installable ZIP root, settings persistence, and rollback before enabling production distribution.

## Plugin runtime vs build requirements

The plugin entrypoint continues to declare PHP 7.4+, and bundled framework runtime uses PHP 7.4-compatible constructs. Local manifest/ZIP tools require PHP 8.2+; they are never included in the release ZIP.

## Package

On a checkout whose root folder is `simple-maintenance-mode`:

```bash
php tools/validate-manifest.php mti-plugin.json
php tools/build-release.php . /tmp/simple-maintenance-mode-test.zip
unzip -t /tmp/simple-maintenance-mode-test.zip
unzip -Z1 /tmp/simple-maintenance-mode-test.zip | grep -E 'css/admin-style.css|js/admin.js|maintenance-fullscreen-template.php'
```

The package **must** include CSS/JS/template assets (declared in `release_paths`), but must not include `tests/`, `tools/`, `.github/`, or development files.

## Stage test checklist

1. Snapshot database/options on staging with legacy v2.0.0 active.
2. Install in-place upgrade using the correct ZIP folder.
3. Verify online, coming-soon (200), maintenance (503 and Retry-After), and exempt URL logic.
4. Verify administrator preview, custom page redirect, bypass token, cookie expiry, token regeneration, and response headers.
5. Verify admin layout, media picker, CSS/JS assets, countdown, CTA, REST/XML-RPC restrictions and caching.
6. Verify native WordPress update notification and details against a **test** update response.
7. Verify settings unchanged before/after, deactivate/reactivate, and recovery/rollback.

**No actual WordPress staging upgrade is claimed by this branch.**
