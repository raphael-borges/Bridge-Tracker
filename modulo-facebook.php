<?php

/**
 * Módulo Facebook: Pixel Client-Side e CAPI Server-Side com UTM Tracking
 */

if (!defined('ABSPATH')) exit;

// -----------------------------------------------------------------------------
// 1. CAPTURA DE UTMS VIA COOKIES (Salva por 30 dias)
// -----------------------------------------------------------------------------
function fb_capture_utms()
{
    // Adicionados utm_id, fbclid e gclid aqui também para que sejam salvos no cookie!
    $utms = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id', 'fbclid', 'gclid'];

    foreach ($utms as $utm) {
        if (isset($_GET[$utm])) {
            $value = sanitize_text_field($_GET[$utm]);
            // Salva o cookie no navegador do usuário (expira em 30 dias)
            setcookie('gtm_' . $utm, $value, time() + (86400 * 30), '/');
            // Popula a variável global para a requisição atual
            $_COOKIE['gtm_' . $utm] = $value;
        }
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
}
add_action('admin_init', 'fb_register_settings');

function fb_settings_html()
{
    $fb_pixel = get_option('fb_pixel_id', '');
    $fb_token = get_option('fb_capi_token', '');
    $fb_test_code = get_option('fb_test_event_code', '');
    $last_debug = get_option('fb_capi_last_debug', 'Nenhum envio registrado ainda.');
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
                <input type="text" id="fb_test_event_code" name="fb_test_event_code" value="<?php echo esc_attr($fb_test_code); ?>" placeholder="Ex: TESTXXXX" class="regular-text" />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label>Diagnóstico da API (Último Envio)</label></th>
            <td>
                <textarea readonly style="width: 100%; height: 120px; background: #f0f0f1; border: 1px solid #8c8f94; font-family: monospace; padding: 10px;"><?php echo esc_textarea($last_debug); ?></textarea>
                <p class="description">Mostra a resposta exata dos servidores da Meta ou do seu WordPress local.</p>
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
    $fb_pixel = get_option('fb_pixel_id');
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
        fbq('track', 'PageView');
    </script>
    <!-- End Meta Pixel Code -->
<?php
}
add_action('wp_head', 'fb_add_pixel_head', 2);


// -----------------------------------------------------------------------------
// 4. FUNÇÃO CORE: ENVIA DADOS E UTMS PARA A API DO FACEBOOK
// -----------------------------------------------------------------------------
function fb_send_capi_event($event_name, $email_raw, $event_source_url)
{
    $fb_pixel = get_option('fb_pixel_id');
    $fb_token = get_option('fb_capi_token');
    $fb_test_code = get_option('fb_test_event_code');

    if (empty($fb_pixel) || empty($fb_token)) {
        update_option('fb_capi_last_debug', 'Erro: Pixel ID ou Token estão vazios.');
        return;
    }

    $email_hash = hash('sha256', strtolower(trim($email_raw)));

    // 1. Monta os dados básicos do usuário
    $user_data = [
        'em' => [$email_hash],
        'client_ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ];

    // 2. Captura os cookies automáticos do Pixel (_fbp e _fbc) e adiciona ao user_data
    if (isset($_COOKIE['_fbp'])) {
        $user_data['fbp'] = sanitize_text_field($_COOKIE['_fbp']);
    }
    if (isset($_COOKIE['_fbc'])) {
        $user_data['fbc'] = sanitize_text_field($_COOKIE['_fbc']);
    }

    // 3. Estrutura do evento
    $event_payload = [
        'event_name' => $event_name,
        'event_time' => time(),
        'action_source' => 'website',
        'event_source_url' => $event_source_url,
        'user_data' => $user_data
    ];

    // Resgata os Cookies de UTM e adiciona ao payload em 'custom_data'
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

    // Monta o pacote final
    $data = [
        'data' => [$event_payload]
    ];

    if (!empty($fb_test_code)) {
        $data['test_event_code'] = sanitize_text_field($fb_test_code);
    }

    $url = "https://graph.facebook.com/v19.0/{$fb_pixel}/events?access_token={$fb_token}";

    $response = wp_remote_post($url, [
        'headers'   => ['Content-Type' => 'application/json'],
        'body'      => wp_json_encode($data),
        'timeout'   => 15,
        'blocking'  => true,
        'sslverify' => true
    ]);

    if (is_wp_error($response)) {
        update_option('fb_capi_last_debug', 'FALHA DO SERVIDOR LOCAL: ' . $response->get_error_message());
    } else {
        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Formata o payload JSON para o log ficar bonito e fácil de ler
        $payload_log = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        update_option('fb_capi_last_debug', "STATUS HTTP: {$status}\n\nPAYLOAD ENVIADO:\n{$payload_log}\n\nRESPOSTA DA META:\n{$body}");
    }
}


// -----------------------------------------------------------------------------
// 5. INTEGRAÇÃO SERVER-SIDE: WPFORMS
// -----------------------------------------------------------------------------
function fb_capi_wpforms_handler($fields, $entry, $form_data, $entry_id)
{
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


// -----------------------------------------------------------------------------
// 6. INTEGRAÇÃO SERVER-SIDE: CONTACT FORM 7
// -----------------------------------------------------------------------------
function fb_capi_cf7_handler($contact_form, $result)
{
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
        } else {
            update_option('fb_capi_last_debug', 'Erro CF7: E-mail não encontrado nos dados enviados.');
        }
    }
}
add_action('wpcf7_submit', 'fb_capi_cf7_handler', 10, 2);
