<?php
/**
 * Consulta de créditos do DS Backgamom Credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once DSBC_PLUGIN_PATH . 'includes/admin/class-ds-admin-base.php';

class DS_Admin_Lookup extends DS_Admin_Base {

    public function lookup_page() {
        $active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'users';
        
        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-search"></span> Consultar Créditos</h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=ds-credits-lookup&tab=users" class="nav-tab <?php echo $active_tab == 'users' ? 'nav-tab-active' : ''; ?>">Usuários</a>
                <a href="?page=ds-credits-lookup&tab=pending-payments" class="nav-tab <?php echo $active_tab == 'pending-payments' ? 'nav-tab-active' : ''; ?>">Pagamentos Pendentes</a>
            </nav>
            
            <?php if ( $active_tab == 'pending-payments' ) {
                $this->render_pending_payments_tab();
            } else {
                $this->render_users_tab();
            }
            ?>
        </div>
        <?php
    }
    
    private function render_users_tab() {
        global $wpdb;
        
        $per_page = 20;
        $current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $offset = ( $current_page - 1 ) * $per_page;
        
        $search = isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '';
        $order_by = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'credits';
        $order = isset( $_GET['order'] ) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';
        
        $where_clause = "WHERE 1=1";
        $search_params = [];
        
        if ( ! empty( $search ) ) {
            $where_clause .= " AND (u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s)";
            $search_like = '%' . $wpdb->esc_like( $search ) . '%';
            $search_params = [ $search_like, $search_like, $search_like ];
        }
        
        $order_clause = "ORDER BY ";
        switch ( $order_by ) {
            case 'name': $order_clause .= "u.display_name {$order}"; break;
            case 'email': $order_clause .= "u.user_email {$order}"; break;
            case 'registered': $order_clause .= "u.user_registered {$order}"; break;
            default: $order_clause .= "CAST(um.meta_value AS DECIMAL(10,2)) {$order}";
        }
        
        $count_query = "SELECT COUNT(*) FROM {$wpdb->users} u {$where_clause}";
        $total_users = empty( $search_params ) ? $wpdb->get_var( $count_query ) : $wpdb->get_var( $wpdb->prepare( $count_query, $search_params ) );
        
        $main_query = "SELECT u.ID, u.display_name, u.user_email, u.user_registered,
                             COALESCE(CAST(um.meta_value AS DECIMAL(10,2)), 0) as credits
                      FROM {$wpdb->users} u 
                      LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = '_dsbc_credit_balance'
                      {$where_clause} {$order_clause} LIMIT %d OFFSET %d";
        
        $query_params = array_merge( $search_params, [ $per_page, $offset ] );
        $users = $wpdb->get_results( $wpdb->prepare( $main_query, $query_params ) );
        
        ?>
        <div class="notice notice-info">
            <p><strong>Dica:</strong> Use esta página para consultar, adicionar créditos e processar saques manuais para qualquer usuário.</p>
        </div>
        
        <div class="postbox">
            <h2 class="hndle"><span class="dashicons dashicons-filter"></span> Filtros e Busca</h2>
            <div class="inside">
                <form method="get" style="display: flex; gap: 10px; align-items: center;">
                    <input type="hidden" name="page" value="ds-credits-lookup" />
                    <input type="hidden" name="tab" value="users" />
                    <input type="search" name="search" value="<?php echo esc_attr( $search ); ?>" placeholder="Buscar por nome, email ou usuário..." style="width: 300px;" />
                    <input type="submit" class="button button-primary" value="Buscar" />
                    <?php if ( $search ): ?>
                        <a href="<?php echo admin_url( 'admin.php?page=ds-credits-lookup&tab=users' ); ?>" class="button">Limpar</a>
                    <?php endif; ?>
                    <span style="margin-left: auto; color: #666;"><?php echo number_format( $total_users ); ?> usuários encontrados</span>
                </form>
            </div>
        </div>
        
        <div class="postbox">
            <h2 class="hndle"><span class="dashicons dashicons-groups"></span> Lista de Usuários</h2>
            <div class="inside">
                <table class="wp-list-table widefat fixed striped users">
                    <thead>
                        <tr>
                            <th class="manage-column column-name">Nome</th>
                            <th class="manage-column column-credits">Créditos</th>
                            <th class="manage-column column-registered">Registro</th>
                            <th class="manage-column column-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( $users ): ?>
                            <?php foreach ( $users as $user ): ?>
                                <tr>
                                    <td class="column-name">
                                        <strong><?php echo esc_html( $user->display_name ); ?></strong>
                                        <br><small style="color: #666;"><?php echo esc_html( $user->user_email ); ?></small>
                                    </td>
                                    <td class="column-credits">
                                        <span style="font-size: 18px; color: <?php echo $user->credits > 0 ? '#0073aa' : '#999'; ?>;">
                                            $ <?php echo number_format( $user->credits, 2 ); ?>
                                        </span>
                                        <?php 
                                        $user_country = get_user_meta( $user->ID, 'billing_country', true );
                                        if ( $user_country === 'BR' && $user->credits > 0 ): 
                                            $brl_value = $user->credits * DS_Credit_Converter::get_exchange_rate();
                                        ?>
                                            <br><small style="color: #666;">≈ R$ <?php echo number_format( $brl_value, 2, ',', '.' ); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="column-registered">
                                        <?php echo date( 'd/m/Y', strtotime( $user->user_registered ) ); ?>
                                    </td>
                                    <td class="column-actions">
                                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                            <button class="button button-small button-primary add-credits-btn" data-user-id="<?php echo $user->ID; ?>" data-user-name="<?php echo esc_attr( $user->display_name ); ?>" title="Adicionar Créditos">
                                                <span class="dashicons dashicons-plus-alt"></span>
                                            </button>
                                            <button class="button button-small history-btn" data-user-id="<?php echo $user->ID; ?>" data-user-name="<?php echo esc_attr( $user->display_name ); ?>" title="Ver Histórico">
                                                <span class="dashicons dashicons-list-view"></span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 30px;">
                                    <div class="notice notice-info inline"><p><?php echo $search ? 'Nenhum usuário encontrado.' : 'Nenhum usuário cadastrado.'; ?></p></div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php $this->render_modals(); ?>
        
        <style>
        .column-credits { width: 120px; text-align: center; }
        .column-actions { width: 200px; text-align: center; }
        .column-registered { width: 150px; }
        </style>
        <?php
    }
    
    private function render_pending_payments_tab() {
        $stats = DS_Payment_Manager::get_payment_stats();
        $pending_payments = DS_Payment_Manager::get_pending_payments( 'pending' );
        $overdue_payments = DS_Payment_Manager::get_pending_payments( 'overdue' );
        
        // Buscar pedidos PIX pendentes
        $pix_orders = $this->get_pending_pix_orders();
        
        ?>
        <div class="dsbc-stats-cards" style="display: flex; gap: 20px; margin: 20px 0;">
            <div class="card" style="padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107;">
                <h3 style="margin: 0 0 10px 0; color: #856404;">Pendentes</h3>
                <p style="margin: 0; font-size: 18px; font-weight: bold;"><?php echo $stats->pending_count ?? 0; ?> pagamentos</p>
                <p style="margin: 5px 0 0 0; color: #666;">R$ <?php echo number_format( $stats->pending_amount ?? 0, 2, ',', '.' ); ?></p>
            </div>
            
            <div class="card" style="padding: 15px; background: #f8d7da; border-left: 4px solid #dc3545;">
                <h3 style="margin: 0 0 10px 0; color: #721c24;">Vencidos</h3>
                <p style="margin: 0; font-size: 18px; font-weight: bold;"><?php echo $stats->overdue_count ?? 0; ?> pagamentos</p>
                <p style="margin: 5px 0 0 0; color: #666;">R$ <?php echo number_format( $stats->overdue_amount ?? 0, 2, ',', '.' ); ?></p>
            </div>
            
            <div class="card" style="padding: 15px; background: #d1ecf1; border-left: 4px solid #17a2b8;">
                <h3 style="margin: 0 0 10px 0; color: #0c5460;">Pedidos PIX</h3>
                <p style="margin: 0; font-size: 18px; font-weight: bold;"><?php echo count( $pix_orders ); ?> pedidos</p>
                <p style="margin: 5px 0 0 0; color: #666;">Aguardando aprovação</p>
            </div>
        </div>
        
        <?php if ( ! empty( $pix_orders ) ) : ?>
        <div class="postbox">
            <h2 class="hndle">Pedidos PIX Pendentes</h2>
            <div class="inside">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Pedido</th>
                            <th>Usuário</th>
                            <th>Valor</th>
                            <th>Data</th>
                            <th>Comprovante</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $pix_orders as $order ) : ?>
                        <tr>
                            <td><a href="<?php echo admin_url( 'post.php?post=' . $order->ID . '&action=edit' ); ?>">#<?php echo $order->ID; ?></a></td>
                            <td>
                                <strong><?php echo esc_html( $order->billing_first_name . ' ' . $order->billing_last_name ); ?></strong><br>
                                <small><?php echo esc_html( $order->billing_email ); ?></small>
                            </td>
                            <td>R$ <?php echo number_format( $order->order_total, 2, ',', '.' ); ?></td>
                            <td><?php echo date_i18n( 'd/m/Y H:i', strtotime( $order->post_date ) ); ?></td>
                            <td>
                                <?php if ( $order->receipt_url ) : ?>
                                    <a href="<?php echo esc_url( $order->receipt_url ); ?>" target="_blank" class="button button-small">Ver</a>
                                <?php else : ?>
                                    <span style="color: #999;">Sem comprovante</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="button button-primary button-small" onclick="approvePixOrder(<?php echo $order->ID; ?>)">Aprovar</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="postbox">
            <h2 class="hndle">Pagamentos Pendentes</h2>
            <div class="inside">
                <?php if ( empty( $pending_payments ) ) : ?>
                    <p>Nenhum pagamento pendente encontrado.</p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Usuário</th>
                                <th>Créditos</th>
                                <th>Data Vencimento</th>
                                <th>Método</th>
                                <th>Observação</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $pending_payments as $payment ) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $payment->display_name ); ?></strong><br>
                                    <small><?php echo esc_html( $payment->user_email ); ?></small>
                                    <?php 
                                    $user_country = get_user_meta( $payment->user_id, 'billing_country', true );
                                    if ( $user_country ) {
                                        echo '<br><span style="color: #666; font-size: 11px;">' . esc_html( $user_country ) . '</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    $ <?php echo number_format( $payment->amount, 2 ); ?>
                                    <?php if ( $user_country === 'BR' ): ?>
                                        <br><small style="color: #666;">≈ R$ <?php echo number_format( $payment->amount * DS_Credit_Converter::get_exchange_rate(), 2, ',', '.' ); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    if ( $payment->payment_due_date ) {
                                        $due_date = date_i18n( 'd/m/Y', strtotime( $payment->payment_due_date ) );
                                        $is_overdue = strtotime( $payment->payment_due_date ) < time();
                                        echo '<span style="color: ' . ( $is_overdue ? '#dc3545' : '#666' ) . ';">' . $due_date . '</span>';
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html( $payment->payment_method ?: '-' ); ?></td>
                                <td><?php echo esc_html( wp_trim_words( $payment->observation, 10 ) ); ?></td>
                                <td>
                                    <button class="button button-primary button-small" onclick="markAsPaid(<?php echo $payment->id; ?>)">
                                        Marcar como Pago
                                    </button>
                                    <button class="button button-small send-reminder-btn" data-log-id="<?php echo $payment->id; ?>">
                                        Enviar Lembrete
                                    </button>
                                    <?php if ( $payment->payment_receipt ) : ?>
                                        <a href="<?php echo esc_url( $payment->payment_receipt ); ?>" target="_blank" class="button button-small" title="Ver Comprovante">
                                            <span class="dashicons dashicons-media-document"></span>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ( ! empty( $overdue_payments ) ) : ?>
        <div class="postbox">
            <h2 class="hndle" style="color: #dc3545;">Pagamentos Vencidos</h2>
            <div class="inside">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Usuário</th>
                            <th>Créditos</th>
                            <th>Data Vencimento</th>
                            <th>Método</th>
                            <th>Observação</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $overdue_payments as $payment ) : ?>
                        <tr style="background-color: #fff2f2;">
                            <td>
                                <strong><?php echo esc_html( $payment->display_name ); ?></strong><br>
                                <small><?php echo esc_html( $payment->user_email ); ?></small>
                                <?php 
                                $user_country = get_user_meta( $payment->user_id, 'billing_country', true );
                                if ( $user_country ) {
                                    echo '<br><span style="color: #666; font-size: 11px;">' . esc_html( $user_country ) . '</span>';
                                }
                                ?>
                            </td>
                            <td>
                                $ <?php echo number_format( $payment->amount, 2 ); ?>
                                <?php if ( $user_country === 'BR' ): ?>
                                    <br><small style="color: #666;">≈ R$ <?php echo number_format( $payment->amount * DS_Credit_Converter::get_exchange_rate(), 2, ',', '.' ); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="color: #dc3545; font-weight: bold;">
                                    <?php echo date_i18n( 'd/m/Y', strtotime( $payment->payment_due_date ) ); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html( $payment->payment_method ?: '-' ); ?></td>
                            <td><?php echo esc_html( wp_trim_words( $payment->observation, 10 ) ); ?></td>
                            <td>
                                <button class="button button-primary button-small" onclick="markAsPaid(<?php echo $payment->id; ?>)">
                                    Marcar como Pago
                                </button>
                                <button class="button button-small send-reminder-btn" data-log-id="<?php echo $payment->id; ?>">
                                    Enviar Lembrete
                                </button>
                                <button class="button button-small button-link-delete cancel-payment-btn" data-log-id="<?php echo $payment->id; ?>" title="Cancelar e remover créditos">
                                    Cancelar
                                </button>
                                <?php if ( $payment->payment_receipt ) : ?>
                                    <a href="<?php echo esc_url( $payment->payment_receipt ); ?>" target="_blank" class="button button-small" title="Ver Comprovante">
                                        <span class="dashicons dashicons-media-document"></span>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <?php $this->render_modals(); ?>
        <?php
    }
    
    private function get_pending_pix_orders() {
        global $wpdb;
        
        // Buscar todos os pedidos on-hold primeiro
        $args = [
            'status' => 'on-hold',
            'limit' => -1,
            'return' => 'ids'
        ];
        
        $order_ids = wc_get_orders( $args );
        $results = [];
        
        foreach ( $order_ids as $order_id ) {
            $order = wc_get_order( $order_id );
            $payment_method = $order->get_payment_method();
            
            if ( in_array( $payment_method, ['ds_pix', 'dsantos_pix'] ) ) {
                $results[] = (object) [
                    'ID' => $order->get_id(),
                    'post_date' => $order->get_date_created()->date( 'Y-m-d H:i:s' ),
                    'order_total' => $order->get_total(),
                    'billing_first_name' => $order->get_billing_first_name(),
                    'billing_last_name' => $order->get_billing_last_name(),
                    'billing_email' => $order->get_billing_email(),
                    'receipt_url' => get_post_meta( $order->get_id(), '_pix_receipt_url', true )
                ];
            }
        }
        
        return $results;
    }
    
    private function render_modals() {
        ?>
        <!-- Modal Adicionar Créditos -->
        <div id="add-credits-modal" style="display:none;">
            <div class="modal-content">
                <h3>Adicionar Créditos</h3>
                <form id="add-credits-form">
                    <table class="form-table">
                        <tr>
                            <th>Usuário:</th>
                            <td><span id="modal-user-name"></span> <span id="modal-user-country" style="color: #666;"></span></td>
                        </tr>
                        <tr>
                            <th>Quantidade (USD):</th>
                            <td>
                                <input type="number" id="credits-amount" min="0.01" step="0.01" required />
                                <div id="brl-conversion" style="margin-top: 5px; color: #666; font-size: 12px;"></div>
                            </td>
                        </tr>
                        <tr id="brl-payment-row" style="display: none;">
                            <th>Valor a Pagar (BRL):</th>
                            <td>
                                <span id="brl-amount" style="font-weight: bold; color: #0073aa;"></span>
                                <p class="description">Valor que o usuário brasileiro deve pagar</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Observação:</th>
                            <td><textarea id="credits-observation" rows="3" required></textarea></td>
                        </tr>
                        <tr>
                            <th>Tipo de Pagamento:</th>
                            <td>
                                <label><input type="radio" name="payment_type" value="immediate" checked /> Pago imediatamente</label><br>
                                <label><input type="radio" name="payment_type" value="later" /> Pagamento posterior</label>
                            </td>
                        </tr>
                    </table>
                    
                    <div id="payment-later-fields" style="display: none; background: #f9f9f9; padding: 15px; margin: 15px 0; border-left: 4px solid #2196F3;">
                        <h4>Configurações de Pagamento Posterior</h4>
                        <table class="form-table">
                            <tr>
                                <th>Data de Vencimento:</th>
                                <td><input type="date" id="due-date" min="<?php echo date('Y-m-d'); ?>" /></td>
                            </tr>
                            <tr>
                                <th>Método de Pagamento:</th>
                                <td>
                                    <select id="payment-method">
                                        <option value="">Selecione...</option>
                                        <option value="pix">PIX</option>
                                        <option value="transferencia">Transferência</option>
                                        <option value="dinheiro">Dinheiro</option>
                                        <option value="wise">WISE</option>
                                        <option value="outro">Outro</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <p>
                        <button type="submit" class="button button-primary">Adicionar Créditos</button>
                        <button type="button" class="button" onclick="closeModal()">Cancelar</button>
                    </p>
                </form>
            </div>
        </div>
        
        <!-- Modal Marcar como Pago -->
        <div id="mark-paid-modal" style="display:none;">
            <div class="modal-content">
                <h3>Marcar Pagamento como Pago</h3>
                <form id="mark-paid-form" enctype="multipart/form-data">
                    <table class="form-table">
                        <tr id="existing-receipt-row" style="display:none;">
                            <th>Comprovante Anexado:</th>
                            <td>
                                <a href="#" id="existing-receipt-link" target="_blank" class="button button-small">
                                    <span class="dashicons dashicons-media-document"></span> Ver Comprovante
                                </a>
                                <p class="description">O usuário já enviou um comprovante. Você pode substituí-lo abaixo se necessário.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Comprovante:</th>
                            <td>
                                <input type="file" id="payment-receipt" accept=".pdf,.jpg,.jpeg,.png" />
                                <p class="description" id="receipt-description">Anexe o comprovante de pagamento (PDF ou imagem)</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Observações:</th>
                            <td><textarea id="payment-notes" rows="3" placeholder="Observações sobre o pagamento (opcional)"></textarea></td>
                        </tr>
                    </table>
                    
                    <p>
                        <button type="submit" class="button button-primary">Confirmar Pagamento</button>
                        <button type="button" class="button" onclick="closeModal()">Cancelar</button>
                    </p>
                </form>
            </div>
        </div>
        
        <!-- Modal Histórico -->
        <div id="history-modal" style="display:none;">
            <div class="modal-content">
                <h3>Histórico de Créditos</h3>
                <div id="history-content" class="history-scroll">Carregando...</div>
                <p><button type="button" class="button" onclick="closeModal()">Fechar</button></p>
            </div>
        </div>
        
        <style>
        #add-credits-modal, #history-modal, #mark-paid-modal {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.7); z-index: 100000;
        }
        .modal-content {
            background: white; margin: 50px auto; padding: 20px;
            width: 90%; max-width: 600px; border-radius: 5px;
            max-height: 80vh; overflow-y: auto;
        }
        .history-scroll {
            max-height: 400px; overflow-y: auto;
            border: 1px solid #ddd; padding: 10px;
        }
        </style>
        
        <script>
        function approvePixOrder(orderId) {
            if (!confirm('Aprovar pagamento deste pedido?')) return;
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'ds_approve_pix_payment',
                    nonce: '<?php echo wp_create_nonce('ds-admin-nonce'); ?>',
                    order_id: orderId
                })
            })
            .then(response => response.json())
            .then(data => {
                alert(data.data);
                if (data.success) {
                    location.reload();
                }
            });
        }
        
        function markAsPaid(logId) {
            window.currentLogId = logId;
            
            // Buscar dados do pagamento
            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'dsbc_get_payment_data',
                    nonce: '<?php echo wp_create_nonce('dsbc_admin_nonce'); ?>',
                    log_id: logId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.payment_receipt) {
                    document.getElementById('existing-receipt-row').style.display = 'table-row';
                    document.getElementById('existing-receipt-link').href = data.data.payment_receipt;
                    document.getElementById('payment-receipt').removeAttribute('required');
                    document.getElementById('receipt-description').textContent = 'Opcional: Anexe um novo comprovante apenas se desejar substituir o existente';
                } else {
                    document.getElementById('existing-receipt-row').style.display = 'none';
                    document.getElementById('payment-receipt').setAttribute('required', 'required');
                    document.getElementById('receipt-description').textContent = 'Anexe o comprovante de pagamento (PDF ou imagem)';
                }
                document.getElementById('mark-paid-modal').style.display = 'block';
            });
        }
        
        function closeModal() {
            document.getElementById('add-credits-modal').style.display = 'none';
            document.getElementById('history-modal').style.display = 'none';
            document.getElementById('mark-paid-modal').style.display = 'none';
        }
        
        function openModal(modalId, userId, userName) {
            window.currentUserId = userId;
            document.getElementById(modalId).style.display = 'block';
            if (modalId === 'add-credits-modal') {
                document.getElementById('modal-user-name').textContent = userName;
                
                // Buscar país do usuário
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'dsbc_get_user_country',
                        nonce: '<?php echo wp_create_nonce('dsbc_admin_nonce'); ?>',
                        user_id: userId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const country = data.data.country;
                        const countryName = data.data.country_name;
                        
                        document.getElementById('modal-user-country').textContent = '(' + countryName + ')';
                        window.currentUserCountry = country;
                        
                        if (country === 'BR') {
                            document.getElementById('brl-payment-row').style.display = 'table-row';
                            updateBrlConversion();
                        } else {
                            document.getElementById('brl-payment-row').style.display = 'none';
                        }
                    }
                });
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Adicionar créditos
            document.querySelectorAll('.add-credits-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    openModal('add-credits-modal', this.dataset.userId, this.dataset.userName);
                });
            });
            
            // Ver histórico
            document.querySelectorAll('.history-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    openModal('history-modal', this.dataset.userId, this.dataset.userName);
                    loadUserHistory(this.dataset.userId);
                });
            });
            
            // Mostrar/ocultar campos de pagamento posterior
            document.querySelectorAll('input[name="payment_type"]').forEach(function(radio) {
                radio.addEventListener('change', function() {
                    if (this.value === 'later') {
                        document.getElementById('payment-later-fields').style.display = 'block';
                    } else {
                        document.getElementById('payment-later-fields').style.display = 'none';
                    }
                });
            });
            
            // Form handlers
            document.getElementById('add-credits-form').addEventListener('submit', handleAddCredits);
            document.getElementById('mark-paid-form').addEventListener('submit', handleMarkPaid);
            
            // Atualizar conversão BRL quando valor mudar
            document.getElementById('credits-amount').addEventListener('input', updateBrlConversion);
            
            // Enviar lembrete
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('send-reminder-btn')) {
                    var logId = e.target.dataset.logId;
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'dsbc_send_payment_reminder',
                            nonce: '<?php echo wp_create_nonce('dsbc_admin_nonce'); ?>',
                            log_id: logId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        alert(data.data);
                    });
                }
                
                // Cancelar pagamento vencido
                if (e.target.classList.contains('cancel-payment-btn')) {
                    var logId = e.target.dataset.logId;
                    if (!confirm('ATENÇÃO: Esta ação irá remover os créditos do usuário. Deseja continuar?')) {
                        return;
                    }
                    
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'dsbc_cancel_overdue_payment',
                            nonce: '<?php echo wp_create_nonce('dsbc_admin_nonce'); ?>',
                            log_id: logId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        alert(data.data);
                        if (data.success) {
                            location.reload();
                        }
                    });
                }
            });
        });
        
        function handleAddCredits(e) {
            e.preventDefault();
            const amount = document.getElementById('credits-amount').value;
            const observation = document.getElementById('credits-observation').value;
            const paymentType = document.querySelector('input[name="payment_type"]:checked').value;
            const dueDate = document.getElementById('due-date').value;
            const paymentMethod = document.getElementById('payment-method').value;
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'dsbc_add_credits_with_payment',
                    user_id: window.currentUserId,
                    amount: amount,
                    observation: observation,
                    payment_type: paymentType,
                    due_date: dueDate,
                    payment_method: paymentMethod,
                    nonce: '<?php echo wp_create_nonce('dsbc_admin_nonce'); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                alert(data.data);
                if (data.success) {
                    closeModal();
                    location.reload();
                }
            });
        }
        
        function loadUserHistory(userId) {
            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'ds_get_user_history',
                    user_id: userId,
                    nonce: '<?php echo wp_create_nonce('ds_user_history_nonce'); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('history-content').innerHTML = data.data;
            });
        }
        
        function handleMarkPaid(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'dsbc_mark_payment_paid');
            formData.append('nonce', '<?php echo wp_create_nonce('dsbc_admin_nonce'); ?>');
            formData.append('log_id', window.currentLogId);
            formData.append('payment_notes', document.getElementById('payment-notes').value);
            
            const fileInput = document.getElementById('payment-receipt');
            if (fileInput.files[0]) {
                formData.append('payment_receipt', fileInput.files[0]);
            }
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alert(data.data);
                if (data.success) {
                    closeModal();
                    location.reload();
                }
            });
        }
        
        function updateBrlConversion() {
            const amount = parseFloat(document.getElementById('credits-amount').value) || 0;
            if (amount > 0 && window.currentUserCountry === 'BR') {
                const exchangeRate = <?php echo DS_Credit_Converter::get_exchange_rate(); ?>;
                const brlAmount = amount * exchangeRate;
                
                document.getElementById('brl-conversion').textContent = 
                    'Equivale a R$ ' + brlAmount.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                document.getElementById('brl-amount').textContent = 
                    'R$ ' + brlAmount.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            } else {
                document.getElementById('brl-conversion').textContent = '';
                document.getElementById('brl-amount').textContent = '';
            }
        }
        </script>
        <?php
    }
}