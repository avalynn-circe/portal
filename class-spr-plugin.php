<?php

if (! defined('ABSPATH')) {
    exit;
}

class SPR_Plugin {
    protected $settings;
    protected $fields;
    protected $progress;
    protected $google_sheets;

    public function __construct() {
        $this->settings      = new SPR_Settings();
        $this->fields        = new SPR_Fields($this->settings);
        $this->progress      = new SPR_Progress($this->settings);
        $this->google_sheets = new SPR_Google_Sheets($this->settings);
    }

    public function boot() {
        add_action('admin_menu', [$this->settings, 'register_menu']);
        add_action('admin_init', [$this->settings, 'register_settings']);

        add_action('add_meta_boxes', [$this->fields, 'register_meta_boxes']);
        add_action('save_post', [$this->fields, 'save_meta_boxes'], 10, 2);

        add_action('add_meta_boxes', [$this->progress, 'register_meta_box']);
        add_filter('manage_page_posts_columns', [$this->progress, 'add_admin_column']);
        add_action('manage_page_posts_custom_column', [$this->progress, 'render_admin_column'], 10, 2);
        add_filter('manage_speaker_posts_columns', [$this->progress, 'add_admin_column']);
        add_action('manage_speaker_posts_custom_column', [$this->progress, 'render_admin_column'], 10, 2);

        add_action('save_post', [$this->google_sheets, 'maybe_sync_post'], 30, 2);
        add_action('admin_post_spr_manual_sync', [$this->google_sheets, 'handle_manual_sync']);
    }
}
