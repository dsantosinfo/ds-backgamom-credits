<?php
/**
 * Debug de Notificações
 * Arquivo temporário para testar o sistema de notificações
 */

// Previne acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Adiciona menu de debug no admin
add_action('admin_menu', function() {
    add_submenu_page(
        'tools.php',
        'Debug Notificações',
        'Debug Notificações',
        'manage_options',
        'debug-notifications',
        'dsbc_debug_notifications_page'
    );
});

function dsbc_debug_notifications_page() {
    if (isset($_POST['test_notification'])) {
        $user_id = intval($_POST['user_id']);
        $amount = floatval($_POST['amount']);
        
        echo '<div class="notice notice-info"><p>Testando notificação...</p></div>';
        
        // Teste 1: Verificar se as classes existem
        echo '<h3>1. Verificação de Classes:</h3>';
        echo '<p>DS_Notification_i18n: ' . (class_exists('DS_Notification_i18n') ? '✅ Existe' : '❌ Não existe') . '</p>';
        echo '<p>WhatsApp_Connector: ' . (class_exists('WhatsApp_Connector') ? '✅ Existe' : '❌ Não existe') . '</p>';
        
        // Teste 2: Verificar telefone do usuário
        echo '<h3>2. Verificação de Telefone:</h3>';
        $phone = null;
        if (function_exists('get_field')) {
            $phone = get_field('user_whatsapp', 'user_' . $user_id);
            echo '<p>ACF user_whatsapp: ' . ($phone ? $phone : 'Não encontrado') . '</p>';
        }
        if (empty($phone)) {
            $phone = get_user_meta($user_id, 'billing_phone', true);
            echo '<p>billing_phone: ' . ($phone ? $phone : 'Não encontrado') . '</p>';
        }
        
        // Teste 3: Tentar enviar notificação
        echo '<h3>3. Teste de Envio:</h3>';
        if (class_exists('DS_Notification_i18n')) {
            $result = DS_Notification_i18n::send($user_id, 'deposit', [
                'name' => 'Teste',
                'amount' => number_format($amount, 2),
                'balance' => number_format($amount + 100, 2),
                'priority' => 'high'
            ]);
            echo '<p>Resultado do envio: ' . ($result ? '✅ Sucesso' : '❌ Falha') . '</p>';
        }
        
        // Teste 4: Verificar logs de erro
        echo '<h3>4. Logs de Erro:</h3>';
        $log_file = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($log_file)) {
            $logs = file_get_contents($log_file);
            $recent_logs = array_slice(explode("\n", $logs), -20);
            foreach ($recent_logs as $log) {
                if (strpos($log, 'DS Notification') !== false) {
                    echo '<p style="color: red;">' . esc_html($log) . '</p>';
                }
            }
        } else {
            echo '<p>Arquivo de log não encontrado</p>';
        }
    }
    
    ?>
    <div class="wrap">
        <h1>Debug de Notificações</h1>
        
        <form method="post">
            <table class="form-table">
                <tr>
                    <th><label for="user_id">ID do Usuário:</label></th>
                    <td><input type="number" name="user_id" id="user_id" value="1" required></td>
                </tr>
                <tr>
                    <th><label for="amount">Valor (USD):</label></th>
                    <td><input type="number" name="amount" id="amount" value="10.50" step="0.01" required></td>
                </tr>
            </table>
            
            <?php submit_button('Testar Notificação', 'primary', 'test_notification'); ?>
        </form>
        
        <hr>
        
        <h2>Verificação Rápida do Sistema</h2>
        <p><strong>Plugin WhatsApp Connector:</strong> <?php echo (class_exists('WhatsApp_Connector') ? '✅ Ativo' : '❌ Inativo'); ?></p>
        <p><strong>DS_Notification_i18n:</strong> <?php echo (class_exists('DS_Notification_i18n') ? '✅ Carregado' : '❌ Não carregado'); ?></p>
        <p><strong>DS_Credit_Manager:</strong> <?php echo (class_exists('DS_Credit_Manager') ? '✅ Carregado' : '❌ Não carregado'); ?></p>
        
        <?php if (class_exists('DS_Notification_i18n')): ?>
        <h3>Templates Disponíveis:</h3>
        <?php 
        $templates = DS_Notification_i18n::get_all_templates();
        foreach ($templates as $type => $langs) {
            echo '<p><strong>' . $type . ':</strong> ' . implode(', ', array_keys($langs)) . '</p>';
        }
        ?>
        <?php endif; ?>
    </div>
    <?php
}