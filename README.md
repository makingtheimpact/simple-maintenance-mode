# Simple Maintenance Mode

A lightweight WordPress maintenance and coming-soon plugin with secure bypass access, flexible layouts, and no bundled stock-background bloat.

## Modes

- **Online** — normal public access.
- **Coming Soon** — hides the normal site while presenting a launch page. Automatic HTTP mode returns `200 OK`.
- **Maintenance** — hides the normal site during temporary work. Automatic HTTP mode returns `503 Service Unavailable` with a configurable `Retry-After` header.

## Version 2.0 features

- Six built-in layouts: Centered, Centered Card, Split Left, Split Right, Bottom Panel, and Minimal.
- Solid-color, CSS-gradient, custom image, and custom video backgrounds.
- Eleven lightweight CSS gradient presets instead of bundled background images.
- Logo upload and width controls.
- Heading/body size, font, weight, alignment, color, width, spacing, radius, panel, and shadow controls.
- Optional countdown and call-to-action button.
- Safe advanced HTML content area.
- Secure bypass URLs with configurable duration and token regeneration.
- Bypass tokens are removed from the visible browser URL after successful authorization.
- Optional public REST API blocking and XML-RPC disabling while maintenance mode is active.
- Automatic or manual `200` / `503` response control.
- Top-level **Maintenance** admin menu, direct Plugins-screen Settings link, toolbar status, and admin warning while restricted mode is active.
- Administrator-only preview.
- Compatible metadata updated for WordPress 7.1 and PHP 7.4+.

## Security changes in 2.0

The request gate was rebuilt rather than extending the old substring-based URL checks. The plugin now uses exact WordPress login/admin handling, capability checks, constant-time token comparison with `hash_equals()`, HTTP-only bypass cookies, input allowlists, clamped numeric settings, WordPress nonces, safe HTML filtering, and WordPress HTTP status/cache APIs.

## Installation

1. Upload the plugin directory to `/wp-content/plugins/` or install a plugin ZIP.
2. Activate **Simple Maintenance Mode**.
3. Open **Maintenance** in the WordPress admin.
4. Configure the page design and access options.
5. Select **Coming Soon** or **Maintenance** and save.

## Bypass links

When enabled, the plugin generates a private URL containing a random token. Visiting the URL validates the token, stores an HTTP-only cookie for the configured duration, and redirects to a clean URL with the token removed. Regenerating the token invalidates previously issued bypass cookies and URLs.

## HTTP response guidance

The default **Automatic** behavior is recommended:

- Maintenance mode: `503 Service Unavailable`
- Coming Soon mode: `200 OK`

You can override either behavior in the settings screen. A `503` response can also include a configurable `Retry-After` value.

## Custom WordPress page

You can use the plugin's self-contained template or choose an existing published WordPress page. The plugin template is the most isolated and lightweight option; the WordPress-page option is useful when you want your theme or page builder to render the maintenance content.

## Update mechanism

The previous custom updater has been removed in 2.0 because it contained stale code from unrelated plugins. A replacement update mechanism will be implemented separately.

## Changelog

### 2.0.0

- Rebuilt request handling and bypass security.
- Added configurable HTTP response behavior.
- Added REST/XML-RPC restriction options.
- Replaced bundled backgrounds with CSS gradients.
- Added six layouts and expanded design controls.
- Added CTA, typography, logo sizing, and panel controls.
- Redesigned the admin experience and plugin discoverability.
- Removed the obsolete updater.
- Updated WordPress compatibility metadata.

### 1.0.2

- Added bypass URL, countdown, backgrounds, and customization.

### 1.0.1

- Bug fixes and improvements.

### 1.0.0

- Initial release.

## License

GPL-2.0-or-later.
