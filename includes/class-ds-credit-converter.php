<?php
/**
 * Conversor de Pagamentos para Créditos USD
 * NOVO SISTEMA: 1 crédito = 1 USD sempre
 *
 * @package DS_Backgamom_Credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DS_Credit_Converter {

    /**
     * Taxa de câmbio padrão USD/BRL
     */
    private static $default_exchange_rate = 5.67;

    /**
     * Obtém taxa de câmbio atual USD/BRL
     * 
     * @return float Taxa de câmbio
     */
    public function get_usd_to_brl_rate() {
        return self::get_exchange_rate();
    }

    /**
     * Converte valor pago em BRL para créditos USD
     * Usa precisão de 4 casas decimais para cálculo exato
     * 
     * @param float $amount_brl Valor pago em reais
     * @return float Quantidade de créditos (USD)
     */
    public static function convert_payment_to_credits( $amount_brl ) {
        $exchange_rate = self::get_exchange_rate(); // 4 casas decimais
        $credits = $amount_brl / $exchange_rate;
        return round( $credits, 4 ); // Precisão interna de 4 casas
    }

    /**
     * Converte créditos USD para valor em BRL
     * 
     * @param float $credits Quantidade de créditos
     * @return float Valor em BRL
     */
    public function convert_credits_to_brl_instance( $credits ) {
        return self::convert_credits_to_brl( $credits );
    }

    /**
     * Converte valor pago em BRL para créditos USD
     * 
     * @param float $amount_brl Valor pago em reais
     * @return int Quantidade de créditos (USD)
     */
    public function convert_payment_to_credits_instance( $amount_brl ) {
        return self::convert_payment_to_credits( $amount_brl );
    }

    /**
     * Converte créditos USD para valor em BRL (método estático)
     * 
     * @param float $credits Quantidade de créditos
     * @return float Valor em BRL
     */
    public static function convert_credits_to_brl( $credits ) {
        $exchange_rate = self::get_exchange_rate();
        return $credits * $exchange_rate;
    }

    /**
     * Obtém créditos que um produto gerará
     * 
     * @param int $product_id ID do produto
     * @return float Créditos em USD
     */
    public static function get_product_credits( $product_id ) {
        return floatval( get_post_meta( $product_id, '_dsbc_credits_amount', true ) );
    }

    /**
     * Calcula preço em BRL baseado nos créditos
     * 
     * @param int $product_id ID do produto
     * @return float Preço em BRL
     */
    public static function get_product_price_brl( $product_id ) {
        $credits = self::get_product_credits( $product_id );
        return self::convert_credits_to_brl( $credits );
    }

    /**
     * Obtém taxa de câmbio atual USD/BRL
     * 
     * @return float Taxa de câmbio
     */
    public static function get_exchange_rate() {
        $settings = get_option( 'ds_backgamom_credits_settings', [] );
        $rate = floatval( $settings['exchange_rate'] ?? self::$default_exchange_rate );
        return $rate > 0 ? $rate : self::$default_exchange_rate;
    }

    /**
     * Atualiza taxa de câmbio
     * 
     * @param float $rate Nova taxa
     * @return bool Sucesso
     */
    public static function update_exchange_rate( $rate ) {
        if ( $rate <= 0 ) {
            return false;
        }
        
        $settings = get_option( 'ds_backgamom_credits_settings', [] );
        $settings['exchange_rate'] = floatval( $rate );
        return update_option( 'ds_backgamom_credits_settings', $settings );
    }

    /**
     * Formata exibição de créditos com conversão
     * Exibe apenas 2 casas decimais para o usuário
     * 
     * @param float $credits Quantidade de créditos
     * @param bool $show_brl Mostrar equivalente em BRL
     * @return string HTML formatado
     */
    public static function format_credits_display( $credits, $show_brl = true ) {
        if ( $credits <= 0 ) {
            return '';
        }
        
        // Exibe apenas 2 casas decimais para o usuário
        $display = number_format( $credits, 2, '.', '' ) . ' créditos (US$ ' . number_format( $credits, 2, '.', ',' ) . ')';
        
        if ( $show_brl ) {
            $brl_value = self::convert_credits_to_brl( $credits );
            $display .= ' = R$ ' . number_format( $brl_value, 2, ',', '.' );
        }
        
        return $display;
    }

    /**
     * COMPATIBILIDADE: Métodos antigos (deprecated)
     */
    
    /**
     * @deprecated Use convert_payment_to_credits()
     */
    public static function calculate_credits( $amount, $currency ) {
        if ( $currency === 'BRL' ) {
            return self::convert_payment_to_credits( $amount );
        }
        return $amount; // USD direto
    }

    /**
     * @deprecated Não mais necessário
     */
    public static function get_exchange_rates() {
        return [
            'BRL' => 1.0,
            'USD' => self::get_exchange_rate(),
        ];
    }
}