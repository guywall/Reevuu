<?php

if (! defined('ABSPATH')) {
    exit;
}

class RRP_Installer
{
    public static function activate()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $tables = self::table_names();

        dbDelta(
            "CREATE TABLE {$tables['targets']} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                slug VARCHAR(100) NOT NULL,
                name VARCHAR(191) NOT NULL,
                type VARCHAR(50) NOT NULL DEFAULT 'sitewide',
                is_public TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$tables['questions']} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                question_key VARCHAR(100) NOT NULL,
                label VARCHAR(191) NOT NULL,
                type VARCHAR(20) NOT NULL,
                help_text TEXT NULL,
                placeholder VARCHAR(191) NULL,
                is_required TINYINT(1) NOT NULL DEFAULT 0,
                is_primary_rating TINYINT(1) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT(11) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY question_key (question_key)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$tables['reviews']} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                target_id BIGINT(20) UNSIGNED NOT NULL,
                reviewer_name VARCHAR(191) NOT NULL,
                reviewer_email VARCHAR(191) NOT NULL,
                review_title VARCHAR(191) NULL,
                review_content LONGTEXT NULL,
                overall_rating DECIMAL(3,2) NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                is_verified TINYINT(1) NOT NULL DEFAULT 0,
                has_consent TINYINT(1) NOT NULL DEFAULT 0,
                submission_ip VARCHAR(45) NULL,
                user_agent TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY target_status (target_id, status),
                KEY created_at (created_at)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$tables['answers']} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                review_id BIGINT(20) UNSIGNED NOT NULL,
                question_id BIGINT(20) UNSIGNED NULL,
                question_key VARCHAR(100) NOT NULL,
                question_label VARCHAR(191) NOT NULL,
                question_type VARCHAR(20) NOT NULL,
                rating_value DECIMAL(3,2) NULL,
                text_value LONGTEXT NULL,
                sort_order INT(11) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY review_id (review_id)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$tables['media']} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                review_id BIGINT(20) UNSIGNED NOT NULL,
                attachment_id BIGINT(20) UNSIGNED NOT NULL,
                sort_order INT(11) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY review_id (review_id)
            ) {$charset_collate};"
        );

        self::seed_default_target($tables['targets']);
        self::seed_default_questions($tables['questions']);

        if (! get_option(RRP_Settings::OPTION_KEY)) {
            $settings = new RRP_Settings();
            update_option(RRP_Settings::OPTION_KEY, $settings->defaults());
        }

        $settings = new RRP_Settings();
        $slug = trim((string) $settings->get('google_feed_slug', 'rrp-google-reviews.xml'), '/');
        if ('' !== $slug) {
            add_rewrite_rule('^' . preg_quote($slug, '/') . '$', 'index.php?rrp_google_feed=1', 'top');
        }

        flush_rewrite_rules();
    }

    public static function table_names()
    {
        global $wpdb;

        return array(
            'targets'   => $wpdb->prefix . 'rrp_review_targets',
            'questions' => $wpdb->prefix . 'rrp_review_question_defs',
            'reviews'   => $wpdb->prefix . 'rrp_reviews',
            'answers'   => $wpdb->prefix . 'rrp_review_answers',
            'media'     => $wpdb->prefix . 'rrp_review_media',
        );
    }

    private static function seed_default_target($table)
    {
        global $wpdb;

        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s LIMIT 1", 'sitewide'));
        if ($exists) {
            return;
        }

        $now = current_time('mysql');

        $wpdb->insert(
            $table,
            array(
                'slug'       => 'sitewide',
                'name'       => __('Sitewide Reviews', 'reevuu-reviews'),
                'type'       => 'sitewide',
                'is_public'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s')
        );
    }

    private static function seed_default_questions($table)
    {
        global $wpdb;

        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        if ($count > 0) {
            return;
        }

        $now = current_time('mysql');
        $defaults = array(
            array(
                'question_key'      => 'overall_rating',
                'label'             => __('Overall rating', 'reevuu-reviews'),
                'type'              => 'rating',
                'help_text'         => '',
                'placeholder'       => '',
                'is_required'       => 1,
                'is_primary_rating' => 1,
                'is_active'         => 1,
                'sort_order'        => 10,
            ),
            array(
                'question_key'      => 'service_quality',
                'label'             => __('Service quality', 'reevuu-reviews'),
                'type'              => 'rating',
                'help_text'         => '',
                'placeholder'       => '',
                'is_required'       => 0,
                'is_primary_rating' => 0,
                'is_active'         => 1,
                'sort_order'        => 20,
            ),
            array(
                'question_key'      => 'what_stood_out',
                'label'             => __('What stood out most?', 'reevuu-reviews'),
                'type'              => 'text',
                'help_text'         => '',
                'placeholder'       => __('e.g. quality, service, speed', 'reevuu-reviews'),
                'is_required'       => 0,
                'is_primary_rating' => 0,
                'is_active'         => 1,
                'sort_order'        => 30,
            ),
        );

        foreach ($defaults as $definition) {
            $wpdb->insert(
                $table,
                array_merge(
                    $definition,
                    array(
                        'created_at' => $now,
                        'updated_at' => $now,
                    )
                ),
                array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s')
            );
        }
    }
}
