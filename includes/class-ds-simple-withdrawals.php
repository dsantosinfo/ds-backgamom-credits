<?php
/**
 * Sistema Simples de Saques
 */

if (!defined('ABSPATH')) {
    exit;
}

class DS_Simple_Withdrawals {
    
    public function __construct() {
        add_action('init', [$this, 'init']);
    }
    
    public function init() {
        // Adicionar página de saques ao menu do usuário
        add_action('wp_ajax_request_withdrawal', [$this, 'handle_withdrawal_request']);
        add_action('wp_ajax_nopriv_request_withdrawal', [$this, 'handle_withdrawal_request']);
        
        // Shortcode para formulário de saque
        add_shortcode('ds_withdrawal_form', [$this, 'withdrawal_form_shortcode']);
    }
    
    /**
     * Shortcode para exibir formulário de saque
     */
    public function withdrawal_form_shortcode() {
        if (!is_user_logged_in()) {
            return '<p>Você precisa estar logado para solicitar saques.</p>';
        }
        
        $user_id = get_current_user_id();
        $balance = DS_Credit_Manager::get_balance($user_id);
        $min_withdrawal = get_option('ds_backgamom_credits_settings')['min_withdrawal'] ?? 10;
        
        ob_start();
        ?>
        <div class="ds-withdrawal-form">
            <h3>Solicitar Saque</h3>
            
            <div class="balance-info">
                <p><strong>Seu saldo atual:</strong> <?php echo number_format($balance); ?> créditos</p>
                <p><small>Saque mínimo: <?php echo number_format($min_withdrawal); ?> créditos</small></p>
            </div>
            
            <?php if ($balance >= $min_withdrawal): ?>
                <form id="withdrawal-form" method="post">
                    <table class="form-table">
                        <tr>
                            <th><label for="amount">Valor do Saque</label></th>
                            <td>
                                <input type="number" id="amount" name="amount" min="<?php echo $min_withdrawal; ?>" max="<?php echo $balance; ?>" required>
                                <small>créditos</small>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="method">Método de Pagamento</label></th>
                            <td>
                                <select id="method" name="method" required>
                                    <option value="">Selecione...</option>
                                    <?php
                                    // PIX apenas para usuários do Brasil
                                    $user_country = '';
                                    if ( function_exists( 'get_field' ) ) {
                                        $user_country = get_field( 'user_country', 'user_' . $user_id );
                                    }
                                    if ( empty( $user_country ) ) {
                                        $user_country = get_user_meta( $user_id, 'billing_country', true );
                                    }
                                    
                                    if ( $user_country === 'BR' ) {
                                        echo '<option value="Pix">PIX (Brasil)</option>';
                                    }
                                    ?>
                                    <option value="Wise">Wise (Internacional)</option>
                                </select>
                                <?php if ( $user_country !== 'BR' ): ?>
                                    <small style="color: #666;">PIX disponível apenas para usuários do Brasil</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="notes">Observações</label></th>
                            <td><textarea id="notes" name="notes" rows="3"></textarea></td>
                        </tr>
                    </table>
                    
                    <p>
                        <button type="submit" class="button button-primary">Solicitar Saque</button>
                    </p>
                </form>
            <?php else: ?>
                <p>Você precisa ter pelo menos <?php echo number_format($min_withdrawal); ?> créditos para solicitar um saque.</p>
                <p><a href="<?php echo wc_get_page_permalink('shop'); ?>" class="button">Comprar Créditos</a></p>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#withdrawal-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = {
                    action: 'request_withdrawal',
                    amount: $('#amount').val(),
                    method: $('#method').val(),
                    notes: $('#notes').val(),
                    nonce: '<?php echo wp_create_nonce('withdrawal_request'); ?>'
                };
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        console.log('Response:', response);
                        try {
                            if (typeof response === 'string') {
                                response = JSON.parse(response);
                            }
                            if (response.success) {
                                alert('Solicitação de saque enviada com sucesso!');
                                location.reload();
                            } else {
                                alert('Erro: ' + (response.data || 'Erro desconhecido'));
                            }
                        } catch (e) {
                            console.error('Parse error:', e);
                            console.error('Raw response:', response);
                            alert('Erro: Resposta inválida do servidor');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        console.error('Response:', xhr.responseText);
                        alert('Erro de comunicação: ' + error);
                    }
                });
            });
        });
        </script>
        
        <style>
        .ds-withdrawal-form { max-width: 600px; margin: 20px 0; }
        .balance-info { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .form-table th { width: 150px; }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Processa solicitação de saque via AJAX
     */
    public function handle_withdrawal_request() {
        header('Content-Type: application/json');
        
        if (!wp_verify_nonce($_POST['nonce'], 'withdrawal_request')) {
            wp_die(json_encode(['success' => false, 'data' => 'Nonce inválido']));
        }
        
        if (!is_user_logged_in()) {
            wp_die(json_encode(['success' => false, 'data' => 'Usuário não logado']));
        }
        
        $user_id = get_current_user_id();
        $amount = floatval($_POST['amount']);
        $method = sanitize_text_field($_POST['method']);
        $notes = sanitize_textarea_field($_POST['notes']);
        
        // Validações
        $balance = DS_Credit_Manager::get_balance($user_id);
        $min_withdrawal = get_option('ds_backgamom_credits_settings')['min_withdrawal'] ?? 10;
        
        if ($amount < $min_withdrawal) {
            wp_die(json_encode(['success' => false, 'data' => 'Valor mínimo não atingido']));
        }
        
        if ($amount > $balance) {
            wp_die(json_encode(['success' => false, 'data' => 'Saldo insuficiente']));
        }
        
        if (!in_array($method, ['Pix', 'Wise'])) {
            wp_die(json_encode(['success' => false, 'data' => 'Método inválido']));
        }
        
        // Validar dados do método
        if ($method == 'Pix') {
            // Verificar se usuário é do Brasil
            $user_country = '';
            if ( function_exists( 'get_field' ) ) {
                $user_country = get_field( 'user_country', 'user_' . $user_id );
            }
            if ( empty( $user_country ) ) {
                $user_country = get_user_meta( $user_id, 'billing_country', true );
            }
            
            if ( $user_country !== 'BR' ) {
                wp_die(json_encode(['success' => false, 'data' => 'PIX disponível apenas para usuários do Brasil']));
            }
            
            $pix_key = get_field('user_pix', 'user_' . $user_id);
            if (empty($pix_key)) {
                wp_die(json_encode(['success' => false, 'data' => 'Cadastre sua chave PIX no perfil']));
            }
        } elseif ($method == 'Wise') {
            $wise_email = get_field('user_wise', 'user_' . $user_id);
            if (empty($wise_email)) {
                wp_die(json_encode(['success' => false, 'data' => 'Cadastre seu email Wise no perfil']));
            }
        }
        
        // Criar solicitação
        try {
            $request_id = $this->create_withdrawal_request($user_id, $amount, $method, $notes);
            
            if ($request_id) {
                $this->notify_user_request_created($user_id, $amount);
                $this->notify_admin_new_request($request_id, $user_id, $amount, $method);
                wp_die(json_encode(['success' => true, 'data' => 'Solicitação criada com sucesso']));
            } else {
                global $wpdb;
                error_log('DS Withdrawal Error: Failed to create request. Last error: ' . $wpdb->last_error);
                wp_die(json_encode(['success' => false, 'data' => 'Erro ao salvar solicitação no banco de dados']));
            }
        } catch (Exception $e) {
            error_log('DS Withdrawal Exception: ' . $e->getMessage());
            error_log('DS Withdrawal Exception Stack: ' . $e->getTraceAsString());
            wp_die(json_encode(['success' => false, 'data' => 'Erro interno: ' . $e->getMessage()]));
        }
    }
    
    /**
     * Cria solicitação de saque no banco
     */
    private function create_withdrawal_request($user_id, $amount, $method, $notes) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ds_withdrawal_requests';
        
        // Criar tabela se não existir
        $this->create_withdrawal_table();
        
        $result = $wpdb->insert(
            $table_name,
            [
                'user_id' => $user_id,
                'amount' => $amount,
                'method' => $method,
                'notes' => $notes,
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ],
            ['%d', '%f', '%s', '%s', '%s', '%s']
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Cria tabela de solicitações
     */
    private function create_withdrawal_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ds_withdrawal_requests';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            amount decimal(10,2) NOT NULL,
            method varchar(50) NOT NULL,
            notes text,
            status varchar(20) DEFAULT 'pending',
            processed_by int(11),
            processed_at datetime,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Notifica usuário sobre criação da solicitação
     */
    private function notify_user_request_created($user_id, $amount) {
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $user_name = $user->first_name ?: $user->display_name;
        
        if (class_exists('DS_Notification_i18n')) {
            DS_Notification_i18n::send($user_id, 'withdrawal_request_created', [
                'name' => $user_name,
                'amount' => number_format($amount, 2),
                'priority' => 'high'
            ]);
        }
    }
    
    /**
     * Notifica admin sobre nova solicitação
     */
    private function notify_admin_new_request($request_id, $user_id, $amount, $method) {
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $payment_info = '';
        if ($method == 'Pix') {
            $pix_key = get_field('user_pix', 'user_' . $user_id);
            $payment_info = "\n🔑 *Chave PIX:* " . ($pix_key ?: 'NÃO CADASTRADA!');
        } elseif ($method == 'Wise') {
            $wise_email = get_field('user_wise', 'user_' . $user_id);
            $payment_info = "\n📧 *E-mail Wise:* " . ($wise_email ?: 'NÃO CADASTRADO!');
        }
        
        if (class_exists('DS_Notification_i18n')) {
            DS_Notification_i18n::send_admin('admin_new_withdrawal', [
                'request_id' => $request_id,
                'user_name' => $user->display_name,
                'user_id' => $user_id,
                'amount' => number_format($amount, 2, ',', '.'),
                'method' => $method,
                'payment_info' => $payment_info,
                'priority' => 'high'
            ]);
        }
    }
    
    /**
     * Aprova solicitação de saque
     */
    public static function approve_withdrawal($request_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ds_withdrawal_requests';
        $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $request_id));
        
        if (!$request || $request->status !== 'pending') {
            return false;
        }
        
        // Deduzir créditos
        if (class_exists('DS_Credit_Manager')) {
            $success = DS_Credit_Manager::deduct_credits($request->user_id, $request->amount, "Saque aprovado #$request_id");
            
            if ($success) {
                // Atualizar status
                $wpdb->update(
                    $table_name,
                    [
                        'status' => 'approved',
                        'processed_by' => get_current_user_id(),
                        'processed_at' => current_time('mysql')
                    ],
                    ['id' => $request_id],
                    ['%s', '%d', '%s'],
                    ['%d']
                );
                
                // Notificar usuário
                self::notify_user_status_change($request->user_id, $request->amount, 'approved');
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Rejeita solicitação de saque
     */
    public static function reject_withdrawal($request_id, $reason = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ds_withdrawal_requests';
        $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $request_id));
        
        if (!$request || $request->status !== 'pending') {
            return false;
        }
        
        // Atualizar status
        $wpdb->update(
            $table_name,
            [
                'status' => 'rejected',
                'notes' => $request->notes . "\n\nMotivo da rejeição: " . $reason,
                'processed_by' => get_current_user_id(),
                'processed_at' => current_time('mysql')
            ],
            ['id' => $request_id],
            ['%s', '%s', '%d', '%s'],
            ['%d']
        );
        
        // Notificar usuário
        self::notify_user_status_change($request->user_id, $request->amount, 'rejected');
        return true;
    }
    
    /**
     * Notifica usuário sobre mudança de status
     */
    public static function notify_user_status_change($user_id, $amount, $status) {
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $user_name = $user->first_name ?: $user->display_name;
        
        if (class_exists('DS_Notification_i18n')) {
            if ($status === 'approved') {
                DS_Notification_i18n::send($user_id, 'withdrawal_approved', [
                    'name' => $user_name,
                    'amount' => number_format($amount, 2),
                    'priority' => 'critical'
                ]);
            } elseif ($status === 'rejected') {
                DS_Notification_i18n::send($user_id, 'withdrawal_rejected', [
                    'name' => $user_name,
                    'amount' => number_format($amount, 2),
                    'reason' => 'Não especificado',
                    'priority' => 'critical'
                ]);
            }
        }
    }
    

}