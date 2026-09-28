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
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => ''
    ]);
    register_setting('gtm_options_group', 'bridge_active_forms', [
        'type'    => 'array',
        'default' => []
    ]);
    register_setting('gtm_options_group', 'gtm_disable_hash', [
        'type'    => 'string',
        'default' => '0'
    ]);
    register_setting('gtm_options_group', 'gtm_default_ddi', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '55'
    ]);
    // Campo de JS Customizado (sem sanitize para preservar o código intacto)
    register_setting('gtm_options_group', 'gtm_custom_js_code', [
        'type'    => 'string',
        'default' => ''
    ]);
}
add_action('admin_init', 'gtm_register_settings');

// ============================================================
// 1.1 LÊ O CONTEÚDO DO gtm-events.js COMO TEMPLATE PADRÃO
// ============================================================
function gtm_get_generic_js_template()
{
    $path = plugin_dir_path(__FILE__) . 'js/gtm-events.js';
    if (!file_exists($path)) {
        return "// Arquivo js/gtm-events.js não encontrado.\n// Escreva seu código aqui.";
    }
    return file_get_contents($path);
}

// ============================================================
// 2. ADICIONA OS CAMPOS NA PÁGINA DE CONFIGURAÇÃO (via hook)
// ============================================================
function gtm_settings_page_html_fields()
{
    $gtm_id       = get_option('gtm_container_id', '');
    $active_forms = get_option('bridge_active_forms', []);
    $disable_hash = get_option('gtm_disable_hash', '0');
    $default_ddi  = get_option('gtm_default_ddi', '55');
    $custom_js    = get_option('gtm_custom_js_code', '');

    if (!is_array($active_forms)) $active_forms = [];

    // Se ainda não houver nada salvo, mostra o genérico como template inicial
    if (empty(trim($custom_js))) {
        $custom_js = gtm_get_generic_js_template();
    }

    $generic_on = in_array('generic', $active_forms, true);
    $custom_on  = in_array('custom',  $active_forms, true);
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
                        <?php checked($generic_on); ?> />
                    <strong>Formulários Genéricos (HTML)</strong>
                </label><br>
                <label>
                    <input type="checkbox" name="bridge_active_forms[]" value="wpforms"
                        <?php checked(in_array('wpforms', $active_forms, true)); ?> />
                    <strong>WPForms</strong>
                </label><br>
                <label>
                    <input type="checkbox" name="bridge_active_forms[]" value="cf7"
                        <?php checked(in_array('cf7', $active_forms, true)); ?> />
                    <strong>Contact Form 7</strong>
                </label><br>
                <label>
                    <input type="checkbox" name="bridge_active_forms[]" value="elementor"
                        <?php checked(in_array('elementor', $active_forms, true)); ?> />
                    <strong>Elementor Pro Forms</strong>
                </label><br>
                <label>
                    <input type="checkbox" name="bridge_active_forms[]" value="custom"
                        <?php checked($custom_on); ?> />
                    <strong>JS Customizado</strong>
                    <em>(usa apenas o código abaixo — não carrega o genérico automaticamente)</em>
                </label>
                <p class="description">Selecione os tipos de formulário que deseja rastrear no GTM, Facebook (CAPI) e Google Analytics (MP).</p>
            </td>
        </tr>
    </table>

    <?php if ($generic_on && $custom_on): ?>
        <div class="notice notice-warning inline" style="margin: 10px 0;">
            <p><strong>Atenção:</strong> "Formulários Genéricos" e "JS Customizado" estão ambos marcados.
                Se o seu JS customizado já contém a lógica do genérico (por exemplo, foi pré-preenchido com o template),
                você terá <strong>eventos duplicados no dataLayer</strong>. Desmarque "Genérico" ou remova
                a parte duplicada do seu JS.</p>
        </div>
    <?php endif; ?>

    <h2>JS Customizado</h2>
    <table class="form-table">
        <tr valign="top">
            <th scope="row"><label for="gtm_custom_js_code">Seu código JavaScript</label></th>
            <td>
                <textarea id="gtm_custom_js_code" name="gtm_custom_js_code" rows="18"
                    style="width:100%;font-family:monospace;font-size:13px;background:#f6f7f7;"
                    placeholder="// Escreva seu código aqui. Ex.:&#10;document.addEventListener('meuEventoCustom', function (e) {&#10;    bridgeBuildEventData({&#10;        email: e.detail.email,&#10;        phone: e.detail.phone,&#10;        form_name: 'Meu Form Personalizado',&#10;        form_id: 'custom-form-1'&#10;    });&#10;});"><?php echo esc_textarea($custom_js); ?></textarea>

                <p class="description">
                    Este código roda <strong>depois</strong> do helpers/genérico, então você tem acesso a:<br>
                    <code>bridgeBuildEventData(options)</code> — empurra o evento completo no dataLayer (GTM + Meta + Google Ads)<br>
                    <code>getSha256(str)</code> — gera hash SHA-256<br>
                    <code>formatPhoneE164(raw)</code> — normaliza telefone para E.164<br>
                    <code>phoneForHash(raw)</code> — telefone pronto para hash (sem <code>+</code>)<br>
                    <code>bridgeGenerateEventId(prefix)</code> — gera <code>event_id</code> único
                </p>

                <textarea id="bridge-template-src" style="display:none;"><?php echo esc_textarea(gtm_get_generic_js_template()); ?></textarea>
                <button type="button" class="button button-secondary" id="bridge-restore-template" style="margin-top:8px;">
                    ↩ Restaurar template genérico
                </button>
            </td>
        </tr>
    </table>

    <script>
        (function() {
            var btn = document.getElementById('bridge-restore-template');
            var src = document.getElementById('bridge-template-src');
            if (!btn || !src) return;
            btn.addEventListener('click', function() {
                if (!confirm('Substituir o código atual pelo template do genérico? (não salva até você clicar em Salvar Alterações)')) return;
                document.getElementById('gtm_custom_js_code').value = src.value;
            });
        })();
    </script>
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
// 5. ENFILEIRAMENTO DOS SCRIPTS DE EVENTOS
// ============================================================
function gtm_enqueue_event_scripts()
{
    $gtm_id = get_option('gtm_container_id');
    if (empty($gtm_id)) return;

    $active_forms = get_option('bridge_active_forms', []);
    if (!is_array($active_forms)) $active_forms = [];

    $module_url = plugin_dir_url(__FILE__);

    // 1. Helpers (sempre que houver algum módulo ativo — dependência de todos)
    wp_register_script('gtm-helpers', $module_url . 'js/gtm-helpers.js', [], '1.1', true);
    wp_localize_script('gtm-helpers', 'bridge_settings', [
        'default_ddi'  => get_option('gtm_default_ddi', '55'),
        'disable_hash' => get_option('gtm_disable_hash', '0')
    ]);
    wp_enqueue_script('gtm-helpers');

    // 2. Genérico (só quando "generic" estiver marcado)
    if (in_array('generic', $active_forms, true)) {
        wp_enqueue_script('gtm-events', $module_url . 'js/gtm-events.js', ['gtm-helpers'], '1.1', true);
    }

    if (in_array('cf7', $active_forms, true) && function_exists('wpcf7')) {
        wp_enqueue_script('gtm-cf7', $module_url . 'js/gtm-cf7.js', ['gtm-helpers'], '1.1', true);
    }

    if (in_array('wpforms', $active_forms, true) && class_exists('WPForms')) {
        wp_enqueue_script('gtm-wpforms', $module_url . 'js/gtm-wpforms.js', ['gtm-helpers'], '1.1', true);
    }

    if (in_array('elementor', $active_forms, true) && defined('ELEMENTOR_PRO_VERSION')) {
        wp_enqueue_script('gtm-elementor', $module_url . 'js/gtm-elementor.js', ['gtm-helpers'], '1.1', true);
    }
}
add_action('wp_enqueue_scripts', 'gtm_enqueue_event_scripts');

// ============================================================
// 5.1 INJEÇÃO DO JS CUSTOMIZADO (rodapé, depois do gtm-events)
// ============================================================
function gtm_output_custom_js()
{
    $active_forms = get_option('bridge_active_forms', []);
    if (!is_array($active_forms) || !in_array('custom', $active_forms, true)) return;

    $code = get_option('gtm_custom_js_code', '');
    if (empty(trim($code))) return;

    // Evita quebra prematura do <script> caso o usuário cole "</script>" no código
    $code = str_replace('</script>', '<\/script>', $code);
?>
    <!-- Bridge Tracker - Custom JS -->
    <script>
        <?php echo $code; ?>
    </script>
    <!-- /Bridge Tracker - Custom JS -->
<?php
}
add_action('wp_footer', 'gtm_output_custom_js', 25);

// ============================================================
// 6. PONTE DE COMPATIBILIDADE JQUERY -> NATIVO
// ============================================================
function gtm_add_events_compatibility()
{
    $active_forms = get_option('bridge_active_forms', []);
    if (!is_array($active_forms)) return;
?>
    <script>
        (function() {
            if (typeof jQuery === 'undefined') return;

            // ---------- WPForms ----------
            if (<?php echo in_array('wpforms', $active_forms, true) ? 'true' : 'false'; ?>) {
                jQuery(document).on('wpformsAjaxSubmitSuccess', function(e, response, $form) {
                    var formEl = $form && $form[0] ? $form[0] : null;
                    if (!formEl) return;
                    document.dispatchEvent(new CustomEvent('wpformsAjaxSubmitSuccess_native', {
                        detail: {
                            form: formEl,
                            response: response
                        }
                    }));
                });
            }

            // ---------- Elementor Pro ----------
            if (<?php echo in_array('elementor', $active_forms, true) ? 'true' : 'false'; ?>) {
                jQuery(document).on('submit_success', function(e, response) {
                    var formEl = e.target;
                    document.dispatchEvent(new CustomEvent('elementorSubmitSuccess_native', {
                        detail: {
                            form: formEl,
                            response: response
                        }
                    }));
                });
            }
        })();
    </script>
<?php
}
add_action('wp_footer', 'gtm_add_events_compatibility', 20);
