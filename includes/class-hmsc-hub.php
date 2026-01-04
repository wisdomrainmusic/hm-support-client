<?php
if (!defined('ABSPATH')) exit;

class HMSC_Hub {

    public static function get_hub_url() {
        $opt = get_option('hmsc_settings', array());
        $url = '';

        if (is_array($opt) && !empty($opt['hub_url'])) {
            $url = trim((string) $opt['hub_url']);
        }

        return $url;
    }

    public static function maybe_provision($force = false) {
        if (!is_admin()) return;
        if (!current_user_can('manage_woocommerce') && !current_user_can('shop_manager') && !current_user_can('manage_options')) {
            return;
        }

        $s = HMSC_Settings::get();

        // zaten provision edildi
        if (!empty($s['site_id']) && !empty($s['api_key'])) return;

        $shared = !empty($s['shared_api_key']) ? $s['shared_api_key'] : (defined('HMSC_DEFAULT_SHARED_KEY') ? HMSC_DEFAULT_SHARED_KEY : '');
        if (empty($shared)) return;

        // spam olmasın: 15 dakikada bir dene (force true ise bypass)
        $last = isset($s['provisioned_at']) ? (int)$s['provisioned_at'] : 0;
        if (!$force && $last > 0 && (time() - $last) < 900) return;

        $res = self::register_to_hub();
        if (is_wp_error($res)) {
            HMSC_Settings::update(array(
                'provisioned_at' => time(),
                'last_error' => $res->get_error_message(),
            ));
            return;
        }

        HMSC_Settings::update(array(
            'site_id' => $res['site_id'],
            'api_key' => $res['api_key'],
            'provisioned_at' => time(),
            'last_error' => '',
        ));
    }

    public static function register_to_hub() {
        $s = HMSC_Settings::get();
        $hub = HMSC_Settings::hub_base_url();
        if (empty($hub)) return new WP_Error('hmsc_no_hub', 'Hub URL boş.');

        $endpoint = $hub . '/wp-json/hmsh/v1/register';

        $shared = !empty($s['shared_api_key']) ? $s['shared_api_key'] : (defined('HMSC_DEFAULT_SHARED_KEY') ? HMSC_DEFAULT_SHARED_KEY : '');

        $body = array(
            'site_url'       => untrailingslashit(site_url('/')),
            'site_name'      => wp_strip_all_tags(get_bloginfo('name')),
            'shared_api_key' => (string)$shared,
        );

        $args = array(
            'timeout' => 15,
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => wp_json_encode($body),
        );

        $resp = wp_remote_post($endpoint, $args);
        if (is_wp_error($resp)) return $resp;

        $code = wp_remote_retrieve_response_code($resp);
        $raw  = wp_remote_retrieve_body($resp);
        $json = json_decode($raw, true);

        if ($code !== 200 || !is_array($json) || empty($json['ok'])) {
            $msg = is_array($json) && !empty($json['message']) ? $json['message'] : ('Hub register başarısız. HTTP ' . $code);
            return new WP_Error('hmsc_register_failed', $msg);
        }

        if (empty($json['site_id']) || empty($json['api_key'])) {
            return new WP_Error('hmsc_register_invalid', 'Hub yanıtı eksik (site_id/api_key).');
        }

        return array(
            'site_id' => sanitize_text_field($json['site_id']),
            'api_key' => sanitize_text_field($json['api_key']),
        );
    }

