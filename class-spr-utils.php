<?php

if (! defined('ABSPATH')) {
    exit;
}

class SPR_Utils {
    public static function field_schema() {
        $fields = [
            'first_name'      => ['label' => 'First Name', 'type' => 'text', 'required' => true],
            'last_name'       => ['label' => 'Last Name', 'type' => 'text', 'required' => true],
            'email'           => ['label' => 'Email', 'type' => 'email', 'required' => true],
            'phone'           => ['label' => 'Phone', 'type' => 'text'],
            'company'         => ['label' => 'Company', 'type' => 'text'],
            'job_title'       => ['label' => 'Job Title', 'type' => 'text'],
            'session_title'   => ['label' => 'Session Title', 'type' => 'text', 'required' => true],
            'session_summary' => ['label' => 'Session Summary', 'type' => 'textarea', 'required' => true],
            'speaker_bio'     => ['label' => 'Speaker Bio', 'type' => 'textarea', 'required' => true],
            'co_presenters'   => ['label' => 'Co-presenters', 'type' => 'text'],
            'website'         => ['label' => 'Website', 'type' => 'url'],
            'linkedin'        => ['label' => 'LinkedIn', 'type' => 'url'],
            'headshot_id'     => ['label' => 'Headshot', 'type' => 'media', 'required' => true],
            'slides_id'       => ['label' => 'Slide Deck', 'type' => 'media'],
            'agreement'       => ['label' => 'Speaker Agreement Accepted', 'type' => 'checkbox', 'required' => true],
            'internal_notes'  => ['label' => 'Internal Notes', 'type' => 'textarea'],
        ];

        return apply_filters('spr_field_schema', $fields);
    }

    public static function post_types() {
        $post_types = get_option('spr_post_types', ['page', 'speaker']);

        if (! is_array($post_types) || empty($post_types)) {
            $post_types = ['page'];
        }

        return apply_filters('spr_post_types', $post_types);
    }

    public static function attachment_data($attachment_id) {
        $attachment_id = absint($attachment_id);

        if (! $attachment_id) {
            return [
                'id'       => '',
                'url'      => '',
                'filename' => '',
            ];
        }

        $url      = wp_get_attachment_url($attachment_id);
        $filename = wp_basename(get_attached_file($attachment_id));

        return [
            'id'       => $attachment_id,
            'url'      => $url ?: '',
            'filename' => $filename ?: '',
        ];
    }

    public static function normalize_meta_value($value, $type = 'text') {
        if (is_array($value)) {
            $flat = array_filter(array_map('trim', array_map('strval', $value)));
            return implode(', ', $flat);
        }

        if ($type === 'checkbox') {
            return empty($value) ? '0' : '1';
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    public static function get_post_payload($post_id) {
        $post    = get_post($post_id);
        $schema  = self::field_schema();
        $payload = [
            'page_id'    => (string) $post_id,
            'post_title' => $post ? $post->post_title : '',
            'post_type'  => $post ? $post->post_type : '',
            'edit_url'   => get_edit_post_link($post_id, ''),
            'view_url'   => get_permalink($post_id),
        ];

        foreach ($schema as $meta_key => $field) {
            $raw_value = get_post_meta($post_id, $meta_key, true);

            if (($field['type'] ?? 'text') === 'media') {
                $file = self::attachment_data($raw_value);
                $payload[$meta_key]               = (string) $file['id'];
                $payload[$meta_key . '_url']      = $file['url'];
                $payload[$meta_key . '_filename'] = $file['filename'];
                continue;
            }

            $payload[$meta_key] = self::normalize_meta_value($raw_value, $field['type'] ?? 'text');
        }

        $payload['completion_percentage'] = (string) self::calculate_completion_percentage($post_id);

        return apply_filters('spr_post_payload', $payload, $post_id);
    }

    public static function calculate_completion_percentage($post_id) {
        $schema        = self::field_schema();
        $required_keys = [];
        $completed     = 0;

        foreach ($schema as $meta_key => $field) {
            if (! empty($field['required'])) {
                $required_keys[] = $meta_key;
            }
        }

        if (empty($required_keys)) {
            return 100;
        }

        foreach ($required_keys as $meta_key) {
            $field = $schema[$meta_key];
            $value = get_post_meta($post_id, $meta_key, true);

            if (($field['type'] ?? 'text') === 'checkbox') {
                if (! empty($value)) {
                    $completed++;
                }
                continue;
            }

            if (($field['type'] ?? 'text') === 'media') {
                if (absint($value) > 0) {
                    $completed++;
                }
                continue;
            }

            if (is_array($value)) {
                $value = implode('', $value);
            }

            if (strlen(trim((string) $value)) > 0) {
                $completed++;
            }
        }

        return (int) round(($completed / count($required_keys)) * 100);
    }

    public static function esc_textarea_for_storage($value) {
        return trim(wp_kses_post($value));
    }

    public static function current_user_can_edit($post_id) {
        return current_user_can('edit_post', $post_id);
    }
}
