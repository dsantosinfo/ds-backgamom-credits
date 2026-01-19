<?php
/**
 * Elementor Cart Widget - Versão Simplificada
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DS_Cart_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'ds_cart_widget';
    }

    public function get_title() {
        return __( 'Carrinho de Créditos USD', 'ds-backgamom-credits' );
    }

    public function get_icon() {
        return 'eicon-cart';
    }

    public function get_categories() {
        return [ 'ds-backgamom-credits' ];
    }

    protected function register_controls() {
        // Conteúdo
        $this->start_controls_section(
            'content_section',
            [
                'label' => __( 'Configurações', 'ds-backgamom-credits' ),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'show_brl_equivalent',
            [
                'label' => __( 'Mostrar Equivalente em BRL', 'ds-backgamom-credits' ),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();

        // Estilo da Tabela
        $this->start_controls_section(
            'table_style',
            [
                'label' => __( 'Tabela', 'ds-backgamom-credits' ),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'table_border_color',
            [
                'label' => __( 'Cor da Borda', 'ds-backgamom-credits' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ddd',
                'selectors' => [
                    '{{WRAPPER}} .ds-cart-table th, {{WRAPPER}} .ds-cart-table td' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'table_padding',
            [
                'label' => __( 'Espaçamento Interno', 'ds-backgamom-credits' ),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'default' => ['top' => 15, 'right' => 15, 'bottom' => 15, 'left' => 15],
                'selectors' => [
                    '{{WRAPPER}} .ds-cart-table th, {{WRAPPER}} .ds-cart-table td' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Estilo dos Preços
        $this->start_controls_section(
            'price_style',
            [
                'label' => __( 'Preços', 'ds-backgamom-credits' ),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'price_color',
            [
                'label' => __( 'Cor dos Preços', 'ds-backgamom-credits' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#2c5aa0',
                'selectors' => [
                    '{{WRAPPER}} .price-credits, {{WRAPPER}} .total-credits' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'price_typography',
                'selector' => '{{WRAPPER}} .price-credits, {{WRAPPER}} .total-credits',
            ]
        );

        $this->end_controls_section();

        // Estilo dos Botões
        $this->start_controls_section(
            'button_style',
            [
                'label' => __( 'Botões', 'ds-backgamom-credits' ),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'button_bg_color',
            [
                'label' => __( 'Cor de Fundo', 'ds-backgamom-credits' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#2c5aa0',
                'selectors' => [
                    '{{WRAPPER}} .ds-cart-actions button, {{WRAPPER}} .checkout-button' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_text_color',
            [
                'label' => __( 'Cor do Texto', 'ds-backgamom-credits' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .ds-cart-actions button, {{WRAPPER}} .checkout-button' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_hover_bg',
            [
                'label' => __( 'Cor de Fundo (Hover)', 'ds-backgamom-credits' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#1e4080',
                'selectors' => [
                    '{{WRAPPER}} .ds-cart-actions button:hover, {{WRAPPER}} .checkout-button:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_padding',
            [
                'label' => __( 'Espaçamento Interno', 'ds-backgamom-credits' ),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'default' => ['top' => 10, 'right' => 20, 'bottom' => 10, 'left' => 20],
                'selectors' => [
                    '{{WRAPPER}} .ds-cart-actions button, {{WRAPPER}} .checkout-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'button_border_radius',
            [
                'label' => __( 'Borda Arredondada', 'ds-backgamom-credits' ),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'default' => ['size' => 4],
                'range' => ['px' => ['min' => 0, 'max' => 50]],
                'selectors' => [
                    '{{WRAPPER}} .ds-cart-actions button, {{WRAPPER}} .checkout-button' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .ds-cart-actions button, {{WRAPPER}} .checkout-button',
            ]
        );

        $this->end_controls_section();

        // Estilo do Total
        $this->start_controls_section(
            'totals_style',
            [
                'label' => __( 'Total do Carrinho', 'ds-backgamom-credits' ),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'totals_bg_color',
            [
                'label' => __( 'Cor de Fundo', 'ds-backgamom-credits' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#f8f9fa',
                'selectors' => [
                    '{{WRAPPER}} .ds-cart-totals' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'totals_padding',
            [
                'label' => __( 'Espaçamento Interno', 'ds-backgamom-credits' ),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'default' => ['top' => 20, 'right' => 20, 'bottom' => 20, 'left' => 20],
                'selectors' => [
                    '{{WRAPPER}} .ds-cart-totals' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'totals_border_radius',
            [
                'label' => __( 'Borda Arredondada', 'ds-backgamom-credits' ),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'default' => ['size' => 8],
                'range' => ['px' => ['min' => 0, 'max' => 50]],
                'selectors' => [
                    '{{WRAPPER}} .ds-cart-totals' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            echo '<p>Carrinho não disponível</p>';
            return;
        }

        $settings = $this->get_settings_for_display();
        $cart = WC()->cart;

        if ( $cart->is_empty() ) {
            $this->render_empty_cart();
            return;
        }

        echo '<div class="ds-cart-page">';
        echo '<h2>Carrinho de Compras</h2>';
        
        echo '<form method="post" action="' . esc_url( wc_get_cart_url() ) . '">';
        wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' );
        
        $this->render_cart_table( $settings );
        $this->render_cart_actions();
        $this->render_cart_totals( $settings );
        
        echo '</form>';
        echo '</div>';
        
        $this->add_base_styles();
    }

    private function render_empty_cart() {
        echo '<div class="ds-empty-cart">';
        echo '<h2>Seu carrinho está vazio</h2>';
        echo '<a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">Continuar Comprando</a>';
        echo '</div>';
    }

    private function render_cart_table( $settings ) {
        $cart = WC()->cart;
        
        echo '<table class="ds-cart-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Produto</th>';
        echo '<th>Preço</th>';
        echo '<th>Quantidade</th>';
        echo '<th>Total</th>';
        echo '<th></th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            $this->render_cart_row( $cart_item_key, $cart_item, $settings );
        }
        
        echo '</tbody>';
        echo '</table>';
    }

    private function render_cart_row( $cart_item_key, $cart_item, $settings ) {
        $product = $cart_item['data'];
        $product_id = $cart_item['product_id'];
        $quantity = $cart_item['quantity'];
        
        $credits = $this->get_product_credits( $product_id );
        $exchange_rate = DS_Credit_Converter::get_exchange_rate();
        $brl_price = $credits * $exchange_rate;
        
        echo '<tr>';
        
        // Produto
        echo '<td>';
        echo '<strong>' . esc_html( $product->get_name() ) . '</strong>';
        echo '</td>';
        
        // Preço unitário
        echo '<td>';
        echo '<div class="price-credits">' . number_format( $credits, 2 ) . '</div>';
        echo '<small>(US$ ' . number_format( $credits, 2 ) . ')</small>';
        if ( ($settings['show_brl_equivalent'] ?? 'yes') === 'yes' ) {
            echo '<br><small>R$ ' . number_format( $brl_price, 2, ',', '.' ) . '</small>';
        }
        echo '</td>';
        
        // Quantidade
        echo '<td>';
        echo '<input type="number" name="cart[' . $cart_item_key . '][qty]" value="' . $quantity . '" min="1" class="qty-input">';
        echo '</td>';
        
        // Total
        echo '<td>';
        $total_credits = $credits * $quantity;
        $total_brl = $total_credits * $exchange_rate;
        echo '<div class="total-credits">' . number_format( $total_credits, 2 ) . '</div>';
        echo '<small>(US$ ' . number_format( $total_credits, 2 ) . ')</small>';
        if ( ($settings['show_brl_equivalent'] ?? 'yes') === 'yes' ) {
            echo '<br><small>R$ ' . number_format( $total_brl, 2, ',', '.' ) . '</small>';
        }
        echo '</td>';
        
        // Remover
        echo '<td>';
        echo '<a href="' . esc_url( wc_get_cart_remove_url( $cart_item_key ) ) . '" class="remove-item">×</a>';
        echo '</td>';
        
        echo '</tr>';
    }

    private function render_cart_actions() {
        echo '<div class="ds-cart-actions">';
        echo '<a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">Continuar Comprando</a>';
        echo '<button type="submit" name="update_cart" value="Atualizar carrinho">Atualizar Carrinho</button>';
        echo '</div>';
    }

    private function render_cart_totals( $settings ) {
        $cart_totals = $this->calculate_cart_totals();
        
        echo '<div class="ds-cart-totals">';
        echo '<h3>Total do Carrinho</h3>';
        
        echo '<div class="total-row">';
        echo '<span>Total: ' . number_format( $cart_totals['credits'], 2 ) . '</span>';
        echo '<small>(US$ ' . number_format( $cart_totals['credits'], 2 ) . ')</small>';
        if ( ($settings['show_brl_equivalent'] ?? 'yes') === 'yes' ) {
            echo '<br><span>Valor para Pagamento: R$ ' . number_format( $cart_totals['brl_price'], 2, ',', '.' ) . '</span>';
        }
        echo '</div>';
        
        echo '<a href="' . esc_url( wc_get_checkout_url() ) . '" class="checkout-button">Finalizar Compra</a>';
        echo '</div>';
    }

    private function get_product_credits( $product_id ) {
        $credits = get_post_meta( $product_id, '_dsbc_credits_amount', true );
        
        if ( empty( $credits ) ) {
            $product = wc_get_product( $product_id );
            if ( $product && $product->get_price() ) {
                $credits = floatval( $product->get_price() ) / 5.67;
            }
        }
        
        return floatval( $credits );
    }

    private function calculate_cart_totals() {
        $cart = WC()->cart;
        $total_credits = 0;
        $exchange_rate = DS_Credit_Converter::get_exchange_rate();
        
        foreach ( $cart->get_cart() as $cart_item ) {
            $product_id = $cart_item['product_id'];
            $quantity = $cart_item['quantity'];
            $credits = $this->get_product_credits( $product_id );
            $total_credits += $credits * $quantity;
        }
        
        return [
            'credits' => $total_credits,
            'brl_price' => $total_credits * $exchange_rate
        ];
    }

    private function add_base_styles() {
        ?>
        <style>
        .ds-cart-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .ds-cart-table th,
        .ds-cart-table td {
            text-align: left;
            border-bottom: 1px solid;
        }
        .price-credits,
        .total-credits {
            font-weight: bold;
        }
        .qty-input {
            width: 60px;
            text-align: center;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .ds-cart-actions {
            margin: 20px 0;
        }
        .ds-cart-actions button,
        .checkout-button {
            border: none;
            text-decoration: none;
            margin-right: 10px;
            cursor: pointer;
            display: inline-block;
            transition: all 0.3s ease;
        }
        .ds-cart-actions a {
            color: #666;
            text-decoration: none;
            margin-right: 15px;
        }
        .ds-cart-actions a:hover {
            color: #333;
        }
        .total-row {
            font-size: 1.2em;
            margin-bottom: 15px;
        }
        .ds-empty-cart {
            text-align: center;
            padding: 40px 20px;
        }
        .ds-empty-cart h2 {
            margin-bottom: 20px;
            color: #666;
        }
        .ds-empty-cart a {
            background: #2c5aa0;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
        }
        .remove-item {
            color: #e74c3c;
            font-size: 18px;
            text-decoration: none;
            font-weight: bold;
        }
        .remove-item:hover {
            color: #c0392b;
        }
        </style>
        <?php
    }
}