<?php
/**
 * Elementor Widgets Manager
 * DS Backgamom Credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DS_Elementor_Widgets {

    public function __construct() {
        // Hook mais cedo para garantir que seja executado
        add_action( 'init', [ $this, 'init' ], 0 );
    }

    public function init() {
        // Verificar se o Elementor está instalado
        if ( ! class_exists( '\Elementor\Plugin' ) ) {
            return;
        }
        
        // Se o Elementor já foi carregado, inicializar imediatamente
        if ( did_action( 'elementor/loaded' ) ) {
            $this->elementor_loaded();
        } else {
            // Caso contrário, aguardar o carregamento
            add_action( 'elementor/loaded', [ $this, 'elementor_loaded' ] );
        }
    }

    public function elementor_loaded() {
        // Verificar versão mínima do Elementor
        if ( defined( 'ELEMENTOR_VERSION' ) && ! version_compare( ELEMENTOR_VERSION, '3.0.0', '>=' ) ) {
            add_action( 'admin_notices', [ $this, 'elementor_version_notice' ] );
            return;
        }

        // Registrar categoria e widgets
        add_action( 'elementor/elements/categories_registered', [ $this, 'add_elementor_category' ] );
        add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
        
        // Fallback para versões mais antigas
        add_action( 'elementor/widgets/widgets_registered', [ $this, 'register_widgets_legacy' ] );
    }

    public function elementor_version_notice() {
        echo '<div class="notice notice-warning"><p>';
        echo __( 'DS Backgamom Credits requer Elementor 3.0.0 ou superior.', 'ds-backgamom-credits' );
        echo '</p></div>';
    }

    public function add_elementor_category( $elements_manager ) {
        $elements_manager->add_category(
            'ds-backgamom-credits',
            [
                'title' => __( 'DS Backgamom Credits', 'ds-backgamom-credits' ),
                'icon' => 'fa fa-coins',
            ]
        );
    }

    public function register_widgets( $widgets_manager ) {
        $this->load_widget_files();
        
        if ( class_exists( 'DS_Product_Price_Widget' ) ) {
            $widgets_manager->register( new DS_Product_Price_Widget() );
        }
        
        if ( class_exists( 'DS_Shop_Price_Widget' ) ) {
            $widgets_manager->register( new DS_Shop_Price_Widget() );
        }
        
        if ( class_exists( 'DS_Cart_Widget' ) ) {
            $widgets_manager->register( new DS_Cart_Widget() );
        }
    }
    
    // Método legacy para versões antigas do Elementor
    public function register_widgets_legacy() {
        $this->load_widget_files();
        
        if ( class_exists( 'DS_Product_Price_Widget' ) ) {
            \Elementor\Plugin::instance()->widgets_manager->register_widget_type( new DS_Product_Price_Widget() );
        }
        
        if ( class_exists( 'DS_Shop_Price_Widget' ) ) {
            \Elementor\Plugin::instance()->widgets_manager->register_widget_type( new DS_Shop_Price_Widget() );
        }
        
        if ( class_exists( 'DS_Cart_Widget' ) ) {
            \Elementor\Plugin::instance()->widgets_manager->register_widget_type( new DS_Cart_Widget() );
        }
    }
    
    private function load_widget_files() {
        $widget_files = [
            'class-ds-product-price-widget.php',
            'class-ds-shop-price-widget.php', 
            'class-ds-cart-widget.php'
        ];
        
        foreach ( $widget_files as $file ) {
            $file_path = DSBC_PLUGIN_PATH . 'includes/elementor/' . $file;
            if ( file_exists( $file_path ) ) {
                require_once $file_path;
            }
        }
    }
}