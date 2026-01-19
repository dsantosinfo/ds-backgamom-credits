<?php
/**
 * Elementor Cart Widget - Versão Corrigida
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
        
        $this->add_styles();
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
        $brl_price = $credits * 5.67;
        
        echo '<tr>';
        
        // Produto
        echo '<td>';
        echo '<strong>' . esc_html( $product->get_name() ) . '</strong>';
        echo '</td>';
        
        // Preço unitário
        echo '<td>';
        echo '<div class="price-credits">' . $credits . '</div>';
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
        $total_brl = $total_credits * 5.67;
        echo '<div class="total-credits">' . $total_credits . '</div>';
        echo '<small>(US$ ' . number_format( $total_credits, 2 ) . ')</small>';
        if ( ($settings['show_brl_equivalent'] ?? 'yes') === 'yes' ) {
            echo '<br><small>R$ ' . number_format( $total_brl, 2, ',', '.' ) . '</small>';
        }
        echo '</td>';
        
        // Remover
        echo '<td>';
        echo '<a href="' . esc_url( wc_get_cart_remove_url( $cart_item_key ) ) . '">×</a>';
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
        echo '<span>Total: ' . $cart_totals['credits'] . '</span>';
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
        
        foreach ( $cart->get_cart() as $cart_item ) {
            $product_id = $cart_item['product_id'];
            $quantity = $cart_item['quantity'];
            $credits = $this->get_product_credits( $product_id );
            $total_credits += $credits * $quantity;
        }
        
        return [
            'credits' => $total_credits,
            'brl_price' => $total_credits * 5.67
        ];
    }

    private function add_styles() {
        ?>
        <style>
        .ds-cart-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .ds-cart-table th,
        .ds-cart-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .price-credits,
        .total-credits {
            font-weight: bold;
            color: #2c5aa0;
        }
        .qty-input {
            width: 60px;
            text-align: center;
            padding: 5px;
        }
        .ds-cart-actions {
            margin: 20px 0;
        }
        .ds-cart-actions button,
        .checkout-button {
            background: #2c5aa0;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            margin-right: 10px;
        }
        .ds-cart-totals {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        .total-row {
            font-size: 1.2em;
            margin-bottom: 15px;
        }
        </style>
        <?php
    }
}