<?php

if (! defined('ABSPATH')) {
    exit;
}

class RRP_Admin
{
    private $settings;
    private $repository;

    public function __construct(RRP_Settings $settings, RRP_Repository $repository)
    {
        $this->settings = $settings;
        $this->repository = $repository;
    }

    public function register()
    {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_post_rrp_save_settings', array($this, 'handle_save_settings'));
        add_action('admin_post_rrp_review_action', array($this, 'handle_review_action'));
    }

    public function register_menu()
    {
        add_menu_page(
            __('ReevuuWP', 'reevuu-reviews'),
            __('ReevuuWP', 'reevuu-reviews'),
            'manage_options',
            'rrp-reviews',
            array($this, 'render_reviews_page'),
            'dashicons-star-filled',
            58
        );

        add_submenu_page(
            'rrp-reviews',
            __('Review Moderation', 'reevuu-reviews'),
            __('Review Moderation', 'reevuu-reviews'),
            'manage_options',
            'rrp-reviews',
            array($this, 'render_reviews_page')
        );

        add_submenu_page(
            'rrp-reviews',
            __('Settings', 'reevuu-reviews'),
            __('Settings', 'reevuu-reviews'),
            'manage_options',
            'rrp-review-settings',
            array($this, 'render_settings_page')
        );
    }

    public function enqueue_assets($hook)
    {
        if (false === strpos((string) $hook, 'rrp-')) {
            return;
        }

        wp_enqueue_style('rrp-admin', RRP_PLUGIN_URL . 'assets/css/admin.css', array(), RRP_VERSION);
        wp_enqueue_script('rrp-admin', RRP_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), RRP_VERSION, true);

