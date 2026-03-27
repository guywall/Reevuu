<?php

if (! defined('ABSPATH')) {
    exit;
}

class RRP_Settings
{
    const OPTION_KEY = 'rrp_settings';

    private function sanitize_feed_slug($slug)
    {
        $slug = strtolower(trim((string) $slug));
        $slug = preg_replace('/[^a-z0-9\-_.\/]/', '', $slug);
        $slug = ltrim((string) $slug, '/');

        return '' === $slug ? 'rrp-google-reviews.xml' : $slug;
    }

    public function defaults()
    {
        return array(
            'moderation_mode'         => 'pending',
            'turnstile_site_key'      => '',
            'turnstile_secret_key'    => '',
            'consent_enabled'         => 1,
            'consent_label'           => __('I agree to the privacy policy and consent to my review being published.', 'reevuu-reviews'),
            'consent_url'             => '',
            'max_images'              => 5,
            'max_image_size_mb'       => 5,
            'review_title_label'      => __('Review title', 'reevuu-reviews'),
            'review_content_label'    => __('Tell us about your experience', 'reevuu-reviews'),
            'review_title_required'   => 0,
            'review_content_required' => 1,
            'success_message'         => __('Thanks for sharing your review.', 'reevuu-reviews'),
            'pending_message'         => __('Thanks for sharing your review. It is awaiting moderation.', 'reevuu-reviews'),
            'gallery_image_limit'     => 12,
            'default_list_limit'      => 10,
            'default_slider_limit'    => 6,
            'default_chip_limit'      => 8,
            'google_feed_enabled'     => 1,
            'google_feed_slug'        => 'rrp-google-reviews.xml',
            'brand_name'              => get_bloginfo('name'),
        );
    }

    public function get_all()
    {
        $settings = get_option(self::OPTION_KEY, array());

        return wp_parse_args(is_array($settings) ? $settings : array(), $this->defaults());
    }

    public function get($key, $default = null)
    {
        $settings = $this->get_all();

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public function update($values)
    {
        $sanitized = $this->sanitize($values);
        update_option(self::OPTION_KEY, $sanitized);

        return $sanitized;
    }

    public function sanitize($values)
    {
        $defaults = $this->defaults();
        $values = is_array($values) ? $values : array();

        $moderation_mode = $values['moderation_mode'] ?? $defaults['moderation_mode'];
        if (! in_array($moderation_mode, array('pending', 'auto_publish'), true)) {
            $moderation_mode = $defaults['moderation_mode'];
        }

        return wp_parse_args(
            array(
                'moderation_mode'         => $moderation_mode,
                'turnstile_site_key'      => sanitize_text_field($values['turnstile_site_key'] ?? ''),
                'turnstile_secret_key'    => sanitize_text_field($values['turnstile_secret_key'] ?? ''),
                'consent_enabled'         => empty($values['consent_enabled']) ? 0 : 1,
                'consent_label'           => sanitize_text_field($values['consent_label'] ?? $defaults['consent_label']),
                'consent_url'             => esc_url_raw($values['consent_url'] ?? ''),
                'max_images'              => max(0, min(10, absint($values['max_images'] ?? $defaults['max_images']))),
                'max_image_size_mb'       => max(1, min(15, absint($values['max_image_size_mb'] ?? $defaults['max_image_size_mb']))),
                'review_title_label'      => sanitize_text_field($values['review_title_label'] ?? $defaults['review_title_label']),
                'review_content_label'    => sanitize_text_field($values['review_content_label'] ?? $defaults['review_content_label']),
                'review_title_required'   => empty($values['review_title_required']) ? 0 : 1,
                'review_content_required' => empty($values['review_content_required']) ? 0 : 1,
                'success_message'         => sanitize_text_field($values['success_message'] ?? $defaults['success_message']),
                'pending_message'         => sanitize_text_field($values['pending_message'] ?? $defaults['pending_message']),
                'gallery_image_limit'     => max(1, min(30, absint($values['gallery_image_limit'] ?? $defaults['gallery_image_limit']))),
                'default_list_limit'      => max(1, min(50, absint($values['default_list_limit'] ?? $defaults['default_list_limit']))),
                'default_slider_limit'    => max(1, min(20, absint($values['default_slider_limit'] ?? $defaults['default_slider_limit']))),
                'default_chip_limit'      => max(1, min(30, absint($values['default_chip_limit'] ?? $defaults['default_chip_limit']))),
                'google_feed_enabled'     => empty($values['google_feed_enabled']) ? 0 : 1,
                'google_feed_slug'        => $this->sanitize_feed_slug($values['google_feed_slug'] ?? $defaults['google_feed_slug']),
                'brand_name'              => sanitize_text_field($values['brand_name'] ?? $defaults['brand_name']),
            ),
            $defaults
        );
    }
}
