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