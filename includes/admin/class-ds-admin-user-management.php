<?php
/**
 * Gestão de Usuários - Sistema USD
 * 
 * @package DS_Backgamom_Credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DS_Admin_User_Management {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ], 40 );
        add_action( 'wp_ajax_ds_search_users', [ $this, 'ajax_search_users' ] );
        add_action( 'wp_ajax_ds_update_user_credits', [ $this, 'ajax_update_user_credits' ] );
        add_action( 'wp_ajax_ds_get_user_history', [ $this, 'ajax_get_user_history' ] );
    }

    /**
     * Adiciona menu administrativo
     */
    public function add_admin_menu() {
        add_submenu_page(
            'ds-backgamom-credits',
            'Gestão de Usuários',
            'Gestão de Usuários',
            'manage_options',
            'ds-user-management',
            [ $this, 'management_page' ]
        );
    }

    /**
     * Página de gestão
     */
    public function management_page() {
        ?>
        <div class="wrap">
            <h1>👥 Gestão de Usuários - Sistema USD</h1>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; max-width: 1400px;">
                
                <!-- Busca de Usuários -->
                <div class="card">
                    <h2>🔍 Buscar Usuário</h2>
                    
                    <div style="margin-bottom: 20px;">
                        <input type="text" 
                               id="user-search" 
                               placeholder="Digite nome, email ou ID do usuário..." 
                               style="width: 100%; padding: 10px; font-size: 14px;">
                    </div>
                    
                    <div id="search-results" style="max-height: 400px; overflow-y: auto;">
                        <p style="text-align: center; color: #666;">Digite para buscar usuários...</p>
                    </div>
                </div>

                <!-- Detalhes do Usuário -->
                <div class="card">
                    <h2>📊 Detalhes do Usuário</h2>
                    
                    <div id="user-details" style="display: none;">
                        <div id="user-info" style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                            <!-- Preenchido via JavaScript -->
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <h3>💰 Gerenciar Créditos</h3>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                                <div>
                                    <label>Valor (USD):</label>
                                    <input type="number" id="credit-amount" step="0.01" min="0" style="width: 100%;">
                                </div>
                                <div>
                                    <label>Ação:</label>
                                    <select id="credit-action" style="width: 100%;">
                                        <option value="add">Adicionar</option>
                                        <option value="deduct">Deduzir</option>
                                        <option value="set">Definir</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <label>Observação:</label>
                                <textarea id="credit-note" placeholder="Motivo da alteração..." style="width: 100%; height: 60px;"></textarea>
                            </div>
                            
                            <button type="button" class="button button-primary" onclick="updateUserCredits()">
                                Atualizar Créditos
                            </button>
                        </div>
                        
                        <div>
                            <h3>📋 Histórico Recente</h3>
                            <div id="user-history">
                                <!-- Preenchido via JavaScript -->
                            </div>
                        </div>
                    </div>
                    
                    <div id="no-user-selected" style="text-align: center; color: #666; padding: 40px;">
                        Selecione um usuário para ver os detalhes
                    </div>
                </div>
            </div>

            <!-- Estatísticas Gerais -->
            <div class="card" style="max-width: 1400px; margin-top: 20px;">
                <h2>📈 Estatísticas Gerais</h2>
                <?php $this->display_general_stats(); ?>
            </div>
        </div>

        <script>
        let searchTimeout;
        let selectedUserId = null;

        // Busca de usuários
        document.getElementById('user-search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                document.getElementById('search-results').innerHTML = '<p style="text-align: center; color: #666;">Digite pelo menos 2 caracteres...</p>';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                searchUsers(query);
            }, 300);
        });

        function searchUsers(query) {
            document.getElementById('search-results').innerHTML = '<p style="text-align: center;">Buscando...</p>';
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'ds_search_users',
                    query: query,
                    _ajax_nonce: '<?php echo wp_create_nonce( 'ds_search_users' ); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displaySearchResults(data.data);
                } else {
                    document.getElementById('search-results').innerHTML = '<p style="color: #dc3232;">Erro na busca</p>';
                }
            });
        }

        function displaySearchResults(users) {
            if (users.length === 0) {
                document.getElementById('search-results').innerHTML = '<p style="text-align: center; color: #666;">Nenhum usuário encontrado</p>';
                return;
            }
            
            let html = '<div style="space-y: 10px;">';
            users.forEach(user => {
                const balance = parseFloat(user.balance) || 0;
                const balanceBrl = balance * <?php echo DS_Credit_Converter::get_exchange_rate(); ?>;
                
                html += `
                    <div class="user-result" onclick="selectUser(${user.ID})" style="
                        padding: 10px; 
                        border: 1px solid #ddd; 
                        border-radius: 5px; 
                        cursor: pointer; 
                        margin-bottom: 10px;
                        transition: all 0.2s;
                    " onmouseover="this.style.backgroundColor='#f0f8ff'" onmouseout="this.style.backgroundColor='white'">
                        <div style="font-weight: bold;">${user.display_name}</div>
                        <div style="font-size: 0.9em; color: #666;">${user.user_email}</div>
                        <div style="font-size: 0.9em; color: #2271b1;">
                            ${balance.toFixed(2)} créditos (≈ R$ ${balanceBrl.toFixed(2)})
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            
            document.getElementById('search-results').innerHTML = html;
        }

        function selectUser(userId) {
            selectedUserId = userId;
            
            // Buscar detalhes do usuário
            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'ds_get_user_history',
                    user_id: userId,
                    _ajax_nonce: '<?php echo wp_create_nonce( 'ds_get_user_history' ); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayUserDetails(data.data);
                }
            });
        }

        function displayUserDetails(userData) {
            const balance = parseFloat(userData.balance) || 0;
            const balanceBrl = balance * <?php echo DS_Credit_Converter::get_exchange_rate(); ?>;
            
            document.getElementById('user-info').innerHTML = `
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <strong>${userData.display_name}</strong><br>
                        <small>${userData.user_email}</small><br>
                        <small>ID: ${userData.ID}</small>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 1.5em; font-weight: bold; color: #2271b1;">
                            ${balance.toFixed(2)} créditos
                        </div>
                        <div style="color: #666;">
                            ≈ R$ ${balanceBrl.toFixed(2)}
                        </div>
                    </div>
                </div>
            `;
            
            // Exibir histórico
            let historyHtml = '<div style="max-height: 300px; overflow-y: auto;">';
            if (userData.history.length === 0) {
                historyHtml += '<p style="text-align: center; color: #666;">Nenhuma transação encontrada</p>';
            } else {
                userData.history.forEach(transaction => {
                    const amount = parseFloat(transaction.amount);
                    const isPositive = amount > 0;
                    
                    historyHtml += `
                        <div style="
                            padding: 10px; 
                            border-left: 3px solid ${isPositive ? '#46b450' : '#dc3232'}; 
                            background: ${isPositive ? '#f0fff0' : '#fff0f0'}; 
                            margin-bottom: 10px;
                            border-radius: 0 5px 5px 0;
                        ">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong>${isPositive ? '+' : ''}${amount.toFixed(2)} créditos</strong><br>
                                    <small>${transaction.observation || transaction.type}</small>
                                </div>
                                <div style="text-align: right; font-size: 0.8em; color: #666;">
                                    ${new Date(transaction.created_at).toLocaleDateString('pt-BR')}
                                </div>
                            </div>
                        </div>
                    `;
                });
            }
            historyHtml += '</div>';
            
            document.getElementById('user-history').innerHTML = historyHtml;
            document.getElementById('user-details').style.display = 'block';
            document.getElementById('no-user-selected').style.display = 'none';
        }

        function updateUserCredits() {
            if (!selectedUserId) return;
            
            const amount = parseFloat(document.getElementById('credit-amount').value);
            const action = document.getElementById('credit-action').value;
            const note = document.getElementById('credit-note').value;
            
            if (!amount || amount <= 0) {
                alert('Digite um valor válido');
                return;
            }
            
            if (!note.trim()) {
                alert('Digite uma observação');
                return;
            }
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'ds_update_user_credits',
                    user_id: selectedUserId,
                    amount: amount,
                    credit_action: action,
                    note: note,
                    _ajax_nonce: '<?php echo wp_create_nonce( 'ds_update_user_credits' ); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Créditos atualizados com sucesso!');
                    selectUser(selectedUserId); // Recarregar dados
                    document.getElementById('credit-amount').value = '';
                    document.getElementById('credit-note').value = '';
                } else {
                    alert('Erro: ' + data.data);
                }
            });
        }
        </script>

        <style>
        .card { 
            background: #fff; 
            border: 1px solid #ccd0d4; 
            box-shadow: 0 1px 1px rgba(0,0,0,.04); 
            padding: 20px; 
            margin-bottom: 20px;
        }
        .card h2, .card h3 { 
            margin-top: 0; 
            color: #23282d; 
        }
        .user-result:hover {
            background-color: #f0f8ff !important;
            border-color: #2271b1 !important;
        }
        </style>
        <?php
    }

    /**
     * Exibe estatísticas gerais
     */
    private function display_general_stats() {
        global $wpdb;
        
        // Estatísticas básicas
        $total_users = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );
        $users_with_credits = $wpdb->get_var( 
            "SELECT COUNT(*) FROM {$wpdb->usermeta} 
             WHERE meta_key = '_dsbc_credit_balance' AND CAST(meta_value AS DECIMAL(10,2)) > 0" 
        );
        $total_credits = $wpdb->get_var( 
            "SELECT SUM(CAST(meta_value AS DECIMAL(10,2))) FROM {$wpdb->usermeta} 
             WHERE meta_key = '_dsbc_credit_balance'" 
        );
        
        // Top usuários
        $top_users = $wpdb->get_results(
            "SELECT u.ID, u.display_name, u.user_email, um.meta_value as balance
             FROM {$wpdb->users} u
             JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
             WHERE um.meta_key = '_dsbc_credit_balance' 
             AND CAST(um.meta_value AS DECIMAL(10,2)) > 0
             ORDER BY CAST(um.meta_value AS DECIMAL(10,2)) DESC
             LIMIT 10"
        );
        
        $exchange_rate = DS_Credit_Converter::get_exchange_rate();
        
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">';
        
        echo '<div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px;">';
        echo '<div style="font-size: 2em; font-weight: bold; color: #2271b1;">' . number_format( $total_users ) . '</div>';
        echo '<div>Total de Usuários</div>';
        echo '</div>';
        
        echo '<div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px;">';
        echo '<div style="font-size: 2em; font-weight: bold; color: #46b450;">' . number_format( $users_with_credits ) . '</div>';
        echo '<div>Com Créditos</div>';
        echo '</div>';
        
        echo '<div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px;">';
        echo '<div style="font-size: 2em; font-weight: bold; color: #ff6900;">' . number_format( $total_credits, 2 ) . '</div>';
        echo '<div>Total USD</div>';
        echo '</div>';
        
        echo '<div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px;">';
        echo '<div style="font-size: 2em; font-weight: bold; color: #9b59b6;">R$ ' . number_format( $total_credits * $exchange_rate, 2, ',', '.' ) . '</div>';
        echo '<div>Equivalente BRL</div>';
        echo '</div>';
        
        echo '</div>';
        
        // Top usuários
        if ( ! empty( $top_users ) ) {
            echo '<h3>🏆 Top 10 Usuários por Saldo</h3>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Usuário</th><th>Email</th><th>Créditos USD</th><th>Equivalente BRL</th></tr></thead>';
            echo '<tbody>';
            
            foreach ( $top_users as $user ) {
                $balance = floatval( $user->balance );
                $balance_brl = $balance * $exchange_rate;
                
                echo '<tr>';
                echo '<td><strong>' . esc_html( $user->display_name ) . '</strong></td>';
                echo '<td>' . esc_html( $user->user_email ) . '</td>';
                echo '<td>' . number_format( $balance, 2 ) . ' créditos</td>';
                echo '<td>R$ ' . number_format( $balance_brl, 2, ',', '.' ) . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        }
    }

    /**
     * AJAX: Buscar usuários
     */
    public function ajax_search_users() {
        check_ajax_referer( 'ds_search_users' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Sem permissão' );
        }
        
        $query = sanitize_text_field( $_POST['query'] );
        
        global $wpdb;
        $users = $wpdb->get_results( $wpdb->prepare(
            "SELECT u.ID, u.display_name, u.user_email, 
                    COALESCE(um.meta_value, '0') as balance
             FROM {$wpdb->users} u
             LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = '_dsbc_credit_balance'
             WHERE u.display_name LIKE %s 
                OR u.user_email LIKE %s 
                OR u.ID = %d
             ORDER BY u.display_name
             LIMIT 20",
            '%' . $query . '%',
            '%' . $query . '%',
            intval( $query )
        ) );
        
        wp_send_json_success( $users );
    }

    /**
     * AJAX: Atualizar créditos do usuário
     */
    public function ajax_update_user_credits() {
        check_ajax_referer( 'ds_update_user_credits' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Sem permissão' );
        }
        
        $user_id = intval( $_POST['user_id'] );
        $amount = floatval( $_POST['amount'] );
        $action = sanitize_text_field( $_POST['credit_action'] );
        $note = sanitize_text_field( $_POST['note'] );
        
        if ( ! $user_id || ! $amount || ! $note ) {
            wp_send_json_error( 'Dados inválidos' );
        }
        
        $current_balance = DS_Credit_Manager::get_balance( $user_id );
        
        switch ( $action ) {
            case 'add':
                $success = DS_Credit_Manager::add_credits_manually( $user_id, $amount, $note, get_current_user_id() );
                break;
            case 'deduct':
                $success = DS_Credit_Manager::deduct_credits( $user_id, $amount, $note );
                break;
            case 'set':
                $diff = $amount - $current_balance;
                if ( $diff > 0 ) {
                    $success = DS_Credit_Manager::add_credits_manually( $user_id, $diff, $note, get_current_user_id() );
                } else {
                    $success = DS_Credit_Manager::deduct_credits( $user_id, abs( $diff ), $note );
                }
                break;
            default:
                wp_send_json_error( 'Ação inválida' );
        }
        
        if ( $success ) {
            wp_send_json_success( 'Créditos atualizados com sucesso' );
        } else {
            wp_send_json_error( 'Erro ao atualizar créditos' );
        }
    }

    /**
     * AJAX: Obter histórico do usuário
     */
    public function ajax_get_user_history() {
        check_ajax_referer( 'ds_get_user_history' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Sem permissão' );
        }
        
        $user_id = intval( $_POST['user_id'] );
        $user = get_userdata( $user_id );
        
        if ( ! $user ) {
            wp_send_json_error( 'Usuário não encontrado' );
        }
        
        global $wpdb;
        $history = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dsbc_credit_logs 
             WHERE user_id = %d 
             ORDER BY created_at DESC 
             LIMIT 20",
            $user_id
        ) );
        
        $balance = DS_Credit_Manager::get_balance( $user_id );
        
        wp_send_json_success( [
            'ID' => $user->ID,
            'display_name' => $user->display_name,
            'user_email' => $user->user_email,
            'balance' => $balance,
            'history' => $history
        ] );
    }
}
