<?php
/**
 * Plugin Name: HM Support Client
 * Description: Müşteri sitelerinde mağaza yöneticisi için destek formu; talepleri Hub'a iletir.
 * Version: 0.2.0
 */

if (!defined('ABSPATH')) exit;

define('HMSC_VERSION', '0.2.0');
define('HMSC_PATH', plugin_dir_path(__FILE__));
define('HMSC_URL', plugin_dir_url(__FILE__));
define('HMSC_DEFAULT_HUB_URL', 'https://hizlimagazapro.com');

// AJANS DAĞITIM ANAHTARI (bundle içine gömülü).
// Bunu repo içinde istersen sonra .env / build pipeline ile yazdırırız.
define('HMSC_DEFAULT_SHARED_KEY', 'hm_shared_2026_secret');

require_once HMSC_PATH . 'includes/class-hmsc-settings.php';
require_once HMSC_PATH . 'includes/class-hmsc-hub.php';
require_once HMSC_PATH . 'includes/class-hmsc-admin.php';
require_once HMSC_PATH . 'includes/class-hmsc-support-page.php';

register_activation_hook(__FILE__, function() {
    if (is_admin()) {
        // Aktivasyonda kesin dene
        if (class_exists('HMSC_Hub')) {
            HMSC_Hub::maybe_provision();
        }
    }
});

add_action('plugins_loaded', function() {
    HMSC_Settings::init();
    HMSC_Admin::init();
    HMSC_Support_Page::init();

    // Otomatik provision (admin tarafında login olunca tetiklenir)
    add_action('admin_init', array('HMSC_Hub', 'maybe_provision'));
});
