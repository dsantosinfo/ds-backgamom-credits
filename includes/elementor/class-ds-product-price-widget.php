<?php
/**
 * Elementor Product Price Widget
 * Para páginas de produto individual
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DS_Product_Price_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'ds_product_price';
    }

    public function get_title() {
        return __( 'Preço do Produto (Multi-Moeda)', 'ds-backgamom-credits' );
    }

    public function get_icon() {
        return 'eicon-price-table';
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
            'show_currencies',
            [
                'label' => __( 'Moedas a Exibir', 'ds-backgamom-credits' ),
                'type' => \Elementor\Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => [
                    'BRL' => 'Real Brasileiro (R$)',
                    'USD' => 'Dólar Americano (US$)',
                ],
                'default' => [ 'BRL', 'USD' ],
            ]
        );

        $this->add_control(
            'show_credits',
            [
                'label' => __( 'Mostrar Créditos', 'ds-backgamom-credits' ),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __( 'Sim', 'ds-backgamom-credits' ),
                'label_off' => __( 'Não', 'ds-backgamom-credits' ),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'layout',
            [
                'label' => __( 'Layout', 'ds-backgamom-credits' ),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'horizontal' => __( 'Horizontal', 'ds-backgamom-credits' ),
                    'vertical' => __( 'Vertical', 'ds-backgamom-credits' ),
                ],
                'default' => 'horizontal',
            ]
        );

        $this->end_controls_section();

        // Style Section
        $this->start_controls_section(
            'style_section',
            [
                'label' => __( 'Estilo', 'ds-backgamom-credits' ),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'price_color',
            [
                'label' => __( 'Cor do Preço', 'ds-backgamom-credits' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ds-price' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'price_typography',
                'selector' => '{{WRAPPER}} .ds-price',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        global $product;
        
        if ( ! $product ) {
            global $post;
            $product = wc_get_product( $post->ID );
        }

        if ( ! $product ) {
            echo '<p>' . __( 'Produto não encontrado', 'ds-backgamom-credits' ) . '</p>';
            return;
        }

        $settings = $this->get_settings_for_display();
        $show_credits = ($settings['show_credits'] ?? 'yes') === 'yes';
        $layout = $settings['layout'] ?? 'horizontal';
        
        $product_id = $product->get_id();
        $credits = DS_Credit_Converter::get_product_credits( $product_id );
        
        if ( $credits <= 0 ) {
            echo '<p>' . __( 'Valor não configurado', 'ds-backgamom-credits' ) . '</p>';
            return;
        }
        
        $price_brl = DS_Credit_Converter::convert_credits_to_brl( $credits );
        
        $display_parts = [];
        
        // Sempre mostrar valor USD
        $display_parts[] = '<span class="ds-credits-usd">US$ ' . number_format( $credits, 2, '.', ',' ) . '</span>';
        
        // Mostrar equivalente em BRL
        $display_parts[] = '<span class="ds-price-brl">R$ ' . number_format( $price_brl, 2, ',', '.' ) . '</span>';
        
        $separator = $layout === 'vertical' ? '<br>' : ' = ';
        echo '<div class="ds-elementor-price-widget layout-' . esc_attr($layout) . '">';
        echo implode( $separator, $display_parts );
        echo '</div>';
    }
}