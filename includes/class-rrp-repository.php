<?php

if (! defined('ABSPATH')) {
    exit;
}

class RRP_Repository
{
    private $wpdb;
    private $settings;
    private $tables;

    public function __construct(RRP_Settings $settings)
    {
        global $wpdb;

        $this->wpdb = $wpdb;
        $this->settings = $settings;
        $this->tables = RRP_Installer::table_names();
    }

    public function get_default_target()
    {
        $target = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->tables['targets']} WHERE slug = %s LIMIT 1", 'sitewide'), ARRAY_A);

        if (! $target) {
            $target = $this->wpdb->get_row("SELECT * FROM {$this->tables['targets']} ORDER BY id ASC LIMIT 1", ARRAY_A);
        }

        return $target ?: null;
    }

    public function get_default_target_id()
    {
        $target = $this->get_default_target();

        return $target ? (int) $target['id'] : 0;
    }

    public function get_question_definitions($active_only = false)
    {
        $sql = "SELECT * FROM {$this->tables['questions']}";

        if ($active_only) {
            $sql .= ' WHERE is_active = 1';
        }

        $sql .= ' ORDER BY sort_order ASC, id ASC';

        return $this->wpdb->get_results($sql, ARRAY_A);
    }

    public function replace_question_definitions($definitions)
    {
        $definitions = is_array($definitions) ? $definitions : array();
        $now = current_time('mysql');

        $this->wpdb->query("TRUNCATE TABLE {$this->tables['questions']}");

        foreach ($definitions as $index => $definition) {
            $type = in_array($definition['type'] ?? '', array('rating', 'text', 'textarea'), true) ? $definition['type'] : 'text';

            $this->wpdb->insert(
                $this->tables['questions'],
                array(
                    'question_key'      => sanitize_key($definition['question_key'] ?? ('question_' . ($index + 1))),
                    'label'             => sanitize_text_field($definition['label'] ?? ''),
                    'type'              => $type,
                    'help_text'         => sanitize_text_field($definition['help_text'] ?? ''),
                    'placeholder'       => sanitize_text_field($definition['placeholder'] ?? ''),
                    'is_required'       => empty($definition['is_required']) ? 0 : 1,
                    'is_primary_rating' => ('rating' === $type && ! empty($definition['is_primary_rating'])) ? 1 : 0,
                    'is_active'         => empty($definition['is_active']) ? 0 : 1,
                    'sort_order'        => isset($definition['sort_order']) ? (int) $definition['sort_order'] : (($index + 1) * 10),
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ),
                array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s')
            );
        }

        $this->ensure_primary_rating();
    }

    public function ensure_primary_rating()
    {
        $primary = (int) $this->wpdb->get_var("SELECT id FROM {$this->tables['questions']} WHERE type = 'rating' AND is_primary_rating = 1 LIMIT 1");

        if ($primary) {
            return;
        }

        $rating_id = (int) $this->wpdb->get_var("SELECT id FROM {$this->tables['questions']} WHERE type = 'rating' ORDER BY sort_order ASC, id ASC LIMIT 1");
        if ($rating_id) {
            $this->wpdb->update(
                $this->tables['questions'],
                array(
                    'is_primary_rating' => 1,
                    'updated_at'        => current_time('mysql'),
                ),
                array('id' => $rating_id),
                array('%d', '%s'),
                array('%d')
            );
        }
    }

    public function insert_review($review, $answers, $attachment_ids = array())
    {
        $now = current_time('mysql');

        $inserted = $this->wpdb->insert(
            $this->tables['reviews'],
            array(
                'target_id'       => (int) $review['target_id'],
                'reviewer_name'   => sanitize_text_field($review['reviewer_name']),
                'reviewer_email'  => sanitize_email($review['reviewer_email']),
                'review_title'    => sanitize_text_field($review['review_title']),
                'review_content'  => wp_kses_post($review['review_content']),
                'overall_rating'  => (float) $review['overall_rating'],
                'status'          => sanitize_key($review['status']),
                'is_verified'     => empty($review['is_verified']) ? 0 : 1,
                'has_consent'     => empty($review['has_consent']) ? 0 : 1,
                'response_content'=> null,
                'response_author' => null,
                'responded_at'    => null,
                'submission_ip'   => sanitize_text_field($review['submission_ip']),
                'user_agent'      => sanitize_textarea_field($review['user_agent']),
                'created_at'      => $now,
                'updated_at'      => $now,
            ),
            array('%d', '%s', '%s', '%s', '%s', '%f', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if (! $inserted) {
            return new WP_Error('rrp_review_insert_failed', __('The review could not be saved. Please try again.', 'reevuu-reviews'));
        }

        $review_id = (int) $this->wpdb->insert_id;

        foreach ($answers as $index => $answer) {
            $this->wpdb->insert(
                $this->tables['answers'],
                array(
                    'review_id'      => $review_id,
                    'question_id'    => empty($answer['question_id']) ? null : (int) $answer['question_id'],
                    'question_key'   => sanitize_key($answer['question_key']),
                    'question_label' => sanitize_text_field($answer['question_label']),
                    'question_type'  => sanitize_key($answer['question_type']),
                    'rating_value'   => isset($answer['rating_value']) && '' !== (string) $answer['rating_value'] ? (float) $answer['rating_value'] : null,
                    'text_value'     => isset($answer['text_value']) ? sanitize_textarea_field($answer['text_value']) : null,
                    'sort_order'     => isset($answer['sort_order']) ? (int) $answer['sort_order'] : (($index + 1) * 10),
                    'created_at'     => $now,
                ),
                array('%d', '%d', '%s', '%s', '%s', '%f', '%s', '%d', '%s')
            );
        }

        foreach (array_values($attachment_ids) as $index => $attachment_id) {
            $this->wpdb->insert(
                $this->tables['media'],
                array(
                    'review_id'     => $review_id,
                    'attachment_id' => (int) $attachment_id,
                    'sort_order'    => (($index + 1) * 10),
                    'created_at'    => $now,
                ),
                array('%d', '%d', '%d', '%s')
            );
        }

        do_action('rrp_review_created', $review_id, $review, $answers, $attachment_ids);

        return $review_id;
    }

    public function get_review($review_id)
    {
        $review = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->tables['reviews']} WHERE id = %d", $review_id), ARRAY_A);

        if (! $review) {
            return null;
        }

        $reviews = $this->hydrate_reviews(array($review));

        return $reviews ? $reviews[0] : null;
    }

    public function get_reviews($args = array())
    {
        $args = wp_parse_args(
            $args,
            array(
                'target_id' => $this->get_default_target_id(),
                'status'    => 'approved',
                'search'    => '',
                'sort'      => 'newest',
                'limit'     => 10,
                'offset'    => 0,
                'ids'       => array(),
            )
        );

        $where = array('1=1');
        $values = array();

        if (! empty($args['target_id'])) {
            $where[] = 'target_id = %d';
            $values[] = (int) $args['target_id'];
        }

        if (! empty($args['status'])) {
            if (is_array($args['status'])) {
                $statuses = array_values(array_filter(array_map('sanitize_key', $args['status'])));
                if ($statuses) {
                    $where[] = 'status IN (' . implode(',', array_fill(0, count($statuses), '%s')) . ')';
                    $values = array_merge($values, $statuses);
                }
            } else {
                $where[] = 'status = %s';
                $values[] = sanitize_key($args['status']);
            }
        }

        if (! empty($args['search'])) {
            $like = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where[] = '(review_title LIKE %s OR review_content LIKE %s OR reviewer_name LIKE %s)';
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
        }

        if (! empty($args['ids']) && is_array($args['ids'])) {
            $ids = array_filter(array_map('absint', $args['ids']));
            if ($ids) {
                $where[] = 'id IN (' . implode(',', array_fill(0, count($ids), '%d')) . ')';
                $values = array_merge($values, $ids);
            }
        }

        $order_by = 'created_at DESC';
        if ('oldest' === $args['sort']) {
            $order_by = 'created_at ASC';
        } elseif ('highest' === $args['sort']) {
            $order_by = 'overall_rating DESC, created_at DESC';
        } elseif ('lowest' === $args['sort']) {
            $order_by = 'overall_rating ASC, created_at DESC';
        }

        $sql = "SELECT * FROM {$this->tables['reviews']} WHERE " . implode(' AND ', $where) . " ORDER BY {$order_by}";
        if (! empty($args['limit'])) {
            $sql .= $this->wpdb->prepare(' LIMIT %d OFFSET %d', (int) $args['limit'], (int) $args['offset']);
        }

        if ($values) {
            $sql = $this->wpdb->prepare($sql, $values);
        }

        return $this->hydrate_reviews($this->wpdb->get_results($sql, ARRAY_A));
    }

    public function count_reviews($args = array())
    {
        $args = wp_parse_args(
            $args,
            array(
                'target_id' => $this->get_default_target_id(),
                'status'    => 'approved',
                'search'    => '',
            )
        );

        $where = array('1=1');
        $values = array();

        if (! empty($args['target_id'])) {
            $where[] = 'target_id = %d';
            $values[] = (int) $args['target_id'];
        }

        if (! empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = sanitize_key($args['status']);
        }

        if (! empty($args['search'])) {
            $like = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where[] = '(review_title LIKE %s OR review_content LIKE %s OR reviewer_name LIKE %s)';
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
        }

        $sql = "SELECT COUNT(*) FROM {$this->tables['reviews']} WHERE " . implode(' AND ', $where);
        if ($values) {
            $sql = $this->wpdb->prepare($sql, $values);
        }

        return (int) $this->wpdb->get_var($sql);
    }

    public function get_status_counts()
    {
        $rows = $this->wpdb->get_results("SELECT status, COUNT(*) AS total FROM {$this->tables['reviews']} GROUP BY status", ARRAY_A);
        $counts = array(
            'approved' => 0,
            'pending'  => 0,
            'rejected' => 0,
            'spam'     => 0,
        );

        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    public function update_review_status($review_id, $status)
    {
        $status = sanitize_key($status);
        if (! in_array($status, array('approved', 'pending', 'rejected', 'spam'), true)) {
            return false;
        }

        $before = $this->get_review($review_id);

        $result = $this->wpdb->update(
            $this->tables['reviews'],
            array(
                'status'     => $status,
                'updated_at' => current_time('mysql'),
            ),
            array('id' => (int) $review_id),
            array('%s', '%s'),
            array('%d')
        );

        if (false !== $result) {
            do_action('rrp_review_status_changed', (int) $review_id, $status, $before);
        }

        return false !== $result;
    }

    public function set_verified($review_id, $is_verified)
    {
        return false !== $this->wpdb->update(
            $this->tables['reviews'],
            array(
                'is_verified' => empty($is_verified) ? 0 : 1,
                'updated_at'  => current_time('mysql'),
            ),
            array('id' => (int) $review_id),
            array('%d', '%s'),
            array('%d')
        );
    }

    public function update_response($review_id, $response_content, $response_author)
    {
        $response_content = trim((string) $response_content);
        $data = array(
            'response_content' => '' === $response_content ? null : wp_kses_post($response_content),
            'response_author'  => '' === $response_content ? null : sanitize_text_field($response_author),
            'responded_at'     => '' === $response_content ? null : current_time('mysql'),
            'updated_at'       => current_time('mysql'),
        );

        return false !== $this->wpdb->update(
            $this->tables['reviews'],
            $data,
            array('id' => (int) $review_id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );
    }

    public function get_summary($target_id = 0)
    {
        $target_id = $target_id ? (int) $target_id : $this->get_default_target_id();
        $summary = array(
            'count'            => 0,
            'average_rating'   => 0,
            'distribution'     => array(5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0),
            'distribution_pct' => array(5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0),
        );

        $aggregate = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT COUNT(*) AS total_reviews, AVG(overall_rating) AS average_rating
                FROM {$this->tables['reviews']}
                WHERE status = %s AND target_id = %d",
                'approved',
                $target_id
            ),
            ARRAY_A
        );

        $summary['count'] = (int) ($aggregate['total_reviews'] ?? 0);
        $summary['average_rating'] = $summary['count'] ? round((float) $aggregate['average_rating'], 1) : 0;

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT FLOOR(overall_rating) AS star_bucket, COUNT(*) AS total
                FROM {$this->tables['reviews']}
                WHERE status = %s AND target_id = %d
                GROUP BY FLOOR(overall_rating)",
                'approved',
                $target_id
            ),
            ARRAY_A
        );

        foreach ($rows as $row) {
            $bucket = max(1, min(5, (int) $row['star_bucket']));
            $summary['distribution'][$bucket] = (int) $row['total'];
        }

        foreach ($summary['distribution'] as $bucket => $count) {
            $summary['distribution_pct'][$bucket] = $summary['count'] ? round(($count / $summary['count']) * 100) : 0;
        }

        return $summary;
    }

    public function get_gallery_images($target_id = 0, $limit = 12)
    {
        $target_id = $target_id ? (int) $target_id : $this->get_default_target_id();
        $limit = max(1, (int) $limit);

        $sql = $this->wpdb->prepare(
            "SELECT m.attachment_id
            FROM {$this->tables['media']} m
            INNER JOIN {$this->tables['reviews']} r ON r.id = m.review_id
            WHERE r.status = %s AND r.target_id = %d
            ORDER BY r.created_at DESC, m.sort_order ASC
            LIMIT %d",
            'approved',
            $target_id,
            $limit
        );

        return array_map('absint', $this->wpdb->get_col($sql));
    }

    private function hydrate_reviews($reviews)
    {
        if (! $reviews) {
            return array();
        }

        $review_ids = array_map(
            static function ($review) {
                return (int) $review['id'];
            },
            $reviews
        );

        $answers_by_review = array();
        $media_by_review = array();

        $answer_sql = "SELECT * FROM {$this->tables['answers']} WHERE review_id IN (" . implode(',', array_fill(0, count($review_ids), '%d')) . ') ORDER BY sort_order ASC, id ASC';
        $answer_rows = $this->wpdb->get_results($this->wpdb->prepare($answer_sql, $review_ids), ARRAY_A);

        foreach ($answer_rows as $row) {
            $answers_by_review[(int) $row['review_id']][] = $row;
        }

        $media_sql = "SELECT * FROM {$this->tables['media']} WHERE review_id IN (" . implode(',', array_fill(0, count($review_ids), '%d')) . ') ORDER BY sort_order ASC, id ASC';
        $media_rows = $this->wpdb->get_results($this->wpdb->prepare($media_sql, $review_ids), ARRAY_A);

        foreach ($media_rows as $row) {
            $attachment_id = (int) $row['attachment_id'];
            $media_by_review[(int) $row['review_id']][] = array(
                'attachment_id' => $attachment_id,
                'image_url'     => wp_get_attachment_image_url($attachment_id, 'large'),
                'thumb_url'     => wp_get_attachment_image_url($attachment_id, 'medium'),
                'image_html'    => wp_get_attachment_image($attachment_id, 'medium'),
            );
        }

        foreach ($reviews as &$review) {
            $review_id = (int) $review['id'];
            $review['answers'] = $answers_by_review[$review_id] ?? array();
            $review['media'] = $media_by_review[$review_id] ?? array();
            $review['initials'] = $this->get_initials($review['reviewer_name']);
        }

        return apply_filters('rrp_reviews_hydrated', $reviews);
    }

    private function get_initials($name)
    {
        $parts = preg_split('/\s+/', trim((string) $name));
        $initials = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            if ('' !== $part) {
                $initials .= strtoupper(substr($part, 0, 1));
            }
        }

        return $initials ?: 'A';
    }
}
