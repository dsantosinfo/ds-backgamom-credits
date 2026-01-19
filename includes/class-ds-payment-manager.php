<?php
/**
 * Gerenciador de Pagamentos Posteriores
 *
 * @package DS_Backgamom_Credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DS_Payment_Manager {

    public function __construct() {
        add_action( 'wp_ajax_dsbc_add_credits_with_payment', [ $this, 'ajax_add_credits_with_payment' ] );
        add_action( 'wp_ajax_dsbc_mark_payment_paid', [ $this, 'ajax_mark_payment_paid' ] );
        add_action( 'wp_ajax_dsbc_send_payment_reminder', [ $this, 'ajax_send_payment_reminder' ] );
        add_action( 'wp_ajax_dsbc_get_user_country', [ $this, 'ajax_get_user_country' ] );
        add_action( 'dsbc_daily_payment_check', [ $this, 'check_overdue_payments' ] );
        
        // Agendar verificação diária
        if ( ! wp_next_scheduled( 'dsbc_daily_payment_check' ) ) {
            wp_schedule_event( time(), 'daily', 'dsbc_daily_payment_check' );
        }
    }

    /**
     * Adiciona créditos com opção de pagamento posterior
     */
    public function ajax_add_credits_with_payment() {
        check_ajax_referer( 'dsbc_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Sem permissão' );
        }

        $user_id = intval( $_POST['user_id'] );
        $amount = floatval( $_POST['amount'] );
        $observation = sanitize_textarea_field( $_POST['observation'] );
        $payment_type = sanitize_text_field( $_POST['payment_type'] );
        $due_date = sanitize_text_field( $_POST['due_date'] ?? '' );
        $payment_method = sanitize_text_field( $_POST['payment_method'] ?? '' );

        if ( ! $user_id || ! $amount || ! $observation ) {
            wp_send_json_error( 'Dados obrigatórios não preenchidos' );
        }

        $payment_status = $payment_type === 'later' ? 'pending' : 'paid';
        $due_date_formatted = $payment_type === 'later' && $due_date ? $due_date : null;

        $result = $this->add_credits_with_payment_info(
            $user_id,
            $amount,
            $observation,
            get_current_user_id(),
            $payment_status,
            $due_date_formatted,
            $payment_method
        );

        if ( $result ) {
            wp_send_json_success( 'Créditos adicionados com sucesso!' );
        } else {
            wp_send_json_error( 'Erro ao adicionar créditos' );
        }
    }

    /**
     * Adiciona créditos com informações de pagamento
     */
    public function add_credits_with_payment_info( $user_id, $amount, $observation, $admin_id, $payment_status = 'paid', $due_date = null, $payment_method = null ) {
        $current_balance = DS_Credit_Manager::get_balance( $user_id );
        $new_balance = $current_balance + floatval( $amount );

        $result = update_user_meta( $user_id, '_dsbc_credit_balance', $new_balance );

        if ( $result ) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'dsbc_credit_logs';
            
            $admin_user = get_userdata( $admin_id );
            $admin_name = $admin_user ? $admin_user->display_name : 'Admin';
            
            $insert_result = $wpdb->insert(
                $table_name,
                [
                    'user_id' => $user_id,
                    'amount' => floatval( $amount ),
                    'type' => 'manual_addition',
                    'observation' => $observation,
                    'admin_id' => $admin_id,
                    'admin_name' => $admin_name,
                    'old_balance' => floatval( $current_balance ),
                    'new_balance' => floatval( $new_balance ),
                    'payment_due_date' => $due_date,
                    'payment_status' => $payment_status,
                    'payment_method' => $payment_method,
                    'created_at' => current_time( 'mysql' )
                ],
                [ '%d', '%f', '%s', '%s', '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%s' ]
            );

            // Enviar notificação apenas se for pagamento imediato
            if ( $payment_status === 'paid' ) {
                DS_Credit_Manager::send_deposit_notification( $user_id, $amount, $new_balance );
            } else {
                // Enviar notificação de créditos com pagamento agendado
                $this->send_scheduled_credits_notification( $user_id, $amount, $new_balance, $due_date, $payment_method, $observation );
            }
        }

        return $result !== false;
    }

    /**
     * Marca pagamento como pago
     */
    public function ajax_mark_payment_paid() {
        check_ajax_referer( 'dsbc_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Sem permissão' );
        }

        $log_id = intval( $_POST['log_id'] );
        
        if ( ! $log_id ) {
            wp_send_json_error( 'ID inválido' );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'dsbc_credit_logs';
        
        $result = $wpdb->update(
            $table_name,
            [ 'payment_status' => 'paid' ],
            [ 'id' => $log_id ],
            [ '%s' ],
            [ '%d' ]
        );

        if ( $result ) {
            // Buscar dados do log para notificação
            $log = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $log_id ) );
            if ( $log ) {
                DS_Credit_Manager::send_deposit_notification( $log->user_id, $log->amount, $log->new_balance );
            }
            
            wp_send_json_success( 'Pagamento marcado como pago!' );
        } else {
            wp_send_json_error( 'Erro ao atualizar pagamento' );
        }
    }

    /**
     * Envia lembrete de pagamento
     */
    public function ajax_send_payment_reminder() {
        check_ajax_referer( 'dsbc_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Sem permissão' );
        }

        $log_id = intval( $_POST['log_id'] );
        
        if ( ! $log_id ) {
            wp_send_json_error( 'ID inválido' );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'dsbc_credit_logs';
        
        $log = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $log_id ) );
        
        if ( ! $log ) {
            wp_send_json_error( 'Registro não encontrado' );
        }

        $this->send_payment_reminder( $log );
        wp_send_json_success( 'Lembrete enviado!' );
    }

    /**
     * Envia lembrete de pagamento
     */
    private function send_payment_reminder( $log ) {
        if ( ! class_exists( 'DS_Notification_i18n' ) ) {
            return false;
        }

        $user_data = get_userdata( $log->user_id );
        if ( ! $user_data ) {
            return false;
        }

        $user_name = $user_data->first_name ?: $user_data->display_name;
        $user_country = get_user_meta( $log->user_id, 'billing_country', true );
        $due_date = date_i18n( 'd/m/Y', strtotime( $log->payment_due_date ) );

        $vars = [
            'name' => $user_name,
            'amount' => '$' . number_format( $log->amount, 2 ),
            'due_date' => $due_date,
            'observation' => $log->observation,
            'priority' => 'high'
        ];
        
        // Adicionar conversão BRL para brasileiros
        if ( $user_country === 'BR' ) {
            $exchange_rate = DS_Credit_Converter::get_exchange_rate();
            $vars['amount_brl'] = 'R$ ' . number_format( $log->amount * $exchange_rate, 2, ',', '.' );
        }

        DS_Notification_i18n::send( $log->user_id, 'payment_reminder', $vars );

        return true;
    }

    /**
     * Verifica pagamentos vencidos diariamente
     */
    public function check_overdue_payments() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dsbc_credit_logs';
        
        // Atualizar status para vencido
        $wpdb->query( $wpdb->prepare(
            "UPDATE $table_name 
             SET payment_status = 'overdue' 
             WHERE payment_status = 'pending' 
             AND payment_due_date < %s",
            current_time( 'Y-m-d' )
        ) );

        // Buscar pagamentos vencidos há 1 dia para enviar lembrete
        $overdue_payments = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE payment_status = 'overdue' 
             AND payment_due_date = %s",
            date( 'Y-m-d', strtotime( '-1 day' ) )
        ) );

        foreach ( $overdue_payments as $payment ) {
            $this->send_payment_reminder( $payment );
        }
    }

    /**
     * Obtém relatório de pagamentos pendentes/vencidos
     */
    public static function get_pending_payments( $status = 'all', $limit = 50 ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dsbc_credit_logs';
        
        if ( $status === 'pending' ) {
            $where = "WHERE payment_status = 'pending'";
        } elseif ( $status === 'overdue' ) {
            $where = "WHERE payment_status = 'overdue'";
        } else {
            $where = "WHERE payment_due_date IS NOT NULL";
        }
        
        $query = $wpdb->prepare(
            "SELECT l.*, u.display_name, u.user_email 
             FROM $table_name l 
             LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID 
             $where 
             ORDER BY l.payment_due_date ASC, l.created_at DESC 
             LIMIT %d",
            $limit
        );
        
        return $wpdb->get_results( $query );
    }

    /**
     * Obtém estatísticas de pagamentos
     */
    public static function get_payment_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dsbc_credit_logs';
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN payment_status = 'overdue' THEN 1 END) as overdue_count,
                COUNT(CASE WHEN payment_status = 'paid' AND payment_due_date IS NOT NULL THEN 1 END) as paid_count,
                SUM(CASE WHEN payment_status = 'pending' THEN amount ELSE 0 END) as pending_amount,
                SUM(CASE WHEN payment_status = 'overdue' THEN amount ELSE 0 END) as overdue_amount
             FROM $table_name 
             WHERE payment_due_date IS NOT NULL"
        );
        
        return $stats;
    }
    
    /**
     * Envia notificação de créditos com pagamento agendado
     */
    private function send_scheduled_credits_notification( $user_id, $amount, $new_balance, $due_date, $payment_method, $observation = '' ) {
        if ( ! class_exists( 'DS_Notification_i18n' ) ) {
            return false;
        }

        $user_data = get_userdata( $user_id );
        if ( ! $user_data ) {
            return false;
        }

        $user_name = $user_data->first_name ?: $user_data->display_name;
        $user_country = get_user_meta( $user_id, 'billing_country', true );
        $due_date_formatted = $due_date ? date_i18n( 'd/m/Y', strtotime( $due_date ) ) : 'N/A';
        $payment_method_formatted = $payment_method ? ucfirst( $payment_method ) : 'N/A';

        $vars = [
            'name' => $user_name,
            'amount' => '$' . number_format( $amount, 2 ),
            'balance' => '$' . number_format( $new_balance, 2 ),
            'due_date' => $due_date_formatted,
            'payment_method' => $payment_method_formatted,
            'observation' => $observation,
            'priority' => 'high'
        ];
        
        // Adicionar conversão BRL para brasileiros
        if ( $user_country === 'BR' ) {
            $exchange_rate = DS_Credit_Converter::get_exchange_rate();
            $vars['amount_brl'] = 'R$ ' . number_format( $amount * $exchange_rate, 2, ',', '.' );
            $vars['balance_brl'] = 'R$ ' . number_format( $new_balance * $exchange_rate, 2, ',', '.' );
        }

        DS_Notification_i18n::send( $user_id, 'credits_scheduled', $vars );

        return true;
    }
    
    /**
     * Busca país do usuário via AJAX
     */
    public function ajax_get_user_country() {
        check_ajax_referer( 'dsbc_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Sem permissão' );
        }

        $user_id = intval( $_POST['user_id'] );
        
        if ( ! $user_id ) {
            wp_send_json_error( 'ID inválido' );
        }

        $country = get_user_meta( $user_id, 'billing_country', true );
        $country_name = $this->get_country_name( $country );
        
        wp_send_json_success( [
            'country' => $country ?: 'N/A',
            'country_name' => $country_name
        ] );
    }
    
    /**
     * Obtém nome do país pelo código
     */
    private function get_country_name( $country_code ) {
        $countries = [
            'BR' => 'Brasil',
            'US' => 'Estados Unidos',
            'CA' => 'Canadá',
            'GB' => 'Reino Unido',
            'DE' => 'Alemanha',
            'FR' => 'França',
            'IT' => 'Itália',
            'ES' => 'Espanha',
            'PT' => 'Portugal',
            'AR' => 'Argentina',
            'MX' => 'México',
            'CL' => 'Chile',
            'CO' => 'Colômbia'
        ];
        
        return $countries[ $country_code ] ?? $country_code ?: 'Não informado';
    }
}