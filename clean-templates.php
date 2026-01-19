<?php
/**
 * Script para limpar templates de notificação antigos
 * Execute uma vez para remover templates com "créditos"
 */

// Previne acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Adiciona ação AJAX para limpar templates
add_action('wp_ajax_dsbc_clean_notification_templates', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Sem permissão');
    }
    
    // Remove templates salvos para forçar uso dos padrões
    delete_option('ds_notification_templates');
    
    wp_send_json_success('Templates limpos com sucesso! Agora serão usados os templates padrão sem "créditos".');
});

// Adiciona menu temporário no admin
add_action('admin_menu', function() {
    add_submenu_page(
        'tools.php',
        'Limpar Templates',
        'Limpar Templates',
        'manage_options',
        'clean-templates',
        function() {
            ?>
            <div class="wrap">
                <h1>Limpar Templates de Notificação</h1>
                <p>Este script remove os templates salvos no banco de dados para forçar o uso dos novos templates padrão sem a palavra "créditos".</p>
                
                <button type="button" class="button button-primary" onclick="cleanTemplates()">Limpar Templates</button>
                
                <div id="result" style="margin-top: 20px;"></div>
                
                <script>
                function cleanTemplates() {
                    if (!confirm('Tem certeza que deseja limpar os templates? Esta ação não pode ser desfeita.')) {
                        return;
                    }
                    
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'dsbc_clean_notification_templates'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('result').innerHTML = 
                            '<div class="notice notice-' + (data.success ? 'success' : 'error') + '"><p>' + data.data + '</p></div>';
                    });
                }
                </script>
            </div>
            <?php
        }
    );
});