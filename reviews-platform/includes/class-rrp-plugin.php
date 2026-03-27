<?php

if (! defined('ABSPATH')) {
    exit;
}

class RRP_Plugin
{
    private $settings;
    private $repository;
    private $admin;
    private $public;
    private $blocks;

    public function __construct()
    {
        $this->settings = new RRP_Settings();
        $this->repository = new RRP_Repository($this->settings);
        $this->admin = new RRP_Admin($this->settings, $this->repository);
        $this->public = new RRP_Public($this->settings, $this->repository);
        $this->blocks = new RRP_Blocks($this->public);
    }

    public function run()
    {
        add_action('init', array($this, 'load_textdomain'));

        $this->admin->register();
        $this->public->register();
        $this->blocks->register();
    }

    public function load_textdomain()
    {
        load_plugin_textdomain('reevuu-reviews', false, dirname(plugin_basename(RRP_PLUGIN_FILE)) . '/languages');
    }
}
