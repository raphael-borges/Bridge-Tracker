<?php

/**
 * Módulo Facebook: Pixel Client-Side e CAPI Server-Side com UTM Tracking e PageView CAPI
 */

if (!defined('ABSPATH')) exit;

// -----------------------------------------------------------------------------
// 1. CAPTURA DE UTMS E GERAÇÃO DE EXTERNAL ID VIA COOKIES (Salva por 30 dias)
// -----------------------------------------------------------------------------
function fb_capture_utms()
{
    $utms = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id', 'fbclid', 'gclid'];

    foreach ($utms as $utm) {
        if (isset($_GET[$utm])) {
            $value = sanitize_text_field($_GET[$utm]);
            setcookie('gtm_' . $utm, $value, time() + (86400 * 30), '/');
            $_COOKIE['gtm_' . $utm] = $value;
        }
    }

    if (!isset($_COOKIE['bridge_ext_id'])) {
        $ext_id = wp_generate_uuid4();
        setcookie('bridge_ext_id', $ext_id, time() + (86400 * 180), '/');
        $_COOKIE['bridge_ext_id'] = $ext_id;
    }
}
add_action('init', 'fb_capture_utms');


// -----------------------------------------------------------------------------
// 2. REGISTRA AS CONFIGURAÇÕES NO PAINEL
// -----------------------------------------------------------------------------
function fb_register_settings()
{
    register_setting('gtm_options_group', 'fb_pixel_id', ['type' => 'string', 'default' => '']);
    register_setting('gtm_options_group', 'fb_capi_token', ['type' => 'string', 'default' => '']);
    register_setting('gtm_options_group', 'fb_test_event_code', ['type' => 'string', 'default' => '']);
    register_setting('gtm_options_group', 'fb_enable_pageview_capi', ['type' => 'string', 'default' => '0']);
    register_setting('gtm_options_group', 'bridge_active_forms', ['type' => 'array', 'default' => []]);
}
add_action('admin_init', 'fb_register_settings');

function fb_settings_html()
{
    $fb_pixel     = get_option('fb_pixel_id', '');
    $fb_token     = get_option('fb_capi_token', '');
    $fb_test_code = get_option('fb_test_event_code', '');
    $fb_pv_capi   = get_option('fb_enable_pageview_capi', '0');
    $last_debug   = get_option('fb_capi_last_debug', 'Nenhum envio registrado ainda.');
?>
    <hr>
    <h2>Integração Facebook (Pixel + CAPI)</h2>
    <table class="form-table">
        <tr valign="top">
            <th scope="row"><label for="fb_pixel_id">ID do Pixel</label></th>
            <td>
                <input type="text" id="fb_pixel_id" name="fb_pixel_id" value="<?php echo esc_attr($fb_pixel); ?>" placeholder="Ex: 1234567890" class="regular-text" />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="fb_capi_token">Token da API (CAPI)</label></th>
            <td>
                <input type="password" id="fb_capi_token" name="fb_capi_token" value="<?php echo esc_attr($fb_token); ?>" class="regular-text" />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="fb_test_event_code">Código de Teste</label></th>
            <td>
                <input type="text" id="fb_test_event_code" name="fb_test_event_code" value="<?php echo esc_attr($fb_test_code); ?>" placeholder="Ex: TEST30195" class="regular-text" />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">PageView Server-Side (CAPI)</th>
            <td>
                <label for="fb_enable_pageview_capi">
                    <input type="checkbox" id="fb_enable_pageview_capi" name="fb_enable_pageview_capi" value="1" <?php checked('1', $fb_pv_capi); ?> />
                    Ativar envio do evento PageView via Servidor com Deduplicação (Event ID)
                </label>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label>Diagnóstico da API (Último Envio)</label></th>
            <td>
                <textarea readonly style="width: 100%; height: 120px; background: #f0f0f1; border: 1px solid #8c8f94; font-family: monospace; padding: 10px;"><?php echo esc_textarea($last_debug); ?></textarea>
            </td>
        </tr>
    </table>
<?php
}
add_action('gtm_settings_page_after_fields', 'fb_settings_html');


// -----------------------------------------------------------------------------
// 3. INSERE O PIXEL BASE NO FRONTEND (Client-Side)
// -----------------------------------------------------------------------------
function fb_add_pixel_head()
{
    $fb_pixel   = get_option('fb_pixel_id');
    $fb_pv_capi = get_option('fb_enable_pageview_capi', '0');

    if (empty($fb_pixel)) return;
?>
    <!-- Meta Pixel Code -->
    <script>
        ! function(f, b, e, v, n, t, s) {
            if (f.fbq) return;
            n = f.fbq = function() {
                n.callMethod ?
                    n.callMethod.apply(n, arguments) : n.queue.push(arguments)
            };
            if (!f._fbq) f._fbq = n;
            n.push = n;
            n.loaded = !0;
            n.version = '2.0';
            n.queue = [];
            t = b.createElement(e);
            t.async = !0;
            t.src = v;
            s = b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t, s)
        }(window, document, 'script',
            'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '<?php echo esc_js($fb_pixel); ?>');

        <?php if ($fb_pv_capi !== '1') : ?>
            fbq('track', 'PageView');
        <?php endif; ?>
    </script>
    <!-- End Meta Pixel Code -->
    <?php
}
add_action('wp_head', 'fb_add_pixel_head', 2);


