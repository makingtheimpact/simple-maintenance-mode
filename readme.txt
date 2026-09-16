=== Simple Maintenance Mode ===
Contributors: makingtheimpact
Donate link: https://makingtheimpact.com
Tags: maintenance, coming soon, maintenance mode, lightweight, website maintenance
Requires at least: 6.4
Tested up to: 7.1
Stable tag: 2.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight maintenance and coming-soon plugin with secure bypass links, customizable layouts, URL exemptions, and no bundled stock-image bloat.

== Description ==

Simple Maintenance Mode lets you temporarily hide the normal WordPress site from visitors while administrators continue working normally.

Choose from three modes:

1. Online
2. Coming Soon
3. Maintenance

Version 2.0 focuses on security, usability, compatibility, and a dramatically smaller distribution size.

== Features ==

* Automatic 200/503 response codes with manual override
* Configurable Retry-After header for maintenance responses
* Secure temporary bypass URL with configurable duration
* Custom login URL exemption for hidden or renamed WordPress login pages
* Additional same-site URL exemptions with exact or explicit trailing-* prefix matching
* Optional REST API blocking while maintenance mode is active
* Optional XML-RPC disabling while maintenance mode is active
* Six layout options
* Solid, CSS gradient, uploaded image, and uploaded video backgrounds
* Eleven lightweight CSS gradient presets
* Logo size controls
* Heading and body typography controls
* Content width, padding, panel opacity, radius, and shadow controls
* Optional countdown timer
* Optional call-to-action button
* Advanced safe HTML content
* Administrator-only preview
* Prominent admin menu, notice, and toolbar status indicator
* Responsive output and reduced-motion handling

== URL Exemptions ==

The normal /wp-login.php endpoint remains available automatically.

If a security plugin changes the WordPress login URL, enter the replacement route in the Custom Login URL field.

Additional URLs can be entered one per line. Exact matching is used by default. Add an asterisk only at the end when you intentionally want to exempt a path and everything below it.

Examples:

/status/
/payment-callback/
/webhooks/*

Query strings are ignored. External-domain URLs are rejected. Exempted routes remain publicly available while maintenance mode is active, so only exempt routes that genuinely need public access.

== Installation ==

1. Upload `simple-maintenance-mode` to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins menu in WordPress.
3. Open Maintenance in the WordPress admin menu.
4. Configure the plugin and choose Online, Coming Soon, or Maintenance.

== Frequently Asked Questions ==

= What response code should I use? =

Automatic mode uses HTTP 503 for Maintenance and HTTP 200 for Coming Soon. A 503 response tells search engines the interruption is temporary. HTTP 200 is generally more appropriate for a brand-new Coming Soon site.

= Can I still log in while maintenance mode is active? =

Yes. The standard WordPress login endpoint remains accessible automatically. If another plugin changes or hides the login URL, enter the replacement path under Access & URL Exemptions.

= Can I keep a specific page or callback URL public? =

Yes. Add the path under Additional Exempt URLs. Entries match exactly unless you intentionally add a trailing asterisk for prefix matching.

= Can I let a client preview the normal site? =

Yes. Enable secure bypass links and share the generated URL. It creates an HTTP-only SameSite cookie for the configured duration. Regenerating the token invalidates previous bypass links and cookies.

= Can I use my own custom page? =

Yes. Select a published WordPress page as the maintenance page source, or use the plugin template and its built-in customization controls.

== Changelog ==

= 2.0.0 =
* Major security and request-handling refactor
* Added configurable HTTP response codes and Retry-After
* Added secure configurable bypass links
* Added custom login and additional URL exemptions
* Added REST API and XML-RPC controls
* Added six layouts and expanded visual customization
* Replaced bundled background images with CSS gradients
* Removed obsolete updater and unused legacy assets
* Improved admin discoverability and status indicators

= 1.0.2 =
* New features and improvements

= 1.0.1 =
* Bug fixes and improvements

= 1.0.0 =
* Initial release