<?php

if (! defined('ABSPATH')) {
    exit;
}

class RRP_Blocks
{
    private $public;

    public function __construct(RRP_Public $public)
    {
        $this->public = $public;
    }

    public function register()
    {
        add_action('init', array($this, 'register_blocks'));
    }

    public function register_blocks()
    {
        if (! function_exists('register_block_type')) {
            return;
        }

        wp_register_style('rrp-public', RRP_PLUGIN_URL . 'assets/css/public.css', array(), RRP_VERSION);

        wp_register_script(
            'rrp-blocks',
            RRP_PLUGIN_URL . 'assets/js/blocks.js',
            array('wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n'),
            RRP_VERSION,
            true
        );

        wp_localize_script(
            'rrp-blocks',
            'rrpBlocks',
            array(
                'defaultTargetId' => 0,
            )
        );

        $blocks = array(
            'form'    => __('Review Form', 'reevuu-reviews'),
            'summary' => __('Review Summary', 'reevuu-reviews'),
            'list'    => __('Review List', 'reevuu-reviews'),
            'slider'  => __('Review Slider', 'reevuu-reviews'),
            'chips'   => __('Review Chips', 'reevuu-reviews'),
            'gallery' => __('Review Gallery', 'reevuu-reviews'),
        );

        foreach ($blocks as $type => $title) {
            register_block_type(
                'rrp/' . $type,
                array(
                    'api_version'     => 2,
                    'editor_script'   => 'rrp-blocks',
                    'style'           => 'rrp-public',
                    'render_callback' => function ($attributes = array()) use ($type) {
                        return $this->public->render_block($type, $attributes);
                    },
                    'attributes'      => $this->get_block_attributes($type),
                )
            );
        }
    }

    private function get_block_attributes($type)
    {
        $attributes = array(
            'target_id' => array(
                'type'    => 'number',
                'default' => 0,
            ),
        );

        if (in_array($type, array('list', 'slider', 'chips', 'gallery'), true)) {
            $attributes['limit'] = array(
                'type'    => 'number',
                'default' => 0,
            );
        }

        if ('list' === $type) {
            $attributes['show_search'] = array(
                'type'    => 'string',
                'default' => '1',
            );
            $attributes['show_sort'] = array(
                'type'    => 'string',
                'default' => '1',
            );
        }

        return $attributes;
    }
}
