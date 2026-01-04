<?php
/**
 * Plugin Name: HM Support Client
 * Description: Adds a Shop Manager support form to send tickets to HM Support Hub.
 * Version: 1.0.0
 * Author: Hızlı Mağaza Pro
 * Text Domain: hm-support-client
 */

if (!defined('ABSPATH')) exit;

define('HMSC_VERSION', '1.0.0');
define('HMSC_PATH', plugin_dir_path(__FILE__));
define('HMSC_URL', plugin_dir_url(__FILE__));

require_once HMSC_PATH . 'includes/class-hmsc-plugin.php';

add_action('plugins_loaded', function () {
    HMSC_Plugin::instance();
});
