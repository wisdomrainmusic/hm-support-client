<?php
if (!defined('ABSPATH')) exit;

class HMSC_Support_Page {
    const ACTION_SUBMIT = 'hmsc_submit_ticket';
    const ACTION_TEST   = 'hmsc_test_connection';
    const CAP = 'manage_woocommerce';

    public static function init() {
        add_action('admin_post_' . self::ACTION_SUBMIT, array(__CLASS__, 'handle_submit'));
        add_action('admin_post_' . self::ACTION_TEST, array(__CLASS__, 'handle_test'));
    }

    public static function render() {
        $success = isset($_GET['hmsc_sent']) && $_GET['hmsc_sent'] === '1';
        $error   = isset($_GET['hmsc_error']) ? sanitize_text_field($_GET['hmsc_error']) : '';
        $status  = isset($_GET['hmsc_status']) ? sanitize_text_field($_GET['hmsc_status']) : '';

        $current_user = wp_get_current_user();
        $default_email = $current_user && !empty($current_user->user_email) ? $current_user->user_email : '';

        ?>
        <div class="wrap hmsc-wrap">
            <h1>HM Destek</h1>

            <?php if ($success): ?>
                <div class="notice notice-success is-dismissible"><p>Talep başarıyla gönderildi.</p></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>

            <?php if (!empty($status)): ?>
                <div class="notice notice-info is-dismissible"><p><?php echo esc_html($status); ?></p></div>
            <?php endif; ?>

            <div class="hmsc-grid">
                <div class="hmsc-card hmsc-card-wide">
                    <h2>Destek Talebi Oluştur</h2>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_SUBMIT); ?>">
                        <?php wp_nonce_field('hmsc_ticket_nonce', 'hmsc_ticket_nonce_field'); ?>

                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="hmsc_email">E-Posta Adresiniz</label></th>
                                <td>
                                    <input type="email" id="hmsc_email" name="hmsc_email" class="regular-text"
                                           value="<?php echo esc_attr($default_email); ?>" required>
                                    <p class="description">Lütfen erişebildiğiniz bir e-posta adresi girin.</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="hmsc_urgency">Aciliyet</label></th>
                                <td>
                                    <select id="hmsc_urgency" name="hmsc_urgency" required>
                                        <option value="Low">Düşük</option>
                                        <option value="Normal" selected>Normal</option>
                                        <option value="High">Yüksek</option>
                                        <option value="Critical">Kritik</option>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="hmsc_subject">Konu</label></th>
                                <td>
                                    <input type="text" id="hmsc_subject" name="hmsc_subject" class="regular-text" maxlength="140" required>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="hmsc_message">Mesaj</label></th>
                                <td>
                                    <textarea id="hmsc_message" name="hmsc_message" rows="8" class="large-text" required></textarea>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="hmsc_phone">Telefon (isteğe bağlı)</label></th>
                                <td>
                                    <input type="text" id="hmsc_phone" name="hmsc_phone" class="regular-text" maxlength="40" placeholder="+90 ...">
                                </td>
                            </tr>
                        </table>

                        <?php submit_button('Talebi Gönder'); ?>
                    </form>

                    <div class="hmsc-note">
                        <p><strong>Pazartesi–Cumartesi 09:00–18:00</strong> arası taleplerinize e-posta ile dönüş yapılacaktır.</p>
                        <p>Lütfen ulaşabildiğiniz bir e-posta adresi yazın.</p>
                        <p>Çok acil durumlarda WhatsApp hattımıza ulaşın:
                            <a href="https://wa.me/905309138778" target="_blank" rel="noopener noreferrer">
                                wa.me/905309138778
                            </a>
                        </p>
                        <p>Aciliyet seviyesine göre dönüş süresi değişebilir.</p>
                    </div>
                </div>

                <div class="hmsc-card">
                    <h2>Bağlantı Durumu</h2>
                    <?php self::render_status(); ?>

