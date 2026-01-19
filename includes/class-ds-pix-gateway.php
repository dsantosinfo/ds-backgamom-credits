<?php
/**
 * Gateway PIX para DS Backgamom Credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DS_Pix_Gateway extends WC_Payment_Gateway {

    private $pix_key;
    private $merchant_name;
    private $merchant_city;

    public function __construct() {
        $this->id = 'ds_pix';
        $this->icon = '';
        $this->has_fields = false;
        $this->method_title = 'PIX (DS Credits)';
        $this->method_description = 'Pagamento via PIX com QR Code';

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );
        $this->pix_key = $this->get_option( 'pix_key' );
        $this->merchant_name = $this->get_option( 'merchant_name' );
        $this->merchant_city = $this->get_option( 'merchant_city' );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
        add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'thankyou_page' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );
    }

    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title' => 'Habilitar/Desabilitar',
                'type' => 'checkbox',
                'label' => 'Habilitar PIX',
                'default' => 'yes'
            ],
            'title' => [
                'title' => 'Título',
                'type' => 'text',
                'default' => 'PIX',
                'desc_tip' => true
            ],
            'description' => [
                'title' => 'Descrição',
                'type' => 'textarea',
                'default' => 'Pague instantaneamente com PIX'
            ],
            'pix_key' => [
                'title' => 'Chave PIX',
                'type' => 'text',
                'description' => 'CPF, CNPJ, E-mail, Telefone ou Chave Aleatória',
                'desc_tip' => true
            ],
            'merchant_name' => [
                'title' => 'Nome do Recebedor',
                'type' => 'text',
                'description' => 'Máximo 25 caracteres',
                'desc_tip' => true
            ],
            'merchant_city' => [
                'title' => 'Cidade',
                'type' => 'text',
                'default' => 'SAO PAULO',
                'desc_tip' => true
            ]
        ];
    }

    public function payment_scripts() {
        if ( ! is_order_received_page() ) {
            return;
        }
        wp_enqueue_script( 'qrcode-lib', 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js', [], '1.0.0', true );
        wp_enqueue_script( 'ds-pix-frontend', DSBC_PLUGIN_URL . 'assets/js/pix-frontend.js', ['jquery', 'qrcode-lib'], DSBC_VERSION, true );
    }

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( empty( $this->pix_key ) || empty( $this->merchant_name ) ) {
            wc_add_notice( 'Erro na configuração do PIX', 'error' );
            return ['result' => 'failure'];
        }

        // Usar helper para conversão BRL
        $conversion = DS_BRL_Gateway_Helper::process_credits_to_brl( $order );
        
        if ( ! $conversion ) {
            wc_add_notice( 'Nenhum crédito encontrado nos produtos.', 'error' );
            return ['result' => 'failure'];
        }

        $payload = $this->generate_pix_payload( $order );
        $order->update_meta_data( '_ds_pix_payload', $payload );
        $order->update_status( 'on-hold', DS_BRL_Gateway_Helper::get_status_note( $conversion['amount_brl'], $conversion['credits'], 'PIX' ) );
        $order->save();

        wc_reduce_stock_levels( $order_id );
        WC()->cart->empty_cart();

        return [
            'result' => 'success',
            'redirect' => $this->get_return_url( $order )
        ];
    }

    private function generate_pix_payload( $order ) {
        $amount = number_format( $order->get_total(), 2, '.', '' );
        $txid = 'PEDIDO' . $order->get_id();
        $txid = preg_replace( '/[^a-zA-Z0-9]/', '', $txid );
        $txid = substr( $txid, 0, 25 );
        
        // Sanitizar nome e cidade
        $merchant_name = $this->sanitize_pix_string( $this->merchant_name, 25 );
        $merchant_city = $this->sanitize_pix_string( $this->merchant_city, 15 );
        
        // Construir payload
        $payload = $this->build_emv( '00', '01' );
        $payload .= $this->build_emv( '26', $this->build_emv( '00', 'br.gov.bcb.pix' ) . $this->build_emv( '01', $this->pix_key ) );
        $payload .= $this->build_emv( '52', '0000' );
        $payload .= $this->build_emv( '53', '986' );
        $payload .= $this->build_emv( '54', $amount );
        $payload .= $this->build_emv( '58', 'BR' );
        $payload .= $this->build_emv( '59', $merchant_name );
        $payload .= $this->build_emv( '60', $merchant_city );
        $payload .= $this->build_emv( '62', $this->build_emv( '05', $txid ) );
        $payload .= '6304';
        $payload .= $this->crc16( $payload );
        
        return $payload;
    }
    
    private function sanitize_pix_string( $string, $limit ) {
        $string = str_replace(
            ['á','à','â','ã','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','ô','õ','ö','ú','ù','û','ü','ç','Á','À','Â','Ã','Ä','É','È','Ê','Ë','Í','Ì','Î','Ï','Ó','Ò','Ô','Õ','Ö','Ú','Ù','Û','Ü','Ç'],
            ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','A','A','A','A','A','E','E','E','E','I','I','I','I','O','O','O','O','O','U','U','U','U','C'],
            $string
        );
        $string = preg_replace( '/[^a-zA-Z0-9 ]/', '', $string );
        return substr( strtoupper( $string ), 0, $limit );
    }

    private function build_emv( $id, $value ) {
        return $id . str_pad( strlen( $value ), 2, '0', STR_PAD_LEFT ) . $value;
    }

    private function crc16( $payload ) {
        $polynomial = 0x1021;
        $resultado = 0xFFFF;
        
        if ( strlen( $payload ) > 0 ) {
            for ( $offset = 0; $offset < strlen( $payload ); $offset++ ) {
                $resultado ^= ( ord( $payload[ $offset ] ) << 8 );
                for ( $bitwise = 0; $bitwise < 8; $bitwise++ ) {
                    if ( ( $resultado <<= 1 ) & 0x10000 ) {
                        $resultado ^= $polynomial;
                    }
                    $resultado &= 0xFFFF;
                }
            }
        }
        
        return strtoupper( str_pad( dechex( $resultado ), 4, '0', STR_PAD_LEFT ) );
    }

    public function thankyou_page( $order_id ) {
        $order = wc_get_order( $order_id );
        $payload = $order->get_meta( '_ds_pix_payload' );

        if ( ! $payload ) {
            return;
        }
        ?>
        <div class="ds-pix-container" style="text-align: center; padding: 20px; background: #f9f9f9; margin: 20px 0;">
            <h3>Pagamento via PIX</h3>
            <p>Escaneie o QR Code ou use o Pix Copia e Cola:</p>
            <div id="ds-pix-qrcode" title="<?php echo esc_attr( $payload ); ?>" style="display: inline-block; margin: 20px 0;"></div>
            <div style="margin: 20px 0;">
                <input type="text" id="pix-copia-cola" value="<?php echo esc_attr( $payload ); ?>" readonly style="width: 100%; padding: 10px; margin-bottom: 10px;">
                <button type="button" id="btn-copy-pix" class="button alt">Copiar Código PIX</button>
                <span id="copy-feedback" style="display:none; color: green; margin-left: 10px;">✓ Copiado!</span>
            </div>
        </div>
        <?php
    }
}
