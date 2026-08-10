<?php

/**
 * Plugin Name: Bridge Tracker (GTM & Meta CAPI)
 * Description: Gerencia o GTM, eventos de formulários no DataLayer e dispara a API de Conversões da Meta (CAPI) Server-Side com captura de UTMs.
 * Version:     1.0.0
 * Author:      Raphael Borges Silveira
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

if (!defined('ABSPATH')) {
    exit;
}
/**
 * 1. TELA DE CONFIGURAÇÕES (Admin)
 */
function gtm_add_admin_menu()
{
    add_options_page(
        'Configurações do GTM',
        'Tag Manager',
        'manage_options',
        'gtm-tracker-settings',
        'gtm_settings_page_html'
    );
}
add_action('admin_menu', 'gtm_add_admin_menu');

function gtm_register_settings()
{
    register_setting('gtm_options_group', 'gtm_container_id', [
        'type'              => 'string',
        'sanitize_callback' => 'gtm_sanitize_container_id',
        'default'           => ''
    ]);

    register_setting('gtm_options_group', 'gtm_track_generic', [
        'type'    => 'boolean',
        'default' => false
    ]);

    register_setting('gtm_options_group', 'gtm_track_wpforms', [
        'type'    => 'boolean',
        'default' => false
    ]);

    // Novo: Registra o Checkbox do Contact Form 7
    register_setting('gtm_options_group', 'gtm_track_cf7', [
        'type'    => 'boolean',
        'default' => false
    ]);
}
add_action('admin_init', 'gtm_register_settings');

function gtm_sanitize_container_id($input)
{
    $clean = strtoupper(trim(sanitize_text_field($input)));
    if (!empty($clean) && !preg_match('/^GTM-[A-Z0-9]+$/', $clean)) {
        add_settings_error('gtm_container_id', 'gtm_id_error', 'O formato do ID do GTM parece inválido (Ex: GTM-XXXXXXX).', 'error');
    }
    return $clean;
}

function gtm_settings_page_html()
{
    if (!current_user_can('manage_options')) return;
?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('gtm_options_group');
            do_settings_sections('gtm_options_group');

            $gtm_id = get_option('gtm_container_id', '');
            $track_generic = get_option('gtm_track_generic', false);
            $track_wpforms = get_option('gtm_track_wpforms', false);
            $track_cf7 = get_option('gtm_track_cf7', false);
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="gtm_container_id">ID do Contêiner GTM</label></th>
                    <td>
                        <input type="text" id="gtm_container_id" name="gtm_container_id" value="<?php echo esc_attr($gtm_id); ?>" placeholder="GTM-XXXXXXX" class="regular-text" />
                        <p class="description">Insira o ID do seu contêiner do Google Tag Manager.</p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">Quais formulários rastrear?</th>
                    <td>
                        <fieldset>
                            <label for="gtm_track_generic">
                                <input type="checkbox" id="gtm_track_generic" name="gtm_track_generic" value="1" <?php checked(1, $track_generic, true); ?> />
                                <strong>Formulários Genéricos (HTML padrão)</strong>
                            </label>
                            <p class="description" style="margin-bottom: 15px;">Captura envios de formulários comuns e barras de pesquisa nativas do WordPress.</p>

                            <label for="gtm_track_wpforms">
                                <input type="checkbox" id="gtm_track_wpforms" name="gtm_track_wpforms" value="1" <?php checked(1, $track_wpforms, true); ?> />
                                <strong>WPForms (Eventos AJAX)</strong>
                            </label>
                            <p class="description" style="margin-bottom: 15px;">Dispara o evento quando o WPForms confirma um envio bem-sucedido.</p>

                            <label for="gtm_track_cf7">
                                <input type="checkbox" id="gtm_track_cf7" name="gtm_track_cf7" value="1" <?php checked(1, $track_cf7, true); ?> />
                                <strong>Contact Form 7 (Eventos AJAX)</strong>
                            </label>
                            <p class="description">Dispara o evento apenas quando o CF7 valida os campos e envia a mensagem com sucesso (<code>wpcf7mailsent</code>).</p>
                        </fieldset>
                    </td>
                </tr>
            </table>
            <!-- Injeta os campos do Facebook aqui -->
            <?php do_action('gtm_settings_page_after_fields'); ?>
            <?php submit_button('Salvar Configurações'); ?>
        </form>
    </div>
<?php
}

/**
 * 2. CÓDIGOS DO GTM NO FRONTEND
 */
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
            j.src =
                'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
            f.parentNode.insertBefore(j, f);
        })(window, document, 'script', 'dataLayer', '<?php echo esc_js($gtm_id); ?>');
    </script>
    <!-- End Google Tag Manager -->
<?php
}
add_action('wp_head', 'gtm_add_head_script', 1);

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
add_action('wp_body_open', 'gtm_add_body_script', 1);

/**
 * 3. ENFILEIRAMENTO DOS SCRIPTS EXTERNOS DE EVENTOS (Condicionais)
 */
function gtm_enqueue_event_scripts()
{
    $gtm_id = get_option('gtm_container_id');
    if (empty($gtm_id)) return;

    $track_generic = get_option('gtm_track_generic');
    $track_wpforms = get_option('gtm_track_wpforms');
    $track_cf7 = get_option('gtm_track_cf7');

    if ($track_generic == '1') {
        wp_enqueue_script('gtm-form-events', plugin_dir_url(__FILE__) . 'js/gtm-events.js', array(), '1.1.0', true);
    }

    if ($track_wpforms == '1' && function_exists('wpforms')) {
        wp_enqueue_script('gtm-wpforms-events', plugin_dir_url(__FILE__) . 'js/gtm-wpforms.js', array('jquery'), '1.1.0', true);
    }

    // Carrega o script do CF7 apenas se a opção estiver ativa e o plugin estiver instalado
    if ($track_cf7 == '1' && defined('WPCF7_VERSION')) {
        wp_enqueue_script('gtm-cf7-events', plugin_dir_url(__FILE__) . 'js/gtm-cf7.js', array(), '1.0.0', true);
    }
}
add_action('wp_enqueue_scripts', 'gtm_enqueue_event_scripts');
require_once plugin_dir_path(__FILE__) . 'modulo-facebook.php';
