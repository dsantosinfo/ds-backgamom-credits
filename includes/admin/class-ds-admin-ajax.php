<?php
/**
 * Handlers AJAX do DS Backgamom Credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once DSBC_PLUGIN_PATH . 'includes/admin/class-ds-admin-base.php';

class DS_Admin_Ajax extends DS_Admin_Base {

    public function __construct() {
        add_action( 'wp_ajax_ds_add_credits_manually', [ $this, 'handle_manual_credit_addition' ] );
        add_action( 'wp_ajax_dsbc_add_credits_with_payment', [ $this, 'handle_add_credits_with_payment' ] );
        add_action( 'wp_ajax_dsbc_mark_payment_paid', [ $this, 'handle_mark_payment_paid' ] );
        add_action( 'wp_ajax_dsbc_get_payment_data', [ $this, 'handle_get_payment_data' ] );
        add_action( 'wp_ajax_dsbc_send_payment_reminder', [ $this, 'handle_send_payment_reminder' ] );
        add_action( 'wp_ajax_ds_get_user_history', [ $this, 'handle_get_user_history' ] );
        add_action( 'wp_ajax_ds_process_manual_withdrawal', [ $this, 'handle_process_manual_withdrawal' ] );
        add_action( 'wp_ajax_approve_withdrawal', [ $this, 'handle_approve_withdrawal' ] );
        add_action( 'wp_ajax_reject_withdrawal', [ $this, 'handle_reject_withdrawal' ] );
        add_action( 'wp_ajax_delete_withdrawal', [ $this, 'handle_delete_withdrawal' ] );
        add_action( 'wp_ajax_dsbc_approve_withdrawal', [ $this, 'handle_approve_simple_withdrawal' ] );
        add_action( 'wp_ajax_dsbc_reject_withdrawal', [ $this, 'handle_reject_simple_withdrawal' ] );
        add_action( 'wp_ajax_dsbc_cancel_overdue_payment', [ $this, 'handle_cancel_overdue_payment' ] );
    }

    public function handle_manual_credit_addition() {
        if ( ! $this->verify_nonce( $_POST['nonce'], 'ds_add_credits_nonce' ) ) {
            $this->ajax_response( false, 'Nonce inválido' );
        }
        
        if ( ! $this->check_permissions() ) {
            $this->ajax_response( false, 'Permissão negada' );
        }
        
        $user_id = intval( $_POST['user_id'] );
        $amount = intval( $_POST['amount'] );
        $observation = sanitize_textarea_field( $_POST['observation'] );
        $admin_id = get_current_user_id();
        
        if ( ! $user_id || ! $amount || ! $observation ) {
            $this->ajax_response( false, 'Dados inválidos' );
        }
        
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            $this->ajax_response( false, 'Usuário não encontrado' );
        }
        
        $success = DS_Credit_Manager::add_credits_manually( $user_id, $amount, $observation, $admin_id );
        
        if ( $success ) {
            $this->ajax_response( true, 'Créditos adicionados com sucesso' );
        } else {
            $this->ajax_response( false, 'Erro ao adicionar créditos' );
        }
    }
    
    public function handle_get_user_history() {
        if ( ! $this->verify_nonce( $_POST['nonce'], 'ds_user_history_nonce' ) ) {
            $this->ajax_response( false, 'Nonce inválido' );
        }
        
        if ( ! $this->check_permissions() ) {
            $this->ajax_response( false, 'Permissão negada' );
        }
        
        $user_id = intval( $_POST['user_id'] );
        
        if ( ! $user_id ) {
            $this->ajax_response( false, 'ID inválido' );
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'dsbc_credit_logs';
        
        $logs = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY created_at DESC LIMIT 20",
            $user_id
        ) );
        
        if ( empty( $logs ) ) {
            $this->ajax_response( true, '<p>Nenhum histórico encontrado.</p>' );
        }
        
        $html = '<table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">';
        $html .= '<thead><tr><th>Data</th><th>Tipo</th><th>Valor</th><th>Observação</th><th>Saldo</th></tr></thead><tbody>';
        
        foreach ( $logs as $log ) {
            $type_labels = [
                'manual_addition' => 'Adição Manual',
                'deposit' => 'Depósito',
                'purchase' => 'Compra',
                'tournament' => 'Torneio',
                'withdrawal' => 'Saque'
            ];
            
            $type_label = $type_labels[ $log->type ] ?? $log->type;
            $amount_color = $log->amount > 0 ? '#28a745' : '#dc3545';
            $amount_prefix = $log->amount > 0 ? '+' : '';
            
            $html .= '<tr>';
            $html .= '<td>' . date( 'd/m/Y H:i', strtotime( $log->created_at ) ) . '</td>';
            $html .= '<td>' . esc_html( $type_label ) . '</td>';
            $html .= '<td style="color: ' . $amount_color . '; font-weight: bold;">' . $amount_prefix . number_format( $log->amount ) . '</td>';
            $html .= '<td>' . esc_html( $log->observation ) . '</td>';
            $html .= '<td>' . number_format( $log->old_balance ) . ' → <strong>' . number_format( $log->new_balance ) . '</strong></td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        
        $this->ajax_response( true, $html );
    }
    
    public function handle_process_manual_withdrawal() {
        if ( ! $this->verify_nonce( $_POST['nonce'], 'ds_manual_withdrawal_nonce' ) ) {
            $this->ajax_response( false, 'Nonce inválido' );
        }
        
        if ( ! $this->check_permissions() ) {
            $this->ajax_response( false, 'Permissão negada' );
        }
        
        $user_id = intval( $_POST['user_id'] );
        $amount = floatval( $_POST['amount'] );
        $method = sanitize_text_field( $_POST['method'] );
        $notes = sanitize_textarea_field( $_POST['notes'] );
        
        if ( ! $user_id || ! $amount || ! $method ) {
            $this->ajax_response( false, 'Dados inválidos' );
        }
        
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            $this->ajax_response( false, 'Usuário não encontrado' );
        }
        
        $file_url = '';
        if ( isset( $_FILES['withdrawal_file'] ) && $_FILES['withdrawal_file']['error'] === UPLOAD_ERR_OK ) {
            $upload = wp_handle_upload( $_FILES['withdrawal_file'], [ 'test_form' => false ] );
            if ( $upload && ! isset( $upload['error'] ) ) {
                $file_url = $upload['url'];
            }
        }
        
        if ( class_exists( 'DS_Credit_Manager' ) ) {
            $reason = "Saque manual - {$method}" . ( $notes ? " - {$notes}" : '' );
            if ( $file_url ) {
                $reason .= " - Comprovante: {$file_url}";
            }
            
            $success = DS_Credit_Manager::deduct_credits( $user_id, $amount, $reason );
            
            if ( $success ) {
                $this->ajax_response( true, 'Saque processado com sucesso' );
            } else {
                $this->ajax_response( false, 'Saldo insuficiente ou erro no processamento' );
            }
        } else {
            $this->ajax_response( false, 'Sistema de créditos não disponível' );
        }
    }

    public function handle_approve_withdrawal() {
        if ( ! $this->verify_nonce( $_POST['nonce'], 'ds_withdrawal_action' ) ) {
            $this->ajax_response( false, 'Nonce inválido' );
        }
        
        if ( ! $this->check_permissions() ) {
            $this->ajax_response( false, 'Permissão negada' );
        }
        
        $entry_id = intval( $_POST['entry_id'] );
        
        if ( ! $entry_id || ! class_exists( 'GFAPI' ) ) {
            $this->ajax_response( false, 'ID inválido' );
        }
        
        $entry = \GFAPI::get_entry( $entry_id );
        if ( ! $entry ) {
            $this->ajax_response( false, 'Entrada não encontrada' );
        }
        
        $settings = get_option( $this->option_name, [] );
        $field_mapping = $settings['field_mapping'] ?? [];
        
        $user_id = rgar( $entry, $field_mapping['user_id'] ?? '' );
        $amount = rgar( $entry, $field_mapping['amount'] ?? '' );
        
        if ( ! $user_id || ! $amount ) {
            $this->ajax_response( false, 'Dados inválidos' );
        }
        
        if ( class_exists( 'DS_Credit_Manager' ) ) {
            $success = DS_Credit_Manager::deduct_credits( $user_id, $amount, 'Saque aprovado pelo administrador' );
            if ( ! $success ) {
                $this->ajax_response( false, 'Saldo insuficiente' );
            }
        }
        
        gform_update_meta( $entry_id, 'withdrawal_status', 'Aprovado' );
        
        $this->ajax_response( true, 'Saque aprovado e créditos deduzidos' );
    }
    
    public function handle_reject_withdrawal() {
        if ( ! $this->verify_nonce( $_POST['nonce'], 'ds_withdrawal_action' ) ) {
            $this->ajax_response( false, 'Nonce inválido' );
        }
        
        if ( ! $this->check_permissions() ) {
            $this->ajax_response( false, 'Permissão negada' );
        }
        
        $entry_id = intval( $_POST['entry_id'] );
        $reason = sanitize_text_field( $_POST['reason'] );
        
        if ( ! $entry_id ) {
            $this->ajax_response( false, 'ID inválido' );
        }
        
        gform_update_meta( $entry_id, 'withdrawal_status', 'Rejeitado' );
        gform_update_meta( $entry_id, 'rejection_reason', $reason );
        
        $this->ajax_response( true, 'Saque rejeitado' );
    }
    
    public function handle_delete_withdrawal() {
        if ( ! $this->verify_nonce( $_POST['nonce'], 'ds_withdrawal_action' ) ) {
            $this->ajax_response( false, 'Nonce inválido' );
        }
        
        if ( ! $this->check_permissions() ) {
            $this->ajax_response( false, 'Permissão negada' );
        }
        
        $entry_id = intval( $_POST['entry_id'] );
        
        if ( ! $entry_id ) {
            $this->ajax_response( false, 'ID inválido' );
        }
        
        if ( class_exists( 'GFAPI' ) ) {
            $result = \GFAPI::delete_entry( $entry_id );
            if ( is_wp_error( $result ) ) {
                $this->ajax_response( false, 'Erro ao excluir' );
            }
        }
        
        $this->ajax_response( true, 'Solicitação excluída' );
    }
    
    public function handle_add_credits_with_payment() {
        if ( ! $this->verify_nonce( $_POST['nonce'], 'dsbc_admin_nonce' ) ) {
            $this->ajax_response( false, 'Nonce inválido' );
        }
        
        if ( ! $this->check_permissions() ) {
            $this->ajax_response( false, 'Permissão negada' );
        }
        
        $user_id = intval( $_POST['user_id'] );
        $amount = intval( $_POST['amount'] );
        $observation = sanitize_textarea_field( $_POST['observation'] );
        $payment_type = sanitize_text_field( $_POST['payment_type'] );
        $due_date = sanitize_text_field( $_POST['due_date'] ?? '' );
        $payment_method = sanitize_text_field( $_POST['payment_method'] ?? '' );
        
        if ( ! $user_id || ! $amount || ! $observation ) {
            $this->ajax_response( false, 'Dados obrigatórios não preenchidos' );
        }
        
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            $this->ajax_response( false, 'Usuário não encontrado' );
        }
        
        $payment_status = $payment_type === 'later' ? 'pending' : 'paid';
        $due_date_formatted = $payment_type === 'later' && $due_date ? $due_date : null;
        
        if ( class_exists( 'DS_Payment_Manager' ) ) {
            $payment_manager = new DS_Payment_Manager();
            $success = $payment_manager->add_credits_with_payment_info(
                $user_id,
                $amount,
                $observation,
                get_current_user_id(),
                $payment_status,
                $due_date_formatted,
                $payment_method
            );
            
            if ( $success ) {
                $this->ajax_response( true, 'Créditos adicionados com sucesso!' );
            } else {
                $this->ajax_response( false, 'Erro ao adicionar créditos' );
            }
        } else {
            $this->ajax_response( false, 'Sistema de pagamentos não disponível' );
        }
    }
    
    public function handle_get_payment_data() {
        if ( ! $this->verify_nonce( $_POST['nonce'], 'dsbc_admin_nonce' ) ) {
            $this->ajax_response( false, 'Nonce inválido' );
        }
        
        if ( ! $this->check_permissions() ) {
            $this->ajax_response( false, 'Permissão negada' );
        }
        
        $log_id = intval( $_POST['log_id'] );
        
        if ( ! $log_id ) {
            $this->ajax_response( false, 'ID inválido' );
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'dsbc_credit_logs';
        $log = $wpdb->get_row( $wpdb->prepare( "SELECT payment_receipt FROM $table_name WHERE id = %d", $log_id ) );
        
        if ( $log ) {
            wp_send_json_success( [ 'payment_receipt' => $log->payment_receipt ] );
        } else {
            $this->ajax_response( false, 'Registro não encontrado' );
        }
    }
    
    public function handle_mark_payment_paid() {
        if ( ! $this->verify_nonce( $_POST['nonce'], 'dsbc_admin_nonce' ) ) {
            $this->ajax_response( false, 'Nonce inválido' );
        }
        
        if ( ! $this->check_permissions() ) {
            $this->ajax_response( false, 'Permissão negada' );
        }
        
        $log_id = intval( $_POST['log_id'] );
        $payment_notes = sanitize_textarea_field( $_POST['payment_notes'] ?? '' );
        
        if ( ! $log_id ) {
            $this->ajax_response( false, 'ID inválido' );
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'dsbc_credit_logs';
        
        // Verificar se já existe comprovante
        $existing_receipt = $wpdb->get_var( $wpdb->prepare( "SELECT payment_receipt FROM $table_name WHERE id = %d", $log_id ) );
        
        // Processar upload do comprovante (opcional se já existir)
        $receipt_url = $existing_receipt;
        if ( isset( $_FILES['payment_receipt'] ) && $_FILES['payment_receipt']['error'] === UPLOAD_ERR_OK ) {
            $upload = wp_handle_upload( $_FILES['payment_receipt'], [ 'test_form' => false ] );
            if ( $upload && ! isset( $upload['error'] ) ) {
                $receipt_url = $upload['url'];
            } else {
                $this->ajax_response( false, 'Erro no upload: ' . ( $upload['error'] ?? 'Erro desconhecido' ) );
            }
        } elseif ( ! $existing_receipt ) {
            $this->ajax_response( false, 'Comprovante é obrigatório' );
        }
        
        $update_data = [ 'payment_status' => 'paid' ];
        $update_format = [ '%s' ];
        
        if ( $receipt_url ) {
            $update_data['payment_receipt'] = $receipt_url;
            $update_format[] = '%s';
        }
        
        if ( $payment_notes ) {
            $current_observation = $wpdb->get_var( $wpdb->prepare( "SELECT observation FROM $table_name WHERE id = %d", $log_id ) );
            $new_observation = $current_observation . "\n\nPagamento confirmado: " . $payment_notes;
            $update_data['observation'] = $new_observation;
            $update_format[] = '%s';
        }
        
        $result = $wpdb->update(
            $table_name,
            $update_data,
            [ 'id' => $log_id ],
            $update_format,
            [ '%d' ]
        );
        
        if ( $result !== false ) {
            $log = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $log_id ) );
            if ( $log ) {
                DS_Credit_Manager::send_deposit_notification( $log->user_id, $log->amount, $log->new_balance );
            }
            
            $this->ajax_response( true, 'Pagamento confirmado com sucesso!' );
        } else {
            $this->ajax_response( false, 'Erro ao atualizar pagamento' );
        }
    }
    
    public function handle_send_payment_reminder() {
        if ( ! $this->verify_nonce( $_POST['nonce'], 'dsbc_admin_nonce' ) ) {
            $this->ajax_response( false, 'Nonce inválido' );
        }
        
        if ( ! $this->check_permissions() ) {
            $this->ajax_response( false, 'Permissão negada' );
        }
        
        $log_id = intval( $_POST['log_id'] );
        
        if ( ! $log_id ) {
            $this->ajax_response( false, 'ID inválido' );
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'dsbc_credit_logs';
        
        $log = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $log_id ) );
        
        if ( ! $log ) {
            $this->ajax_response( false, 'Registro não encontrado' );
        }
        
        // Enviar lembrete via WhatsApp
        if ( class_exists( 'WhatsApp_Connector' ) ) {
            $user_data = get_userdata( $log->user_id );
            if ( $user_data ) {
                $user_name = $user_data->first_name ?: $user_data->display_name;
                $due_date = $log->payment_due_date ? date_i18n( 'd/m/Y', strtotime( $log->payment_due_date ) ) : 'não definida';
                
                $message = "Olá {$user_name}!\n\n";
                $message .= "Lembrete: Você possui um pagamento pendente de {$log->amount} créditos.\n";
                $message .= "Data de vencimento: {$due_date}\n";
                $message .= "Observação: {$log->observation}\n\n";
                $message .= "Por favor, efetue o pagamento o quanto antes.";
                
                $whatsapp = new WhatsApp_Connector();
                $phone = get_user_meta( $log->user_id, 'user_whatsapp', true ) ?: get_user_meta( $log->user_id, 'billing_phone', true );
                
                if ( $phone ) {
                    $whatsapp->send_message( $phone, $message );
                    $this->ajax_response( true, 'Lembrete enviado via WhatsApp!' );
                } else {
                    $this->ajax_response( false, 'Telefone do usuário não encontrado' );
                }
            } else {
                $this->ajax_response( false, 'Usuário não encontrado' );
            }
        } else {
            $this->ajax_response( false, 'Sistema de WhatsApp não disponível' );
        }
    }
    
    public function handle_approve_simple_withdrawal() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'dsbc_withdrawal_action' ) ) {
            wp_send_json_error( 'Nonce inválido' );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permissão negada' );
        }
        
        $request_id = intval( $_POST['request_id'] ?? 0 );
        $notes = sanitize_textarea_field( $_POST['notes'] ?? '' );
        
        if ( ! $request_id ) {
            wp_send_json_error( 'ID inválido' );
        }
        
        // Upload do comprovante
        $receipt_url = '';
        if ( isset( $_FILES['receipt'] ) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK ) {
            $upload = wp_handle_upload( $_FILES['receipt'], [ 'test_form' => false ] );
            if ( $upload && ! isset( $upload['error'] ) ) {
                $receipt_url = $upload['url'];
            } else {
                wp_send_json_error( 'Erro no upload do comprovante' );
            }
        } else {
            wp_send_json_error( 'Comprovante é obrigatório' );
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ds_withdrawal_requests';
        $request = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $request_id ) );
        
        if ( ! $request || $request->status !== 'pending' ) {
            wp_send_json_error( 'Solicitação não encontrada ou já processada' );
        }
        
        // Deduzir créditos
        if ( class_exists( 'DS_Credit_Manager' ) ) {
            $reason = "Saque aprovado #$request_id";
            if ( $notes ) $reason .= " - $notes";
            if ( $receipt_url ) $reason .= " - Comprovante: $receipt_url";
            
            $success = DS_Credit_Manager::deduct_credits( $request->user_id, $request->amount, $reason );
            
            if ( $success ) {
                // Atualizar status
                $wpdb->update(
                    $table_name,
                    [
                        'status' => 'approved',
                        'processed_by' => get_current_user_id(),
                        'processed_at' => current_time('mysql'),
                        'notes' => $request->notes . "\n\nAprovado: " . $notes . "\nComprovante: " . $receipt_url
                    ],
                    [ 'id' => $request_id ],
                    [ '%s', '%d', '%s', '%s' ],
                    [ '%d' ]
                );
                
                // Notificar usuário
                if ( class_exists( 'DS_Simple_Withdrawals' ) ) {
                    DS_Simple_Withdrawals::notify_user_status_change( $request->user_id, $request->amount, 'approved' );
                }
                
                wp_send_json_success( 'Saque aprovado com sucesso!' );
            } else {
                wp_send_json_error( 'Saldo insuficiente' );
            }
        } else {
            wp_send_json_error( 'Sistema de créditos não disponível' );
        }
    }
    
    public function handle_reject_simple_withdrawal() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'dsbc_withdrawal_action' ) ) {
            wp_send_json_error( 'Nonce inválido' );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permissão negada' );
        }
        
        $request_id = intval( $_POST['request_id'] ?? 0 );
        $reason = sanitize_textarea_field( $_POST['reason'] ?? '' );
        
        if ( ! $request_id ) {
            wp_send_json_error( 'ID inválido' );
        }
        
        if ( class_exists( 'DS_Simple_Withdrawals' ) ) {
            $success = DS_Simple_Withdrawals::reject_withdrawal( $request_id, $reason );
            if ( $success ) {
                wp_send_json_success( 'Saque rejeitado com sucesso!' );
            } else {
                wp_send_json_error( 'Erro ao rejeitar saque.' );
            }
        } else {
            wp_send_json_error( 'Sistema de saques não disponível' );
        }
    }
    
    public function handle_cancel_overdue_payment() {
        if ( ! $this->verify_nonce( $_POST['nonce'], 'dsbc_admin_nonce' ) ) {
            $this->ajax_response( false, 'Nonce inválido' );
        }
        
        if ( ! $this->check_permissions() ) {
            $this->ajax_response( false, 'Permissão negada' );
        }
        
        $log_id = intval( $_POST['log_id'] );
        
        if ( ! $log_id ) {
            $this->ajax_response( false, 'ID inválido' );
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'dsbc_credit_logs';
        
        // Buscar o log
        $log = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $log_id ) );
        
        if ( ! $log ) {
            $this->ajax_response( false, 'Registro não encontrado' );
        }
        
        // Verificar se é um pagamento vencido
        if ( $log->payment_status !== 'overdue' && $log->payment_status !== 'pending' ) {
            $this->ajax_response( false, 'Apenas pagamentos pendentes ou vencidos podem ser cancelados' );
        }
        
        // Remover os créditos do usuário
        if ( class_exists( 'DS_Credit_Manager' ) ) {
            $success = DS_Credit_Manager::deduct_credits( 
                $log->user_id, 
                $log->amount, 
                'Cancelamento de pagamento vencido - Log #' . $log_id 
            );
            
            if ( $success ) {
                // Marcar o log como cancelado
                $wpdb->update(
                    $table_name,
                    [ 
                        'payment_status' => 'cancelled',
                        'observation' => $log->observation . "\n\nPagamento cancelado em " . current_time( 'mysql' ) . " por " . wp_get_current_user()->display_name
                    ],
                    [ 'id' => $log_id ],
                    [ '%s', '%s' ],
                    [ '%d' ]
                );
                
                // Notificar usuário
                if ( class_exists( 'DS_Notification_i18n' ) ) {
                    $user_data = get_userdata( $log->user_id );
                    if ( $user_data ) {
                        $user_name = $user_data->first_name ?: $user_data->display_name;
                        DS_Notification_i18n::send( $log->user_id, 'payment_cancelled', [
                            'name' => $user_name,
                            'amount' => number_format( $log->amount, 0, ',', '.' ),
                            'priority' => 'critical'
                        ] );
                    }
                }
                
                $this->ajax_response( true, 'Pagamento cancelado e créditos removidos com sucesso!' );
            } else {
                $this->ajax_response( false, 'Erro ao remover créditos. Verifique se o usuário tem saldo suficiente.' );
            }
        } else {
            $this->ajax_response( false, 'Sistema de créditos não disponível' );
        }
    }
}