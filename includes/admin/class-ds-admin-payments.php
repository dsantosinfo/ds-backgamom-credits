<?php
/**
 * Administração de Pagamentos Posteriores
 *
 * @package DS_Backgamom_Credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DS_Admin_Payments {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ], 20 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    public function add_menu() {
        add_submenu_page(
            'ds-backgamom-credits',
            __( 'Pagamentos Posteriores', 'ds-backgamom-credits' ),
            __( 'Pagamentos', 'ds-backgamom-credits' ),
            'manage_options',
            'ds-payments',
            [ $this, 'render_page' ]
        );
    }

    public function enqueue_scripts( $hook ) {
        if ( strpos( $hook, 'ds-payments' ) === false ) {
            return;
        }
        
        wp_enqueue_script( 'jquery' );
        wp_localize_script( 'jquery', 'dsbc_ajax', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'dsbc_admin_nonce' )
        ] );
    }

    public function render_page() {
        $active_tab = $_GET['tab'] ?? 'pending';
        $stats = DS_Payment_Manager::get_payment_stats();
        ?>
        <div class="wrap">
            <h1><?php _e( 'Pagamentos Posteriores', 'ds-backgamom-credits' ); ?></h1>
            
            <!-- Estatísticas -->
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
                
                <div class="card" style="padding: 15px; background: #d1edff; border-left: 4px solid #0084ff;">
                    <h3 style="margin: 0 0 10px 0; color: #004085;">Pagos</h3>
                    <p style="margin: 0; font-size: 18px; font-weight: bold;"><?php echo $stats->paid_count ?? 0; ?> pagamentos</p>
                </div>
            </div>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=ds-payments&tab=pending" class="nav-tab <?php echo $active_tab == 'pending' ? 'nav-tab-active' : ''; ?>">Pendentes</a>
                <a href="?page=ds-payments&tab=overdue" class="nav-tab <?php echo $active_tab == 'overdue' ? 'nav-tab-active' : ''; ?>">Vencidos</a>
                <a href="?page=ds-payments&tab=paid" class="nav-tab <?php echo $active_tab == 'paid' ? 'nav-tab-active' : ''; ?>">Pagos</a>
                <a href="?page=ds-payments&tab=add" class="nav-tab <?php echo $active_tab == 'add' ? 'nav-tab-active' : ''; ?>">Adicionar Créditos</a>
            </nav>

            <?php
            switch ( $active_tab ) {
                case 'add':
                    $this->render_add_credits_tab();
                    break;
                case 'paid':
                    $this->render_payments_list( 'paid' );
                    break;
                case 'overdue':
                    $this->render_payments_list( 'overdue' );
                    break;
                default:
                    $this->render_payments_list( 'pending' );
            }
            ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Adicionar créditos com pagamento
            $('#dsbc-add-credits-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = {
                    action: 'dsbc_add_credits_with_payment',
                    nonce: dsbc_ajax.nonce,
                    user_id: $('#user_id').val(),
                    amount: $('#amount').val(),
                    observation: $('#observation').val(),
                    payment_type: $('input[name="payment_type"]:checked').val(),
                    due_date: $('#due_date').val(),
                    payment_method: $('#payment_method').val()
                };
                
                $.post(dsbc_ajax.ajax_url, formData, function(response) {
                    if (response.success) {
                        alert(response.data);
                        location.reload();
                    } else {
                        alert('Erro: ' + response.data);
                    }
                });
            });
            
            // Marcar como pago
            $('.mark-paid-btn').on('click', function() {
                if (!confirm('Marcar este pagamento como pago?')) return;
                
                var logId = $(this).data('log-id');
                $.post(dsbc_ajax.ajax_url, {
                    action: 'dsbc_mark_payment_paid',
                    nonce: dsbc_ajax.nonce,
                    log_id: logId
                }, function(response) {
                    if (response.success) {
                        alert(response.data);
                        location.reload();
                    } else {
                        alert('Erro: ' + response.data);
                    }
                });
            });
            
            // Enviar lembrete
            $('.send-reminder-btn').on('click', function() {
                var logId = $(this).data('log-id');
                $.post(dsbc_ajax.ajax_url, {
                    action: 'dsbc_send_payment_reminder',
                    nonce: dsbc_ajax.nonce,
                    log_id: logId
                }, function(response) {
                    if (response.success) {
                        alert(response.data);
                    } else {
                        alert('Erro: ' + response.data);
                    }
                });
            });
            
            // Mostrar/ocultar campos de pagamento posterior
            $('input[name="payment_type"]').on('change', function() {
                if ($(this).val() === 'later') {
                    $('#payment-later-fields').show();
                } else {
                    $('#payment-later-fields').hide();
                }
            });
        });
        </script>
        <?php
    }

    private function render_add_credits_tab() {
        ?>
        <div class="postbox">
            <h2 class="hndle">Adicionar Créditos com Opção de Pagamento Posterior</h2>
            <div class="inside">
                <form id="dsbc-add-credits-form">
                    <table class="form-table">
                        <tr>
                            <th>Usuário</th>
                            <td>
                                <input type="number" id="user_id" placeholder="ID do usuário" required style="width: 100px;" />
                                <p class="description">Digite o ID do usuário</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Quantidade de Créditos</th>
                            <td>
                                <input type="number" id="amount" min="1" required style="width: 150px;" />
                            </td>
                        </tr>
                        <tr>
                            <th>Observação</th>
                            <td>
                                <textarea id="observation" rows="3" style="width: 100%;" required placeholder="Motivo da adição de créditos"></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th>Tipo de Pagamento</th>
                            <td>
                                <label>
                                    <input type="radio" name="payment_type" value="immediate" checked />
                                    Pago imediatamente
                                </label>
                                <br>
                                <label>
                                    <input type="radio" name="payment_type" value="later" />
                                    Pagamento posterior
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <div id="payment-later-fields" style="display: none; background: #f9f9f9; padding: 15px; margin: 15px 0; border-left: 4px solid #2196F3;">
                        <h4>Configurações de Pagamento Posterior</h4>
                        <table class="form-table">
                            <tr>
                                <th>Data de Vencimento</th>
                                <td>
                                    <input type="date" id="due_date" min="<?php echo date('Y-m-d'); ?>" />
                                    <p class="description">Data limite para pagamento</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Método de Pagamento</th>
                                <td>
                                    <select id="payment_method">
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
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary">Adicionar Créditos</button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    private function render_payments_list( $status ) {
        $payments = DS_Payment_Manager::get_pending_payments( $status );
        
        $status_labels = [
            'pending' => 'Pendente',
            'overdue' => 'Vencido',
            'paid' => 'Pago'
        ];
        
        ?>
        <div class="postbox">
            <h2 class="hndle">Pagamentos <?php echo $status_labels[$status]; ?></h2>
            <div class="inside">
                <?php if ( empty( $payments ) ) : ?>
                    <p>Nenhum pagamento <?php echo strtolower( $status_labels[$status] ); ?> encontrado.</p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Usuário</th>
                                <th>Créditos</th>
                                <th>Data Vencimento</th>
                                <th>Método</th>
                                <th>Observação</th>
                                <th>Admin</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $payments as $payment ) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $payment->display_name ); ?></strong><br>
                                    <small><?php echo esc_html( $payment->user_email ); ?></small>
                                </td>
                                <td><?php echo number_format( $payment->amount, 0, ',', '.' ); ?></td>
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
                                <td><?php echo esc_html( $payment->admin_name ); ?></td>
                                <td>
                                    <?php if ( $payment->payment_status !== 'paid' ) : ?>
                                        <button class="button button-primary button-small mark-paid-btn" data-log-id="<?php echo $payment->id; ?>">
                                            Marcar como Pago
                                        </button>
                                        <button class="button button-small send-reminder-btn" data-log-id="<?php echo $payment->id; ?>">
                                            Enviar Lembrete
                                        </button>
                                    <?php else : ?>
                                        <span style="color: #28a745;">✓ Pago</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}