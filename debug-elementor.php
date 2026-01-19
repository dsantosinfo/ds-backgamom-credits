<?php
/**
 * Debug Elementor Widgets
 * Arquivo temporário para debug
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Hook para debug
add_action( 'wp_footer', function() {
    if ( ! is_admin() && current_user_can( 'manage_options' ) ) {
        echo '<div style="position: fixed; bottom: 10px; right: 10px; background: #000; color: #fff; padding: 10px; z-index: 9999; font-size: 12px;">';
        echo '<strong>DS Credits Debug:</strong><br>';
        
        // Verificar se Elementor está ativo
        if ( did_action( 'elementor/loaded' ) ) {
            echo '✅ Elementor carregado<br>';
            
            // Verificar versão
            if ( defined( 'ELEMENTOR_VERSION' ) ) {
                echo '📦 Versão: ' . ELEMENTOR_VERSION . '<br>';
            }
            
            // Verificar se widgets estão registrados
            if ( class_exists( '\Elementor\Plugin' ) ) {
                $widgets_manager = \Elementor\Plugin::instance()->widgets_manager;
                $registered_widgets = $widgets_manager->get_widget_types();
                
                $ds_widgets = array_filter( array_keys( $registered_widgets ), function( $widget ) {
                    return strpos( $widget, 'ds_' ) === 0;
                });
                
                if ( ! empty( $ds_widgets ) ) {
                    echo '✅ Widgets DS: ' . implode( ', ', $ds_widgets ) . '<br>';
                } else {
                    echo '❌ Nenhum widget DS encontrado<br>';
                }
            }
        } else {
            echo '❌ Elementor não carregado<br>';
        }
        
        // Verificar se classes existem
        $classes = [
            'DS_Elementor_Widgets',
            'DS_Product_Price_Widget', 
            'DS_Shop_Price_Widget',
            'DS_Cart_Widget'
        ];
        
        foreach ( $classes as $class ) {
            if ( class_exists( $class ) ) {
                echo '✅ ' . $class . '<br>';
            } else {
                echo '❌ ' . $class . '<br>';
            }
        }
        
        echo '</div>';
    }
});

// Debug no admin
add_action( 'admin_notices', function() {
    if ( isset( $_GET['page'] ) && $_GET['page'] === 'ds-backgamom-credits' ) {
        echo '<div class="notice notice-info"><p>';
        echo '<strong>Debug Elementor:</strong> ';
        
        if ( did_action( 'elementor/loaded' ) ) {
            echo 'Elementor carregado ✅';
        } else {
            echo 'Elementor não carregado ❌';
        }
        
        if ( class_exists( 'DS_Elementor_Widgets' ) ) {
            echo ' | Classe DS_Elementor_Widgets existe ✅';
        } else {
            echo ' | Classe DS_Elementor_Widgets não existe ❌';
        }
        
        echo '</p></div>';
    }
});