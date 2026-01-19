<?php
/**
 * Gerenciador de Multi-Moedas
 *
 * @package DS_Backgamom_Credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DS_Currency_Manager {

    public function __construct() {
        add_action( 'woocommerce_product_options_general_product_data', [ $this, 'add_currency_fields' ] );
        add_action( 'woocommerce_process_product_meta', [ $this, 'save_currency_fields' ] );
        add_filter( 'woocommerce_get_price_html', [ $this, 'custom_price_html' ], 10, 2 );
        add_filter( 'woocommerce_cart_item_price', [ $this, 'custom_cart_price' ], 10, 3 );
        add_filter( 'woocommerce_cart_item_subtotal', [ $this, 'custom_cart_price' ], 10, 3 );
        add_filter( 'woocommerce_available_payment_gateways', [ $this, 'filter_gateways_by_currency' ] );
        add_action( 'woocommerce_admin_order_data_after_order_details', [ $this, 'display_order_currency' ] );
        add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'add_currency_selector' ] );
        add_filter( 'woocommerce_add_cart_item_data', [ $this, 'add_currency_to_cart_item' ], 10, 3 );
        add_filter( 'woocommerce_get_item_data', [ $this, 'display_currency_in_cart' ], 10, 2 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_styles' ] );
        add_action( 'woocommerce_checkout_process', [ $this, 'enforce_currency_by_country' ] );
        add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'save_order_currency' ] );
        
        // Elementor compatibility
        add_action( 'elementor/frontend/after_enqueue_styles', [ $this, 'enqueue_frontend_styles' ] );
        add_filter( 'woocommerce_format_price_range', [ $this, 'format_elementor_price_range' ], 10, 3 );
    }
    
    public function enqueue_frontend_styles() {
        if ( is_product() || is_shop() || is_product_category() || is_woocommerce() ) {
            wp_enqueue_style( 'dsbc-frontend', DSBC_PLUGIN_URL . 'assets/css/frontend.css', [], DSBC_VERSION );
            wp_enqueue_script( 'dsbc-currency-selector', DSBC_PLUGIN_URL . 'assets/js/currency-selector.js', ['jquery'], DSBC_VERSION, true );
        }
    }

    public function add_currency_fields() {
        global $post;
        
        $currencies_config = get_option( 'dsbc_currencies_config', ['enabled' => ['BRL', 'USD']] );
        $enabled_currencies = $currencies_config['enabled'] ?? ['BRL', 'USD'];
        
        echo '<div class="options_group show_if_simple show_if_variable">';
        echo '<h4 style="padding: 10px; margin: 0; border-bottom: 1px solid #eee;">' . __( 'Preços por Moeda', 'ds-backgamom-credits' ) . '</h4>';
        
        echo '<p class="form-field" style="padding: 0 12px; margin-bottom: 15px;"><small style="color: #666;">';
        echo __( 'Defina o preço do produto em cada moeda. Deixe em branco para não oferecer naquela moeda.', 'ds-backgamom-credits' );
        echo '</small></p>';
        
        $all_currencies = [
            'BRL' => ['name' => 'Real Brasileiro', 'symbol' => 'R$'],
            'USD' => ['name' => 'Dólar Americano', 'symbol' => '$'],
            'EUR' => ['name' => 'Euro', 'symbol' => '€'],
            'GBP' => ['name' => 'Libra Esterlina', 'symbol' => '£'],
        ];
        
        foreach ( $enabled_currencies as $currency_code ) {
            if ( ! isset( $all_currencies[$currency_code] ) ) continue;
            
            $currency = $all_currencies[$currency_code];
            $price_value = get_post_meta( $post->ID, '_dsbc_price_' . strtolower($currency_code), true );
            
            woocommerce_wp_text_input([
                'id'          => '_dsbc_price_' . strtolower($currency_code),
                'label'       => sprintf( '%s (%s)', $currency['name'], $currency['symbol'] ),
                'placeholder' => '0.00',
                'type'        => 'number',
                'custom_attributes' => [
                    'step' => '0.01',
                    'min'  => '0',
                ],
                'value'       => $price_value,
            ]);
        }
        
        echo '<p class="form-field" style="padding: 0 12px; background: #fff3cd; border-left: 4px solid #ffc107; margin: 10px 12px;">';
        echo '<strong>' . __( 'Visibilidade:', 'ds-backgamom-credits' ) . '</strong><br>';
        echo '• <strong>Brasil:</strong> Vê BRL e USD<br>';
        echo '• <strong>Outros países:</strong> Vê apenas USD<br>';
        echo '<small>Gateway Asaas: apenas BRL | Gateway WISE: todas as moedas</small>';
        echo '</p>';
        
        echo '</div>';
    }

    public function save_currency_fields( $post_id ) {
        $currencies = ['brl', 'usd', 'eur', 'gbp'];
        
        foreach ( $currencies as $currency ) {
            $field_name = '_dsbc_price_' . $currency;
            if ( isset( $_POST[$field_name] ) ) {
                $price = sanitize_text_field( $_POST[$field_name] );
                if ( is_numeric( $price ) && $price >= 0 ) {
                    update_post_meta( $post_id, $field_name, $price );
                } else {
                    delete_post_meta( $post_id, $field_name );
                }
            }
        }
    }

    public function custom_price_html( $price, $product ) {
        $product_id = $product->get_id();
        
        // Buscar preços disponíveis
        $price_brl = get_post_meta( $product_id, '_dsbc_price_brl', true );
        $price_usd = get_post_meta( $product_id, '_dsbc_price_usd', true );
        
        // Se não tem preços customizados, retorna preço padrão
        if ( empty( $price_brl ) && empty( $price_usd ) ) {
            return $price;
        }
        
        $prices_html = [];
        
        // Sempre mostrar BRL e USD quando disponíveis (para loja/Elementor)
        if ( ! empty( $price_brl ) ) {
            $credits_brl = DS_Credit_Converter::get_product_credits( $product_id, 'BRL' );
            $prices_html[] = '<span class="price-brl">' . self::format_price( $price_brl, 'BRL' ) . 
                ' <small>(' . $credits_brl . ' créditos)</small></span>';
        }
        
        if ( ! empty( $price_usd ) ) {
            $credits_usd = DS_Credit_Converter::get_product_credits( $product_id, 'USD' );
            $prices_html[] = '<span class="price-usd">' . self::format_price( $price_usd, 'USD' ) . 
                ' <small>(' . $credits_usd . ' créditos)</small></span>';
        }
        
        if ( empty( $prices_html ) ) {
            return $price;
        }
        
        return '<span class="dsbc-multi-price">' . implode( ' <span class="price-separator">|</span> ', $prices_html ) . '</span>';
    }
    
    public function custom_cart_price( $price, $cart_item, $cart_item_key ) {
        // No carrinho, usar a moeda selecionada pelo usuário
        if ( isset( $cart_item['dsbc_selected_currency'] ) ) {
            $currency = $cart_item['dsbc_selected_currency'];
            $product_id = $cart_item['product_id'];
            $price_value = get_post_meta( $product_id, '_dsbc_price_' . strtolower($currency), true );
            
            if ( $price_value ) {
                $amount = $price_value * $cart_item['quantity'];
                return self::format_price( $amount, $currency );
            }
        }
        
        return $price;
    }

    public function filter_gateways_by_currency( $gateways ) {
        if ( is_admin() ) {
            return $gateways;
        }

        $user_country = $this->get_user_country();
        $cart_currency = self::get_cart_currency_static();
        
        // Configurar quais gateways aceitam quais moedas
        $gateway_currencies = apply_filters( 'dsbc_gateway_supported_currencies', [
            'ds_asaas' => ['BRL'],
            'ds_wise'  => ['BRL', 'USD', 'EUR', 'GBP'],
            'ds_pix'   => ['BRL'],
        ]);
        
        // Lógica específica por país
        if ( $user_country === 'BR' ) {
            // Brasileiros: forçar BRL e remover gateways que não suportam BRL
            foreach ( $gateways as $gateway_id => $gateway ) {
                if ( isset( $gateway_currencies[$gateway_id] ) ) {
                    $supported = $gateway_currencies[$gateway_id];
                    if ( ! in_array( 'BRL', $supported ) ) {
                        unset( $gateways[$gateway_id] );
                    }
                }
            }
        } else {
            // Outros países: priorizar USD, remover gateways BRL-only
            foreach ( $gateways as $gateway_id => $gateway ) {
                if ( isset( $gateway_currencies[$gateway_id] ) ) {
                    $supported = $gateway_currencies[$gateway_id];
                    // Remover gateways que só aceitam BRL
                    if ( $supported === ['BRL'] ) {
                        unset( $gateways[$gateway_id] );
                    }
                    // Para outros gateways, verificar se suportam a moeda do carrinho
                    elseif ( ! in_array( $cart_currency, $supported ) ) {
                        unset( $gateways[$gateway_id] );
                    }
                }
            }
        }
        
        return $gateways;
    }

    public function get_cart_currency() {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return 'BRL';
        }

        // Verificar se há moeda selecionada no carrinho
        foreach ( WC()->cart->get_cart() as $item ) {
            if ( isset( $item['dsbc_selected_currency'] ) ) {
                return $item['dsbc_selected_currency'];
            }
        }
        
        // Fallback: detectar por país
        $country = $this->get_user_country();
        return $country === 'BR' ? 'BRL' : 'USD';
    }
    
    public static function get_cart_currency_static() {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return 'BRL';
        }

        // Verificar se há moeda selecionada no carrinho
        foreach ( WC()->cart->get_cart() as $item ) {
            if ( isset( $item['dsbc_selected_currency'] ) ) {
                return $item['dsbc_selected_currency'];
            }
        }
        
        // Fallback: BRL
        return 'BRL';
    }

    public static function get_product_currency( $product_id ) {
        $currency = get_post_meta( $product_id, '_dsbc_currency', true );
        return $currency ?: 'BRL';
    }

    public static function format_price( $amount, $currency ) {
        $symbols = [
            'BRL' => 'R$ ',
            'USD' => 'US$ ',
            'EUR' => '€ ',
            'GBP' => '£ ',
        ];
        
        $symbol = $symbols[ $currency ] ?? 'R$ ';
        
        // Formatação brasileira para BRL, internacional para outras
        if ( $currency === 'BRL' ) {
            return $symbol . number_format( $amount, 2, ',', '.' );
        }
        
        return $symbol . number_format( $amount, 2, '.', ',' );
    }
    
    public function display_order_currency( $order ) {
        $currency = $order->get_meta( '_order_currency' );
        if ( $currency && $currency !== 'BRL' ) {
            echo '<p class="form-field form-field-wide"><strong>' . __( 'Moeda do Pedido:', 'ds-backgamom-credits' ) . '</strong> ' . esc_html( $currency ) . '</p>';
        }
    }
    
    public static function get_supported_currencies() {
        return [
            'BRL' => __( 'Real Brasileiro (R$)', 'ds-backgamom-credits' ),
            'USD' => __( 'Dólar Americano ($)', 'ds-backgamom-credits' ),
            'EUR' => __( 'Euro (€)', 'ds-backgamom-credits' ),
            'GBP' => __( 'Libra Esterlina (£)', 'ds-backgamom-credits' ),
        ];
    }
    
    private function get_user_country() {
        // 1. Tentar billing_country do usuário logado
        if ( is_user_logged_in() ) {
            $country = get_user_meta( get_current_user_id(), 'billing_country', true );
            if ( $country ) {
                return $country;
            }
        }
        
        // 2. Tentar sessão do WooCommerce
        if ( function_exists( 'WC' ) && WC()->customer ) {
            $country = WC()->customer->get_billing_country();
            if ( $country ) {
                return $country;
            }
        }
        
        // 3. Fallback: Brasil
        return 'BR';
    }
    
    public static function get_product_prices( $product_id ) {
        return [
            'BRL' => get_post_meta( $product_id, '_dsbc_price_brl', true ),
            'USD' => get_post_meta( $product_id, '_dsbc_price_usd', true ),
            'EUR' => get_post_meta( $product_id, '_dsbc_price_eur', true ),
            'GBP' => get_post_meta( $product_id, '_dsbc_price_gbp', true ),
        ];
    }
    
    public static function get_available_currencies_for_product( $product_id ) {
        $prices = self::get_product_prices( $product_id );
        $available = [];
        
        foreach ( $prices as $currency => $price ) {
            if ( ! empty( $price ) && $price > 0 ) {
                $available[] = $currency;
            }
        }
        
        return $available;
    }
    
    public function add_currency_selector() {
        global $product;
        
        if ( ! $product ) return;
        
        $available_currencies = self::get_available_currencies_for_product( $product->get_id() );
        
        // Se tiver apenas uma moeda, não mostrar seletor
        if ( count( $available_currencies ) <= 1 ) {
            return;
        }
        
        $user_country = $this->get_user_country();
        $prices = self::get_product_prices( $product->get_id() );
        
        // Filtrar moedas visíveis baseado no país
        $visible_currencies = [];
        if ( $user_country === 'BR' ) {
            // Brasil: BRL como padrão, USD como alternativa
            if ( in_array( 'BRL', $available_currencies ) ) $visible_currencies[] = 'BRL';
            if ( in_array( 'USD', $available_currencies ) ) $visible_currencies[] = 'USD';
        } else {
            // Outros: USD como padrão, outras moedas como alternativas
            if ( in_array( 'USD', $available_currencies ) ) {
                $visible_currencies[] = 'USD';
            }
            // Adicionar outras moedas disponíveis (exceto BRL para não-brasileiros)
            foreach ( $available_currencies as $currency ) {
                if ( $currency !== 'BRL' && $currency !== 'USD' ) {
                    $visible_currencies[] = $currency;
                }
            }
            // Se não tem USD, mostrar BRL como fallback
            if ( empty( $visible_currencies ) && in_array( 'BRL', $available_currencies ) ) {
                $visible_currencies[] = 'BRL';
            }
        }
        
        if ( count( $visible_currencies ) <= 1 ) {
            return;
        }
        
        $currency_names = [
            'BRL' => 'Real (R$)',
            'USD' => 'Dólar (US$)',
            'EUR' => 'Euro (€)',
            'GBP' => 'Libra (£)',
        ];
        
        // Determinar moeda padrão
        $default_currency = $user_country === 'BR' ? 'BRL' : 'USD';
        if ( ! in_array( $default_currency, $visible_currencies ) ) {
            $default_currency = $visible_currencies[0];
        }
        
        ?>
        <div class="dsbc-currency-selector" style="margin: 15px 0;">
            <label for="dsbc_currency_choice" style="font-weight: bold; display: block; margin-bottom: 8px;">
                <?php _e( 'Selecione a moeda:', 'ds-backgamom-credits' ); ?>
            </label>
            <select name="dsbc_currency_choice" id="dsbc_currency_choice" style="width: 100%; max-width: 300px; padding: 8px;">
                <?php foreach ( $visible_currencies as $currency ) : ?>
                    <option value="<?php echo esc_attr( $currency ); ?>" <?php selected( $currency, $default_currency ); ?>>
                        <?php echo esc_html( $currency_names[$currency] ?? $currency ); ?> - 
                        <?php echo esc_html( self::format_price( $prices[$currency], $currency ) ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ( $user_country === 'BR' ) : ?>
                <small style="display: block; margin-top: 5px; color: #666;">
                    <?php _e( 'Brasileiros pagam em Real via Asaas (PIX/Cartão)', 'ds-backgamom-credits' ); ?>
                </small>
            <?php else : ?>
                <small style="display: block; margin-top: 5px; color: #666;">
                    <?php _e( 'Pagamento internacional via WISE', 'ds-backgamom-credits' ); ?>
                </small>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function add_currency_to_cart_item( $cart_item_data, $product_id, $variation_id ) {
        if ( isset( $_POST['dsbc_currency_choice'] ) ) {
            $selected_currency = sanitize_text_field( $_POST['dsbc_currency_choice'] );
            $cart_item_data['dsbc_selected_currency'] = $selected_currency;
        } else {
            // Auto-detectar baseado no país e disponibilidade
            $country = $this->get_user_country();
            $available = self::get_available_currencies_for_product( $product_id );
            
            // Brasileiros: priorizar BRL
            if ( $country === 'BR' ) {
                if ( in_array( 'BRL', $available ) ) {
                    $cart_item_data['dsbc_selected_currency'] = 'BRL';
                } elseif ( in_array( 'USD', $available ) ) {
                    $cart_item_data['dsbc_selected_currency'] = 'USD';
                } elseif ( ! empty( $available ) ) {
                    $cart_item_data['dsbc_selected_currency'] = $available[0];
                }
            } else {
                // Outros países: priorizar USD
                if ( in_array( 'USD', $available ) ) {
                    $cart_item_data['dsbc_selected_currency'] = 'USD';
                } elseif ( ! empty( $available ) ) {
                    $cart_item_data['dsbc_selected_currency'] = $available[0];
                }
            }
        }
        
        return $cart_item_data;
    }
    
    public function display_currency_in_cart( $item_data, $cart_item ) {
        if ( isset( $cart_item['dsbc_selected_currency'] ) ) {
            $currency_names = [
                'BRL' => 'Real Brasileiro',
                'USD' => 'Dólar Americano',
                'EUR' => 'Euro',
                'GBP' => 'Libra Esterlina',
            ];
            
            $item_data[] = [
                'name'  => __( 'Moeda', 'ds-backgamom-credits' ),
                'value' => $currency_names[ $cart_item['dsbc_selected_currency'] ] ?? $cart_item['dsbc_selected_currency'],
            ];
        }
        
        return $item_data;
    }
    
    /**
     * Enforce currency selection based on user country during checkout
     */
    public function enforce_currency_by_country() {
        if ( ! WC()->cart || WC()->cart->is_empty() ) {
            return;
        }
        
        $user_country = $this->get_user_country();
        $cart_currency = $this->get_cart_currency();
        
        // Brasileiros devem pagar em BRL
        if ( $user_country === 'BR' && $cart_currency !== 'BRL' ) {
            // Verificar se todos os produtos no carrinho têm preço em BRL
            $can_convert_to_brl = true;
            foreach ( WC()->cart->get_cart() as $cart_item ) {
                $product_id = $cart_item['product_id'];
                $price_brl = get_post_meta( $product_id, '_dsbc_price_brl', true );
                if ( empty( $price_brl ) ) {
                    $can_convert_to_brl = false;
                    break;
                }
            }
            
            if ( ! $can_convert_to_brl ) {
                wc_add_notice( 
                    __( 'Alguns produtos no seu carrinho não estão disponíveis em Real Brasileiro. Por favor, remova-os ou entre em contato conosco.', 'ds-backgamom-credits' ), 
                    'error' 
                );
            }
        }
        
        // Não-brasileiros devem pagar em USD (se disponível)
        if ( $user_country !== 'BR' && $cart_currency === 'BRL' ) {
            // Verificar se todos os produtos têm preço em USD
            $can_convert_to_usd = true;
            foreach ( WC()->cart->get_cart() as $cart_item ) {
                $product_id = $cart_item['product_id'];
                $price_usd = get_post_meta( $product_id, '_dsbc_price_usd', true );
                if ( empty( $price_usd ) ) {
                    $can_convert_to_usd = false;
                    break;
                }
            }
            
            if ( ! $can_convert_to_usd ) {
                wc_add_notice( 
                    __( 'Alguns produtos no seu carrinho não estão disponíveis em Dólar. Por favor, entre em contato conosco.', 'ds-backgamom-credits' ), 
                    'error' 
                );
            }
        }
    }
    
    /**
     * Save order currency metadata
     */
    public function save_order_currency( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }
        
        $currency = $this->get_cart_currency();
        $order->update_meta_data( '_dsbc_order_currency', $currency );
        $order->save();
    }
    
    /**
     * Format price range for Elementor compatibility
     */
    public function format_elementor_price_range( $price, $from, $to ) {
        // For variable products in Elementor, ensure both currencies show
        return $price;
    }
}
