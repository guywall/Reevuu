=== ReevuuWP ===
Contributors: webrankers
Tags: reviews, testimonials, ratings, customer reviews, turnstile
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.3
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
- Compact rating badge shortcode
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

`[reevuu_reviews_badge]`

== Changelog ==

= 1.1.3 =
- Refined review list styling for a more modern layout, improved mobile spacing, and fixed verified badge alignment

= 1.1.2 =
- Added a Town/City reviewer field and switched the mobile review list from horizontal scroll to stacked cards

= 1.1.1 =
- Added submission IP visibility in moderation and a compact reviews badge shortcode

= 1.1.0 =
- Added moderation badges, admin replies, configurable review notification emails by rating, secondary gradient color control, and list/filter UI improvements

= 1.0.3 =
- Removed the review title field from the public form, moved the main review textarea below ratings, and added admin appearance controls

= 1.0.2 =
- Improved frontend form layout resilience and field/label accessibility

= 1.0.1 =
- Updated branding, repository/plugin structure, and documentation

= 1.0.0 =
- Initial release
