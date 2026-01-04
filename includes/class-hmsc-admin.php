<?php
if (!defined('ABSPATH')) exit;

class HMSC_Admin {
    const CAP = 'manage_woocommerce';

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
    }

    public static function menu() {
        add_menu_page(
            'HM Destek',
            'HM Destek',
            self::CAP,
            'hm-support',
            array(__CLASS__, 'render_support_page'),
            'dashicons-sos',
            58
        );

        add_submenu_page(
            'hm-support',
            'Destek Formu',
            'Destek Formu',
            self::CAP,
            'hm-support',
            array(__CLASS__, 'render_support_page')
        );

        add_submenu_page(
            'hm-support',
            'Ayarlar',
            'Ayarlar',
            self::CAP,
            'hm-support-settings',
            array(__CLASS__, 'render_settings_page')
        );
    }

    private static function allow_user() {
        $u = wp_get_current_user();
        if (!$u || empty($u->roles)) return false;
        return in_array('shop_manager', (array)$u->roles, true) || in_array('administrator', (array)$u->roles, true);
    }

    public static function render_support_page() {
        if (!current_user_can(self::CAP) || !self::allow_user()) {
            wp_die('Bu sayfaya erişim izniniz yok.');
        }
        HMSC_Form::render();
    }

    public static function render_settings_page() {
        if (!current_user_can(self::CAP) || !self::allow_user()) {
            wp_die('Bu sayfaya erişim izniniz yok.');
        }

        $s = HMSC_Settings::get_settings();
        ?>
        <div class="wrap hmsc-wrap">
            <h1>HM Destek Ayarları</h1>

            <form method="post" action="options.php">
                <?php
                settings_fields('hmsc_settings_group');
                ?>

                <div class="hmsc-card">
                    <h2>Hub Bağlantısı</h2>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="hmsc_hub_url">Hub URL</label></th>
                            <td>
                                <input type="url" id="hmsc_hub_url" name="<?php echo esc_attr(HMSC_Settings::OPTION_KEY); ?>[hub_url]"
                                       value="<?php echo esc_attr($s['hub_url']); ?>" class="regular-text" required>
                                <p class="description">Örnek: https://hizlimagazapro.com</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="hmsc_site_id">Site Kimliği</label></th>
                            <td>
                                <input type="text" id="hmsc_site_id" name="<?php echo esc_attr(HMSC_Settings::OPTION_KEY); ?>[site_id]"
                                       value="<?php echo esc_attr($s['site_id']); ?>" class="regular-text" placeholder="HMZP-1001" required>
                                <p class="description">Bu değer, müşteri sitesini Hub içinde tanımlar.</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="hmsc_api_key">API Anahtarı</label></th>
                            <td>
                                <input type="text" id="hmsc_api_key" name="<?php echo esc_attr(HMSC_Settings::OPTION_KEY); ?>[api_key]"
                                       value="<?php echo esc_attr($s['api_key']); ?>" class="regular-text" placeholder="Anahtarınızı yapıştırın" required>
                                <p class="description">Bu değeri gizli tutun. Hub, istekleri doğrulamak için kullanır.</p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button('Ayarları Kaydet'); ?>
                </div>

                <div class="hmsc-card">
                    <h2>Notlar</h2>
                    <p>Bu eklenti yalnızca talepleri Hub’a iletir ve Mağaza Yöneticisi için bir destek formu gösterir.</p>
                </div>
            </form>
        </div>
        <?php
    }
}
