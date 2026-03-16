<?php
/**
 * Plugin Name: Speaker Portal Reconstruction
 * Description: Reconstructed portal plugin for speaker profile management, completion tracking, and Google Sheets sync.
 * Version: 0.1.0
 * Author: OpenAI
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('SPR_PLUGIN_FILE')) {
    define('SPR_PLUGIN_FILE', __FILE__);
}

if (! defined('SPR_PLUGIN_DIR')) {
    define('SPR_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (! defined('SPR_PLUGIN_URL')) {
    define('SPR_PLUGIN_URL', plugin_dir_url(__FILE__));
}

require_once SPR_PLUGIN_DIR . 'includes/class-spr-utils.php';
require_once SPR_PLUGIN_DIR . 'includes/class-spr-settings.php';
require_once SPR_PLUGIN_DIR . 'includes/class-spr-fields.php';
require_once SPR_PLUGIN_DIR . 'includes/class-spr-progress.php';
require_once SPR_PLUGIN_DIR . 'includes/class-spr-google-sheets.php';
require_once SPR_PLUGIN_DIR . 'includes/class-spr-plugin.php';

function spr_boot_plugin() {
    $plugin = new SPR_Plugin();
    $plugin->boot();
}

spr_boot_plugin();