    public static function send_ticket($data) {
        $s   = HMSC_Settings::get();
        $hub = HMSC_Settings::hub_base_url();
        if (empty($hub)) return new WP_Error('hmsc_no_hub', 'Hub URL boş.');

        $endpoint = $hub . '/wp-json/hmsh/v1/tickets';

        $payload = array(
            'site_id'        => !empty($s['site_id']) ? $s['site_id'] : (isset($data['site_id']) ? $data['site_id'] : ''),
            'site_url'       => home_url('/'),
            'site_name'      => wp_strip_all_tags(get_bloginfo('name')),
            'client_site_url' => isset($data['client_site_url']) ? (string) $data['client_site_url'] : home_url('/'),
            'client_site_name' => isset($data['client_site_name']) ? (string) $data['client_site_name'] : wp_strip_all_tags(get_bloginfo('name')),
            'customer_email' => (string)($data['customer_email'] ?? ''),
            'customer_phone' => (string)($data['customer_phone'] ?? ''),
            'urgency'        => (string)($data['urgency'] ?? 'Normal'),
            'subject'        => (string)($data['subject'] ?? ''),
            'message'        => (string)($data['message'] ?? ''),
            'submitted_by'   => (string)($data['submitted_by'] ?? ''),
            'submitted_by_email' => (string)($data['submitted_by_email'] ?? ''),
        );

        // bazı hub’lar site_id’yi body’den okuyor
        if (is_array($data) && empty($payload['site_id']) && !empty($s['site_id'])) {
            $payload['site_id'] = (string) $s['site_id'];
        }

        // auth: provisioned api_key varsa onu kullan, yoksa shared key
        $auth_key = !empty($s['api_key']) ? $s['api_key'] : (string)$s['shared_api_key'];

        $args = array(
            'timeout' => 20,
            'headers' => array(
                'Content-Type'    => 'application/json',
                // new + legacy compatibility
                'X-HMSH-API-Key'   => $auth_key,
                'X-HM-Key'         => $auth_key,
                'X-HM-Site'        => isset($s['site_id']) ? (string)$s['site_id'] : '',
            ),
            'body'    => wp_json_encode($payload),
        );

        $resp = wp_remote_post($endpoint, $args);
        if (is_wp_error($resp)) return $resp;

        $code = wp_remote_retrieve_response_code($resp);
        $raw  = wp_remote_retrieve_body($resp);
        $json = json_decode($raw, true);

        $ok_flag = false;
        if (is_array($json)) {
            $ok_flag = (!empty($json['ok']) || !empty($json['success']));
        }

        if ($code < 200 || $code >= 300 || !is_array($json) || !$ok_flag) {
            $endpoint_path = wp_parse_url($endpoint, PHP_URL_PATH);
            $body_hint = '';

            if (is_array($json) && !empty($json['message'])) {
                $body_hint = sanitize_text_field($json['message']);
            } elseif (!empty($raw)) {
                $snippet = wp_strip_all_tags($raw);
                $body_hint = mb_substr($snippet, 0, 200);
            }

            $msg = sprintf('Ticket gönderilemedi (POST %s - HTTP %d)', $endpoint_path ?: $endpoint, (int) $code);
            if (!empty($body_hint)) {
                $msg .= ': ' . $body_hint;
            }

            return new WP_Error('hmsc_ticket_failed', $msg);
        }

        return $json;
    }

    public static function test_connection() {
        $hub_url = self::get_hub_url();
        if (empty($hub_url)) {
            return new WP_Error('hmsc_no_hub_url', 'Hub URL is not set.');
        }

        $url = rtrim($hub_url, '/') . '/wp-json/hmsh/v1/ping';

        $args = array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/json',
            ),
        );

        $res = wp_remote_get($url, $args);

        if (is_wp_error($res)) {
            return $res;
        }

        $code = (int) wp_remote_retrieve_response_code($res);
        $body = (string) wp_remote_retrieve_body($res);

        $json = null;
        if ($body !== '') {
            $json = json_decode($body, true);
        }

        $ok = ($code >= 200 && $code < 300) && is_array($json) && (!empty($json['ok']) || !empty($json['success']));

        if (!$ok) {
            $snippet = substr(trim($body), 0, 300);
            return new WP_Error(
                'hmsc_test_failed',
                'Hub ping failed.',
                array(
                    'http_code' => $code,
                    'response'  => $json,
                    'body_snippet' => $snippet,
                    'url' => $url,
                )
            );
        }

        return array(
            'ok' => true,
            'http_code' => $code,
            'response' => $json,
            'url' => $url,
        );
    }
}
