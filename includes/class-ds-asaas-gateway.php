<?php
/**
 * Gateway de Pagamento Asaas
 *
 * @package DS_Backgamom_Credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class DS_Asaas_Gateway extends WC_Payment_Gateway {

    /**
     * Chave da API Asaas.
     *
     * @var string
     */
    private $api_key;

    /**
     * Modo sandbox.
     *
     * @var bool
     */
    private $sandbox;

    /**
     * Construtor.
     */
    public function __construct() {
        $this->id                 = 'ds_asaas';
        $this->icon               = apply_filters( 'woocommerce_asaas_icon', '' );
        $this->has_fields         = true;
        $this->method_title       = __( 'Asaas - PIX e Cartão de Crédito', 'ds-backgamom-credits' );
        $this->method_description = __( 'Habilita pagamentos via PIX e Cartão de Crédito através do Asaas.', 'ds-backgamom-credits' );

        // Carrega as configurações
        $this->init_form_fields();
        $this->init_settings();

        // Define as propriedades do gateway
        $this->title        = $this->get_option( 'title' );
        $this->description  = $this->get_option( 'description' );
        $this->api_key      = $this->get_option( 'asaas_api_key' );
        $this->sandbox      = $this->get_option( 'sandbox' ) === 'yes';

        // Hooks
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );
    }

    /**
     * Define os campos do formulário de administração.
     */
    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title'   => __( 'Habilitar/Desabilitar', 'ds-backgamom-credits' ),
                'type'    => 'checkbox',
                'label'   => __( 'Habilitar Gateway Asaas', 'ds-backgamom-credits' ),
                'default' => 'yes',
            ],
            'title' => [
                'title'       => __( 'Título', 'ds-backgamom-credits' ),
                'type'        => 'text',
                'description' => __( 'Título que o cliente verá durante o checkout.', 'ds-backgamom-credits' ),
                'default'     => __( 'PIX e Cartão de Crédito (Asaas)', 'ds-backgamom-credits' ),
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => __( 'Descrição', 'ds-backgamom-credits' ),
                'type'        => 'textarea',
                'description' => __( 'Descrição que o cliente verá durante o checkout.', 'ds-backgamom-credits' ),
                'default'     => __( 'Pague com PIX ou Cartão de Crédito.', 'ds-backgamom-credits' ),
            ],
            'asaas_api_key' => [
                'title'       => __( 'Chave da API Asaas', 'ds-backgamom-credits' ),
                'type'        => 'text',
                'description' => __( 'Sua chave da API Asaas. Funciona para ambos os ambientes, Produção e Sandbox.', 'ds-backgamom-credits' ),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'sandbox' => [
                'title'       => __( 'Modo Sandbox', 'ds-backgamom-credits' ),
                'type'        => 'checkbox',
                'label'       => __( 'Habilitar Modo Sandbox', 'ds-backgamom-credits' ),
                'description' => __( 'Se habilitado, usará o ambiente de testes do Asaas. Use para testar pagamentos.', 'ds-backgamom-credits' ),
                'default'     => 'no',
                'desc_tip'    => true,
            ],
        ];
    }

    /**
     * Verifica se o gateway deve ser exibido.
     *
     * @return bool
     */
    public function is_available() {
        if ( 'no' === $this->enabled ) {
            return false;
        }

        // Verifica se o cliente está logado
        if ( ! is_user_logged_in() ) {
            return false;
        }

        // Verificar se a moeda do carrinho é BRL
        if ( class_exists( 'DS_Currency_Manager' ) && WC()->cart ) {
            $currency_manager = new DS_Currency_Manager();
            $cart_currency = $currency_manager->get_cart_currency();
            if ( $cart_currency !== 'BRL' ) {
                return false;
            }
        }
        
        // Verificar se o usuário é brasileiro (opcional, mas recomendado)
        $user_country = WC()->customer ? WC()->customer->get_billing_country() : '';
        if ( empty( $user_country ) && is_user_logged_in() ) {
            $user_country = get_user_meta( get_current_user_id(), 'billing_country', true );
        }
        
        // Se não é brasileiro e tem outras opções, não mostrar Asaas
        if ( $user_country && $user_country !== 'BR' ) {
            return false;
        }

        return true;
    }

    /**
     * Processa o pagamento.
     *
     * @param int $order_id ID do pedido.
     * @return array
     */
    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        $user = $order->get_user();

        if ( ! $user ) {
            wc_add_notice( 'Usuário inválido.', 'error' );
            return ['result' => 'failure'];
        }

        // Usar helper para conversão BRL
        $conversion = DS_BRL_Gateway_Helper::process_credits_to_brl( $order );
        
        if ( ! $conversion ) {
            wc_add_notice( 'Nenhum crédito encontrado nos produtos.', 'error' );
            return ['result' => 'failure'];
        }

        // 1. INICIAR CLIENTE API
        $api_client = new DS_Asaas_API_Client( $this->api_key, $this->sandbox );

        // 2. OBTER OU CRIAR CLIENTE ASAS
        try {
            $asaas_customer_id = $this->get_or_create_asaas_customer( $user, $api_client );
        } catch ( Exception $e ) {
            wc_add_notice( 'Erro ao processar cliente: ' . $e->getMessage(), 'error' );
            return ['result' => 'failure'];
        }

        // 3. CRIAR COBRANÇA
        $payment_data = [
            'customer'      => $asaas_customer_id,
            'billingType'   => 'CREDIT_CARD',
            'value'         => $conversion['amount_brl'],
            'dueDate'       => ( new DateTime() )->format( 'Y-m-d' ),
            'description'   => DS_BRL_Gateway_Helper::get_payment_description( $order, $conversion['credits'], 'Asaas' ),
            'externalReference' => $order_id,
        ];
        
        // Verificar CPF
        $cpf = $this->get_customer_cpf( $user->ID );
        if ( ! empty( $cpf ) ) {
            update_user_meta( $user->ID, 'billing_cpf', $cpf );
            $payment_data['billingType'] = 'UNDEFINED';
        }

        $payment_response = $api_client->create_payment( $payment_data );

        if ( is_wp_error( $payment_response ) ) {
            wc_add_notice( 'Erro ao criar cobrança: ' . $payment_response->get_error_message(), 'error' );
            return ['result' => 'failure'];
        }

        // 4. PROCESSAR RESPOSTA
        $order->update_meta_data( '_asaas_payment_id', $payment_response['id'] );
        $order->update_meta_data( '_asaas_invoice_url', $payment_response['invoiceUrl'] );
        $order->update_status( 'on-hold', DS_BRL_Gateway_Helper::get_status_note( $conversion['amount_brl'], $conversion['credits'], 'Asaas' ) );

        wc_reduce_stock_levels( $order_id );
        WC()->cart->empty_cart();

        return [
            'result'   => 'success',
            'redirect' => $payment_response['invoiceUrl'],
        ];
    }

    /**
     * Obtém ou cria um cliente no Asaas.
     *
     * @param WP_User $user Usuário do WordPress.
     * @param DS_Asaas_API_Client $api_client Cliente da API.
     * @return string ID do cliente Asaas.
     * @throws Exception Se ocorrer um erro.
     */
    private function get_or_create_asaas_customer( $user, $api_client ) {
        $asaas_customer_id = get_user_meta( $user->ID, '_asaas_customer_id', true );
        
        $email = $user->get( 'user_email' );
        
        // Obter CPF de múltiplas fontes
        $cpf = '';
        if ( ! empty( $_POST['billing_cpf'] ) ) {
            $cpf = preg_replace( '/\D/', '', $_POST['billing_cpf'] );
        } elseif ( ! empty( $_POST['ds_cpf'] ) ) {
            $cpf = preg_replace( '/\D/', '', $_POST['ds_cpf'] );
        } elseif ( ! empty( get_user_meta( $user->ID, 'billing_cpf', true ) ) ) {
            $cpf = get_user_meta( $user->ID, 'billing_cpf', true );
        }
        

        
        // Se tem cliente salvo e CPF, verificar se cliente tem CPF
        if ( ! empty( $asaas_customer_id ) && ! empty( $cpf ) ) {
            $customer_info = $api_client->get_customer( $asaas_customer_id );
            if ( ! is_wp_error( $customer_info ) && ! empty( $customer_info['cpfCnpj'] ) ) {
                return $asaas_customer_id;
            }
            // Cliente existe mas sem CPF, limpar para recriar
            delete_user_meta( $user->ID, '_asaas_customer_id' );
            $asaas_customer_id = '';
        }
        
        // 1. Tentar buscar por CPF primeiro (mais específico)
        if ( ! empty( $cpf ) ) {
            $customer_response = $api_client->get_customer_by_cpf( $cpf );
            if ( ! is_wp_error( $customer_response ) && ! empty( $customer_response['data'] ) ) {
                $asaas_customer_id = $customer_response['data'][0]['id'];
                update_user_meta( $user->ID, '_asaas_customer_id', $asaas_customer_id );
                return $asaas_customer_id;
            }
        }
        
        // 2. Tentar buscar por email
        $customer_response = $api_client->get_customer_by_email( $email );
        if ( ! is_wp_error( $customer_response ) && ! empty( $customer_response['data'] ) ) {
            $asaas_customer_id = $customer_response['data'][0]['id'];
            update_user_meta( $user->ID, '_asaas_customer_id', $asaas_customer_id );
            return $asaas_customer_id;
        }
        
        // 3. Criar novo cliente
        $new_customer_data = [
            'name'  => trim( $user->get( 'first_name' ) . ' ' . $user->get( 'last_name' ) ) ?: $user->get( 'display_name' ),
            'email' => $email,
        ];
        
        // CPF é obrigatório para clientes brasileiros
        if ( ! empty( $cpf ) ) {
            $new_customer_data['cpfCnpj'] = $cpf;
        }
        

        
        $new_customer_response = $api_client->create_customer( $new_customer_data );
        
        if ( is_wp_error( $new_customer_response ) ) {
            throw new Exception( $new_customer_response->get_error_message() );
        }
        $asaas_customer_id = $new_customer_response['id'];

        update_user_meta( $user->ID, '_asaas_customer_id', $asaas_customer_id );
        return $asaas_customer_id;
    }

    /**
     * Campos do formulário de pagamento.
     */
    public function payment_fields() {
        if ( $this->description ) {
            echo wpautop( wptexturize( $this->description ) );
        }
        ?>
        <div id="ds-asaas-payment-form">
            <div id="ds-cpf-field" style="display:none; margin: 10px 0;">
                <label for="ds_cpf">CPF <span class="required">*</span></label>
                <input type="text" id="ds_cpf" name="ds_cpf" placeholder="000.000.000-00" maxlength="14" />
                <small>Necessário para pagamento via PIX</small>
            </div>
        </div>
        <?php
    }

    /**
     * Scripts do gateway.
     */
    public function payment_scripts() {
        if ( ! is_checkout() ) {
            return;
        }
        ?>
        <script>
        jQuery(document).ready(function($) {
            if (typeof $ === 'undefined') {
                $ = jQuery;
            }
            function toggleCPFField() {
                var country = $('#billing_country').val();
                var cpfField = $('#ds-cpf-field');
                var billingCpf = $('#billing_cpf').val();
                
                if (country === 'BR') {
                    // Se já tem CPF no checkout, ocultar campo do gateway
                    if (billingCpf && billingCpf.length >= 11) {
                        cpfField.hide();
                        $('#ds_cpf').val(billingCpf);
                    } else {
                        cpfField.show();
                    }
                } else {
                    cpfField.hide();
                    $('#ds_cpf').val('');
                }
            }
            
            // Verificar no carregamento
            toggleCPFField();
            
            // Verificar quando país mudar
            $(document.body).on('change', '#billing_country', toggleCPFField);
            
            // Verificar quando CPF do checkout mudar
            $(document.body).on('input change', '#billing_cpf', function() {
                var cpfValue = $(this).val();
                $('#ds_cpf').val(cpfValue);
                toggleCPFField();
            });
            
            // Máscara CPF
            $('#ds_cpf').on('input', function() {
                var value = this.value.replace(/\D/g, '');
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                this.value = value;
            });
        });
        </script>
        <?php
    }

    /**
     * Validar campos do formulário.
     */
    public function validate_fields() {
        $country = WC()->customer->get_billing_country();
        
        if ( $country === 'BR' ) {
            // Verificar CPF nos campos disponíveis
            $cpf = '';
            if ( ! empty( $_POST['billing_cpf'] ) ) {
                $cpf = sanitize_text_field( $_POST['billing_cpf'] );
            } elseif ( ! empty( $_POST['ds_cpf'] ) ) {
                $cpf = sanitize_text_field( $_POST['ds_cpf'] );
            }
            
            if ( empty( $cpf ) ) {
                wc_add_notice( 'CPF é obrigatório para clientes do Brasil.', 'error' );
                return false;
            }
            
            // Validar formato CPF
            $cpf_numbers = preg_replace( '/\D/', '', $cpf );
            if ( strlen( $cpf_numbers ) !== 11 ) {
                wc_add_notice( 'CPF deve ter 11 dígitos.', 'error' );
                return false;
            }
        }
        
        return true;
    }
    
    private function get_customer_cpf( $user_id ) {
        // 1. Tentar pegar do checkout padrão (billing_cpf)
        if ( ! empty( $_POST['billing_cpf'] ) ) {
            return preg_replace( '/\D/', '', sanitize_text_field( $_POST['billing_cpf'] ) );
        }
        // 2. Fallback para campo do gateway (ds_cpf)
        elseif ( ! empty( $_POST['ds_cpf'] ) ) {
            return preg_replace( '/\D/', '', sanitize_text_field( $_POST['ds_cpf'] ) );
        }
        // 3. Fallback para CPF salvo no usuário
        elseif ( ! empty( get_user_meta( $user_id, 'billing_cpf', true ) ) ) {
            return get_user_meta( $user_id, 'billing_cpf', true );
        }
        
        return '';
    }
}
