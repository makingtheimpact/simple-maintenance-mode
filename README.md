# Simple Maintenance Mode

A lightweight WordPress maintenance and coming-soon plugin with secure bypass links, configurable HTTP responses, customizable layouts, and no bundled stock-image bloat.

## Modes

1. **Online** — the normal website is publicly accessible.
2. **Coming Soon** — visitors see a coming-soon page while administrators retain normal access.
3. **Maintenance** — visitors see a temporary maintenance page while administrators retain normal access.

## Highlights

- Automatic SEO-friendly response codes: 200 for Coming Soon and 503 for Maintenance, with manual override.
- Configurable `Retry-After` header for 503 responses.
- Secure temporary bypass URLs with configurable cookie duration and token regeneration.
- Custom login URL exemption for sites that hide or rename `/wp-login.php`.
- Additional URL exemptions with exact matching or explicit trailing-`*` prefix matching.
- Same-site-only exemption validation; external domains are rejected.
- Optional public REST API blocking and XML-RPC disabling while restricted mode is active.
- Six lightweight layouts: Centered, Centered Card, Split Left, Split Right, Bottom Panel, and Minimal.
- Solid, CSS gradient, uploaded image, and uploaded video backgrounds.
- Eleven built-in CSS gradient presets without bundled background images.
- Logo sizing, typography, alignment, colors, spacing, content-panel styling, CTA button, and countdown controls.
- Administrator preview, top-level Maintenance menu, Plugins-page Settings link, admin notice, and toolbar status indicator.
- Safe advanced HTML content using WordPress sanitization.
- Responsive output and reduced-motion handling.

## URL exemptions

The standard WordPress login endpoint remains accessible automatically. If a security plugin changes the login URL, enter that route in **Maintenance → Access & URL Exemptions → Custom Login URL**.

Additional routes can be entered one per line. Entries match exactly by default:

```text
/status/
/payment-callback/
```

Add a trailing `*` only when you intentionally want to allow a route and everything below it:

```text
/webhooks/*
```

Query strings are ignored for matching, and external-domain URLs are rejected. Because exempt routes remain publicly accessible to everyone while maintenance mode is active, only add routes that genuinely need to remain public.

## Installation

1. Upload `simple-maintenance-mode` to `/wp-content/plugins/`.
2. Activate **Simple Maintenance Mode** in WordPress.
3. Open **Maintenance** in the WordPress admin menu.
4. Configure the page and enable Coming Soon or Maintenance mode when ready.

## Bypass access

When secure bypass links are enabled, the settings page generates a unique URL. Opening that URL stores an HTTP-only SameSite cookie for the configured duration and removes the token from the visible browser URL. Regenerating the token invalidates previous bypass links and cookies.

## HTTP response behavior

- **Maintenance:** defaults to HTTP 503 Service Unavailable.
- **Coming Soon:** defaults to HTTP 200 OK.
- Administrators can override the response code when needed.

A 503 response also sends the configured `Retry-After` header.

## Security notes

- Maintenance access checks use explicit WordPress admin/login rules rather than broad URL substring matching.
- Bypass tokens use cryptographically secure random values and constant-time comparison.
- Settings are protected by WordPress capabilities and nonces.
- Configurable enumerated values are allowlisted and numeric values are clamped to supported ranges.
- Custom login and exempt URL rules are normalized to same-site paths before being stored.

## Version 2.0

Version 2.0 is a major refactor focused on security, compatibility, smaller distribution size, and usability. The previous custom updater has been removed and will be replaced separately.

## License

GPL-2.0-or-later.

## MTI Framework Integration (draft)

The [framework integration PR](https://github.com/makingtheimpact/simple-maintenance-mode/pull/2) vendors the independent MTI plugin framework with **Ed25519-signed WordPress updates**, primary/backup metadata endpoints, and SHA-256 ZIP verification. It preserves existing settings and bypass tokens. This remains an **unreleased draft**, with no trusted production signing key included and no update requests made by the draft plugin.

The framework-integrated version requires **PHP 8.1+** (tested on 8.1–8.5). The original plugin's WordPress 6.4 minimum remains unchanged. PHP 8.5 requires a suitably compatible WordPress version, tested here on 6.9 and 7.1.4. PHP 8.1 itself is end-of-life.

Automated GitHub Actions checks have passed for all five PHP versions and for **native signed upgrades** on WordPress 6.9/7.1.4 with PHP 8.1/8.5, including broken-primary API failover, tampered ZIP rejection, signed mirror recovery, preserved active status, settings, bypass token and assets. CI builds a **temporary synthetic 2.0.1 release** with an ephemeral signing key; no 2.0.1 artifact is published. See [integration documentation](docs/FRAMEWORK-INTEGRATION.md).
