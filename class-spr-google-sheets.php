<?php

if (! defined('ABSPATH')) {
    exit;
}

class SPR_Google_Sheets {
    protected $settings;

    public function __construct($settings) {
        $this->settings = $settings;
    }

    public function maybe_sync_post($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        if (! in_array($post->post_type, SPR_Utils::post_types(), true)) {
            return;
        }

        if (! SPR_Utils::current_user_can_edit($post_id)) {
            return;
        }

        $this->sync_post_to_sheet($post_id);
    }

    public function handle_manual_sync() {
        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;

        if (! $post_id) {
            wp_die('Missing post ID.');
        }

        check_admin_referer('spr_manual_sync_' . $post_id);

        if (! current_user_can('edit_post', $post_id)) {
            wp_die('Not allowed.');
        }

        $result = $this->sync_post_to_sheet($post_id);
        $status = is_wp_error($result) ? 'error' : 'success';
        $msg    = is_wp_error($result) ? rawurlencode($result->get_error_message()) : rawurlencode('Sheet sync completed.');

        wp_safe_redirect(add_query_arg([
            'post'             => $post_id,
            'action'           => 'edit',
            'spr_sync_status'  => $status,
            'spr_sync_message' => $msg,
        ], admin_url('post.php')));
        exit;
    }

    public function sync_post_to_sheet($post_id) {
        $sheet_id = trim((string) get_option('spr_google_sheet_id', ''));
        $sheet_tab = trim((string) get_option('spr_google_sheet_tab', 'Speakers'));

        if ($sheet_id === '') {
            return new WP_Error('spr_missing_sheet_id', 'Google Sheet ID is missing.');
        }

        $token = $this->get_access_token();

        if (is_wp_error($token)) {
            return $token;
        }

        $payload = SPR_Utils::get_post_payload($post_id);
        $headers = array_keys($payload);
        $row     = array_values($payload);

        $sheet_values = $this->get_sheet_values($sheet_id, $sheet_tab, $token);

        if (is_wp_error($sheet_values)) {
            return $sheet_values;
        }

        $existing_headers = [];
        $existing_rows    = [];

        if (! empty($sheet_values['values']) && is_array($sheet_values['values'])) {
            $existing_headers = $sheet_values['values'][0] ?? [];
            $existing_rows    = array_slice($sheet_values['values'], 1);
        }

        if (empty($existing_headers)) {
            $header_result = $this->update_range($sheet_id, $sheet_tab . '!A1', [$headers], $token);
            if (is_wp_error($header_result)) {
                return $header_result;
            }
            $existing_headers = $headers;
        }

        if ($existing_headers !== $headers) {
            $header_result = $this->update_range($sheet_id, $sheet_tab . '!A1', [$headers], $token);
            if (is_wp_error($header_result)) {
                return $header_result;
            }
        }

        $row_index = $this->find_row_index_by_page_id($existing_rows, $headers, (string) $post_id);

        if ($row_index !== null) {
            $sheet_row_number = $row_index + 2;
            return $this->update_range($sheet_id, $sheet_tab . '!A' . $sheet_row_number, [$row], $token);
        }

        return $this->append_row($sheet_id, $sheet_tab, [$row], $token);
    }

    protected function find_row_index_by_page_id($rows, $headers, $page_id) {
        $page_id_col = array_search('page_id', $headers, true);

        if ($page_id_col === false) {
            return null;
        }

        foreach ($rows as $index => $row) {
            if ((string) ($row[$page_id_col] ?? '') === $page_id) {
                return $index;
            }
        }

        return null;
    }

    protected function get_access_token() {
        $json = trim((string) get_option('spr_google_service_account_json', ''));

        if ($json === '') {
            return new WP_Error('spr_missing_credentials', 'Google service account JSON is missing.');
        }

        $creds = json_decode($json, true);

        if (! is_array($creds)) {
            return new WP_Error('spr_invalid_credentials', 'Google service account JSON could not be decoded.');
        }

        $client_email = $creds['client_email'] ?? '';
        $private_key  = $creds['private_key'] ?? '';
        $token_uri    = $creds['token_uri'] ?? 'https://oauth2.googleapis.com/token';

        if ($client_email === '' || $private_key === '') {
            return new WP_Error('spr_incomplete_credentials', 'Google service account credentials are incomplete.');
        }

        $now = time();
        $jwt_header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $jwt_claims = [
            'iss'   => $client_email,
            'scope' => 'https://www.googleapis.com/auth/spreadsheets',
            'aud'   => $token_uri,
            'exp'   => $now + 3600,
            'iat'   => $now,
        ];

        $jwt_unsigned = $this->base64url_encode(wp_json_encode($jwt_header)) . '.' . $this->base64url_encode(wp_json_encode($jwt_claims));

        $signature = '';
        $sign_ok   = openssl_sign($jwt_unsigned, $signature, $private_key, 'sha256WithRSAEncryption');

        if (! $sign_ok) {
            return new WP_Error('spr_jwt_sign_failed', 'Could not sign Google JWT. Make sure OpenSSL is available and the private key is valid.');
        }

        $jwt = $jwt_unsigned . '.' . $this->base64url_encode($signature);

        $response = wp_remote_post($token_uri, [
            'timeout' => 20,
            'body'    => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code < 200 || $code >= 300) {
            return new WP_Error('spr_token_request_failed', 'Google token request failed: ' . wp_json_encode($body));
        }

        if (empty($body['access_token'])) {
            return new WP_Error('spr_missing_access_token', 'Google did not return an access token.');
        }

        return $body['access_token'];
    }

    protected function get_sheet_values($sheet_id, $sheet_tab, $token) {
        $url = sprintf(
            'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s',
            rawurlencode($sheet_id),
            rawurlencode($sheet_tab)
        );

        $response = wp_remote_get($url, [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 404) {
            return ['values' => []];
        }

        if ($code < 200 || $code >= 300) {
            return new WP_Error('spr_read_sheet_failed', 'Could not read Google Sheet values: ' . wp_json_encode($body));
        }

        return is_array($body) ? $body : ['values' => []];
    }

    protected function update_range($sheet_id, $range, $values, $token) {
        $url = sprintf(
            'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s?valueInputOption=USER_ENTERED',
            rawurlencode($sheet_id),
            rawurlencode($range)
        );

        $response = wp_remote_request($url, [
            'method'  => 'PUT',
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'range'  => $range,
                'majorDimension' => 'ROWS',
                'values' => $values,
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code < 200 || $code >= 300) {
            return new WP_Error('spr_update_sheet_failed', 'Could not update Google Sheet range: ' . wp_json_encode($body));
        }

        return $body;
    }

    protected function append_row($sheet_id, $sheet_tab, $values, $token) {
        $url = sprintf(
            'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s:append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS',
            rawurlencode($sheet_id),
            rawurlencode($sheet_tab)
        );

        $response = wp_remote_post($url, [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'majorDimension' => 'ROWS',
                'values'         => $values,
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code < 200 || $code >= 300) {
            return new WP_Error('spr_append_sheet_failed', 'Could not append row to Google Sheet: ' . wp_json_encode($body));
        }

        return $body;
    }

    protected function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
