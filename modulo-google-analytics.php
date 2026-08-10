<?php

/**
 * Módulo Google Analytics 4: Client-Side (gtag.js) e Server-Side (Measurement Protocol)
 */

if (!defined('ABSPATH')) exit;

// 1. REGISTRA AS CONFIGURAÇÕES NO PAINEL
function ga_register_settings()
{
    register_setting('gtm_options_group', 'ga_measurement_id', ['type' => 'string', 'default' => '']);
    register_setting('gtm_options_group', 'ga_api_secret', ['type' => 'string', 'default' => '']);
    register_setting('gtm_options_group', 'ga_enable_pageview_mp', ['type' => 'string', 'default' => '0']);
}
add_action('admin_init', 'ga_register_settings');

function ga_settings_html()
{
    $ga_id      = get_option('ga_measurement_id', '');
    $ga_secret  = get_option('ga_api_secret', '');
    $ga_pv_mp   = get_option('ga_enable_pageview_mp', '0');
    $last_debug = get_option('ga_mp_last_debug', 'Nenhum envio do GA4 registrado ainda.');
?>
    <hr>
    <h2>Integração Google Analytics 4 (Client + Measurement Protocol)</h2>
    <table class="form-table">
        <tr valign="top">
            <th scope="row"><label for="ga_measurement_id">ID da Métrica (Measurement ID)</label></th>
            <td>
                <input type="text" id="ga_measurement_id" name="ga_measurement_id" value="<?php echo esc_attr($ga_id); ?>" placeholder="Ex: G-XXXXXXXXXX" class="regular-text" />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="ga_api_secret">Segredo da API (API Secret)</label></th>
            <td>
                <input type="password" id="ga_api_secret" name="ga_api_secret" value="<?php echo esc_attr($ga_secret); ?>" placeholder="Gerado no painel do GA4" class="regular-text" />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">PageView Server-Side (MP)</th>
            <td>
                <label for="ga_enable_pageview_mp">
                    <input type="checkbox" id="ga_enable_pageview_mp" name="ga_enable_pageview_mp" value="1" <?php checked('1', $ga_pv_mp); ?> />
                    Ativar envio do evento PageView via Servidor (Measurement Protocol)
                </label>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label>Diagnóstico GA4 (Último Envio)</label></th>
            <td>
                <textarea readonly style="width: 100%; height: 120px; background: #f0f0f1; border: 1px solid #8c8f94; font-family: monospace; padding: 10px;"><?php echo esc_textarea($last_debug); ?></textarea>
            </td>
        </tr>
    </table>
<?php
}
add_action('gtm_settings_page_after_fields', 'ga_settings_html');


// 2. INSERE A TAG GLOBAL DO GA4 NO FRONTEND (Client-Side)
function ga_add_gtag_head()
{
    $ga_id    = get_option('ga_measurement_id');
    $ga_pv_mp = get_option('ga_enable_pageview_mp', '0');
    if (empty($ga_id)) return;
?>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($ga_id); ?>"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }
        gtag('js', new Date());

        <?php if ($ga_pv_mp === '1'): ?>
            gtag('config', '<?php echo esc_js($ga_id); ?>', {
                'send_page_view': false
            });
            gtag('event', 'page_view');
        <?php else: ?>
            gtag('config', '<?php echo esc_js($ga_id); ?>');
        <?php endif; ?>
    </script>
<?php
}
add_action('wp_head', 'ga_add_gtag_head', 1);


// 3. CAPTURA O CLIENT_ID DO GA4 COM CORREÇÃO DE COOKIE
function ga_get_client_id()
{
    if (isset($_COOKIE['_ga'])) {
        $parts = explode('.', sanitize_text_field($_COOKIE['_ga']));
        if (count($parts) >= 4) {
            return $parts[2] . '.' . $parts[3];
        }
    }
    if (!isset($_COOKIE['bridge_ga_client_fallback'])) {
        $fallback = mt_rand(100000000, 2147483647) . '.' . time();
        setcookie('bridge_ga_client_fallback', $fallback, time() + (86400 * 30), '/');
        $_COOKIE['bridge_ga_client_fallback'] = $fallback;
    }
    return $_COOKIE['bridge_ga_client_fallback'];
}


