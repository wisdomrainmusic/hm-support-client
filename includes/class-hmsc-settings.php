<?php
if (!defined('ABSPATH')) exit;

class HMSC_Settings {
    const OPTION_KEY = 'hmsc_settings';

    public static function init() {
        // gerekirse burada register_setting yaparsın (admin sayfası varsa)
        add_action('admin_init', array(__CLASS__, 'register_settings'));
    }

    public static function defaults() {
        return array(
            'hub_url'        => 'https://hizlimagazapro.com',
            'site_id'        => '',
            'api_key'        => '',
            'shared_api_key' => '',
            'provisioned_at' => 0,
            'last_error'     => '',
        );
    }

    public static function get() {
        $d = self::defaults();
        $v = get_option(self::OPTION_KEY, array());
        if (!is_array($v)) $v = array();
        return array_merge($d, $v);
    }

    public static function update($patch) {
        $cur = self::get();
        if (!is_array($patch)) $patch = array();
        $new = array_merge($cur, $patch);
        update_option(self::OPTION_KEY, $new, false);
        return $new;
    }

    public static function register_settings() {
        register_setting('hmsc_settings_group', self::OPTION_KEY, array(
            'type'              => 'array',
            'sanitize_callback' => array(__CLASS__, 'sanitize'),
            'default'           => self::defaults(),
        ));
    }

    public static function sanitize($input) {
        $defaults = self::defaults();
        if (!is_array($input)) $input = array();

        $out = array_merge($defaults, $input);

        $out['hub_url']        = rtrim(esc_url_raw(trim((string) $out['hub_url'])), '/');
        $out['site_id']        = sanitize_text_field(trim((string) $out['site_id']));
        $out['api_key']        = sanitize_text_field(trim((string) $out['api_key']));
        $out['shared_api_key'] = sanitize_text_field(trim((string) $out['shared_api_key']));
        $out['provisioned_at'] = absint($out['provisioned_at']);
        $out['last_error']     = sanitize_text_field(trim((string) $out['last_error']));

        return $out;
    }

    public static function hub_base_url() {
        $s = self::get();
        $url = trim((string)$s['hub_url']);
        $url = rtrim($url, "/ \t\n\r\0\x0B");
        return $url;
    }

    public static function is_configured() {
        $settings = self::get();
        return !empty($settings['hub_url']) && !empty($settings['site_id']) && (!empty($settings['api_key']) || !empty($settings['shared_api_key']));
    }

    public static function is_provisioned() {
        $settings = self::get();
        return !empty($settings['site_id']) && !empty($settings['api_key']);
    }

    // Backwards compatibility for previous calls.
    public static function get_settings() {
        return self::get();
    }

    public static function update_settings($settings) {
        return self::update($settings);
    }
}
