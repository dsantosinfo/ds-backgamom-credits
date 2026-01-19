<?php
/**
 * Gestão de saques do DS Backgamom Credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once DSBC_PLUGIN_PATH . 'includes/admin/class-ds-admin-base.php';

class DS_Admin_Withdrawals extends DS_Admin_Base {

    public function __construct() {
        add_action('wp_ajax_dsbc_search_users', [$this, 'ajax_search_users']);
        add_action('wp_ajax_dsbc_process_manual_withdrawal', [$this, 'ajax_process_manual_withdrawal']);
    }
    
    public function ajax_search_users() {
        check_ajax_referer('dsbc_search_users', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sem permissão');
        }
        
        $query = sanitize_text_field($_POST['query']);
        
        $users = get_users([
            'search' => '*' . $query . '*',
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'number' => 10
        ]);
        
        $results = [];
        foreach ($users as $user) {
            $balance = get_user_meta($user->ID, '_dsbc_credit_balance', true) ?: 0;
            $country = get_user_meta($user->ID, 'billing_country', true) ?: 'N/A';
            $results[] = [
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'balance' => number_format(floatval($balance), 2),
                'country' => $country
            ];
        }
        
        wp_send_json_success($results);
    }
    
    public function ajax_process_manual_withdrawal() {
        check_ajax_referer('dsbc_manual_withdrawal', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sem permissão');
        }
        
        $user_id = intval($_POST['user_id']);
        $amount = floatval($_POST['amount']);
        $method = sanitize_text_field($_POST['method']);
        $notes = sanitize_textarea_field($_POST['notes']);
        
        if (!$user_id || $amount <= 0 || empty($method)) {
            wp_send_json_error('Dados inválidos');
        }
        
        if (class_exists('DS_Credit_Manager')) {
            $success = DS_Credit_Manager::deduct_credits($user_id, $amount, "Saque manual - {$method} - {$notes}");
            if ($success) {
                wp_send_json_success('Saque processado com sucesso');
            } else {
                wp_send_json_error('Saldo insuficiente ou erro ao processar');
            }
        } else {
            wp_send_json_error('Sistema de créditos não disponível');
        }
    }

    public function simple_withdrawals_page() {
        global $wpdb;
        
        $active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'pending';
        
        if (isset($_POST['action'])) {
            $request_id = intval($_POST['request_id']);
            
            if ($_POST['action'] === 'approve' && wp_verify_nonce($_POST['nonce'], 'withdrawal_action')) {
                if (DS_Simple_Withdrawals::approve_withdrawal($request_id)) {
                    echo '<div class="notice notice-success"><p>Saque aprovado com sucesso!</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Erro ao aprovar saque.</p></div>';
                }
            } elseif ($_POST['action'] === 'reject' && wp_verify_nonce($_POST['nonce'], 'withdrawal_action')) {
                $reason = sanitize_textarea_field($_POST['reason']);
                if (DS_Simple_Withdrawals::reject_withdrawal($request_id, $reason)) {
                    echo '<div class="notice notice-success"><p>Saque rejeitado com sucesso!</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Erro ao rejeitar saque.</p></div>';
                }
            } elseif ($_POST['action'] === 'manual_withdrawal' && wp_verify_nonce($_POST['nonce'], 'withdrawal_action')) {
                $user_id = intval($_POST['user_id']);
                $amount = floatval($_POST['amount']);
                $method = sanitize_text_field($_POST['method']);
                $notes = sanitize_textarea_field($_POST['notes']);
                
                if (class_exists('DS_Credit_Manager')) {
                    $success = DS_Credit_Manager::deduct_credits($user_id, $amount, "Saque manual - {$method} - {$notes}");
                    if ($success) {
                        echo '<div class="notice notice-success"><p>Saque manual processado com sucesso!</p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p>Erro: Saldo insuficiente ou dados inválidos.</p></div>';
                    }
                }
            }
        }
        
        $table_name = $wpdb->prefix . 'ds_withdrawal_requests';
        
        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-money-alt"></span> Gestão de Saques</h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=ds-credits-withdrawals&tab=pending" class="nav-tab <?php echo $active_tab == 'pending' ? 'nav-tab-active' : ''; ?>">Pendentes</a>
                <a href="?page=ds-credits-withdrawals&tab=approved" class="nav-tab <?php echo $active_tab == 'approved' ? 'nav-tab-active' : ''; ?>">Aprovados</a>
                <a href="?page=ds-credits-withdrawals&tab=rejected" class="nav-tab <?php echo $active_tab == 'rejected' ? 'nav-tab-active' : ''; ?>">Rejeitados</a>
                <a href="?page=ds-credits-withdrawals&tab=manual" class="nav-tab <?php echo $active_tab == 'manual' ? 'nav-tab-active' : ''; ?>">Novo Saque</a>
            </nav>

            <?php
            switch ( $active_tab ) {
                case 'approved':
                    $this->render_approved_withdrawals( $table_name );
                    break;
                case 'rejected':
                    $this->render_rejected_withdrawals( $table_name );
                    break;
                case 'manual':
                    $this->render_manual_withdrawal_form();
                    break;
                default:
                    $this->render_pending_withdrawals( $table_name );
            }
            ?>
        </div>
        <?php
    }

    private function render_pending_withdrawals( $table_name ) {
        global $wpdb;
        $requests = $wpdb->get_results( "SELECT * FROM $table_name WHERE status = 'pending' ORDER BY created_at DESC LIMIT 50" );
        
        ?>
        <div class="postbox">
            <h2 class="hndle"><span class="dashicons dashicons-clock"></span> Solicitações Pendentes</h2>
            <div class="inside">
                <?php if ( $requests ): ?>
                    <?php $this->render_withdrawals_table( $requests, true ); ?>
                <?php else: ?>
                    <div class="notice notice-info inline"><p>Nenhuma solicitação pendente no momento.</p></div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function render_approved_withdrawals( $table_name ) {
        global $wpdb;
        $requests = $wpdb->get_results( "SELECT * FROM $table_name WHERE status = 'approved' ORDER BY processed_at DESC LIMIT 50" );
        
        ?>
        <div class="postbox">
            <h2 class="hndle"><span class="dashicons dashicons-yes-alt"></span> Saques Aprovados</h2>
            <div class="inside">
                <?php if ( $requests ): ?>
                    <?php $this->render_withdrawals_table( $requests, false ); ?>
                <?php else: ?>
                    <div class="notice notice-info inline"><p>Nenhum saque aprovado ainda.</p></div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function render_rejected_withdrawals( $table_name ) {
        global $wpdb;
        $requests = $wpdb->get_results( "SELECT * FROM $table_name WHERE status = 'rejected' ORDER BY processed_at DESC LIMIT 50" );
        
        ?>
        <div class="postbox">
            <h2 class="hndle"><span class="dashicons dashicons-dismiss"></span> Saques Rejeitados</h2>
            <div class="inside">
                <?php if ( $requests ): ?>
                    <?php $this->render_withdrawals_table( $requests, false ); ?>
                <?php else: ?>
                    <div class="notice notice-info inline"><p>Nenhum saque rejeitado.</p></div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function render_manual_withdrawal_form() {
        ?>
        <div class="postbox">
            <h2 class="hndle"><span class="dashicons dashicons-plus-alt"></span> Processar Saque Manual</h2>
            <div class="inside">
                <div class="notice notice-warning inline">
                    <p><strong>Atenção:</strong> Saques manuais debitam créditos imediatamente e não podem ser desfeitos.</p>
                </div>
                
                <form id="manual-withdrawal-form" enctype="multipart/form-data" style="max-width: 600px;">
                    <table class="form-table">
                        <tr>
                            <th><label for="withdrawal-user-search">Usuário *</label></th>
                            <td>
                                <input type="text" id="withdrawal-user-search" placeholder="Digite o nome ou email do usuário..." style="width: 100%;" autocomplete="off" />
                                <input type="hidden" id="withdrawal-user-id" required />
                                <input type="hidden" id="withdrawal-user-country" />
                                <div id="user-search-results" style="display:none; border: 1px solid #ddd; max-height: 200px; overflow-y: auto; background: #fff; position: absolute; z-index: 1000; width: 400px;"></div>
                                <p class="description" id="selected-user-info" style="display:none;"></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="withdrawal-amount">Valor do Saque (USD) *</label></th>
                            <td>
                                <input type="number" id="withdrawal-amount" step="0.01" min="0.01" required style="width: 200px;" />
                                <div id="brl-conversion-withdrawal" style="margin-top: 5px; color: #666; font-size: 12px;"></div>
                                <p class="description">Quantidade de créditos USD a debitar</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="withdrawal-method">Método *</label></th>
                            <td>
                                <select id="withdrawal-method" required style="width: 200px;">
                                    <option value="">Selecione...</option>
                                    <option value="PIX">PIX</option>
                                    <option value="Wise">Wise</option>
                                    <option value="Transferência">Transferência Bancária</option>
                                    <option value="Outro">Outro</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="withdrawal-notes">Observações</label></th>
                            <td>
                                <textarea id="withdrawal-notes" rows="3" style="width: 100%;" placeholder="Motivo do saque, detalhes do pagamento..."></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="withdrawal-file">Comprovante</label></th>
                            <td>
                                <input type="file" id="withdrawal-file" name="withdrawal_file" accept="image/*,.pdf" />
                                <p class="description">Anexe comprovante de pagamento (opcional)</p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary button-large">Processar Saque</button>
                        <span style="margin-left: 20px; color: #666;">Esta ação não pode ser desfeita</span>
                    </p>
                </form>
                
                <script>
                jQuery(document).ready(function($) {
                    let searchTimeout;
                    
                    $('#withdrawal-user-search').on('input', function() {
                        clearTimeout(searchTimeout);
                        const query = $(this).val();
                        
                        if (query.length < 2) {
                            $('#user-search-results').hide();
                            return;
                        }
                        
                        searchTimeout = setTimeout(function() {
                            $.post(ajaxurl, {
                                action: 'dsbc_search_users',
                                query: query,
                                nonce: '<?php echo wp_create_nonce('dsbc_search_users'); ?>'
                            }, function(response) {
                                if (response.success && response.data.length > 0) {
                                    let html = '';
                                    response.data.forEach(function(user) {
                                        html += '<div class="user-result-item" data-user-id="' + user.id + '" data-user-name="' + user.name + '" data-user-balance="' + user.balance + '" data-user-country="' + user.country + '" style="padding: 10px; cursor: pointer; border-bottom: 1px solid #eee;">';
                                        html += '<strong>' + user.name + '</strong><br>';
                                        html += '<small style="color: #666;">' + user.email + ' | $ ' + user.balance + ' | ' + user.country + '</small>';
                                        html += '</div>';
                                    });
                                    $('#user-search-results').html(html).show();
                                } else {
                                    $('#user-search-results').html('<div style="padding: 10px; color: #666;">Nenhum usuário encontrado</div>').show();
                                }
                            });
                        }, 300);
                    });
                    
                    $(document).on('click', '.user-result-item', function() {
                        const userId = $(this).data('user-id');
                        const userName = $(this).data('user-name');
                        const userBalance = $(this).data('user-balance');
                        const userCountry = $(this).data('user-country');
                        
                        $('#withdrawal-user-id').val(userId);
                        $('#withdrawal-user-country').val(userCountry);
                        $('#withdrawal-user-search').val(userName);
                        $('#selected-user-info').html('<strong>Selecionado:</strong> ' + userName + ' | Saldo: $ ' + userBalance + ' | País: ' + userCountry).show();
                        $('#user-search-results').hide();
                        
                        // Atualizar conversão se for brasileiro
                        updateWithdrawalBrlConversion();
                    });
                    
                    $(document).on('click', function(e) {
                        if (!$(e.target).closest('#withdrawal-user-search, #user-search-results').length) {
                            $('#user-search-results').hide();
                        }
                    });
                    
                    $('#withdrawal-amount').on('input', updateWithdrawalBrlConversion);
                    
                    $('#manual-withdrawal-form').on('submit', function(e) {
                        e.preventDefault();
                        
                        const userId = $('#withdrawal-user-id').val();
                        const amount = $('#withdrawal-amount').val();
                        const method = $('#withdrawal-method').val();
                        const notes = $('#withdrawal-notes').val();
                        
                        if (!userId || !amount || !method) {
                            alert('Preencha todos os campos obrigatórios');
                            return;
                        }
                        
                        if (!confirm('Confirma o processamento deste saque?')) {
                            return;
                        }
                        
                        const formData = new FormData();
                        formData.append('action', 'dsbc_process_manual_withdrawal');
                        formData.append('nonce', '<?php echo wp_create_nonce('dsbc_manual_withdrawal'); ?>');
                        formData.append('user_id', userId);
                        formData.append('amount', amount);
                        formData.append('method', method);
                        formData.append('notes', notes);
                        
                        const fileInput = $('#withdrawal-file')[0];
                        if (fileInput.files.length > 0) {
                            formData.append('withdrawal_file', fileInput.files[0]);
                        }
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: formData,
                            processData: false,
                            contentType: false,
                            success: function(response) {
                                if (response.success) {
                                    alert('Saque processado com sucesso!');
                                    location.reload();
                                } else {
                                    alert('Erro: ' + response.data);
                                }
                            }
                        });
                    });
                    
                    function updateWithdrawalBrlConversion() {
                        const amount = parseFloat($('#withdrawal-amount').val()) || 0;
                        const userCountry = $('#withdrawal-user-country').val();
                        
                        if (amount > 0 && userCountry === 'BR') {
                            const exchangeRate = <?php echo DS_Credit_Converter::get_exchange_rate(); ?>;
                            const brlAmount = amount * exchangeRate;
                            
                            $('#brl-conversion-withdrawal').text(
                                'Equivale a R$ ' + brlAmount.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})
                            );
                        } else {
                            $('#brl-conversion-withdrawal').text('');
                        }
                    }
                });
                </script>
            </div>
        </div>
        <?php
    }

    private function render_withdrawals_table( $requests, $show_actions = false ) {
        ?>
        <!-- Modal Aprovar Saque -->
        <div id="approve-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:100000;">
            <div style="background:white; margin:50px auto; padding:20px; width:90%; max-width:500px; border-radius:5px;">
                <h3>Aprovar Saque</h3>
                <form id="approve-withdrawal-form" enctype="multipart/form-data">
                    <p><label>Comprovante de Pagamento:</label></p>
                    <input type="file" id="approve-receipt" accept="image/*,.pdf" required />
                    <p class="description">Anexe o comprovante de pagamento</p>
                    <p><label>Observações (opcional):</label></p>
                    <textarea id="approve-notes" rows="3" style="width:100%;"></textarea>
                    <p style="margin-top:15px;">
                        <button type="submit" class="button button-primary">Confirmar Aprovação</button>
                        <button type="button" class="button" onclick="closeWithdrawalModal()">Cancelar</button>
                    </p>
                </form>
            </div>
        </div>
        
        <!-- Modal Rejeitar Saque -->
        <div id="reject-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:100000;">
            <div style="background:white; margin:50px auto; padding:20px; width:90%; max-width:500px; border-radius:5px;">
                <h3>Rejeitar Saque</h3>
                <form id="reject-withdrawal-form">
                    <p><label>Motivo da rejeição:</label></p>
                    <textarea id="rejection-reason" rows="4" style="width:100%;" required></textarea>
                    <p style="margin-top:15px;">
                        <button type="submit" class="button button-primary">Confirmar Rejeição</button>
                        <button type="button" class="button" onclick="closeWithdrawalModal()">Cancelar</button>
                    </p>
                </form>
            </div>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 100px;">Data</th>
                    <th>Usuário</th>
                    <th style="width: 80px;">Valor</th>
                    <th style="width: 100px;">Método</th>
                    <th style="width: 80px;">Status</th>
                    <?php if ( $show_actions ): ?>
                        <th style="width: 150px;">Ações</th>
                    <?php else: ?>
                        <th style="width: 100px;">Comprovante</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $request): ?>
                    <?php $user = get_userdata($request->user_id); ?>
                    <tr>
                        <td>
                            <strong><?php echo date('d/m/Y', strtotime($request->created_at)); ?></strong>
                            <br><small><?php echo date('H:i', strtotime($request->created_at)); ?></small>
                        </td>
                        <td>
                            <?php if ($user): ?>
                                <strong><?php echo esc_html($user->display_name); ?></strong>
                                <br><small style="color: #666;"><?php echo esc_html($user->user_email); ?></small>
                            <?php else: ?>
                                <em>Usuário não encontrado</em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="dsbc-stat-number" style="font-size: 16px;">
                                $ <?php echo number_format($request->amount, 2); ?>
                            </span>
                            <?php 
                            $user_country = get_user_meta($request->user_id, 'billing_country', true);
                            if ($user_country === 'BR'): 
                                $brl_value = $request->amount * DS_Credit_Converter::get_exchange_rate();
                            ?>
                                <br><small style="color: #666;">≈ R$ <?php echo number_format($brl_value, 2, ',', '.'); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($request->method); ?></td>
                        <td>
                            <?php
                            $status_colors = [
                                'pending' => '#f56e28',
                                'approved' => '#00a32a', 
                                'rejected' => '#d63638'
                            ];
                            $status_labels = [
                                'pending' => 'Pendente',
                                'approved' => 'Aprovado',
                                'rejected' => 'Rejeitado'
                            ];
                            $color = $status_colors[$request->status] ?? '#666';
                            $label = $status_labels[$request->status] ?? $request->status;
                            ?>
                            <span style="background: <?php echo $color; ?>; color: white; padding: 3px 8px; border-radius: 3px; font-size: 11px;">
                                <?php echo $label; ?>
                            </span>
                        </td>
                        <?php if ( $show_actions && $request->status === 'pending' ): ?>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <button class="button button-small button-primary approve-withdrawal-btn" data-request-id="<?php echo $request->id; ?>" title="Aprovar">
                                        <span class="dashicons dashicons-yes"></span>
                                    </button>
                                    <button class="button button-small reject-withdrawal-btn" data-request-id="<?php echo $request->id; ?>" title="Rejeitar">
                                        <span class="dashicons dashicons-no"></span>
                                    </button>
                                </div>
                            </td>
                        <?php elseif ( $show_actions ): ?>
                            <td>-</td>
                        <?php else: ?>
                            <td>
                                <?php 
                                // Extrair URL do comprovante das notas
                                if ( preg_match('/Comprovante: (https?:\/\/[^\s]+)/', $request->notes, $matches) ) {
                                    echo '<a href="' . esc_url($matches[1]) . '" target="_blank" class="button button-small" title="Ver Comprovante"><span class="dashicons dashicons-media-document"></span></a>';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <script>
        let currentWithdrawalId = null;
        
        function closeWithdrawalModal() {
            document.getElementById('approve-modal').style.display = 'none';
            document.getElementById('reject-modal').style.display = 'none';
            currentWithdrawalId = null;
        }
        
        jQuery(document).ready(function($) {
            $('.approve-withdrawal-btn').on('click', function() {
                currentWithdrawalId = $(this).data('request-id');
                document.getElementById('approve-modal').style.display = 'block';
            });
            
            $('.reject-withdrawal-btn').on('click', function() {
                currentWithdrawalId = $(this).data('request-id');
                document.getElementById('reject-modal').style.display = 'block';
            });
            
            $('#approve-withdrawal-form').on('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData();
                formData.append('action', 'dsbc_approve_withdrawal');
                formData.append('request_id', currentWithdrawalId);
                formData.append('notes', $('#approve-notes').val());
                formData.append('nonce', '<?php echo wp_create_nonce('dsbc_withdrawal_action'); ?>');
                
                const fileInput = $('#approve-receipt')[0];
                if (fileInput.files.length > 0) {
                    formData.append('receipt', fileInput.files[0]);
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            alert(response.data || 'Saque aprovado com sucesso!');
                            location.reload();
                        } else {
                            alert('Erro: ' + (response.data || 'Erro desconhecido'));
                        }
                    },
                    error: function() {
                        alert('Erro de comunicação com o servidor');
                    }
                });
            });
            
            $('#reject-withdrawal-form').on('submit', function(e) {
                e.preventDefault();
                
                $.post(ajaxurl, {
                    action: 'dsbc_reject_withdrawal',
                    request_id: currentWithdrawalId,
                    reason: $('#rejection-reason').val(),
                    nonce: '<?php echo wp_create_nonce('dsbc_withdrawal_action'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert(response.data || 'Saque rejeitado com sucesso!');
                        location.reload();
                    } else {
                        alert('Erro: ' + (response.data || 'Erro desconhecido'));
                    }
                }).fail(function() {
                    alert('Erro de comunicação com o servidor');
                });
            });
        });
        </script>
        <?php
    }
}