<?php
/**
 * Elementor Shop Products Widget
 * Widget completo para exibição de produtos na loja
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DS_Shop_Price_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'ds_shop_products';
    }

    public function get_title() {
        return __( 'Produtos da Loja (DS Credits)', 'ds-backgamom-credits' );
    }

    public function get_icon() {
        return 'eicon-products';
    }

    public function get_categories() {
        return [ 'ds-backgamom-credits' ];
    }

    protected function register_controls() {
        // Content Section
        $this->start_controls_section(
            'content_section',
            [
                'label' => __( 'Produtos', 'ds-backgamom-credits' ),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'products_per_page',
            [
                'label' => __( 'Produtos por Página', 'ds-backgamom-credits' ),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 8,
                'min' => 1,
                'max' => 50,
            ]
        );

        $this->add_control(
            'columns',
            [
                'label' => __( 'Colunas', 'ds-backgamom-credits' ),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                ],
                'default' => '3',
            ]
        );

        $this->add_control(
            'category_filter',
            [
                'label' => __( 'Filtrar por Categoria', 'ds-backgamom-credits' ),
                'type' => \Elementor\Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => $this->get_product_categories(),
                'description' => __( 'Deixe vazio para mostrar todas', 'ds-backgamom-credits' ),
            ]
        );

        $this->add_control(
            'show_description',
            [
                'label' => __( 'Mostrar Descrição', 'ds-backgamom-credits' ),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'description_length',
            [
                'label' => __( 'Tamanho da Descrição', 'ds-backgamom-credits' ),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 100,
                'condition' => [
                    'show_description' => 'yes',
                ],
            ]
        );

        $this->end_controls_section();

        // Price Section
        $this->start_controls_section(
            'price_section',
            [
                'label' => __( 'Preços e Créditos', 'ds-backgamom-credits' ),
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
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();

        // Button Section
        $this->start_controls_section(
            'button_section',
            [
                'label' => __( 'Botão', 'ds-backgamom-credits' ),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label' => __( 'Texto do Botão', 'ds-backgamom-credits' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __( 'Ver Produto', 'ds-backgamom-credits' ),
            ]
        );

        $this->end_controls_section();

        // Style Sections
        $this->add_style_controls();
    }

    private function add_style_controls() {
        // Card Style
        $this->start_controls_section(
            'card_style',
            [
                'label' => __( 'Card do Produto', 'ds-backgamom-credits' ),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'card_background',
            [
                'label' => __( 'Cor de Fundo', 'ds-backgamom-credits' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .ds-product-card' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'card_border',
                'selector' => '{{WRAPPER}} .ds-product-card',
            ]
        );

        $this->add_control(
            'card_border_radius',
            [
                'label' => __( 'Border Radius', 'ds-backgamom-credits' ),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'selectors' => [
                    '{{WRAPPER}} .ds-product-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'card_shadow',
                'selector' => '{{WRAPPER}} .ds-product-card',
            ]
        );

        $this->end_controls_section();

        // Price Style
        $this->start_controls_section(
            'price_style',
            [
                'label' => __( 'Preços', 'ds-backgamom-credits' ),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'price_color_brl',
            [
                'label' => __( 'Cor BRL', 'ds-backgamom-credits' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#2c5aa0',
                'selectors' => [
                    '{{WRAPPER}} .ds-price-brl' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'price_color_usd',
            [
                'label' => __( 'Cor USD', 'ds-backgamom-credits' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#0073aa',
                'selectors' => [
                    '{{WRAPPER}} .ds-price-usd' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'price_typography',
                'selector' => '{{WRAPPER}} .ds-product-price',
            ]
        );

        $this->end_controls_section();

        // Button Style
        $this->start_controls_section(
            'button_style',
            [
                'label' => __( 'Botão', 'ds-backgamom-credits' ),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'button_color',
            [
                'label' => __( 'Cor do Texto', 'ds-backgamom-credits' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .ds-product-button' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'button_background',
            [
                'label' => __( 'Cor de Fundo', 'ds-backgamom-credits' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#2c5aa0',
                'selectors' => [
                    '{{WRAPPER}} .ds-product-button' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .ds-product-button',
            ]
        );

        $this->end_controls_section();
    }

    private function get_product_categories() {
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ]);

        $options = [];
        foreach ( $categories as $category ) {
            $options[ $category->term_id ] = $category->name;
        }

        return $options;
    }

    protected function render() {
        // Garantir que o CSS seja carregado
        wp_enqueue_style( 'dsbc-frontend', DSBC_PLUGIN_URL . 'assets/css/frontend.css', [], DSBC_VERSION );
        
        $settings = $this->get_settings_for_display();
        
        // Debug: verificar se WooCommerce está ativo
        if ( ! function_exists( 'wc_get_product' ) ) {
            echo '<p>' . __( 'WooCommerce não está ativo.', 'ds-backgamom-credits' ) . '</p>';
            return;
        }
        
        $args = [
            'post_type' => 'product',
            'posts_per_page' => $settings['products_per_page'] ?? 8,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_stock_status',
                    'value' => 'instock',
                    'compare' => '='
                ]
            ]
        ];

        if ( ! empty( $settings['category_filter'] ) ) {
            $args['tax_query'] = [[
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $settings['category_filter'],
            ]];
        }

        $products = new WP_Query( $args );
        
        if ( ! $products->have_posts() ) {
            echo '<p>' . __( 'Nenhum produto encontrado.', 'ds-backgamom-credits' ) . '</p>';
            wp_reset_postdata();
            return;
        }

        $columns = $settings['columns'] ?? '3';
        echo '<div class="ds-products-grid columns-' . esc_attr( $columns ) . '">';
        
        while ( $products->have_posts() ) {
            $products->the_post();
            $product = wc_get_product( get_the_ID() );
            
            if ( $product ) {
                $this->render_product_card( $product, $settings );
            }
        }
        
        echo '</div>';
        wp_reset_postdata();
    }

    private function render_product_card( $product, $settings ) {
        if ( ! $product ) {
            return;
        }
        
        $product_id = $product->get_id();
        
        echo '<div class="ds-product-card">';
        
        // Image
        echo '<div class="ds-product-image">';
        echo '<a href="' . esc_url( get_permalink( $product_id ) ) . '">';
        if ( has_post_thumbnail( $product_id ) ) {
            echo get_the_post_thumbnail( $product_id, 'medium' );
        } else {
            echo '<img src="' . wc_placeholder_img_src() . '" alt="' . esc_attr( $product->get_name() ) . '">';
        }
        echo '</a>';
        echo '</div>';
        
        // Content
        echo '<div class="ds-product-content">';
        
        // Title
        echo '<h3 class="ds-product-title">';
        echo '<a href="' . esc_url( get_permalink( $product_id ) ) . '">' . esc_html( $product->get_name() ) . '</a>';
        echo '</h3>';
        
        // Description
        if ( ( $settings['show_description'] ?? 'yes' ) === 'yes' ) {
            $description = $product->get_short_description();
            if ( ! $description ) {
                $description = $product->get_description();
            }
            if ( $description ) {
                $length = $settings['description_length'] ?? 100;
                $description = wp_trim_words( wp_strip_all_tags( $description ), intval($length / 5) );
                echo '<p class="ds-product-description">' . esc_html( $description ) . '</p>';
            }
        }
        
        // Prices
        echo '<div class="ds-product-price">';
        $this->render_prices( $product_id, $settings );
        echo '</div>';
        
        // Button
        $button_text = $settings['button_text'] ?? __( 'Ver Produto', 'ds-backgamom-credits' );
        echo '<div class="ds-product-actions">';
        echo '<a href="' . esc_url( get_permalink( $product_id ) ) . '" class="ds-product-button">';
        echo esc_html( $button_text );
        echo '</a>';
        echo '</div>';
        
        echo '</div>'; // content
        echo '</div>'; // card
    }

    private function render_prices( $product_id, $settings ) {
        $show_credits = ($settings['show_credits'] ?? 'yes') === 'yes';
        
        $credits = DS_Credit_Converter::get_product_credits( $product_id );
        
        if ( $credits <= 0 ) {
            echo '<span class="ds-no-price">' . __( 'Valor não configurado', 'ds-backgamom-credits' ) . '</span>';
            return;
        }
        
        $price_brl = DS_Credit_Converter::convert_credits_to_brl( $credits );
        
        // Exibir valor USD
        echo '<div class="ds-price ds-credits-usd">';
        echo esc_html( 'US$ ' . number_format( $credits, 2, '.', ',' ) );
        echo '</div>';
        
        // Exibir equivalente BRL
        echo '<div class="ds-price ds-price-brl">';
        echo esc_html( 'R$ ' . number_format( $price_brl, 2, ',', '.' ) );
        echo '</div>';
    }
}