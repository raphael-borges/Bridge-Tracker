<?php

/**
 * Plugin Name: Bridge Tracker
 * Description: Gerenciador de pixels e APIs de conversão (Meta e GA4) com rastreamento de UTMs.
 * Version: 1.0.1
 * Author: Raphael
 */

if (!defined('ABSPATH')) exit;

// Menu e Página de Configuração Geral do Painel
function bridge_add_admin_menu()
{
    add_options_page(
        'Bridge Tracker Configurações',
        'Bridge Tracker',
        'manage_options',
        'bridge-tracker',
        'bridge_options_page_html'
    );
}
add_action('admin_menu', 'bridge_add_admin_menu');

function bridge_options_page_html()
{
    if (!current_user_can('manage_options')) return;
?>
    <div class="wrap">
        <h1>Bridge Tracker - Configurações de Rastreamento</h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('gtm_options_group');
            do_settings_sections('gtm_options_group');

            // Gancho para os módulos injetarem seus campos na tela
            do_action('gtm_settings_page_after_fields');

            submit_button('Salvar Alterações');
            ?>
        </form>
    </div>
<?php
}

// Carrega os módulos do plugin
require_once plugin_dir_path(__FILE__) . 'modulo-gtm.php';
require_once plugin_dir_path(__FILE__) . 'modulo-facebook.php';
require_once plugin_dir_path(__FILE__) . 'modulo-google-analytics.php';
