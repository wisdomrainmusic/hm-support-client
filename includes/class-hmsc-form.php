<?php
if (!defined('ABSPATH')) exit;

class HMSC_Form {
    const CAP = 'manage_woocommerce';

    // WhatsApp line
    const WHATSAPP_LINK = 'https://wa.me/905309138778';

    // Admin-post action
    const ACTION = 'hmsc_submit_ticket';

    public static function init() {
        add_action('admin_post_' . self::ACTION, array(__CLASS__, 'handle_submit'));
    }

    public static function render() {
        $success = isset($_GET['hmsc_sent']) && $_GET['hmsc_sent'] === '1';
        $error   = isset($_GET['hmsc_error']) ? sanitize_text_field($_GET['hmsc_error']) : '';

        $current_user = wp_get_current_user();
        $default_email = $current_user && !empty($current_user->user_email) ? $current_user->user_email : '';

        ?>
        <div class="wrap hmsc-wrap">
            <h1>HM Support</h1>

            <?php if ($success): ?>
                <div class="notice notice-success is-dismissible"><p>Ticket sent successfully.</p></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>

            <div class="hmsc-grid">
                <div class="hmsc-card hmsc-card-wide">
                    <h2>Create a Support Ticket</h2>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
                        <?php wp_nonce_field('hmsc_ticket_nonce', 'hmsc_ticket_nonce_field'); ?>

                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="hmsc_email">Your Email</label></th>
                                <td>
                                    <input type="email" id="hmsc_email" name="hmsc_email" class="regular-text"
                                           value="<?php echo esc_attr($default_email); ?>" required>
                                    <p class="description">Please enter an email address you can access.</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="hmsc_urgency">Urgency</label></th>
                                <td>
                                    <select id="hmsc_urgency" name="hmsc_urgency" required>
                                        <option value="Low">Low</option>
                                        <option value="Normal" selected>Normal</option>
                                        <option value="High">High</option>
                                        <option value="Critical">Critical</option>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="hmsc_subject">Subject</label></th>
                                <td>
                                    <input type="text" id="hmsc_subject" name="hmsc_subject" class="regular-text" maxlength="140" required>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="hmsc_message">Message</label></th>
                                <td>
                                    <textarea id="hmsc_message" name="hmsc_message" rows="8" class="large-text" required></textarea>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="hmsc_phone">Phone (optional)</label></th>
                                <td>
                                    <input type="text" id="hmsc_phone" name="hmsc_phone" class="regular-text" maxlength="40" placeholder="+90 ...">
                                </td>
                            </tr>
                        </table>

                        <?php submit_button('Send Ticket'); ?>
                    </form>

                    <div class="hmsc-note">
                        <p><strong>Pazartesi–Cumartesi 09:00–18:00</strong> arası taleplerinize e-posta ile dönüş yapılacaktır.</p>
                        <p>Lütfen ulaşabildiğiniz bir e-posta adresi yazın.</p>
                        <p>Çok acil durumlarda WhatsApp hattımıza ulaşın:
                            <a href="<?php echo esc_url(self::WHATSAPP_LINK); ?>" target="_blank" rel="noopener noreferrer">
                                wa.me/905309138778
                            </a>
                        </p>
                        <p>Aciliyet seviyesine göre dönüş süresi değişebilir.</p>
                    </div>
                </div>

                <div class="hmsc-card">
                    <h2>Urgent WhatsApp</h2>
                    <p>If this is urgent, contact us via WhatsApp.</p>
                    <a class="button button-primary button-hero" style="width:100%; text-align:center;"
                       href="<?php echo esc_url(self::WHATSAPP_LINK); ?>" target="_blank" rel="noopener noreferrer">
                        WhatsApp Urgent Support
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    public static function handle_submit() {
        if (!current_user_can(self::CAP)) {
            wp_die('Not allowed.');
        }

        if (!isset($_POST['hmsc_ticket_nonce_field']) || !wp_verify_nonce($_POST['hmsc_ticket_nonce_field'], 'hmsc_ticket_nonce')) {
            self::redirect_error('Security check failed.');
        }

        $email   = isset($_POST['hmsc_email']) ? sanitize_email($_POST['hmsc_email']) : '';
        $urgency = isset($_POST['hmsc_urgency']) ? sanitize_text_field($_POST['hmsc_urgency']) : 'Normal';
        $subject = isset($_POST['hmsc_subject']) ? sanitize_text_field($_POST['hmsc_subject']) : '';
        $message = isset($_POST['hmsc_message']) ? wp_strip_all_tags(wp_unslash($_POST['hmsc_message'])) : '';
        $phone   = isset($_POST['hmsc_phone']) ? sanitize_text_field($_POST['hmsc_phone']) : '';

        if (empty($email) || empty($subject) || empty($message)) {
            self::redirect_error('Please fill in Email, Subject, and Message.');
        }

        $settings = HMSC_Settings::get_settings();
        if (empty($settings['hub_url']) || empty($settings['site_id']) || empty($settings['api_key'])) {
            self::redirect_error('Hub settings are missing. Please configure Settings first.');
        }

        $endpoint = rtrim($settings['hub_url'], '/') . '/wp-json/hmsh/v1/tickets';

        $user = wp_get_current_user();

        $payload = array(
            'client_site_url'  => home_url('/'),
            'client_site_name' => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),

            'customer_email'   => $email,
            'customer_phone'   => $phone,

            'urgency'          => $urgency,
            'subject'          => $subject,
            'message'          => $message,

            'submitted_by'     => $user ? $user->display_name : '',
            'submitted_by_email' => $user ? $user->user_email : '',
        );

        $args = array(
            'timeout' => 15,
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8',
                'X-HM-Site'    => $settings['site_id'],
                'X-HM-Key'     => $settings['api_key'],
            ),
            'body' => wp_json_encode($payload),
        );

        $res = wp_remote_post($endpoint, $args);

        if (is_wp_error($res)) {
            self::redirect_error('Could not reach Hub: ' . $res->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($res);
        $body = wp_remote_retrieve_body($res);

        if ($code < 200 || $code >= 300) {
            $msg = 'Hub rejected the request.';
            if (!empty($body)) {
                $decoded = json_decode($body, true);
                if (is_array($decoded) && !empty($decoded['message'])) {
                    $msg .= ' ' . sanitize_text_field($decoded['message']);
                }
            }
            self::redirect_error($msg);
        }

        wp_safe_redirect(add_query_arg(array('page' => 'hm-support', 'hmsc_sent' => '1'), admin_url('admin.php')));
        exit;
    }

    private static function redirect_error($msg) {
        wp_safe_redirect(add_query_arg(array(
            'page' => 'hm-support',
            'hmsc_error' => rawurlencode($msg),
        ), admin_url('admin.php')));
        exit;
    }
}
