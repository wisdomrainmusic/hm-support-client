<?php
if (!defined('ABSPATH')) exit;
class HMSC_Admin {
    const CAP = 'manage_woocommerce';

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_post_hmsc_reprovision', array(__CLASS__, 'handle_reprovision'));
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
                settings_errors('hmsc_messages');
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
                                       value="<?php echo esc_attr($s['site_id']); ?>" class="regular-text" placeholder="HMZP-1001" readonly>
                                <p class="description">Bu değer Hub tarafından otomatik atanır. Değiştirmek için “Yeniden Bağlan” kullanın.</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="hmsc_api_key">API Anahtarı</label></th>
                            <td>
                                <input type="text" id="hmsc_api_key" name="<?php echo esc_attr(HMSC_Settings::OPTION_KEY); ?>[api_key]"
                                       value="<?php echo esc_attr($s['api_key']); ?>" class="regular-text" placeholder="Anahtarınızı yapıştırın" readonly>
                                <p class="description">Bu değer Hub tarafından otomatik atanır.</p>
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

                <div class="hmsc-card">
                    <h2>Kayıt Sıfırla</h2>
                    <p>Site kimliği ve API anahtarı Hub tarafından atanır. Bu buton mevcut kaydı temizler ve admin paneline tekrar girdiğinizde yeni bir site kimliği alınmasını sağlar.</p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="hmsc_reprovision" />
                        <?php wp_nonce_field('hmsc_reprovision'); ?>
                        <p><button class="button button-secondary">Kayıt Sıfırla (Yeni Site Kimliği Al)</button></p>
                    </form>
                </div>
            </form>
        </div>
        <?php
    }

    public static function handle_reprovision() {
        if (!current_user_can(self::CAP) || !self::allow_user()) {
            wp_die('Bu işlemi yapma izniniz yok.');
        }

        check_admin_referer('hmsc_reprovision');

        HMSC_Settings::update(array(
            'site_id'        => '',
            'api_key'        => '',
            'provisioned_at' => 0,
            'last_error'     => '',
        ));

        add_settings_error('hmsc_messages', 'hmsc_reprovision_reset', 'Kayıt bilgileri temizlendi. Admin paneli yeniden açıldığında yeni site kimliği alınacaktır.', 'info');

        // hataları bir sonraki sayfaya taşı
        set_transient('settings_errors', get_settings_errors('hmsc_messages'), 30);

        wp_safe_redirect(add_query_arg(array('page' => 'hm-support-settings', 'settings-updated' => 'true'), admin_url('admin.php')));
        exit;
    }
}
