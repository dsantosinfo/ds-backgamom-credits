<?php
/**
 * Gerenciador de Taxas e Descontos por Gateway
 *
 * @package DS_Backgamom_Credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DS_Gateway_Fees {

    public function __construct() {
        add_action( 'woocommerce_cart_calculate_fees', [ $this, 'add_gateway_fee' ] );
        add_filter( 'woocommerce_gateway_title', [ $this, 'add_fee_to_gateway_title' ], 10, 2 );
    }

    /**
     * Adiciona taxa/desconto ao carrinho baseado no gateway selecionado
     */
    public function add_gateway_fee( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        $chosen_gateway = WC()->session->get( 'chosen_payment_method' );
        
        if ( ! $chosen_gateway ) {
            return;
        }

        $fee_config = $this->get_gateway_fee_config( $chosen_gateway );
        
        if ( ! $fee_config || ! $fee_config['enabled'] ) {
            return;
        }

        $cart_total = $cart->get_subtotal() + $cart->get_subtotal_tax();
        $fee_amount = $this->calculate_fee( $cart_total, $fee_config );

        if ( $fee_amount != 0 ) {
            $label = $fee_amount > 0 
                ? sprintf( __( 'Taxa %s', 'ds-backgamom-credits' ), $fee_config['label'] )
                : sprintf( __( 'Desconto %s', 'ds-backgamom-credits' ), $fee_config['label'] );
            
            $cart->add_fee( $label, $fee_amount, $fee_config['taxable'] );
        }
    }

    /**
     * Calcula o valor da taxa/desconto
     */
    private function calculate_fee( $cart_total, $config ) {
        $fee = 0;

        // Taxa percentual
        if ( ! empty( $config['percentage'] ) ) {
            $fee += ( $cart_total * $config['percentage'] ) / 100;
        }

        // Taxa fixa
        if ( ! empty( $config['fixed'] ) ) {
            $fee += $config['fixed'];
        }

        return $fee;
    }

    /**
     * Adiciona informação de taxa/desconto ao título do gateway
     */
    public function add_fee_to_gateway_title( $title, $gateway_id ) {
        if ( ! is_checkout() ) {
            return $title;
        }

        $fee_config = $this->get_gateway_fee_config( $gateway_id );
        
        if ( ! $fee_config || ! $fee_config['enabled'] || ! $fee_config['show_in_title'] ) {
            return $title;
        }

        $fee_text = $this->get_fee_description( $fee_config );
        
        if ( $fee_text ) {
            $title .= ' <small style="color: #666;">(' . $fee_text . ')</small>';
        }

        return $title;
    }

    /**
     * Gera descrição da taxa/desconto
     */
    private function get_fee_description( $config ) {
        $parts = [];

        if ( ! empty( $config['percentage'] ) ) {
            $sign = $config['percentage'] > 0 ? '+' : '';
            $parts[] = $sign . $config['percentage'] . '%';
        }

        if ( ! empty( $config['fixed'] ) ) {
            $sign = $config['fixed'] > 0 ? '+' : '';
            $parts[] = $sign . wc_price( abs( $config['fixed'] ) );
        }

        return implode( ' ', $parts );
    }

    /**
     * Obtém configuração de taxa para um gateway
     */
    private function get_gateway_fee_config( $gateway_id ) {
        $all_configs = get_option( 'dsbc_gateway_fees', [] );
        
        if ( ! isset( $all_configs[$gateway_id] ) ) {
            return null;
        }

        return wp_parse_args( $all_configs[$gateway_id], [
            'enabled'       => false,
            'label'         => '',
            'percentage'    => 0,
            'fixed'         => 0,
            'taxable'       => false,
            'show_in_title' => true,
        ]);
    }

    /**
     * Salva configuração de taxa para um gateway
     */
    public static function save_gateway_fee_config( $gateway_id, $config ) {
        $all_configs = get_option( 'dsbc_gateway_fees', [] );
        $all_configs[$gateway_id] = $config;
        update_option( 'dsbc_gateway_fees', $all_configs );
    }

    /**
     * Obtém todos os gateways disponíveis
     */
    public static function get_available_gateways() {
        $gateways = WC()->payment_gateways->payment_gateways();
        $available = [];

        foreach ( $gateways as $gateway ) {
            if ( $gateway->enabled === 'yes' ) {
                $available[$gateway->id] = $gateway->get_title();
            }
        }

        return $available;
    }
}
