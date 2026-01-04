<?php
if (!defined('ABSPATH')) exit;

class HMSC_Hub {

    public static function maybe_provision() {
        if (!is_admin()) return;
        if (!current_user_can('manage_woocommerce') && !current_user_can('shop_manager') && !current_user_can('manage_options')) {
            return;
        }

        $s = HMSC_Settings::get();

        // zaten provision edildi
        if (!empty($s['site_id']) && !empty($s['api_key'])) return;

        // shared_api_key yoksa provision yapamayız (şimdilik)
        if (empty($s['shared_api_key'])) return;

        // spam olmasın: 15 dakikada bir dene
        $last = isset($s['provisioned_at']) ? (int)$s['provisioned_at'] : 0;
        if ($last > 0 && (time() - $last) < 900) return;

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

        $body = array(
            'site_url'       => home_url('/'),
            'site_name'      => wp_strip_all_tags(get_bloginfo('name')),
            'shared_api_key' => (string)$s['shared_api_key'],
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

        $endpoint = $hub . '/wp-json/hmsh/v1/ticket';

        $payload = array(
            'site_id'        => !empty($s['site_id']) ? $s['site_id'] : (isset($data['site_id']) ? $data['site_id'] : ''),
            'site_url'       => home_url('/'),
            'site_name'      => wp_strip_all_tags(get_bloginfo('name')),
            'customer_email' => (string)($data['customer_email'] ?? ''),
            'customer_phone' => (string)($data['customer_phone'] ?? ''),
            'urgency'        => (string)($data['urgency'] ?? 'Normal'),
            'subject'        => (string)($data['subject'] ?? ''),
            'message'        => (string)($data['message'] ?? ''),
        );

        // auth: provisioned api_key varsa onu kullan, yoksa shared key
        $auth_key = !empty($s['api_key']) ? $s['api_key'] : (string)$s['shared_api_key'];

        $args = array(
            'timeout' => 20,
            'headers' => array(
                'Content-Type'    => 'application/json',
                'X-HMSH-API-Key'   => $auth_key,
            ),
            'body'    => wp_json_encode($payload),
        );

        $resp = wp_remote_post($endpoint, $args);
        if (is_wp_error($resp)) return $resp;

        $code = wp_remote_retrieve_response_code($resp);
        $raw  = wp_remote_retrieve_body($resp);
        $json = json_decode($raw, true);

        if ($code !== 200 || !is_array($json) || empty($json['ok'])) {
            $msg = is_array($json) && !empty($json['message']) ? $json['message'] : ('Ticket gönderilemedi. HTTP ' . $code);
            return new WP_Error('hmsc_ticket_failed', $msg);
        }

        return $json;
    }

    public static function test_connection() {
        $s   = HMSC_Settings::get();
        $hub = HMSC_Settings::hub_base_url();
        if (empty($hub)) return new WP_Error('hmsc_no_hub', 'Hub URL boş.');

        $endpoint = $hub . '/wp-json/hmsh/v1/ping';
        $auth_key = !empty($s['api_key']) ? $s['api_key'] : (string)$s['shared_api_key'];

        $args = array(
            'timeout' => 10,
            'headers' => array(
                'X-HMSH-API-Key' => $auth_key,
                'X-HM-Site'      => isset($s['site_id']) ? $s['site_id'] : '',
            ),
        );

        return wp_remote_get($endpoint, $args);
    }
}
