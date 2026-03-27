<?php
/**
 * Plugin Name: ReevuuWP
 * Description: A standalone customer reviews platform for WordPress with moderation, media uploads, summaries, shortcodes, blocks, and XML feed output.
 * Version: 1.1.0
 * Author: WebRankers
 * Text Domain: reevuu-reviews
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */

if (! defined('ABSPATH')) {
    exit;
}

define('RRP_VERSION', '1.1.0');
define('RRP_PLUGIN_FILE', __FILE__);
define('RRP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RRP_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once RRP_PLUGIN_DIR . 'includes/class-rrp-installer.php';
require_once RRP_PLUGIN_DIR . 'includes/class-rrp-settings.php';
require_once RRP_PLUGIN_DIR . 'includes/class-rrp-repository.php';
require_once RRP_PLUGIN_DIR . 'includes/class-rrp-turnstile.php';
require_once RRP_PLUGIN_DIR . 'admin/class-rrp-admin.php';
require_once RRP_PLUGIN_DIR . 'public/class-rrp-public.php';
require_once RRP_PLUGIN_DIR . 'blocks/class-rrp-blocks.php';
require_once RRP_PLUGIN_DIR . 'includes/class-rrp-plugin.php';

register_activation_hook(__FILE__, array('RRP_Installer', 'activate'));

function rrp_plugin()
{
    static $plugin = null;

    if (null === $plugin) {
        $plugin = new RRP_Plugin();
    }

    return $plugin;
}

rrp_plugin()->run();
