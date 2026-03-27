=== ReevuuWP ===
Contributors: webrankers
Tags: reviews, testimonials, ratings, customer reviews, turnstile
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Standalone customer reviews platform for WordPress with image uploads, configurable questions, moderation, frontend displays, and XML feed output.

== Description ==

ReevuuWP is a customer reviews plugin for WordPress sites that do not use WooCommerce.

Features include:

- Public review form with Cloudflare Turnstile
- Image uploads on reviews
- Configurable extra rating and text questions
- Admin moderation and verified review flags
- Review summaries and distribution bars
- Searchable/sortable review list output
- Review slider, chips, and image gallery displays
- JSON-LD structured data
- Google review XML feed

== Installation ==

1. Upload the plugin ZIP in WordPress or copy the plugin folder into `wp-content/plugins/`.
2. Activate `ReevuuWP`.
3. Go to `ReevuuWP > Settings`.
4. Configure Turnstile, moderation, consent, uploads, and your question set.
5. Add the shortcode(s) or Gutenberg blocks to your pages.

== Shortcodes ==

`[reevuu_reviews_form]`

`[reevuu_reviews_summary]`

`[reevuu_reviews_list]`

`[reevuu_reviews_slider]`

`[reevuu_reviews_chips]`

`[reevuu_reviews_gallery]`

== Changelog ==

= 1.0.2 =
- Improved frontend form layout resilience and field/label accessibility

= 1.0.1 =
- Updated branding, repository/plugin structure, and documentation

= 1.0.0 =
- Initial release
