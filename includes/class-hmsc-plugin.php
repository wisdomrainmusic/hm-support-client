<?php
if (!defined('ABSPATH')) exit;

require_once HMSC_PATH . 'includes/class-hmsc-admin.php';
require_once HMSC_PATH . 'includes/class-hmsc-settings.php';
require_once HMSC_PATH . 'includes/class-hmsc-form.php';

final class HMSC_Plugin {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        HMSC_Settings::init();
        HMSC_Admin::init();
        HMSC_Form::init();
    }

    public function admin_assets($hook) {
        if (isset($_GET['page']) && strpos(sanitize_text_field($_GET['page']), 'hm-support') === 0) {
            wp_enqueue_style('hmsc-admin', HMSC_URL . 'assets/admin.css', array(), HMSC_VERSION);
        }
    }
}
