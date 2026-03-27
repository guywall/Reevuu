# ReevuuWP

ReevuuWP is a standalone WordPress customer reviews plugin by WebRankers.

It provides:
- A public review submission form with Cloudflare Turnstile
- Image uploads on review submissions
- Star ratings and configurable extra review questions
- Admin moderation and verified-review toggles
- Frontend review summaries, tables, sliders, chips, and image galleries
- Compact header/footer rating badge shortcode
- JSON-LD review schema
- A Google review XML feed for approved reviews

## Installation

This repository is now structured so the repository root is the plugin root.

You can install it in either of these ways:

1. Download the GitHub ZIP and upload it in WordPress via `Plugins > Add New > Upload Plugin`.
2. Clone or copy this repository into `wp-content/plugins/Reevuu`.

Then activate `ReevuuWP` from the WordPress plugins screen.

## Initial Setup

After activation:

1. Go to `ReevuuWP` in wp-admin.
2. Open `Settings`.
3. Configure:
   - Moderation mode
   - Cloudflare Turnstile site and secret keys
   - Consent checkbox text and policy URL
   - Max image uploads and size limits
   - Default frontend display limits
   - Google review XML feed slug
4. Use the question builder to add, remove, reword, activate/deactivate, and reorder extra questions.
5. Choose one rating question as the primary rating.

## Frontend Display

### Gutenberg blocks

ReevuuWP registers these blocks:
- Review Form
- Review Summary
- Review List
- Review Slider
- Review Chips
- Review Gallery

Search for `ReevuuWP`/review blocks in the block inserter and place them on any page.

### Shortcodes

You can also place the plugin on any page, post, or widget area with shortcodes.

#### Review form

```text
[reevuu_reviews_form]
```

Optional attributes:

```text
[reevuu_reviews_form title="Leave Us A Review" target_id="1"]
```

#### Review summary

```text
[reevuu_reviews_summary]
```

Optional attributes:

```text
[reevuu_reviews_summary target_id="1" schema="1"]
```

#### Searchable/sortable review table

```text
[reevuu_reviews_list]
```

Optional attributes:

```text
[reevuu_reviews_list limit="10" show_search="1" show_sort="1" show_schema="1"]
```

#### Review slider

```text
[reevuu_reviews_slider]
```

Optional attributes:

```text
[reevuu_reviews_slider title="Customer Reviews" limit="6"]
```

#### Review chips

```text
[reevuu_reviews_chips]
```

Optional attributes:

```text
[reevuu_reviews_chips limit="8"]
```

#### Customer image gallery

```text
[reevuu_reviews_gallery]
```

Optional attributes:

```text
[reevuu_reviews_gallery title="Customer Images" limit="12"]
```

#### Compact rating badge

```text
[reevuu_reviews_badge]
```

Optional attributes:

```text
[reevuu_reviews_badge show_count="1" show_label="1" label="Rated"]
```

## Recommended Page Setup

For a full reviews page, a good starting layout is:

```text
[reevuu_reviews_summary]
[reevuu_reviews_gallery]
[reevuu_reviews_list]
```

For a homepage or landing page:

```text
[reevuu_reviews_slider]
[reevuu_reviews_chips]
```

For a submission page:

```text
[reevuu_reviews_form]
```

## Moderation Workflow

- New submissions are stored in custom database tables.
- Depending on your moderation setting, reviews will either:
  - publish immediately, or
  - stay pending until approved in wp-admin.
- Admins can also mark a review as verified manually.

## Cloudflare Turnstile

If Turnstile keys are configured in settings, the public form will render a Turnstile challenge and validate it server-side before saving a review.

## Google Review XML Feed

If enabled, the plugin exposes a public XML feed of approved reviews.

The feed URL is:

```text
https://your-site.example/rrp-google-reviews.xml
```

You can change the slug in `ReevuuWP > Settings`.

## Notes

- The plugin stores review data in custom tables, not WordPress comments.
- The schema includes an internal review target model so future product/service/location review streams can be added later.
- v1 supports images only, not videos.
