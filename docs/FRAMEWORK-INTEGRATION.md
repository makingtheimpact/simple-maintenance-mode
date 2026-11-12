# MTI Framework integration — Simple Maintenance Mode (draft)

**Source of truth:** owner-supplied `simple-maintenance-mode-v2.zip`, matching `feature/v2-maintenance-refactor` (main plugin file Git blob `acd63b16c84f7cb913ba742b3abdcc7107f306f8`).

**Status:** Draft PR #2. The plugin release version remains **2.0.0**; no public ZIP, private key, or production update metadata has been published. Do not merge or install this branch on client websites yet.

## Integration

This branch now vendors the latest reviewed [MTI WP Plugin Framework PR #1](https://github.com/makingtheimpact/mti-wp-plugin-framework/pull/1), including:

- Namespaced `Core\Module`, `Core\Kernel`, `Update\Metadata`, `Update\SignedEnvelope`, and `Update\Client` classes under `includes/framework/src/`.
- Existing `includes/UpdaterModule.php` registers the native external update client **only when the installed manifest contains a valid, pinned public signing key**. The current development manifest contains an empty `public_keys` object, so **no update checks run**.
- Canonical WordPress `Update URI`: `https://api.nightmarketplugins.com/plugins/simple-maintenance-mode/`.
- Primary signed update API: `https://api.nightmarketplugins.com/api/v1/plugins/simple-maintenance-mode/update`.
- Secondary signed update API: `https://updates.thepluginmarket.com/api/v1/plugins/simple-maintenance-mode/update`.
- Approved package hosts: `github.com`, `downloads.thepluginmarket.com`.
- Shared manifest validator, safe ZIP builder, and **offline** Ed25519 release signer copied into development-only `tools/`. No signing key is stored in this repository or on a web host.

The separate [Night Market Plugins Laravel app](https://github.com/makingtheimpact/nightmarketplugins/pull/1) contains a draft, read-only signed metadata API, but is **not deployed**. DNS currently points the two API names to one VPS, so the secondary is not an independent disaster-recovery service yet.

## Compatibility

- **PHP 8.1–8.5** for the bundled framework runtime and the current plugin version.
- WordPress header minimum stays **6.4** to preserve the existing plugin's declared WordPress compatibility, pending product-specific testing. For PHP **8.5**, use WordPress **6.9+**, the oldest WordPress release with PHP 8.5 support.
- PHP 8.1 is end-of-life and should not be recommended for live hosting merely because we keep source compatibility.
- The plugin's existing PHP 7.4 baseline is retired by this new framework integration. An existing site running PHP 7.4 or 8.0 **must upgrade PHP before installing the future framework-enabled release**.

## Zero user data changes

**Do not rename or migrate** `simple_maintenance_mode_status`, `simple_maintenance_mode_page`, `simple_maintenance_mode_bypass_token` or any `smm_*` option. Preserve existing bypass cookies/tokens, layouts, customized pages, and URL exemptions.

The main v2 plugin's operational functions, maintenance/coming-soon response codes, admin view, and CSS/JS/template files were not reworked as part of this framework update. We are replacing only the framework integration and developer tooling.

## Test and release process

The CI checks PHP syntax, source-level option preservation, safe package content, signed envelope validation, backup metadata failover, and protected ZIP downloads. A separate disposable WordPress job installs original v2, assigns representative saved options, overlays the framework integration, and checks settings persistence.

The signed integration test additionally generates a **temporary, locally held** Ed25519 keypair, creates a synthetic `2.0.1` ZIP **only inside CI**, uses the offline signer, injects the **public test key** into the disposable WordPress plugin, and exercises native WordPress upgrade requests with an invalid primary API response and tampered primary ZIP. Only a signed backup API response and a ZIP whose SHA-256 matches the signed metadata should be accepted.

The synthetic version **2.0.1 is not a real tagged release**; the PR source remains 2.0.0.

### Local preview build

From a checkout whose directory basename is `simple-maintenance-mode`:

```bash
php tools/validate-manifest.php mti-plugin.json
php tools/build-release.php . /tmp/simple-maintenance-mode-preview.zip
unzip -t /tmp/simple-maintenance-mode-preview.zip
unzip -Z1 /tmp/simple-maintenance-mode-preview.zip | grep -E 'css/admin-style.css|js/admin.js|maintenance-fullscreen-template.php'
```

The ZIP builder enforces manifest/header/readme version alignment, refuses symlinks and hidden/secret-like assets, and does not package `tests/` or `tools/`. Preview ZIPs with no pinned key are **not** approved releases.

### Remaining release gates

1. Verify WordPress native update leaves the plugin functional and configured as expected after a separate request; test activation status explicitly.
2. Test online, coming-soon (HTTP 200), maintenance (HTTP 503 + Retry-After), exempt pages, login route and bypass tokens/cookies in an interactive browser, on desktop and mobile.
3. Validate admin settings, media picker, layout preview, countdown, REST/XML-RPC controls, caching and third-party plugin interactions.
4. Independently test rollback and multisite behavior, or document multisite as unsupported.
5. Provision live read-only API, trusted public key rotation procedures, TLS, staging and immutable release ZIPs; re-test a **real** signed download/install on staging.
6. Approve and assign a real release version, update all version fields, tag and sign only after code review.

**No production site, customer installation, or live endpoint was modified by this work.**
