<?php
if (!defined('ABSPATH')) exit;

class HMSC_Settings {
    const OPTION_KEY = 'hmsc_settings';

    public static function init() {
        add_action('admin_init', array(__CLASS__, 'register_settings'));
    }

    public static function defaults() {
        return array(
            'hub_url'  => 'https://hizlimagazapro.com',
            'site_id'  => '',
            'api_key'  => '',
        );
    }

    public static function get_settings() {
        $saved = get_option(self::OPTION_KEY, array());
        return wp_parse_args($saved, self::defaults());
    }

    public static function register_settings() {
        register_setting('hmsc_settings_group', self::OPTION_KEY, array(
            'type'              => 'array',
            'sanitize_callback' => array(__CLASS__, 'sanitize'),
            'default'           => self::defaults(),
        ));
    }

    public static function sanitize($input) {
        $out = self::defaults();

        $out['hub_url'] = isset($input['hub_url']) ? esc_url_raw(trim($input['hub_url'])) : $out['hub_url'];
        $out['site_id'] = isset($input['site_id']) ? sanitize_text_field(trim($input['site_id'])) : '';
        $out['api_key'] = isset($input['api_key']) ? sanitize_text_field(trim($input['api_key'])) : '';

        // remove trailing slash
        $out['hub_url'] = rtrim($out['hub_url'], '/');

        return $out;
    }

    public static function get_hub_url() {
        $settings = self::get_settings();
        return $settings['hub_url'];
    }

    public static function get_site_id() {
        $settings = self::get_settings();
        return $settings['site_id'];
    }

    public static function get_api_key() {
        $settings = self::get_settings();
        return $settings['api_key'];
    }

    public static function is_configured() {
        $settings = self::get_settings();
        return !empty($settings['hub_url']) && !empty($settings['site_id']) && !empty($settings['api_key']);
    }
}
