<?php

if (! defined('ABSPATH')) {
    exit;
}

class SPR_Progress {
    protected $settings;

    public function __construct($settings) {
        $this->settings = $settings;
    }

    public function register_meta_box() {
        foreach (SPR_Utils::post_types() as $post_type) {
            add_meta_box(
                'spr_profile_progress',
                'Portal Completion',
                [$this, 'render_meta_box'],
                $post_type,
                'side',
                'high'
            );
        }
    }

    public function render_meta_box($post) {
        $percentage = SPR_Utils::calculate_completion_percentage($post->ID);
        $color      = $percentage >= 100 ? '#1d7a1d' : ($percentage >= 60 ? '#b7791f' : '#b42318');
        $manual_url = wp_nonce_url(admin_url('admin-post.php?action=spr_manual_sync&post_id=' . $post->ID), 'spr_manual_sync_' . $post->ID);
        ?>
        <div style="font-size:16px; font-weight:600; margin-bottom:8px;">
            <?php echo esc_html($percentage); ?>% complete
        </div>
        <div style="height:12px; background:#e5e7eb; border-radius:999px; overflow:hidden; margin-bottom:12px;">
            <div style="width:<?php echo esc_attr($percentage); ?>%; background:<?php echo esc_attr($color); ?>; height:100%;"></div>
        </div>
        <p style="margin-bottom:12px;">Calculated from required fields only.</p>
        <p>
            <a class="button button-secondary" href="<?php echo esc_url($manual_url); ?>">Manual Google Sheet Sync</a>
        </p>
        <?php
    }

    public function add_admin_column($columns) {
        $columns['spr_completion'] = 'Portal Complete';
        return $columns;
    }

    public function render_admin_column($column, $post_id) {
        if ($column !== 'spr_completion') {
            return;
        }

        echo esc_html(SPR_Utils::calculate_completion_percentage($post_id) . '%');
    }
}