        wp_localize_script(
            'rrp-admin',
            'rrpAdmin',
            array(
                'newQuestionLabel' => __('New question', 'reevuu-reviews'),
            )
        );
    }

    public function handle_save_settings()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage reviews settings.', 'reevuu-reviews'));
        }

        check_admin_referer('rrp_save_settings');

        $old_settings = $this->settings->get_all();
        $settings = $this->settings->update($_POST['settings'] ?? array());

        $raw_questions = $_POST['questions'] ?? array();
        $questions = array();

        if (is_array($raw_questions)) {
            foreach ($raw_questions as $index => $question) {
                $label = sanitize_text_field($question['label'] ?? '');
                $type = sanitize_key($question['type'] ?? 'text');

                if ('' === $label || ! in_array($type, array('rating', 'text', 'textarea'), true)) {
                    continue;
                }

                $questions[] = array(
                    'question_key'      => sanitize_key($question['question_key'] ?? ('question_' . ($index + 1))),
                    'label'             => $label,
                    'type'              => $type,
                    'help_text'         => sanitize_text_field($question['help_text'] ?? ''),
                    'placeholder'       => sanitize_text_field($question['placeholder'] ?? ''),
                    'is_required'       => empty($question['is_required']) ? 0 : 1,
                    'is_primary_rating' => empty($question['is_primary_rating']) ? 0 : 1,
                    'is_active'         => empty($question['is_active']) ? 0 : 1,
                    'sort_order'        => isset($question['sort_order']) ? (int) $question['sort_order'] : (($index + 1) * 10),
                );
            }
        }

        if (! $questions) {
            $questions[] = array(
                'question_key'      => 'overall_rating',
                'label'             => __('Overall rating', 'reevuu-reviews'),
                'type'              => 'rating',
                'help_text'         => '',
                'placeholder'       => '',
                'is_required'       => 1,
                'is_primary_rating' => 1,
                'is_active'         => 1,
                'sort_order'        => 10,
            );
        }

        $has_rating = false;
        foreach ($questions as &$question) {
            if ('rating' === $question['type'] && ! empty($question['is_active'])) {
                $has_rating = true;
                break;
            }
        }
        unset($question);

        if (! $has_rating) {
            $questions[] = array(
                'question_key'      => 'overall_rating',
                'label'             => __('Overall rating', 'reevuu-reviews'),
                'type'              => 'rating',
                'help_text'         => '',
                'placeholder'       => '',
                'is_required'       => 1,
                'is_primary_rating' => 1,
                'is_active'         => 1,
                'sort_order'        => (count($questions) + 1) * 10,
            );
        }

        $primary_found = false;
        foreach ($questions as &$question) {
            if ('rating' !== $question['type']) {
                $question['is_primary_rating'] = 0;
                continue;
            }

            if (! empty($question['is_primary_rating']) && empty($primary_found)) {
                $question['is_primary_rating'] = 1;
                $primary_found = true;
            } else {
                $question['is_primary_rating'] = 0;
            }
        }
        unset($question);

        if (! $primary_found) {
            foreach ($questions as &$question) {
                if ('rating' === $question['type']) {
                    $question['is_primary_rating'] = 1;
                    break;
                }
            }
            unset($question);
        }

        $this->repository->replace_question_definitions($questions);

        if (($old_settings['google_feed_slug'] ?? '') !== ($settings['google_feed_slug'] ?? '')) {
            flush_rewrite_rules();
        }

        $redirect = add_query_arg(
            array(
                'page'       => 'rrp-review-settings',
                'rrp_notice' => 'saved',
            ),
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect);
        exit;
    }

    public function handle_review_action()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage reviews.', 'reevuu-reviews'));
        }

        check_admin_referer('rrp_review_action');

        $review_id = absint($_POST['review_id'] ?? 0);
        $status = sanitize_key($_POST['status'] ?? '');
        $verified = empty($_POST['is_verified']) ? 0 : 1;

        if ($review_id) {
            if ($status) {
                $this->repository->update_review_status($review_id, $status);
            }
            $this->repository->set_verified($review_id, $verified);
        }

        $redirect = add_query_arg(
            array(
                'page'       => 'rrp-reviews',
                'rrp_notice' => 'review-updated',
            ),
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect);
        exit;
    }

    public function render_settings_page()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $settings = $this->settings->get_all();
        $questions = $this->repository->get_question_definitions(false);
        $feed_url = home_url('/' . ltrim($settings['google_feed_slug'], '/'));
        ?>
        <div class="wrap rrp-admin-wrap">
            <h1><?php esc_html_e('ReevuuWP Settings', 'reevuu-reviews'); ?></h1>
            <?php $this->render_notice(); ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('rrp_save_settings'); ?>
                <input type="hidden" name="action" value="rrp_save_settings" />

                <div class="rrp-admin-grid">
                    <section class="rrp-admin-card">
                        <h2><?php esc_html_e('General Settings', 'reevuu-reviews'); ?></h2>

                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="rrp-moderation-mode"><?php esc_html_e('Moderation mode', 'reevuu-reviews'); ?></label></th>
                                <td>
                                    <select id="rrp-moderation-mode" name="settings[moderation_mode]">
                                        <option value="pending" <?php selected($settings['moderation_mode'], 'pending'); ?>><?php esc_html_e('Hold new reviews for moderation', 'reevuu-reviews'); ?></option>
                                        <option value="auto_publish" <?php selected($settings['moderation_mode'], 'auto_publish'); ?>><?php esc_html_e('Auto-publish after submission', 'reevuu-reviews'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Cloudflare Turnstile', 'reevuu-reviews'); ?></th>
                                <td class="rrp-stack">
                                    <input type="text" class="regular-text" name="settings[turnstile_site_key]" value="<?php echo esc_attr($settings['turnstile_site_key']); ?>" placeholder="<?php esc_attr_e('Site key', 'reevuu-reviews'); ?>" />
                                    <input type="text" class="regular-text" name="settings[turnstile_secret_key]" value="<?php echo esc_attr($settings['turnstile_secret_key']); ?>" placeholder="<?php esc_attr_e('Secret key', 'reevuu-reviews'); ?>" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Consent checkbox', 'reevuu-reviews'); ?></th>
                                <td class="rrp-stack">
                                    <label><input type="checkbox" name="settings[consent_enabled]" value="1" <?php checked($settings['consent_enabled'], 1); ?> /> <?php esc_html_e('Require consent before submission', 'reevuu-reviews'); ?></label>
                                    <input type="text" class="regular-text" name="settings[consent_label]" value="<?php echo esc_attr($settings['consent_label']); ?>" placeholder="<?php esc_attr_e('Consent label', 'reevuu-reviews'); ?>" />
                                    <input type="url" class="regular-text" name="settings[consent_url]" value="<?php echo esc_attr($settings['consent_url']); ?>" placeholder="<?php esc_attr_e('Privacy policy URL', 'reevuu-reviews'); ?>" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Review fields', 'reevuu-reviews'); ?></th>
                                <td class="rrp-stack">
                                    <input type="text" class="regular-text" name="settings[review_content_label]" value="<?php echo esc_attr($settings['review_content_label']); ?>" placeholder="<?php esc_attr_e('Content field label', 'reevuu-reviews'); ?>" />
                                    <label><input type="checkbox" name="settings[review_content_required]" value="1" <?php checked($settings['review_content_required'], 1); ?> /> <?php esc_html_e('Content is required', 'reevuu-reviews'); ?></label>
                                    <p class="description"><?php esc_html_e('The review title field has been removed from the public form. This label controls the main experience textarea shown after the rating questions.', 'reevuu-reviews'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Appearance', 'reevuu-reviews'); ?></th>
                                <td class="rrp-stack">
                                    <input type="text" class="regular-text" name="settings[style_font_family]" value="<?php echo esc_attr($settings['style_font_family']); ?>" placeholder="<?php esc_attr_e('inherit or e.g. Montserrat, sans-serif', 'reevuu-reviews'); ?>" />
                                    <p class="description"><?php esc_html_e('Use "inherit" to piggyback off your theme or Elementor typography. If Elementor already loads a Google Font, entering the same font family here will use it without ReevuuWP loading another copy.', 'reevuu-reviews'); ?></p>
                                    <div class="rrp-inline-inputs">
                                        <label><?php esc_html_e('Form background', 'reevuu-reviews'); ?> <input type="color" name="settings[style_form_background]" value="<?php echo esc_attr($settings['style_form_background']); ?>" /></label>
                                        <label><?php esc_html_e('Card background', 'reevuu-reviews'); ?> <input type="color" name="settings[style_card_background]" value="<?php echo esc_attr($settings['style_card_background']); ?>" /></label>
                                        <label><?php esc_html_e('Accent/button', 'reevuu-reviews'); ?> <input type="color" name="settings[style_accent_color]" value="<?php echo esc_attr($settings['style_accent_color']); ?>" /></label>
                                        <label><?php esc_html_e('Star colour', 'reevuu-reviews'); ?> <input type="color" name="settings[style_star_color]" value="<?php echo esc_attr($settings['style_star_color']); ?>" /></label>
                                        <label><?php esc_html_e('Text colour', 'reevuu-reviews'); ?> <input type="color" name="settings[style_text_color]" value="<?php echo esc_attr($settings['style_text_color']); ?>" /></label>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Image uploads', 'reevuu-reviews'); ?></th>
                                <td class="rrp-inline-inputs">
                                    <label><?php esc_html_e('Max images', 'reevuu-reviews'); ?> <input type="number" min="0" max="10" name="settings[max_images]" value="<?php echo esc_attr($settings['max_images']); ?>" /></label>
                                    <label><?php esc_html_e('Max image size (MB)', 'reevuu-reviews'); ?> <input type="number" min="1" max="15" name="settings[max_image_size_mb]" value="<?php echo esc_attr($settings['max_image_size_mb']); ?>" /></label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Frontend defaults', 'reevuu-reviews'); ?></th>
                                <td class="rrp-inline-inputs">
                                    <label><?php esc_html_e('List limit', 'reevuu-reviews'); ?> <input type="number" min="1" max="50" name="settings[default_list_limit]" value="<?php echo esc_attr($settings['default_list_limit']); ?>" /></label>
                                    <label><?php esc_html_e('Slider limit', 'reevuu-reviews'); ?> <input type="number" min="1" max="20" name="settings[default_slider_limit]" value="<?php echo esc_attr($settings['default_slider_limit']); ?>" /></label>
                                    <label><?php esc_html_e('Chip limit', 'reevuu-reviews'); ?> <input type="number" min="1" max="30" name="settings[default_chip_limit]" value="<?php echo esc_attr($settings['default_chip_limit']); ?>" /></label>
                                    <label><?php esc_html_e('Gallery images', 'reevuu-reviews'); ?> <input type="number" min="1" max="30" name="settings[gallery_image_limit]" value="<?php echo esc_attr($settings['gallery_image_limit']); ?>" /></label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Submission messages', 'reevuu-reviews'); ?></th>
                                <td class="rrp-stack">
                                    <input type="text" class="regular-text" name="settings[success_message]" value="<?php echo esc_attr($settings['success_message']); ?>" placeholder="<?php esc_attr_e('Success message', 'reevuu-reviews'); ?>" />
                                    <input type="text" class="regular-text" name="settings[pending_message]" value="<?php echo esc_attr($settings['pending_message']); ?>" placeholder="<?php esc_attr_e('Pending message', 'reevuu-reviews'); ?>" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Google review XML feed', 'reevuu-reviews'); ?></th>
                                <td class="rrp-stack">
                                    <label><input type="checkbox" name="settings[google_feed_enabled]" value="1" <?php checked($settings['google_feed_enabled'], 1); ?> /> <?php esc_html_e('Expose a public XML feed for approved reviews', 'reevuu-reviews'); ?></label>
                                    <input type="text" class="regular-text" name="settings[google_feed_slug]" value="<?php echo esc_attr($settings['google_feed_slug']); ?>" placeholder="<?php esc_attr_e('Feed slug', 'reevuu-reviews'); ?>" />
                                    <input type="text" class="regular-text" name="settings[brand_name]" value="<?php echo esc_attr($settings['brand_name']); ?>" placeholder="<?php esc_attr_e('Brand name', 'reevuu-reviews'); ?>" />
                                    <p class="description"><?php echo esc_html($feed_url); ?></p>
                                </td>
                            </tr>
                        </table>
                    </section>

                    <section class="rrp-admin-card">
                        <div class="rrp-admin-card-header">
                            <div>
                                <h2><?php esc_html_e('Question Builder', 'reevuu-reviews'); ?></h2>
                                <p><?php esc_html_e('Add, remove, reword, and reorder the extra rating/text questions shown on the public review form.', 'reevuu-reviews'); ?></p>
                            </div>
                            <button type="button" class="button button-secondary" id="rrp-add-question"><?php esc_html_e('Add question', 'reevuu-reviews'); ?></button>
                        </div>

                        <div id="rrp-question-list" class="rrp-question-list">
                            <?php foreach ($questions as $index => $question) : ?>
                                <?php $this->render_question_row($question, $index); ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>

                <template id="rrp-question-template">
                    <?php
                    $this->render_question_row(
                        array(
                            'question_key'      => '',
                            'label'             => '',
                            'type'              => 'text',
                            'help_text'         => '',
                            'placeholder'       => '',
                            'is_required'       => 0,
                            'is_primary_rating' => 0,
                            'is_active'         => 1,
                            'sort_order'        => 999,
                        ),
                        '__index__'
                    );
                    ?>
                </template>

                <?php submit_button(__('Save settings', 'reevuu-reviews')); ?>
            </form>
        </div>
        <?php
    }

    public function render_reviews_page()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $status_filter = sanitize_key($_GET['status'] ?? '');
        $search = sanitize_text_field($_GET['s'] ?? '');
        $counts = $this->repository->get_status_counts();
        $reviews = $this->repository->get_reviews(
            array(
                'status' => $status_filter ? $status_filter : array('approved', 'pending', 'rejected', 'spam'),
                'search' => $search,
                'limit'  => 50,
            )
        );
        ?>
        <div class="wrap rrp-admin-wrap">
            <h1><?php esc_html_e('Review Moderation', 'reevuu-reviews'); ?></h1>
            <?php $this->render_notice(); ?>

            <div class="rrp-status-tabs">
                <?php foreach ($counts as $status => $count) : ?>
                    <?php $url = add_query_arg(array('page' => 'rrp-reviews', 'status' => $status), admin_url('admin.php')); ?>
                    <a class="rrp-status-tab <?php echo $status_filter === $status ? 'is-active' : ''; ?>" href="<?php echo esc_url($url); ?>">
                        <?php echo esc_html(ucfirst($status)); ?> <span><?php echo esc_html((string) $count); ?></span>
                    </a>
                <?php endforeach; ?>
                <a class="rrp-status-tab <?php echo '' === $status_filter ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(array('page' => 'rrp-reviews'), admin_url('admin.php'))); ?>">
                    <?php esc_html_e('All', 'reevuu-reviews'); ?>
                </a>
            </div>

            <form class="rrp-search-form" method="get">
                <input type="hidden" name="page" value="rrp-reviews" />
                <?php if ($status_filter) : ?>
                    <input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>" />
                <?php endif; ?>
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search reviews', 'reevuu-reviews'); ?>" />
                <button type="submit" class="button"><?php esc_html_e('Search', 'reevuu-reviews'); ?></button>
            </form>

            <div class="rrp-review-table">
                <?php if (! $reviews) : ?>
                    <p><?php esc_html_e('No reviews found for this filter.', 'reevuu-reviews'); ?></p>
                <?php endif; ?>

                <?php foreach ($reviews as $review) : ?>
                    <article class="rrp-review-admin-card">
                        <header class="rrp-review-admin-header">
                            <div>
                                <h2><?php echo esc_html($review['reviewer_name']); ?></h2>
                                <p>
                                    <strong><?php echo esc_html(number_format_i18n((float) $review['overall_rating'], 1)); ?>/5</strong>
                                    <span class="rrp-pill status-<?php echo esc_attr($review['status']); ?>"><?php echo esc_html(ucfirst($review['status'])); ?></span>
                                    <?php if (! empty($review['is_verified'])) : ?>
                                        <span class="rrp-pill verified"><?php esc_html_e('Verified', 'reevuu-reviews'); ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="rrp-meta">
                                <span><?php echo esc_html($review['reviewer_email']); ?></span>
                                <span><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $review['created_at'])); ?></span>
                            </div>
                        </header>

                        <?php if (! empty($review['review_title'])) : ?>
                            <h3><?php echo esc_html($review['review_title']); ?></h3>
                        <?php endif; ?>
                        <?php if (! empty($review['review_content'])) : ?>
                            <p><?php echo esc_html($review['review_content']); ?></p>
                        <?php endif; ?>

                        <?php if (! empty($review['answers'])) : ?>
                            <dl class="rrp-answer-grid">
                                <?php foreach ($review['answers'] as $answer) : ?>
                                    <dt><?php echo esc_html($answer['question_label']); ?></dt>
                                    <dd>
                                        <?php if ('rating' === $answer['question_type']) : ?>
                                            <?php echo esc_html(number_format_i18n((float) $answer['rating_value'], 1)); ?>/5
                                        <?php else : ?>
                                            <?php echo esc_html($answer['text_value']); ?>
                                        <?php endif; ?>
                                    </dd>
                                <?php endforeach; ?>
                            </dl>
                        <?php endif; ?>

                        <?php if (! empty($review['media'])) : ?>
                            <div class="rrp-admin-media-strip">
                                <?php foreach ($review['media'] as $media) : ?>
                                    <?php if (! empty($media['thumb_url'])) : ?>
                                        <img src="<?php echo esc_url($media['thumb_url']); ?>" alt="" />
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <form class="rrp-review-action-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('rrp_review_action'); ?>
                            <input type="hidden" name="action" value="rrp_review_action" />
                            <input type="hidden" name="review_id" value="<?php echo esc_attr((string) $review['id']); ?>" />

                            <label>
                                <?php esc_html_e('Status', 'reevuu-reviews'); ?>
                                <select name="status">
                                    <option value="pending" <?php selected($review['status'], 'pending'); ?>><?php esc_html_e('Pending', 'reevuu-reviews'); ?></option>
                                    <option value="approved" <?php selected($review['status'], 'approved'); ?>><?php esc_html_e('Approved', 'reevuu-reviews'); ?></option>
                                    <option value="rejected" <?php selected($review['status'], 'rejected'); ?>><?php esc_html_e('Rejected', 'reevuu-reviews'); ?></option>
                                    <option value="spam" <?php selected($review['status'], 'spam'); ?>><?php esc_html_e('Spam', 'reevuu-reviews'); ?></option>
                                </select>
                            </label>

                            <label class="rrp-checkbox-inline">
                                <input type="checkbox" name="is_verified" value="1" <?php checked(! empty($review['is_verified'])); ?> />
                                <?php esc_html_e('Verified review', 'reevuu-reviews'); ?>
                            </label>

                            <button type="submit" class="button button-primary"><?php esc_html_e('Update review', 'reevuu-reviews'); ?></button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    private function render_notice()
    {
        $notice = sanitize_key($_GET['rrp_notice'] ?? '');
        if (! $notice) {
            return;
        }

        $message = '';
        if ('saved' === $notice) {
            $message = __('Review settings saved.', 'reevuu-reviews');
        } elseif ('review-updated' === $notice) {
            $message = __('Review updated.', 'reevuu-reviews');
        }

        if ($message) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }

    private function render_question_row($question, $index)
    {
        ?>
        <div class="rrp-question-row" data-index="<?php echo esc_attr((string) $index); ?>">
            <input type="hidden" name="questions[<?php echo esc_attr((string) $index); ?>][question_key]" value="<?php echo esc_attr($question['question_key']); ?>" class="rrp-question-key" />

            <div class="rrp-question-row-main">
                <label>
                    <span><?php esc_html_e('Label', 'reevuu-reviews'); ?></span>
                    <input type="text" name="questions[<?php echo esc_attr((string) $index); ?>][label]" value="<?php echo esc_attr($question['label']); ?>" />
                </label>

                <label>
                    <span><?php esc_html_e('Type', 'reevuu-reviews'); ?></span>
                    <select name="questions[<?php echo esc_attr((string) $index); ?>][type]">
                        <option value="rating" <?php selected($question['type'], 'rating'); ?>><?php esc_html_e('Rating', 'reevuu-reviews'); ?></option>
                        <option value="text" <?php selected($question['type'], 'text'); ?>><?php esc_html_e('Text', 'reevuu-reviews'); ?></option>
                        <option value="textarea" <?php selected($question['type'], 'textarea'); ?>><?php esc_html_e('Textarea', 'reevuu-reviews'); ?></option>
                    </select>
                </label>

                <label>
                    <span><?php esc_html_e('Sort order', 'reevuu-reviews'); ?></span>
                    <input type="number" name="questions[<?php echo esc_attr((string) $index); ?>][sort_order]" value="<?php echo esc_attr((string) $question['sort_order']); ?>" />
                </label>
            </div>

            <div class="rrp-question-row-secondary">
                <label>
                    <span><?php esc_html_e('Placeholder', 'reevuu-reviews'); ?></span>
                    <input type="text" name="questions[<?php echo esc_attr((string) $index); ?>][placeholder]" value="<?php echo esc_attr($question['placeholder']); ?>" />
                </label>
                <label>
                    <span><?php esc_html_e('Help text', 'reevuu-reviews'); ?></span>
                    <input type="text" name="questions[<?php echo esc_attr((string) $index); ?>][help_text]" value="<?php echo esc_attr($question['help_text']); ?>" />
                </label>
            </div>

            <div class="rrp-question-row-flags">
                <label><input type="checkbox" name="questions[<?php echo esc_attr((string) $index); ?>][is_required]" value="1" <?php checked(! empty($question['is_required'])); ?> /> <?php esc_html_e('Required', 'reevuu-reviews'); ?></label>
                <label><input type="checkbox" name="questions[<?php echo esc_attr((string) $index); ?>][is_active]" value="1" <?php checked(! empty($question['is_active'])); ?> /> <?php esc_html_e('Active', 'reevuu-reviews'); ?></label>
                <label><input type="radio" name="rrp_primary_rating_choice" value="<?php echo esc_attr((string) $index); ?>" <?php checked(! empty($question['is_primary_rating'])); ?> class="rrp-primary-rating-radio" /> <?php esc_html_e('Primary rating', 'reevuu-reviews'); ?></label>
                <input type="hidden" name="questions[<?php echo esc_attr((string) $index); ?>][is_primary_rating]" value="<?php echo ! empty($question['is_primary_rating']) ? '1' : '0'; ?>" class="rrp-primary-rating-hidden" />

                <div class="rrp-question-row-actions">
                    <button type="button" class="button-link rrp-move-question-up"><?php esc_html_e('Move up', 'reevuu-reviews'); ?></button>
                    <button type="button" class="button-link rrp-move-question-down"><?php esc_html_e('Move down', 'reevuu-reviews'); ?></button>
                    <button type="button" class="button-link-delete rrp-remove-question"><?php esc_html_e('Remove', 'reevuu-reviews'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }
}