                    <h2 style="margin-top:24px;">Acil WhatsApp</h2>
                    <p>Acil durumlarda WhatsApp üzerinden bizimle iletişime geçin.</p>
                    <a class="button button-primary button-hero" style="width:100%; text-align:center;"
                       href="https://wa.me/905309138778" target="_blank" rel="noopener noreferrer">
                        WhatsApp Acil Destek
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_status() {
        $settings = HMSC_Settings::get();
        $configured = HMSC_Settings::is_configured();
        $provisioned = HMSC_Settings::is_provisioned();
        ?>
        <p>
            <?php if ($provisioned): ?>
                <span class="dashicons dashicons-yes" style="color:green"></span> Site Hub’a kayıtlı.
            <?php elseif ($configured): ?>
                <span class="dashicons dashicons-yes-alt" style="color:#46b450"></span> Hub bilgileri girildi, kayıt bekleniyor.
            <?php else: ?>
                <span class="dashicons dashicons-warning" style="color:#dba617"></span> Hub ayarları eksik.
            <?php endif; ?>
        </p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_TEST); ?>">
            <?php wp_nonce_field('hmsc_test_nonce', 'hmsc_test_nonce_field'); ?>
            <?php submit_button('Bağlantıyı Test Et', 'secondary', ''); ?>
        </form>
        <?php
    }

    public static function handle_submit() {
        if (!current_user_can(self::CAP)) {
            wp_die('İzin verilmedi.');
        }

        if (!isset($_POST['hmsc_ticket_nonce_field']) || !wp_verify_nonce($_POST['hmsc_ticket_nonce_field'], 'hmsc_ticket_nonce')) {
            self::redirect_error('Güvenlik kontrolü başarısız oldu.');
        }

        $email   = isset($_POST['hmsc_email']) ? sanitize_email($_POST['hmsc_email']) : '';
        $urgency = isset($_POST['hmsc_urgency']) ? sanitize_text_field($_POST['hmsc_urgency']) : 'Normal';
        $subject = isset($_POST['hmsc_subject']) ? sanitize_text_field($_POST['hmsc_subject']) : '';
        $message = isset($_POST['hmsc_message']) ? wp_strip_all_tags(wp_unslash($_POST['hmsc_message'])) : '';
        $phone   = isset($_POST['hmsc_phone']) ? sanitize_text_field($_POST['hmsc_phone']) : '';

        if (empty($email) || empty($subject) || empty($message)) {
            self::redirect_error('Lütfen E-posta, Konu ve Mesaj alanlarını doldurun.');
        }

        $settings = HMSC_Settings::get();
        if (empty($settings['hub_url']) || (empty($settings['site_id']) && empty($settings['shared_api_key']))) {
            self::redirect_error('Hub ayarları eksik. Lütfen önce Ayarlar bölümünü yapılandırın.');
        }

        $res = HMSC_Hub::send_ticket(array(
            'customer_email' => $email,
            'customer_phone' => $phone,
            'urgency'        => $urgency,
            'subject'        => $subject,
            'message'        => $message,
        ));

        if (is_wp_error($res)) {
            self::redirect_error('Hub’a ulaşılamadı: ' . $res->get_error_message());
        }

        wp_safe_redirect(add_query_arg(array('page' => 'hm-support', 'hmsc_sent' => '1'), admin_url('admin.php')));
        exit;
    }

    public static function handle_test() {
        if (!current_user_can(self::CAP)) {
            wp_die('İzin verilmedi.');
        }

        if (!isset($_POST['hmsc_test_nonce_field']) || !wp_verify_nonce($_POST['hmsc_test_nonce_field'], 'hmsc_test_nonce')) {
            self::redirect_error('Güvenlik kontrolü başarısız oldu.');
        }

        $res = HMSC_Hub::test_connection();

        if (is_wp_error($res)) {
            self::redirect_status('Hub bağlantı testi başarısız: ' . $res->get_error_message());
        }

        // NEW: test_connection artık array döndürüyor
        if (is_array($res) && !empty($res['ok'])) {
            $code = isset($res['http_code']) ? (int) $res['http_code'] : 200;
            self::redirect_status('Hub bağlantı testi başarılı. (HTTP ' . $code . ')');
        }

        // Eğer beklenmeyen bir şey dönerse:
        self::redirect_status('Hub bağlantı testi başarısız (beklenmeyen yanıt).');
    }

    private static function redirect_error($msg) {
        wp_safe_redirect(add_query_arg(array(
            'page' => 'hm-support',
            'hmsc_error' => rawurlencode($msg),
        ), admin_url('admin.php')));
        exit;
    }

    private static function redirect_status($msg) {
        wp_safe_redirect(add_query_arg(array(
            'page' => 'hm-support',
            'hmsc_status' => rawurlencode($msg),
        ), admin_url('admin.php')));
        exit;
    }
}