// -----------------------------------------------------------------------------
// 4. FUNÇÃO CORE: ENVIA DADOS PARA A API DO FACEBOOK
// -----------------------------------------------------------------------------
function fb_send_capi_event($event_name, $email_raw = '', $event_source_url = '', $event_id = null)
{
    $fb_pixel     = get_option('fb_pixel_id');
    $fb_token     = get_option('fb_capi_token');
    $fb_test_code = get_option('fb_test_event_code');

    if (empty($fb_pixel) || empty($fb_token)) {
        update_option('fb_capi_last_debug', 'Erro: Pixel ID ou Token estão vazios.');
        return;
    }

    $user_data = [
        'client_ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ];

    if (!empty($email_raw)) {
        $user_data['em'] = [hash('sha256', strtolower(trim($email_raw)))];
    }

    if (isset($_COOKIE['_fbp'])) {
        $user_data['fbp'] = sanitize_text_field($_COOKIE['_fbp']);
    }
    if (isset($_COOKIE['_fbc'])) {
        $user_data['fbc'] = sanitize_text_field($_COOKIE['_fbc']);
    }
    if (isset($_COOKIE['bridge_ext_id'])) {
        $user_data['external_id'] = [hash('sha256', sanitize_text_field($_COOKIE['bridge_ext_id']))];
    }

    $event_payload = [
        'event_name'       => $event_name,
        'event_time'       => time(),
        'action_source'    => 'website',
        'event_source_url' => !empty($event_source_url) ? $event_source_url : home_url($_SERVER['REQUEST_URI'] ?? ''),
        'user_data'        => $user_data
    ];

    if (!empty($event_id)) {
        $event_payload['event_id'] = $event_id;
    }

    $custom_data = [];
    $utms = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id', 'fbclid', 'gclid'];

    foreach ($utms as $utm) {
        if (isset($_COOKIE['gtm_' . $utm])) {
            $custom_data[$utm] = sanitize_text_field($_COOKIE['gtm_' . $utm]);
        }
    }

    if (!empty($custom_data)) {
        $event_payload['custom_data'] = $custom_data;
    }

    $data = ['data' => [$event_payload]];

    if (!empty($fb_test_code)) {
        $data['test_event_code'] = sanitize_text_field($fb_test_code);
    }

    $url = "https://graph.facebook.com/v19.0/{$fb_pixel}/events?access_token={$fb_token}";
    $is_pageview = ($event_name === 'PageView');

    $response = wp_remote_post($url, [
        'headers'   => ['Content-Type' => 'application/json'],
        'body'      => wp_json_encode($data),
        'timeout'   => 15,
        'blocking'  => !$is_pageview,
        'sslverify' => false
    ]);

    if (!$is_pageview && !is_wp_error($response)) {
        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $payload_log = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        update_option('fb_capi_last_debug', "STATUS HTTP: {$status}\n\nPAYLOAD ENVIADO:\n{$payload_log}\n\nRESPOSTA DA META:\n{$body}");
    }
}


// -----------------------------------------------------------------------------
// 5. DISPARO DE PAGEVIEW SERVER-SIDE
// -----------------------------------------------------------------------------
function fb_handle_pageview_capi()
{
    $fb_pv_capi = get_option('fb_enable_pageview_capi', '0');
    if ($fb_pv_capi !== '1' || is_admin()) return;

    $event_id = 'pv_' . uniqid() . '_' . time();

    add_action('wp_head', function () use ($event_id) {
    ?>
        <script>
            if (typeof fbq !== 'undefined') {
                fbq('track', 'PageView', {}, {
                    event_id: '<?php echo esc_js($event_id); ?>'
                });
            }
        </script>
<?php
    }, 3);

    fb_send_capi_event('PageView', '', '', $event_id);
}
add_action('wp', 'fb_handle_pageview_capi');


// -----------------------------------------------------------------------------
// 6. INTEGRAÇÕES DE FORMULÁRIOS (WPForms & CF7)
// -----------------------------------------------------------------------------
function fb_capi_wpforms_handler($fields, $entry, $form_data, $entry_id)
{
    $forms_ativos = get_option('bridge_active_forms', []);
    if (!is_array($forms_ativos) || !in_array('wpforms', $forms_ativos)) return;

    $email = '';
    foreach ($fields as $field) {
        if ($field['type'] === 'email' && !empty($field['value'])) {
            $email = $field['value'];
            break;
        }
    }

    if (!empty($email)) {
        $url = home_url(add_query_arg([], $GLOBALS['wp']->request));
        fb_send_capi_event('Lead', $email, $url);
    }
}
add_action('wpforms_process_complete', 'fb_capi_wpforms_handler', 10, 4);

function fb_capi_cf7_handler($contact_form, $result)
{
    $forms_ativos = get_option('bridge_active_forms', []);
    if (!is_array($forms_ativos) || !in_array('cf7', $forms_ativos)) return;

    if (in_array($result['status'], ['validation_failed', 'acceptance_missing', 'spam'])) {
        return;
    }

    $submission = WPCF7_Submission::get_instance();
    if ($submission) {
        $posted_data = $submission->get_posted_data();
        $email = '';

        foreach ($posted_data as $key => $value) {
            if (strpos($key, 'email') !== false && !empty($value)) {
                $email = is_array($value) ? $value[0] : $value;
                break;
            }
        }

        if (!empty($email)) {
            $url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : home_url();
            fb_send_capi_event('Lead', $email, $url);
        }
    }
}
add_action('wpcf7_submit', 'fb_capi_cf7_handler', 10, 2);
