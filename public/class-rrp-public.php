<?php

if (! defined('ABSPATH')) {
    exit;
}

class RRP_Public
{
    private $settings;
    private $repository;
    private $turnstile;
    private $submission_result = null;
    private $submission_values = array();

    public function __construct(RRP_Settings $settings, RRP_Repository $repository)
    {
        $this->settings = $settings;
        $this->repository = $repository;
        $this->turnstile = new RRP_Turnstile($settings);
    }

    public function register()
    {
        add_action('init', array($this, 'register_feed_rewrite'));
        add_action('init', array($this, 'maybe_handle_submission'), 20);
        add_action('template_redirect', array($this, 'maybe_render_google_feed'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_filter('query_vars', array($this, 'register_query_vars'));

        add_shortcode('reevuu_reviews_form', array($this, 'render_form_shortcode'));
        add_shortcode('reevuu_reviews_summary', array($this, 'render_summary_shortcode'));
        add_shortcode('reevuu_reviews_list', array($this, 'render_list_shortcode'));
        add_shortcode('reevuu_reviews_slider', array($this, 'render_slider_shortcode'));
        add_shortcode('reevuu_reviews_chips', array($this, 'render_chips_shortcode'));
        add_shortcode('reevuu_reviews_gallery', array($this, 'render_gallery_shortcode'));
        add_shortcode('reevuu_reviews_badge', array($this, 'render_badge_shortcode'));
    }

    public function enqueue_assets()
    {
        if (is_admin()) {
            return;
        }

        wp_enqueue_style('rrp-public', RRP_PLUGIN_URL . 'assets/css/public.css', array(), RRP_VERSION);
        wp_enqueue_script('rrp-public', RRP_PLUGIN_URL . 'assets/js/public.js', array(), RRP_VERSION, true);
        wp_add_inline_style('rrp-public', $this->get_dynamic_css());

        wp_localize_script(
            'rrp-public',
            'rrpPublic',
            array(
                'maxRating' => 5,
            )
        );

        if ($this->turnstile->is_enabled()) {
            wp_enqueue_script('rrp-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true);
        }
    }

    public function register_query_vars($vars)
    {
        $vars[] = 'rrp_google_feed';

        return $vars;
    }

    public function register_feed_rewrite()
    {
        $slug = trim((string) $this->settings->get('google_feed_slug', 'rrp-google-reviews.xml'), '/');
        if ('' === $slug) {
            return;
        }

        add_rewrite_rule('^' . preg_quote($slug, '/') . '$', 'index.php?rrp_google_feed=1', 'top');
    }

    public function maybe_render_google_feed()
    {
        if (! $this->settings->get('google_feed_enabled') || ! get_query_var('rrp_google_feed')) {
            return;
        }

        $reviews = $this->repository->get_reviews(
            array(
                'status' => 'approved',
                'sort'   => 'newest',
                'limit'  => 0,
            )
        );

        header('Content-Type: application/xml; charset=' . get_bloginfo('charset'), true);
        echo $this->build_google_feed_xml($reviews, $this->settings->get('brand_name', get_bloginfo('name'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    public function render_block($type, $attributes = array())
    {
        if ('form' === $type) {
            return $this->render_form_shortcode($attributes);
        }

        if ('summary' === $type) {
            return $this->render_summary_shortcode($attributes);
        }

        if ('list' === $type) {
            return $this->render_list_shortcode($attributes);
        }

        if ('slider' === $type) {
            return $this->render_slider_shortcode($attributes);
        }

        if ('chips' === $type) {
            return $this->render_chips_shortcode($attributes);
        }

        if ('gallery' === $type) {
            return $this->render_gallery_shortcode($attributes);
        }

        return '';
    }

    public function maybe_handle_submission()
    {
        if ('POST' !== strtoupper($_SERVER['REQUEST_METHOD'] ?? '')) {
            return;
        }

        if ('rrp_submit_review' !== ($_POST['rrp_action'] ?? '')) {
            return;
        }

        $this->submission_values = wp_unslash($_POST);

        if (! isset($_POST['rrp_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rrp_nonce'])), 'rrp_submit_review')) {
            $this->submission_result = array(
                'type'    => 'error',
                'message' => __('The review form has expired. Please refresh the page and try again.', 'reevuu-reviews'),
                'errors'  => array(),
            );

            return;
        }

        $questions = apply_filters('rrp_review_questions', $this->repository->get_question_definitions(true));
        $errors = array();

        $reviewer_name = sanitize_text_field(wp_unslash($_POST['reviewer_name'] ?? ''));
        $reviewer_email = sanitize_email(wp_unslash($_POST['reviewer_email'] ?? ''));
        $review_content = sanitize_textarea_field(wp_unslash($_POST['review_content'] ?? ''));
        $has_consent = empty($_POST['has_consent']) ? 0 : 1;
        $target_id = absint($_POST['target_id'] ?? $this->repository->get_default_target_id());

        if ('' === $reviewer_name) {
            $errors['reviewer_name'] = __('Please enter your name.', 'reevuu-reviews');
        }

        if (! is_email($reviewer_email)) {
            $errors['reviewer_email'] = __('Please enter a valid email address.', 'reevuu-reviews');
        }

        if ($this->settings->get('review_content_required') && '' === $review_content) {
            $errors['review_content'] = __('Please share your review.', 'reevuu-reviews');
        }

        if ($this->settings->get('consent_enabled') && ! $has_consent) {
            $errors['has_consent'] = __('Please confirm the consent checkbox before submitting.', 'reevuu-reviews');
        }

        $answers = array();
        $overall_rating = 0;

        foreach ($questions as $index => $question) {
            $field_name = 'question_' . $question['question_key'];
            $raw_value = wp_unslash($_POST[$field_name] ?? '');

            if ('rating' === $question['type']) {
                $value = max(0, min(5, (int) $raw_value));
                if (! empty($question['is_required']) && $value < 1) {
                    $errors[$field_name] = sprintf(__('Please complete the "%s" rating.', 'reevuu-reviews'), $question['label']);
                }

                if (! empty($question['is_primary_rating'])) {
                    $overall_rating = $value;
                }

                $answers[] = array(
                    'question_id'    => (int) $question['id'],
                    'question_key'   => $question['question_key'],
                    'question_label' => $question['label'],
                    'question_type'  => 'rating',
                    'rating_value'   => $value,
                    'sort_order'     => isset($question['sort_order']) ? (int) $question['sort_order'] : (($index + 1) * 10),
                );
            } else {
                $value = sanitize_textarea_field($raw_value);
                if (! empty($question['is_required']) && '' === $value) {
                    $errors[$field_name] = sprintf(__('Please complete the "%s" field.', 'reevuu-reviews'), $question['label']);
                }

                $answers[] = array(
                    'question_id'    => (int) $question['id'],
                    'question_key'   => $question['question_key'],
                    'question_label' => $question['label'],
                    'question_type'  => $question['type'],
                    'text_value'     => $value,
                    'sort_order'     => isset($question['sort_order']) ? (int) $question['sort_order'] : (($index + 1) * 10),
                );
            }
        }

        if ($overall_rating < 1) {
            foreach ($questions as $question) {
                if ('rating' === $question['type']) {
                    $overall_rating = max(1, min(5, (int) wp_unslash($_POST['question_' . $question['question_key']] ?? 0)));
                    break;
                }
            }
        }

        $turnstile_response = $this->turnstile->verify(
            sanitize_text_field(wp_unslash($_POST['cf-turnstile-response'] ?? '')),
            sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '')
        );

        if (is_wp_error($turnstile_response)) {
            $errors['turnstile'] = $turnstile_response->get_error_message();
        }

        $upload_errors = $this->validate_upload_limits($_FILES['rrp_images'] ?? array());
        if ($upload_errors) {
            $errors = array_merge($errors, $upload_errors);
        }

        $payload = apply_filters(
            'rrp_pre_save_review_payload',
            array(
                'review'  => array(
                    'target_id'      => $target_id ?: $this->repository->get_default_target_id(),
                    'reviewer_name'  => $reviewer_name,
                    'reviewer_email' => $reviewer_email,
                    'review_title'   => '',
                    'review_content' => $review_content,
                    'overall_rating' => $overall_rating,
                    'status'         => 'auto_publish' === $this->settings->get('moderation_mode') ? 'approved' : 'pending',
                    'is_verified'    => 0,
                    'has_consent'    => $has_consent,
                    'submission_ip'  => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
                    'user_agent'     => sanitize_textarea_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
                ),
                'answers' => $answers,
            ),
            $questions,
            $this->submission_values
        );

        if ($errors) {
            $this->submission_result = array(
                'type'    => 'error',
                'message' => __('Please correct the highlighted fields and try again.', 'reevuu-reviews'),
                'errors'  => $errors,
            );

            return;
        }

        $attachment_ids = $this->handle_uploaded_images($_FILES['rrp_images'] ?? array());
        if (is_wp_error($attachment_ids)) {
            $this->submission_result = array(
                'type'    => 'error',
                'message' => $attachment_ids->get_error_message(),
                'errors'  => array('rrp_images' => $attachment_ids->get_error_message()),
            );

            return;
        }

        $review_id = $this->repository->insert_review($payload['review'], $payload['answers'], $attachment_ids);

        if (is_wp_error($review_id)) {
            $this->submission_result = array(
                'type'    => 'error',
                'message' => $review_id->get_error_message(),
                'errors'  => array(),
            );

            return;
        }

        $is_published = 'approved' === $payload['review']['status'];
        $this->submission_result = array(
            'type'    => 'success',
            'message' => $is_published ? $this->settings->get('success_message') : $this->settings->get('pending_message'),
            'errors'  => array(),
        );
        $this->send_notification_email($review_id, $payload['review']);
        $this->submission_values = array();
    }

    public function render_form_shortcode($atts = array())
    {
        $atts = shortcode_atts(
            array(
                'target_id' => $this->repository->get_default_target_id(),
                'title'     => __('Leave a review', 'reevuu-reviews'),
            ),
            $atts,
            'reevuu_reviews_form'
        );

        $questions = apply_filters('rrp_review_questions', $this->repository->get_question_definitions(true));
        $settings = $this->settings->get_all();
        $errors = $this->submission_result['errors'] ?? array();
        $form_uid = wp_unique_id('rrp-form-');
        $reviewer_name_id = $form_uid . '-reviewer-name';
        $reviewer_email_id = $form_uid . '-reviewer-email';
        $review_content_id = $form_uid . '-review-content';
        $images_id = $form_uid . '-images';
        $consent_id = $form_uid . '-consent';

        ob_start();
        ?>
        <div class="rrp-form-wrap">
            <h2><?php echo esc_html($atts['title']); ?></h2>
            <?php $this->render_submission_notice(); ?>
            <form class="rrp-review-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('rrp_submit_review', 'rrp_nonce'); ?>
                <input type="hidden" name="rrp_action" value="rrp_submit_review" />
                <input type="hidden" name="target_id" value="<?php echo esc_attr((string) $atts['target_id']); ?>" />

                <div class="rrp-form-grid">
                    <label class="rrp-field <?php echo isset($errors['reviewer_name']) ? 'has-error' : ''; ?>" for="<?php echo esc_attr($reviewer_name_id); ?>">
                        <span><?php esc_html_e('Your name *', 'reevuu-reviews'); ?></span>
                        <input id="<?php echo esc_attr($reviewer_name_id); ?>" type="text" name="reviewer_name" value="<?php echo esc_attr($this->submitted_value('reviewer_name')); ?>" required />
                        <?php $this->render_error('reviewer_name'); ?>
                    </label>

                    <label class="rrp-field <?php echo isset($errors['reviewer_email']) ? 'has-error' : ''; ?>" for="<?php echo esc_attr($reviewer_email_id); ?>">
                        <span><?php esc_html_e('Your email *', 'reevuu-reviews'); ?></span>
                        <input id="<?php echo esc_attr($reviewer_email_id); ?>" type="email" name="reviewer_email" value="<?php echo esc_attr($this->submitted_value('reviewer_email')); ?>" required />
                        <?php $this->render_error('reviewer_email'); ?>
                    </label>
                </div>

                <?php foreach ($questions as $question) : ?>
                    <?php if ('rating' !== $question['type']) : ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <?php $field_name = 'question_' . $question['question_key']; ?>
                    <?php $field_id = $form_uid . '-' . sanitize_html_class($field_name); ?>
                    <fieldset class="rrp-rating-field <?php echo isset($errors[$field_name]) ? 'has-error' : ''; ?>">
                        <legend><?php echo esc_html($question['label']); ?><?php echo ! empty($question['is_required']) ? ' *' : ''; ?></legend>
                        <?php if (! empty($question['help_text'])) : ?>
                            <p class="rrp-help"><?php echo esc_html($question['help_text']); ?></p>
                        <?php endif; ?>
                        <div class="rrp-stars-input" data-target="<?php echo esc_attr($field_name); ?>">
                            <?php for ($rating = 5; $rating >= 1; $rating--) : ?>
                                <input type="radio" id="<?php echo esc_attr($field_id . '_' . $rating); ?>" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr((string) $rating); ?>" <?php checked((int) $this->submitted_value($field_name), $rating); ?> />
                                <label class="rrp-star-label" for="<?php echo esc_attr($field_id . '_' . $rating); ?>" aria-label="<?php echo esc_attr(sprintf(__('%d star', 'reevuu-reviews'), $rating)); ?>">&#9733;</label>
                            <?php endfor; ?>
                        </div>
                        <?php $this->render_error($field_name); ?>
                    </fieldset>
                <?php endforeach; ?>

                <label class="rrp-field <?php echo isset($errors['review_content']) ? 'has-error' : ''; ?>" for="<?php echo esc_attr($review_content_id); ?>">
                    <span><?php echo esc_html($settings['review_content_label']); ?></span>
                    <textarea id="<?php echo esc_attr($review_content_id); ?>" name="review_content" rows="5" <?php echo $settings['review_content_required'] ? 'required' : ''; ?>><?php echo esc_textarea($this->submitted_value('review_content')); ?></textarea>
                    <?php $this->render_error('review_content'); ?>
                </label>

                <?php foreach ($questions as $question) : ?>
                    <?php if ('rating' === $question['type']) : ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <?php $field_name = 'question_' . $question['question_key']; ?>
                    <?php $field_id = $form_uid . '-' . sanitize_html_class($field_name); ?>
                    <label class="rrp-field <?php echo isset($errors[$field_name]) ? 'has-error' : ''; ?>" for="<?php echo esc_attr($field_id); ?>">
                        <span><?php echo esc_html($question['label']); ?><?php echo ! empty($question['is_required']) ? ' *' : ''; ?></span>
                        <?php if ('textarea' === $question['type']) : ?>
                            <textarea id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($field_name); ?>" rows="4" placeholder="<?php echo esc_attr($question['placeholder']); ?>"><?php echo esc_textarea($this->submitted_value($field_name)); ?></textarea>
                        <?php else : ?>
                            <input id="<?php echo esc_attr($field_id); ?>" type="text" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr($this->submitted_value($field_name)); ?>" placeholder="<?php echo esc_attr($question['placeholder']); ?>" />
                        <?php endif; ?>
                        <?php if (! empty($question['help_text'])) : ?>
                            <small class="rrp-help"><?php echo esc_html($question['help_text']); ?></small>
                        <?php endif; ?>
                        <?php $this->render_error($field_name); ?>
                    </label>
                <?php endforeach; ?>

                <?php if ((int) $settings['max_images'] > 0) : ?>
                    <label class="rrp-field <?php echo isset($errors['rrp_images']) ? 'has-error' : ''; ?>" for="<?php echo esc_attr($images_id); ?>">
                        <span><?php printf(esc_html__('Add up to %d images', 'reevuu-reviews'), (int) $settings['max_images']); ?></span>
                        <input id="<?php echo esc_attr($images_id); ?>" type="file" name="rrp_images[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple />
                        <small class="rrp-help"><?php printf(esc_html__('Max file size: %dMB per image.', 'reevuu-reviews'), (int) $settings['max_image_size_mb']); ?></small>
                        <?php $this->render_error('rrp_images'); ?>
                    </label>
                <?php endif; ?>

                <?php if ($settings['consent_enabled']) : ?>
                    <label class="rrp-consent <?php echo isset($errors['has_consent']) ? 'has-error' : ''; ?>" for="<?php echo esc_attr($consent_id); ?>">
                        <input id="<?php echo esc_attr($consent_id); ?>" type="checkbox" name="has_consent" value="1" <?php checked((int) $this->submitted_value('has_consent'), 1); ?> />
                        <span>
                            <?php echo esc_html($settings['consent_label']); ?>
                            <?php if (! empty($settings['consent_url'])) : ?>
                                <a href="<?php echo esc_url($settings['consent_url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Read policy', 'reevuu-reviews'); ?></a>
                            <?php endif; ?>
                        </span>
                    </label>
                    <?php $this->render_error('has_consent'); ?>
                <?php endif; ?>

                <?php if ($this->turnstile->is_enabled()) : ?>
                    <div class="cf-turnstile" data-sitekey="<?php echo esc_attr($settings['turnstile_site_key']); ?>"></div>
                    <?php $this->render_error('turnstile'); ?>
                <?php endif; ?>

                <button type="submit" class="rrp-button"><?php esc_html_e('Submit review', 'reevuu-reviews'); ?></button>
            </form>
        </div>
        <?php

        return ob_get_clean();
    }

    public function render_summary_shortcode($atts = array())
    {
        $atts = shortcode_atts(
            array(
                'target_id' => $this->repository->get_default_target_id(),
                'schema'    => '1',
            ),
            $atts,
            'reevuu_reviews_summary'
        );

        $summary = $this->repository->get_summary((int) $atts['target_id']);

        ob_start();
        ?>
        <section class="rrp-summary-card">
            <div class="rrp-summary-score">
                <span class="rrp-big-score"><?php echo esc_html(number_format_i18n((float) $summary['average_rating'], 1)); ?></span>
                <div><?php echo $this->render_stars((float) $summary['average_rating']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <p><?php printf(esc_html(_n('Based on %d review', 'Based on %d reviews', $summary['count'], 'reevuu-reviews')), (int) $summary['count']); ?></p>
            </div>
            <div class="rrp-summary-bars">
                <?php for ($star = 5; $star >= 1; $star--) : ?>
                    <div class="rrp-summary-row">
                        <span><?php echo esc_html((string) $star); ?> <?php esc_html_e('star', 'reevuu-reviews'); ?></span>
                        <div class="rrp-bar"><span style="width: <?php echo esc_attr((string) $summary['distribution_pct'][$star]); ?>%"></span></div>
                        <strong><?php echo esc_html((string) $summary['distribution'][$star]); ?></strong>
                    </div>
                <?php endfor; ?>
            </div>
        </section>
        <?php

        if ('1' === (string) $atts['schema']) {
            echo $this->render_schema_script(array(), $summary); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        return ob_get_clean();
    }

    public function render_list_shortcode($atts = array())
    {
        $atts = shortcode_atts(
            array(
                'target_id'   => $this->repository->get_default_target_id(),
                'limit'       => $this->settings->get('default_list_limit'),
                'show_search' => '1',
                'show_sort'   => '1',
                'show_schema' => '1',
            ),
            $atts,
            'reevuu_reviews_list'
        );

        $search = sanitize_text_field($_GET['rrp_search'] ?? '');
        $sort = sanitize_key($_GET['rrp_sort'] ?? 'newest');
        if (! in_array($sort, array('newest', 'oldest', 'highest', 'lowest'), true)) {
            $sort = 'newest';
        }

        $reviews = $this->repository->get_reviews(
            array(
                'target_id' => (int) $atts['target_id'],
                'status'    => 'approved',
                'search'    => $search,
                'sort'      => $sort,
                'limit'     => (int) $atts['limit'],
            )
        );
        $summary = $this->repository->get_summary((int) $atts['target_id']);

        ob_start();
        ?>
        <section class="rrp-list-wrap">
            <?php if ('1' === (string) $atts['show_search'] || '1' === (string) $atts['show_sort']) : ?>
                <form method="get" class="rrp-list-controls">
                    <?php if ('1' === (string) $atts['show_sort']) : ?>
                        <select name="rrp_sort">
                            <option value="newest" <?php selected($sort, 'newest'); ?>><?php esc_html_e('Most recent', 'reevuu-reviews'); ?></option>
                            <option value="oldest" <?php selected($sort, 'oldest'); ?>><?php esc_html_e('Oldest first', 'reevuu-reviews'); ?></option>
                            <option value="highest" <?php selected($sort, 'highest'); ?>><?php esc_html_e('Highest rating', 'reevuu-reviews'); ?></option>
                            <option value="lowest" <?php selected($sort, 'lowest'); ?>><?php esc_html_e('Lowest rating', 'reevuu-reviews'); ?></option>
                        </select>
                    <?php endif; ?>
                    <input type="search" name="rrp_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search customer reviews', 'reevuu-reviews'); ?>" />
                    <button type="submit" class="rrp-button rrp-button-secondary"><?php esc_html_e('Search', 'reevuu-reviews'); ?></button>
                </form>
            <?php endif; ?>

            <div class="rrp-results-count">
                <?php printf(esc_html(_n('%d review shown', '%d reviews shown', count($reviews), 'reevuu-reviews')), count($reviews)); ?>
            </div>

            <div class="rrp-table-scroll">
                <table class="rrp-review-table-public">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Reviewer', 'reevuu-reviews'); ?></th>
                            <th><?php esc_html_e('Rating', 'reevuu-reviews'); ?></th>
                            <th><?php esc_html_e('Date', 'reevuu-reviews'); ?></th>
                            <th><?php esc_html_e('Review', 'reevuu-reviews'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (! $reviews) : ?>
                            <tr>
                                <td colspan="4"><?php esc_html_e('No approved reviews matched your search.', 'reevuu-reviews'); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($reviews as $review) : ?>
                            <tr>
                                <td>
                                    <div class="rrp-reviewer-cell">
                                        <span class="rrp-avatar"><?php echo esc_html($review['initials']); ?></span>
                                        <div>
                                            <strong><?php echo esc_html($review['reviewer_name']); ?></strong>
                                            <?php if (! empty($review['is_verified'])) : ?>
                                                <span class="rrp-verified"><?php esc_html_e('Verified review', 'reevuu-reviews'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $this->render_stars((float) $review['overall_rating']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                                <td><?php echo esc_html(mysql2date(get_option('date_format'), $review['created_at'])); ?></td>
                                <td>
                                    <?php if (! empty($review['review_title'])) : ?>
                                        <strong><?php echo esc_html($review['review_title']); ?></strong>
                                    <?php endif; ?>
                                    <?php if (! empty($review['review_content'])) : ?>
                                        <p><?php echo esc_html($review['review_content']); ?></p>
                                    <?php endif; ?>
                                    <?php if (! empty($review['response_content'])) : ?>
                                        <div class="rrp-review-response">
                                            <strong><?php echo esc_html($review['response_author'] ?: __('Admin response', 'reevuu-reviews')); ?></strong>
                                            <p><?php echo esc_html($review['response_content']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    <?php foreach ($review['answers'] as $answer) : ?>
                                        <div class="rrp-answer-line">
                                            <span><?php echo esc_html($answer['question_label']); ?>:</span>
                                            <strong>
                                                <?php echo 'rating' === $answer['question_type'] ? esc_html(number_format_i18n((float) $answer['rating_value'], 1) . '/5') : esc_html($answer['text_value']); ?>
                                            </strong>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (! empty($review['media'])) : ?>
                                        <div class="rrp-inline-gallery">
                                            <?php foreach ($review['media'] as $media) : ?>
                                                <?php if (! empty($media['thumb_url'])) : ?>
                                                    <img src="<?php echo esc_url($media['thumb_url']); ?>" alt="" />
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php

        if ('1' === (string) $atts['show_schema']) {
            echo $this->render_schema_script($reviews, $summary); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        return ob_get_clean();
    }

    public function render_slider_shortcode($atts = array())
    {
        $atts = shortcode_atts(
            array(
                'target_id' => $this->repository->get_default_target_id(),
                'limit'     => $this->settings->get('default_slider_limit'),
                'title'     => __('Customer Reviews', 'reevuu-reviews'),
            ),
            $atts,
            'reevuu_reviews_slider'
        );

        $reviews = $this->repository->get_reviews(
            array(
                'target_id' => (int) $atts['target_id'],
                'status'    => 'approved',
                'sort'      => 'newest',
                'limit'     => (int) $atts['limit'],
            )
        );

        ob_start();
        ?>
        <section class="rrp-slider-wrap">
            <div class="rrp-slider-header">
                <h2><?php echo esc_html($atts['title']); ?></h2>
                <div class="rrp-slider-buttons">
                    <button type="button" class="rrp-slider-prev" aria-label="<?php esc_attr_e('Previous reviews', 'reevuu-reviews'); ?>">&lsaquo;</button>
                    <button type="button" class="rrp-slider-next" aria-label="<?php esc_attr_e('Next reviews', 'reevuu-reviews'); ?>">&rsaquo;</button>
                </div>
            </div>
            <div class="rrp-slider-track" tabindex="0">
                <?php foreach ($reviews as $review) : ?>
                    <article class="rrp-review-card">
                        <div class="rrp-review-card-header">
                            <span class="rrp-avatar"><?php echo esc_html($review['initials']); ?></span>
                            <div>
                                <strong><?php echo esc_html($review['reviewer_name']); ?></strong>
                                <?php if (! empty($review['is_verified'])) : ?>
                                    <span class="rrp-verified"><?php esc_html_e('Verified review', 'reevuu-reviews'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="rrp-card-stars"><?php echo $this->render_stars((float) $review['overall_rating']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                        <?php if (! empty($review['review_title'])) : ?>
                            <h3><?php echo esc_html($review['review_title']); ?></h3>
                        <?php endif; ?>
                        <?php if (! empty($review['review_content'])) : ?>
                            <p><?php echo esc_html(wp_trim_words($review['review_content'], 28)); ?></p>
                        <?php endif; ?>
                        <?php if (! empty($review['response_content'])) : ?>
                            <div class="rrp-review-response">
                                <strong><?php echo esc_html($review['response_author'] ?: __('Admin response', 'reevuu-reviews')); ?></strong>
                                <p><?php echo esc_html(wp_trim_words($review['response_content'], 22)); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (! empty($review['media'][0]['thumb_url'])) : ?>
                            <img class="rrp-card-image" src="<?php echo esc_url($review['media'][0]['thumb_url']); ?>" alt="" />
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php

        return ob_get_clean();
    }

    public function render_chips_shortcode($atts = array())
    {
        $atts = shortcode_atts(
            array(
                'target_id' => $this->repository->get_default_target_id(),
                'limit'     => $this->settings->get('default_chip_limit'),
            ),
            $atts,
            'reevuu_reviews_chips'
        );

        $reviews = $this->repository->get_reviews(
            array(
                'target_id' => (int) $atts['target_id'],
                'status'    => 'approved',
                'sort'      => 'highest',
                'limit'     => (int) $atts['limit'],
            )
        );

        ob_start();
        ?>
        <div class="rrp-chip-cloud">
            <?php foreach ($reviews as $review) : ?>
                <article class="rrp-chip">
                    <strong><?php echo esc_html($review['reviewer_name']); ?></strong>
                    <span><?php echo esc_html(number_format_i18n((float) $review['overall_rating'], 1)); ?>/5</span>
                    <small><?php echo esc_html(wp_trim_words($review['review_content'] ?: $review['review_title'], 10)); ?></small>
                </article>
            <?php endforeach; ?>
        </div>
        <?php

        return ob_get_clean();
    }

    public function render_gallery_shortcode($atts = array())
    {
        $atts = shortcode_atts(
            array(
                'target_id' => $this->repository->get_default_target_id(),
                'limit'     => $this->settings->get('gallery_image_limit'),
                'title'     => __('Customer Images', 'reevuu-reviews'),
            ),
            $atts,
            'reevuu_reviews_gallery'
        );

        $image_ids = $this->repository->get_gallery_images((int) $atts['target_id'], (int) $atts['limit']);

        ob_start();
        ?>
        <section class="rrp-gallery-wrap">
            <h3><?php echo esc_html($atts['title']); ?></h3>
            <div class="rrp-gallery-grid">
                <?php foreach ($image_ids as $attachment_id) : ?>
                    <a href="<?php echo esc_url((string) wp_get_attachment_image_url($attachment_id, 'large')); ?>" class="rrp-gallery-item">
                        <?php echo wp_get_attachment_image($attachment_id, 'medium_large'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php

        return ob_get_clean();
    }

    public function render_badge_shortcode($atts = array())
    {
        $atts = shortcode_atts(
            array(
                'target_id'   => $this->repository->get_default_target_id(),
                'show_count'  => '1',
                'show_label'  => '1',
                'label'       => __('Rated', 'reevuu-reviews'),
            ),
            $atts,
            'reevuu_reviews_badge'
        );

        $summary = $this->repository->get_summary((int) $atts['target_id']);

        if (empty($summary['count'])) {
            return '';
        }

        ob_start();
        ?>
        <span class="rrp-badge" aria-label="<?php echo esc_attr(sprintf(__('Average rating %1$s from %2$s reviews', 'reevuu-reviews'), number_format_i18n((float) $summary['average_rating'], 1), number_format_i18n((int) $summary['count']))); ?>">
            <?php if ('1' === (string) $atts['show_label']) : ?>
                <span class="rrp-badge-label"><?php echo esc_html($atts['label']); ?></span>
            <?php endif; ?>
            <span class="rrp-badge-score"><?php echo esc_html(number_format_i18n((float) $summary['average_rating'], 1)); ?></span>
            <span class="rrp-badge-stars"><?php echo $this->render_stars((float) $summary['average_rating']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            <?php if ('1' === (string) $atts['show_count']) : ?>
                <span class="rrp-badge-count"><?php echo esc_html(number_format_i18n((int) $summary['count'])); ?></span>
            <?php endif; ?>
        </span>
        <?php

        return ob_get_clean();
    }

    private function validate_upload_limits($files)
    {
        $errors = array();
        $max_images = (int) $this->settings->get('max_images');
        $max_size = (int) $this->settings->get('max_image_size_mb') * 1024 * 1024;

        if (empty($files['name']) || ! is_array($files['name'])) {
            return array();
        }

        $count = 0;
        foreach ($files['name'] as $index => $name) {
            if ('' === (string) $name) {
                continue;
            }

            $count++;

            if ($count > $max_images) {
                $errors['rrp_images'] = sprintf(__('You can upload up to %d images.', 'reevuu-reviews'), $max_images);
                break;
            }

            if (! empty($files['size'][$index]) && (int) $files['size'][$index] > $max_size) {
                $errors['rrp_images'] = sprintf(__('Each image must be %dMB or smaller.', 'reevuu-reviews'), (int) $this->settings->get('max_image_size_mb'));
                break;
            }
        }

        return $errors;
    }

    private function handle_uploaded_images($files)
    {
        if (empty($files['name']) || ! is_array($files['name'])) {
            return array();
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_ids = array();
        $allowed_mimes = array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'gif'          => 'image/gif',
            'webp'         => 'image/webp',
        );

        foreach ($files['name'] as $index => $name) {
            if ('' === (string) $name) {
                continue;
            }

            $file = array(
                'name'     => $files['name'][$index],
                'type'     => $files['type'][$index],
                'tmp_name' => $files['tmp_name'][$index],
                'error'    => $files['error'][$index],
                'size'     => $files['size'][$index],
            );

            $uploaded = wp_handle_upload($file, array('test_form' => false, 'mimes' => $allowed_mimes));
            if (isset($uploaded['error'])) {
                return new WP_Error('rrp_upload_failed', $uploaded['error']);
            }

            $attachment = array(
                'post_mime_type' => $uploaded['type'],
                'post_title'     => sanitize_file_name(pathinfo($uploaded['file'], PATHINFO_FILENAME)),
                'post_content'   => '',
                'post_status'    => 'inherit',
            );

            $attachment_id = wp_insert_attachment($attachment, $uploaded['file']);
            if (is_wp_error($attachment_id)) {
                return $attachment_id;
            }

            $metadata = wp_generate_attachment_metadata($attachment_id, $uploaded['file']);
            wp_update_attachment_metadata($attachment_id, $metadata);
            $attachment_ids[] = (int) $attachment_id;
        }

        return $attachment_ids;
    }

    private function render_submission_notice()
    {
        if (! $this->submission_result) {
            return;
        }

        $class = 'success' === $this->submission_result['type'] ? 'rrp-notice-success' : 'rrp-notice-error';
        echo '<div class="rrp-notice ' . esc_attr($class) . '"><p>' . esc_html($this->submission_result['message']) . '</p></div>';
    }

    private function render_error($field)
    {
        if (empty($this->submission_result['errors'][$field])) {
            return;
        }

        echo '<small class="rrp-field-error">' . esc_html($this->submission_result['errors'][$field]) . '</small>';
    }

    private function submitted_value($key)
    {
        return $this->submission_values[$key] ?? '';
    }

    private function render_stars($rating)
    {
        $rounded = round((float) $rating);
        $output = '<span class="rrp-stars" aria-label="' . esc_attr(sprintf(__('Rated %s out of 5', 'reevuu-reviews'), number_format_i18n((float) $rating, 1))) . '">';

        for ($i = 1; $i <= 5; $i++) {
            $output .= '<span class="' . ($i <= $rounded ? 'is-filled' : '') . '">&#9733;</span>';
        }

        $output .= '</span>';

        return $output;
    }

    private function get_dynamic_css()
    {
        $settings = $this->settings->get_all();
        $font_family = trim((string) $settings['style_font_family']);
        if ('' === $font_family) {
            $font_family = 'inherit';
        }

        $css = ':root{'
            . '--rrp-form-bg:' . esc_html($settings['style_form_background']) . ';'
            . '--rrp-form-bg-secondary:' . esc_html($settings['style_form_background_secondary']) . ';'
            . '--rrp-card-bg:' . esc_html($settings['style_card_background']) . ';'
            . '--rrp-accent:' . esc_html($settings['style_accent_color']) . ';'
            . '--rrp-star:' . esc_html($settings['style_star_color']) . ';'
            . '--rrp-text:' . esc_html($settings['style_text_color']) . ';'
            . '--rrp-font:' . esc_html($font_family) . ';'
            . '}';

        return $css;
    }

    private function send_notification_email($review_id, $review)
    {
        $recipients = preg_split('/[\r\n,]+/', (string) $this->settings->get('notification_recipients', ''));
        $recipients = array_filter(array_map('trim', (array) $recipients));
        $recipients = array_values(array_filter($recipients, 'is_email'));

        if (! $recipients) {
            return;
        }

        $enabled_ratings = array_map('intval', (array) $this->settings->get('notification_ratings', array()));
        $rating_bucket = max(1, min(5, (int) round((float) ($review['overall_rating'] ?? 0))));

        if ($enabled_ratings && ! in_array($rating_bucket, $enabled_ratings, true)) {
            return;
        }

        $subject = sprintf(__('New %1$d-star review on %2$s', 'reevuu-reviews'), $rating_bucket, wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES));
        $admin_url = admin_url('admin.php?page=rrp-reviews');
        $body = array(
            sprintf(__('A new review has been submitted on %s.', 'reevuu-reviews'), get_bloginfo('name')),
            '',
            sprintf(__('Reviewer: %s', 'reevuu-reviews'), $review['reviewer_name'] ?? ''),
            sprintf(__('Email: %s', 'reevuu-reviews'), $review['reviewer_email'] ?? ''),
            sprintf(__('Overall rating: %s/5', 'reevuu-reviews'), number_format_i18n((float) ($review['overall_rating'] ?? 0), 1)),
            sprintf(__('Status: %s', 'reevuu-reviews'), ucfirst((string) ($review['status'] ?? 'pending'))),
            '',
            __('Review content:', 'reevuu-reviews'),
            (string) ($review['review_content'] ?? ''),
            '',
            sprintf(__('Moderate or reply here: %s', 'reevuu-reviews'), $admin_url),
        );

        wp_mail($recipients, $subject, implode("\n", $body));
    }

    private function render_schema_script($reviews, $summary)
    {
        $payload = array(
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'     => get_bloginfo('name'),
        );

        if (! empty($summary['count'])) {
            $payload['aggregateRating'] = array(
                '@type'       => 'AggregateRating',
                'ratingValue' => (float) $summary['average_rating'],
                'reviewCount' => (int) $summary['count'],
                'bestRating'  => 5,
                'worstRating' => 1,
            );
        }

        if ($reviews) {
            $payload['review'] = array();
            foreach ($reviews as $review) {
                $payload['review'][] = array(
                    '@type'         => 'Review',
                    'author'        => array(
                        '@type' => 'Person',
                        'name'  => $review['reviewer_name'],
                    ),
                    'datePublished' => mysql2date('c', $review['created_at']),
                    'headline'      => $review['review_title'],
                    'reviewBody'    => $review['review_content'],
                    'reviewRating'  => array(
                        '@type'       => 'Rating',
                        'ratingValue' => (float) $review['overall_rating'],
                        'bestRating'  => 5,
                        'worstRating' => 1,
                    ),
                );
            }
        }

        return '<script type="application/ld+json">' . wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    }

    private function build_google_feed_xml($reviews, $brand_name)
    {
        $xml = new DOMDocument('1.0', get_bloginfo('charset'));
        $xml->formatOutput = true;

        $feed = $xml->createElement('feed');
        $feed->setAttribute('xmlns', 'http://schemas.google.com/product/2.1');
        $xml->appendChild($feed);

        $feed->appendChild($xml->createElement('version', '2.3'));

        $aggregator = $xml->createElement('aggregator');
        $aggregator->appendChild($xml->createElement('name', $brand_name));
        $feed->appendChild($aggregator);

        $publisher = $xml->createElement('publisher');
        $publisher->appendChild($xml->createElement('name', $brand_name));
        $feed->appendChild($publisher);

        $reviews_node = $xml->createElement('reviews');
        $feed->appendChild($reviews_node);

        foreach ($reviews as $review) {
            $review_node = $xml->createElement('review');
            $reviews_node->appendChild($review_node);

            $review_node->appendChild($xml->createElement('review_id', (string) $review['id']));

            $reviewer = $xml->createElement('reviewer');
            $reviewer->appendChild($xml->createElement('name', $review['reviewer_name']));
            $review_node->appendChild($reviewer);

            $review_node->appendChild($xml->createElement('review_timestamp', mysql2date('c', $review['created_at'])));
            $review_node->appendChild($xml->createElement('title', $review['review_title']));
            $review_node->appendChild($xml->createElement('content', wp_strip_all_tags($review['review_content'])));

            $ratings = $xml->createElement('ratings');
            $overall = $xml->createElement('overall', (string) $review['overall_rating']);
            $overall->setAttribute('min', '1');
            $overall->setAttribute('max', '5');
            $ratings->appendChild($overall);
            $review_node->appendChild($ratings);

            $review_node->appendChild($xml->createElement('collection_method', 'unsolicited'));
            $review_node->appendChild($xml->createElement('is_spam', 'false'));

            if (! empty($review['media'])) {
                $images = $xml->createElement('reviewer_images');
                foreach ($review['media'] as $media) {
                    if (! empty($media['image_url'])) {
                        $images->appendChild($xml->createElement('reviewer_image', $media['image_url']));
                    }
                }
                $review_node->appendChild($images);
            }
        }

        return $xml->saveXML();
    }
}
