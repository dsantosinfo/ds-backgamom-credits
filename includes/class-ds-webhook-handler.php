<?php
/**
 * Manipulador de Webhooks Asaas
 *
 * @package DS_Backgamom_Credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class DS_Webhook_Handler {

    /**
     * Namespace da rota da API REST.
     *
     * @var string
     */
    private $namespace = 'ds-backgamom-credits/v1';

    /**
     * Construtor.
     */
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_webhook_route' ] );
    }

    /**
     * Registra a rota do webhook.
     */
    public function register_webhook_route() {
        register_rest_route( $this->namespace, '/asaas-webhook', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_webhook_request' ],
            'permission_callback' => [ $this, 'validate_webhook_token' ],
        ] );
    }

    /**
     * Valida o token do webhook Asaas.
     *
     * @param WP_REST_Request $request Requisição.
     * @return bool
     */
    public function validate_webhook_token( WP_REST_Request $request ) {
        $local_token = get_option( 'dsbc_asaas_webhook_token' );
        $request_token = $request->get_header( 'asaas-access-token' );
        
        if ( empty( $local_token ) ) {
            error_log( 'DS Webhook: Token local não configurado' );
            return false;
        }
        
        if ( empty( $request_token ) ) {
            error_log( 'DS Webhook: Token não enviado na requisição' );
            return false;
        }
        
        $is_valid = hash_equals( $local_token, $request_token );
        
        if ( ! $is_valid ) {
            error_log( 'DS Webhook: Token inválido - Local: ' . substr( $local_token, 0, 8 ) . '... Request: ' . substr( $request_token, 0, 8 ) . '...' );
        }
        
        return $is_valid;
    }

    /**
     * Manipula a requisição do webhook.
     *
     * @param WP_REST_Request $request Requisição.
     * @return WP_REST_Response
     */
    public function handle_webhook_request( WP_REST_Request $request ) {
        $body = $request->get_json_params();
        error_log( 'Asaas Webhook: ' . wp_json_encode( $body ) );

        if ( ! isset( $body['event'] ) || ! isset( $body['payment'] ) ) {
            return new WP_REST_Response( [ 'status' => 'error', 'message' => 'Payload inválido.' ], 400 );
        }

        $event = $body['event'];
        $payment = $body['payment'];
        $payment_id = $payment['id'] ?? '';
        $order_id = isset( $payment['externalReference'] ) ? (int) $payment['externalReference'] : 0;
        
        if ( ! $order_id ) {
            return new WP_REST_Response( [ 'status' => 'ok', 'message' => 'Ignorado: externalReference não encontrada.' ], 200 );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_REST_Response( [ 'status' => 'error', 'message' => 'Pedido não encontrado.' ], 404 );
        }

        $payment_status = $payment['status'] ?? '';
        $billing_type = $payment['billingType'] ?? '';
        $payment_value = $payment['value'] ?? 0;
        $net_value = $payment['netValue'] ?? 0;
        
        switch ( $event ) {
            case 'PAYMENT_CREATED':
                $order->add_order_note( "Cobrança criada no Asaas (ID: {$payment_id}, Tipo: {$billing_type}, Valor: R$ {$payment_value})" );
                break;
                
            case 'PAYMENT_CONFIRMED':
            case 'PAYMENT_RECEIVED':
                $this->process_payment_success( $order, $event, $payment );
                break;
                
            case 'PAYMENT_OVERDUE':
                $order->update_status( 'failed', "Pagamento vencido no Asaas (ID: {$payment_id})" );
                break;
                
            case 'PAYMENT_REFUNDED':
                $this->process_refund( $order, $payment );
                break;
                
            case 'PAYMENT_DELETED':
                $order->add_order_note( "Cobrança removida no Asaas (ID: {$payment_id})" );
                break;
                
            case 'PAYMENT_UPDATED':
                $order->add_order_note( "Cobrança atualizada no Asaas (ID: {$payment_id}, Status: {$payment_status})" );
                break;
                
            case 'PAYMENT_RESTORED':
                $order->add_order_note( "Cobrança restaurada no Asaas (ID: {$payment_id})" );
                break;
        }

        return new WP_REST_Response( [ 'status' => 'success', 'event' => $event ], 200 );
    }
    
    private function process_payment_success( $order, $event, $payment ) {
        if ( $order->get_meta( '_dsbc_credits_awarded' ) ) {
            return;
        }

        $total_credits = 0;
        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            $credits = get_post_meta( $product_id, '_dsbc_credits_amount', true );
            if ( is_numeric( $credits ) ) {
                $total_credits += (int) $credits * $item->get_quantity();
            }
        }

        if ( $total_credits > 0 ) {
            $user_id = $order->get_customer_id();
            if ( $user_id ) {
                DS_Credit_Manager::add_credits( $user_id, $total_credits, $order->get_id() );
            }
        }

        $order->update_meta_data( '_dsbc_credits_awarded', true );
        $order->update_meta_data( '_asaas_payment_data', wp_json_encode( $payment ) );
        $order->save();

        $payment_id = $payment['id'] ?? '';
        $billing_type = $payment['billingType'] ?? '';
        $net_value = $payment['netValue'] ?? 0;
        
        $status_msg = $event === 'PAYMENT_CONFIRMED' ? 'Pagamento confirmado' : 'Pagamento recebido';
        $order->update_status( 'completed', "{$status_msg} via {$billing_type} (ID: {$payment_id}, Líquido: R$ {$net_value})" );
    }
    
    private function process_refund( $order, $payment ) {
        $payment_id = $payment['id'] ?? '';
        $order->update_status( 'refunded', "Pagamento estornado no Asaas (ID: {$payment_id})" );
        
        if ( $order->get_meta( '_dsbc_credits_awarded' ) ) {
            $order->add_order_note( 'ATENÇÃO: Créditos já foram concedidos. Remoção manual necessária.' );
        }
    }
}