// 4. FUNÇÃO CORE: ENVIA DADOS PARA O MEASUREMENT PROTOCOL DO GA4
function ga_send_mp_event($event_name, $event_params = [])
{
    $ga_id     = get_option('ga_measurement_id');
    $ga_secret = get_option('ga_api_secret');

    if (empty($ga_id) || empty($ga_secret)) {
        update_option('ga_mp_last_debug', 'Erro: Measurement ID ou API Secret estão vazios.');
        return;
    }

    $utms = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
    foreach ($utms as $utm) {
        if (isset($_COOKIE['gtm_' . $utm]) && !isset($event_params[str_replace('utm_', '', $utm)])) {
            $event_params[str_replace('utm_', '', $utm)] = sanitize_text_field($_COOKIE['gtm_' . $utm]);
        }
    }

    $payload = [
        'client_id' => ga_get_client_id(),
        'events'    => [
            [
                'name'   => $event_name,
                'params' => $event_params
            ]
        ]
    ];

    $url = "https://www.google-analytics.com/mp/collect?measurement_id={$ga_id}&api_secret={$ga_secret}";
    $is_pageview = ($event_name === 'page_view');

    $response = wp_remote_post($url, [
        'body'      => wp_json_encode($payload),
        'headers'   => ['Content-Type' => 'application/json'],
        'timeout'   => 15,
        'blocking'  => true,
        'sslverify' => false
    ]);

    if (is_wp_error($response)) {
        update_option('ga_mp_last_debug', 'Erro WP_Remote: ' . $response->get_error_message());
    } else {
        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $payload_json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $debug_msg = "STATUS HTTP: {$status} (204 indica sucesso no GA4)\n\nPAYLOAD ENVIADO:\n{$payload_json}";
        if (!empty($body)) {
            $debug_msg .= "\n\nRESPOSTA:\n{$body}";
        }
        update_option('ga_mp_last_debug', $debug_msg);
    }
}


// 5. DISPARO DE PAGEVIEW SERVER-SIDE NO GA4
function ga_handle_pageview_mp()
{
    $ga_pv_mp = get_option('ga_enable_pageview_mp', '0');
    if ($ga_pv_mp !== '1' || is_admin()) return;

    $page_title = wp_get_document_title();
    $page_location = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

    ga_send_mp_event('page_view', [
        'page_title'    => $page_title,
        'page_location' => $page_location
    ]);
}
add_action('wp', 'ga_handle_pageview_mp');


// 6. INTEGRAÇÕES SERVER-SIDE: WPFORMS & CF7 (Gera Lead)
function ga_mp_wpforms_handler($fields, $entry, $form_data, $entry_id)
{
    $forms_ativos = get_option('bridge_active_forms', []);
    if (!is_array($forms_ativos) || !in_array('wpforms', $forms_ativos)) return;

    ga_send_mp_event('generate_lead', [
        'form_id'   => $form_data['id'] ?? 'wpforms',
        'form_name' => $form_data['settings']['form_title'] ?? 'Formulário'
    ]);
}
add_action('wpforms_process_complete', 'ga_mp_wpforms_handler', 10, 4);

function ga_mp_cf7_handler($contact_form, $result)
{
    $forms_ativos = get_option('bridge_active_forms', []);
    if (!is_array($forms_ativos) || !in_array('cf7', $forms_ativos)) return;

    if (in_array($result['status'], ['validation_failed', 'acceptance_missing', 'spam'])) {
        return;
    }
    ga_send_mp_event('generate_lead', [
        'form_id'   => $contact_form->id(),
        'form_name' => $contact_form->title()
    ]);
}
add_action('wpcf7_submit', 'ga_mp_cf7_handler', 10, 2);
