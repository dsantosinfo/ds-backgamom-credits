<?php
/**
 * Helper para Gateways BRL
 * Converte créditos USD para valor BRL em qualquer gateway brasileiro
 *
 * @package DS_Backgamom_Credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DS_BRL_Gateway_Helper {

    /**
     * Processa conversão de créditos para BRL em qualquer gateway
     * 
     * @param WC_Order $order Pedido do WooCommerce
     * @return array|false Array com dados ou false se erro
     */
    public static function process_credits_to_brl( $order ) {
        if ( ! $order ) {
            return false;
        }

        // Calcular total de créditos do pedido
        $total_credits = 0;
        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            $credits = DS_Credit_Converter::get_product_credits( $product_id );
            $total_credits += $credits * $item->get_quantity();
        }
        
        if ( $total_credits <= 0 ) {
            return false;
        }
        
        // Converter créditos USD para valor BRL
        $amount_brl = DS_Credit_Converter::convert_credits_to_brl( $total_credits );
        $exchange_rate = DS_Credit_Converter::get_exchange_rate();
        
        // Salvar informações no pedido
        $order->update_meta_data( '_dsbc_total_credits', $total_credits );
        $order->update_meta_data( '_dsbc_amount_brl', $amount_brl );
        $order->update_meta_data( '_dsbc_exchange_rate_used', $exchange_rate );
        
        // Atualizar total do pedido
        $order->set_total( $amount_brl );
        $order->save();
        
        return [
            'credits' => $total_credits,
            'amount_brl' => $amount_brl,
            'exchange_rate' => $exchange_rate
        ];
    }

    /**
     * Gera descrição padronizada para gateways BRL
     * 
     * @param WC_Order $order Pedido
     * @param float $credits Créditos
     * @param string $gateway_name Nome do gateway
     * @return string Descrição formatada
     */
    public static function get_payment_description( $order, $credits, $gateway_name = 'Pagamento' ) {
        return sprintf( 
            'Pedido #%s - %.2f créditos (US$ %.2f) via %s', 
            $order->get_order_number(), 
            $credits, 
            $credits,
            $gateway_name
        );
    }

    /**
     * Gera nota de status padronizada
     * 
     * @param float $amount_brl Valor em BRL
     * @param float $credits Créditos
     * @param string $gateway_name Nome do gateway
     * @return string Nota formatada
     */
    public static function get_status_note( $amount_brl, $credits, $gateway_name = 'Gateway' ) {
        return sprintf( 
            'Aguardando %s: R$ %.2f por %.2f créditos', 
            $gateway_name, 
            $amount_brl, 
            $credits 
        );
    }

    /**
     * Valida se o pedido tem produtos com créditos
     * 
     * @param WC_Order $order Pedido
     * @return bool True se válido
     */
    public static function validate_credits_order( $order ) {
        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            $credits = DS_Credit_Converter::get_product_credits( $product_id );
            if ( $credits > 0 ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Hook para aplicar conversão em gateways existentes
     */
    public static function init_gateway_hooks() {
        // Hook genérico para qualquer gateway BRL
        add_action( 'woocommerce_checkout_order_processed', [ self::class, 'auto_convert_brl_gateways' ], 10, 3 );
    }

    /**
     * Converte automaticamente pedidos de gateways BRL
     * 
     * @param int $order_id ID do pedido
     * @param array $posted_data Dados do checkout
     * @param WC_Order $order Pedido
     */
    public static function auto_convert_brl_gateways( $order_id, $posted_data, $order ) {
        $payment_method = $order->get_payment_method();
        
        // Lista de gateways BRL que precisam de conversão
        $brl_gateways = apply_filters( 'dsbc_brl_gateways', [
            'bacs',           // Transferência bancária
            'cheque',         // Cheque
            'cod',            // Pagamento na entrega
            'paypal',         // PayPal (se configurado para BRL)
            'stripe',         // Stripe (se BRL)
            'pagseguro',      // PagSeguro
            'mercadopago',    // Mercado Pago
            'cielo',          // Cielo
            'rede',           // Rede
            'getnet',         // Getnet
        ]);
        
        // Verificar se é gateway BRL e não foi processado ainda
        if ( in_array( $payment_method, $brl_gateways ) && ! $order->get_meta( '_dsbc_brl_converted' ) ) {
            $conversion = self::process_credits_to_brl( $order );
            
            if ( $conversion ) {
                $order->update_meta_data( '_dsbc_brl_converted', true );
                $order->add_order_note( 
                    sprintf( 
                        'Conversão automática: %.2f créditos = R$ %.2f (Taxa: %.2f)', 
                        $conversion['credits'], 
                        $conversion['amount_brl'], 
                        $conversion['exchange_rate'] 
                    )
                );
                $order->save();
            }
        }
    }
}

// Inicializar hooks
DS_BRL_Gateway_Helper::init_gateway_hooks();