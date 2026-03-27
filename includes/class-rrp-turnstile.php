<?php

if (! defined('ABSPATH')) {
    exit;
}

class RRP_Turnstile
{
    private $settings;

    public function __construct(RRP_Settings $settings)
    {
        $this->settings = $settings;
    }

    public function is_enabled()
    {
        return (bool) ($this->settings->get('turnstile_site_key') && $this->settings->get('turnstile_secret_key'));
    }

    public function verify($token, $remote_ip = '')
    {
        if (! $this->is_enabled()) {
            return true;
        }

        if ('' === trim((string) $token)) {
            return new WP_Error('rrp_turnstile_missing', __('Turnstile verification failed. Please try again.', 'reevuu-reviews'));
        }

        $response = wp_remote_post(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            array(
                'timeout' => 15,
                'body'    => array(
                    'secret'   => $this->settings->get('turnstile_secret_key'),
                    'response' => sanitize_text_field((string) $token),
                    'remoteip' => sanitize_text_field((string) $remote_ip),
                ),
            )
        );

        if (is_wp_error($response)) {
            return new WP_Error('rrp_turnstile_request_failed', __('Unable to verify Turnstile right now. Please try again later.', 'reevuu-reviews'));
        }

        $payload = json_decode(wp_remote_retrieve_body($response), true);

        if (! is_array($payload) || empty($payload['success'])) {
            return new WP_Error('rrp_turnstile_invalid', __('Turnstile verification failed. Please try again.', 'reevuu-reviews'));
        }

        return true;
    }
}
