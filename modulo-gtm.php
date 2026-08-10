<?php

/**
 * Módulo GTM: Configurações, Injeção de Scripts e Ouvintes de Eventos (AJAX)
 * Integrado ao Bridge Tracker - com opção unificada bridge_active_forms
 */

if (!defined('ABSPATH')) exit;

// ============================================================
// 1. REGISTRA AS CONFIGURAÇÕES (usando a opção unificada)
// ============================================================
function gtm_register_settings()
{
    register_setting('gtm_options_group', 'gtm_container_id', [
        'type'              => 'string',
        'sanitize_callback' => 'gtm_sanitize_container_id',
        'default'           => ''
    ]);
    // Opção unificada com os outros módulos
    register_setting('gtm_options_group', 'bridge_active_forms', [
        'type'    => 'array',
        'default' => []
    ]);
}
add_action('admin_init', 'gtm_register_settings');

function gtm_sanitize_container_id($input)
{
    $clean = strtoupper(trim(sanitize_text_field($input)));
    if (!empty($clean) && !preg_match('/^GTM-[A-Z0-9]+$/', $clean)) {
        add_settings_error(
            'gtm_container_id',
            'gtm_id_error',
            'Formato de ID inválido (Ex: GTM-XXXXXXX).',
            'error'
        );
    }
    return $clean;
}

// ============================================================
// 2. ADICIONA OS CAMPOS NA PÁGINA DE CONFIGURAÇÃO (via hook)
// ============================================================
function gtm_settings_page_html_fields()
{
    $gtm_id          = get_option('gtm_container_id', '');
    $active_forms    = get_option('bridge_active_forms', []);
    if (!is_array($active_forms)) $active_forms = [];
?>
    <h2>Google Tag Manager (GTM)</h2>
    <table class="form-table">
        <tr valign="top">
            <th scope="row"><label for="gtm_container_id">ID do Contêiner GTM</label></th>
            <td>
                <input type="text" id="gtm_container_id" name="gtm_container_id"
                    value="<?php echo esc_attr($gtm_id); ?>"
                    placeholder="GTM-XXXXXXX" class="regular-text" />
                <p class="description">Insira o ID do seu contêiner do Google Tag Manager.</p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">Quais formulários rastrear?</th>
            <td>
                <label>
                    <input type="checkbox" name="bridge_active_forms[]" value="generic"
                        <?php checked(in_array('generic', $active_forms)); ?> />
                    <strong>Formulários Genéricos (HTML)</strong>
                </label><br>
                <label>
                    <input type="checkbox" name="bridge_active_forms[]" value="wpforms"
                        <?php checked(in_array('wpforms', $active_forms)); ?> />
                    <strong>WPForms</strong>
                </label><br>
                <label>
                    <input type="checkbox" name="bridge_active_forms[]" value="cf7"
                        <?php checked(in_array('cf7', $active_forms)); ?> />
                    <strong>Contact Form 7</strong>
                </label>
                <p class="description">Selecione os tipos de formulário que deseja rastrear no GTM, Facebook (CAPI) e Google Analytics (MP).</p>
            </td>
        </tr>
    </table>
<?php
}
add_action('gtm_settings_page_after_fields', 'gtm_settings_page_html_fields');

// ============================================================
// 3. AVISO DE HTTPS (opcional)
// ============================================================
function gtm_https_notice()
{
    if (!is_ssl()) {
        echo '<div class="notice notice-warning"><p><strong>Atenção:</strong> Seu site está em <strong>HTTP</strong>. O hash SHA-256 do e‑mail (usado para rastreamento) só é gerado em conexões HTTPS. Ative SSL para garantir o funcionamento.</p></div>';
    }
}
add_action('gtm_settings_page_after_fields', 'gtm_https_notice', 5);

// ============================================================
// 4. INJEÇÃO DOS SCRIPTS DO GTM NO HEAD E BODY
// ============================================================
function gtm_add_head_script()
{
    $gtm_id = get_option('gtm_container_id');
    if (empty($gtm_id)) return;
?>
    <!-- Google Tag Manager -->
    <script>
        (function(w, d, s, l, i) {
            w[l] = w[l] || [];
            w[l].push({
                'gtm.start': new Date().getTime(),
                event: 'gtm.js'
            });
            var f = d.getElementsByTagName(s)[0],
                j = d.createElement(s),
                dl = l != 'dataLayer' ? '&l=' + l : '';
            j.async = true;
            j.src = 'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
            f.parentNode.insertBefore(j, f);
        })(window, document, 'script', 'dataLayer', '<?php echo esc_js($gtm_id); ?>');
    </script>
    <!-- End Google Tag Manager -->
<?php
}
add_action('wp_head', 'gtm_add_head_script', 0);

function gtm_add_body_script()
{
    $gtm_id = get_option('gtm_container_id');
    if (empty($gtm_id)) return;
?>
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr($gtm_id); ?>"
            height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
<?php
}
if (function_exists('wp_body_open')) {
    add_action('wp_body_open', 'gtm_add_body_script', 0);
} else {
    add_action('wp_footer', 'gtm_add_body_script', 0);
}

// ============================================================
// 5. ENFILEIRAMENTO DOS SCRIPTS DE EVENTOS (usando bridge_active_forms)
// ============================================================
function gtm_enqueue_event_scripts()
{
    $gtm_id = get_option('gtm_container_id');
    if (empty($gtm_id)) return;

    $active_forms = get_option('bridge_active_forms', []);
    if (!is_array($active_forms)) $active_forms = [];

    $module_url = plugin_dir_url(__FILE__);

    // Genérico
    if (in_array('generic', $active_forms)) {
        wp_enqueue_script('gtm-events', $module_url . 'js/gtm-events.js', [], '1.1', true);
    }

    // Contact Form 7
    if (in_array('cf7', $active_forms) && function_exists('wpcf7')) {
        wp_enqueue_script('gtm-cf7', $module_url . 'js/gtm-cf7.js', [], '1.1', true);
    }

    // WPForms
    if (in_array('wpforms', $active_forms) && class_exists('WPForms')) {
        wp_enqueue_script('gtm-wpforms', $module_url . 'js/gtm-wpforms.js', ['jquery'], '1.1', true);
    }
}
add_action('wp_enqueue_scripts', 'gtm_enqueue_event_scripts');

// ============================================================
// 6. SCRIPT DE COMPATIBILIDADE PARA WPForms (redireciona evento)
// ============================================================
function gtm_add_wpforms_compatibility()
{
    $active_forms = get_option('bridge_active_forms', []);
    if (!is_array($active_forms) || !in_array('wpforms', $active_forms) || !function_exists('wpforms')) {
        return;
    }
?>
    <script>
        (function($) {
            // Escuta o evento que o WPForms realmente dispara (com underscore)
            $(document).on('wpforms_ajax_submit_success', function(event, response) {
                // Dispara o mesmo evento com o nome que seu script original espera
                $(event.target).trigger('wpformsAjaxSubmitSuccess', response);
            });
        })(jQuery);
    </script>
<?php
}
add_action('wp_footer', 'gtm_add_wpforms_compatibility', 20);
