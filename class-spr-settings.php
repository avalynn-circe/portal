<?php

if (! defined('ABSPATH')) {
    exit;
}

class SPR_Settings {
    public function register_menu() {
        add_options_page(
            'Speaker Portal Settings',
            'Speaker Portal',
            'manage_options',
            'spr-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting('spr_settings_group', 'spr_google_service_account_json', [
            'sanitize_callback' => [$this, 'sanitize_json'],
        ]);

        register_setting('spr_settings_group', 'spr_google_sheet_id', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_setting('spr_settings_group', 'spr_google_sheet_tab', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_setting('spr_settings_group', 'spr_post_types', [
            'sanitize_callback' => [$this, 'sanitize_post_types'],
        ]);
    }

    public function sanitize_json($value) {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            add_settings_error('spr_google_service_account_json', 'invalid_json', 'Google service account JSON is not valid JSON.');
            return get_option('spr_google_service_account_json', '');
        }

        return $value;
    }

    public function sanitize_post_types($value) {
        $value = is_array($value) ? $value : [];
        $value = array_values(array_filter(array_map('sanitize_key', $value)));
        return empty($value) ? ['page'] : $value;
    }

    public function render_settings_page() {
        if (! current_user_can('manage_options')) {
            return;
        }

        $available_post_types = get_post_types(['public' => true], 'objects');
        $selected_post_types  = SPR_Utils::post_types();
        ?>
        <div class="wrap">
            <h1>Speaker Portal Settings</h1>
            <p>This is a reconstruction plugin. Review field names and sync settings before using it in production.</p>

            <form method="post" action="options.php">
                <?php settings_fields('spr_settings_group'); ?>
                <?php do_settings_sections('spr_settings_group'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="spr_google_sheet_id">Google Sheet ID</label></th>
                        <td>
                            <input type="text" class="regular-text" id="spr_google_sheet_id" name="spr_google_sheet_id" value="<?php echo esc_attr(get_option('spr_google_sheet_id', '')); ?>">
                            <p class="description">The spreadsheet ID from the Google Sheets URL.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="spr_google_sheet_tab">Sheet Tab</label></th>
                        <td>
                            <input type="text" class="regular-text" id="spr_google_sheet_tab" name="spr_google_sheet_tab" value="<?php echo esc_attr(get_option('spr_google_sheet_tab', 'Speakers')); ?>">
                            <p class="description">Example: Speakers</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="spr_google_service_account_json">Service Account JSON</label></th>
                        <td>
                            <textarea id="spr_google_service_account_json" name="spr_google_service_account_json" rows="14" class="large-text code"><?php echo esc_textarea(get_option('spr_google_service_account_json', '')); ?></textarea>
                            <p class="description">Paste the full Google service account credentials JSON. The service account must have access to the target spreadsheet.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Portal Post Types</th>
                        <td>
                            <?php foreach ($available_post_types as $post_type) : ?>
                                <label style="display:block; margin-bottom:6px;">
                                    <input type="checkbox" name="spr_post_types[]" value="<?php echo esc_attr($post_type->name); ?>" <?php checked(in_array($post_type->name, $selected_post_types, true)); ?>>
                                    <?php echo esc_html($post_type->labels->singular_name . ' (' . $post_type->name . ')'); ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
