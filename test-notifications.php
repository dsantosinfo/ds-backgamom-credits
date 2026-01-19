<?php
/**
 * Teste de Notificações - DS Backgamom Credits
 * Arquivo temporário para testar o sistema
 */

// Previne acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Adiciona ação AJAX para teste
add_action('wp_ajax_dsbc_test_notification', 'dsbc_test_notification_ajax');

function dsbc_test_notification_ajax() {
    // Verificar permissões
    if (!current_user_can('manage_options')) {
        wp_die('Sem permissão');
    }
    
    $user_id = intval($_POST['user_id']);
    $amount = floatval($_POST['amount']);
    
    echo "<h3>Teste de Notificação</h3>";
    
    // 1. Verificar classes
    echo "<p><strong>Classes:</strong></p>";
    echo "<p>DS_Credit_Manager: " . (class_exists('DS_Credit_Manager') ? '✅' : '❌') . "</p>";
    echo "<p>DS_Notification_i18n: " . (class_exists('DS_Notification_i18n') ? '✅' : '❌') . "</p>";
    echo "<p>WhatsApp_Connector: " . (class_exists('WhatsApp_Connector') ? '✅' : '❌') . "</p>";
    
    // 2. Testar adição de créditos
    if (class_exists('DS_Credit_Manager')) {
        echo "<p><strong>Testando adição de créditos...</strong></p>";
        
        $old_balance = DS_Credit_Manager::get_balance($user_id);
        echo "<p>Saldo anterior: $ " . number_format($old_balance, 2) . "</p>";
        
        $result = DS_Credit_Manager::add_credits_manually($user_id, $amount, 'Teste de notificação', get_current_user_id());
        
        if ($result) {
            $new_balance = DS_Credit_Manager::get_balance($user_id);
            echo "<p>✅ Créditos adicionados com sucesso!</p>";
            echo "<p>Novo saldo: $ " . number_format($new_balance, 2) . "</p>";
        } else {
            echo "<p>❌ Falha ao adicionar créditos</p>";
        }
    }
    
    // 3. Verificar logs
    echo "<p><strong>Verificando logs recentes...</strong></p>";
    $log_file = WP_CONTENT_DIR . '/debug.log';
    if (file_exists($log_file)) {
        $logs = file_get_contents($log_file);
        $lines = explode("\n", $logs);
        $recent_logs = array_slice($lines, -30);
        
        $found_logs = false;
        foreach ($recent_logs as $log) {
            if (strpos($log, 'DS Notification') !== false || strpos($log, 'DS Credit Manager') !== false) {
                echo "<p style='font-size: 12px; color: #666;'>" . esc_html($log) . "</p>";
                $found_logs = true;
            }
        }
        
        if (!$found_logs) {
            echo "<p>Nenhum log encontrado nos últimos registros</p>";
        }
    } else {
        echo "<p>Arquivo de log não encontrado</p>";
    }
    
    wp_die();
}

// Adiciona menu de teste
add_action('admin_menu', function() {
    add_submenu_page(
        'tools.php',
        'Teste Notificações',
        'Teste Notificações',
        'manage_options',
        'test-notifications',
        'dsbc_test_notifications_page'
    );
});

function dsbc_test_notifications_page() {
    ?>
    <div class="wrap">
        <h1>Teste de Notificações</h1>
        
        <div id="test-form">
            <table class="form-table">
                <tr>
                    <th><label for="user_id">ID do Usuário:</label></th>
                    <td><input type="number" id="user_id" value="1" required></td>
                </tr>
                <tr>
                    <th><label for="amount">Valor (USD):</label></th>
                    <td><input type="number" id="amount" value="10.50" step="0.01" required></td>
                </tr>
            </table>
            
            <button type="button" class="button button-primary" onclick="testNotification()">Testar Adição de Créditos</button>
        </div>
        
        <div id="test-results" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; display: none;">
            <h3>Resultados do Teste:</h3>
            <div id="results-content"></div>
        </div>
    </div>
    
    <script>
    function testNotification() {
        var userId = document.getElementById('user_id').value;
        var amount = document.getElementById('amount').value;
        
        if (!userId || !amount) {
            alert('Preencha todos os campos');
            return;
        }
        
        var data = new FormData();
        data.append('action', 'dsbc_test_notification');
        data.append('user_id', userId);
        data.append('amount', amount);
        
        fetch(ajaxurl, {
            method: 'POST',
            body: data
        })
        .then(response => response.text())
        .then(html => {
            document.getElementById('results-content').innerHTML = html;
            document.getElementById('test-results').style.display = 'block';
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao executar teste');
        });
    }
    </script>
    <?php
}