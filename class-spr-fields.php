<?php

if (! defined('ABSPATH')) {
    exit;
}

class SPR_Fields {
    protected $settings;

    public function __construct($settings) {
        $this->settings = $settings;
    }

    public function register_meta_boxes() {
        foreach (SPR_Utils::post_types() as $post_type) {
            add_meta_box(
                'spr_speaker_profile',
                'Speaker Portal Fields',
                [$this, 'render_meta_box'],
                $post_type,
                'normal',
                'default'
            );
        }
    }

    public function render_meta_box($post) {
        wp_nonce_field('spr_save_fields', 'spr_fields_nonce');
        $schema = SPR_Utils::field_schema();
        ?>
        <table class="form-table" role="presentation">
            <tbody>
                <?php foreach ($schema as $meta_key => $field) :
                    $type  = $field['type'] ?? 'text';
                    $label = $field['label'] ?? $meta_key;
                    $value = get_post_meta($post->ID, $meta_key, true);
                    ?>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr($meta_key); ?>"><?php echo esc_html($label); ?></label></th>
                        <td>
                            <?php if ($type === 'textarea') : ?>
                                <textarea class="large-text" rows="5" id="<?php echo esc_attr($meta_key); ?>" name="spr_meta[<?php echo esc_attr($meta_key); ?>]"><?php echo esc_textarea($value); ?></textarea>
                            <?php elseif ($type === 'checkbox') : ?>
                                <label>
                                    <input type="checkbox" id="<?php echo esc_attr($meta_key); ?>" name="spr_meta[<?php echo esc_attr($meta_key); ?>]" value="1" <?php checked(! empty($value)); ?>>
                                    Yes
                                </label>
                            <?php elseif ($type === 'media') : ?>
                                <input type="number" class="small-text" id="<?php echo esc_attr($meta_key); ?>" name="spr_meta[<?php echo esc_attr($meta_key); ?>]" value="<?php echo esc_attr($value); ?>">
                                <?php $attachment = SPR_Utils::attachment_data($value); ?>
                                <?php if (! empty($attachment['filename'])) : ?>
                                    <p class="description">Current file: <?php echo esc_html($attachment['filename']); ?></p>
                                <?php else : ?>
                                    <p class="description">Enter a WordPress attachment ID. This reconstruction does not include the media picker UI.</p>
                                <?php endif; ?>
                            <?php else : ?>
                                <input type="<?php echo esc_attr($type); ?>" class="regular-text" id="<?php echo esc_attr($meta_key); ?>" name="spr_meta[<?php echo esc_attr($meta_key); ?>]" value="<?php echo esc_attr($value); ?>">
                            <?php endif; ?>
                            <?php if (! empty($field['required'])) : ?>
                                <p class="description">Required field</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    public function save_meta_boxes($post_id, $post) {
        if (! isset($_POST['spr_fields_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spr_fields_nonce'])), 'spr_save_fields')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (! in_array($post->post_type, SPR_Utils::post_types(), true)) {
            return;
        }

        if (! SPR_Utils::current_user_can_edit($post_id)) {
            return;
        }

        $schema = SPR_Utils::field_schema();
        $input  = isset($_POST['spr_meta']) ? (array) wp_unslash($_POST['spr_meta']) : [];

        foreach ($schema as $meta_key => $field) {
            $type = $field['type'] ?? 'text';

            if ($type === 'checkbox') {
                update_post_meta($post_id, $meta_key, empty($input[$meta_key]) ? '0' : '1');
                continue;
            }

            if (! array_key_exists($meta_key, $input)) {
                continue;
            }

            $value = $input[$meta_key];

            switch ($type) {
                case 'email':
                    $value = sanitize_email($value);
                    break;
                case 'url':
                    $value = esc_url_raw($value);
                    break;
                case 'textarea':
                    $value = SPR_Utils::esc_textarea_for_storage($value);
                    break;
                case 'media':
                    $value = absint($value);
                    break;
                default:
                    $value = sanitize_text_field($value);
                    break;
            }

            update_post_meta($post_id, $meta_key, $value);
        }
    }
}
