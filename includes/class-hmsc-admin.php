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
        HMSC_Support_Page::render();
    }

    public static function render_settings_page() {
        if (!current_user_can(self::CAP) || !self::allow_user()) {
            wp_die('Bu sayfaya erişim izniniz yok.');
        }

        $s = HMSC_Settings::get();
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

                        <tr>
                            <th scope="row"><label for="hmsc_shared_api_key">Paylaşılan API Anahtarı</label></th>
                            <td>
                                <input type="text" id="hmsc_shared_api_key" name="<?php echo esc_attr(HMSC_Settings::OPTION_KEY); ?>[shared_api_key]"
                                       value="<?php echo esc_attr($s['shared_api_key']); ?>" class="regular-text" placeholder="Opsiyonel">
                                <p class="description">Site bazlı anahtar yoksa bu anahtar kullanılacaktır.</p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button('Ayarları Kaydet'); ?>
                </div>

                <div class="hmsc-card">
                    <h2>Bağlantı Durumu</h2>
                    <p>
                        <?php if (HMSC_Settings::is_provisioned()): ?>
                            <span class="dashicons dashicons-yes" style="color:green"></span> Site Hub’a kayıtlı.
                        <?php else: ?>
                            <span class="dashicons dashicons-warning" style="color:#dba617"></span> Site kimliği / anahtar eksik. Admin paneline girince otomatik kayıt denenir.
                        <?php endif; ?>
                    </p>
                    <p>Hub bağlantısını test etmek için aşağıdaki butona tıklayın.</p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(HMSC_Support_Page::ACTION_TEST); ?>">
                        <?php wp_nonce_field('hmsc_test_nonce', 'hmsc_test_nonce_field'); ?>
                        <?php submit_button('Bağlantıyı Test Et', 'secondary', ''); ?>
                    </form>
                </div>
            </form>
        </div>
        <?php
    }
}
