<?php
/**
 * Dashboard administrativo do DS Backgamom Credits - Sistema USD
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once DSBC_PLUGIN_PATH . 'includes/admin/class-ds-admin-base.php';

class DS_Admin_Dashboard extends DS_Admin_Base {

    public function render_dashboard_tab() {
        ?>
        <div class="notice notice-success">
            <p><strong>🚀 DS Backgamom Credits - Sistema USD</strong> Sistema unificado de créditos baseado em dólares com conversão automática para BRL.</p>
        </div>
        
        <div class="dashboard-widgets-wrap">
            <div class="dashboard-widgets">
                <div class="postbox-container" style="width: 65%;">
                    <div class="postbox">
                        <h2 class="hndle"><span class="dashicons dashicons-chart-area"></span> Estatísticas do Sistema USD</h2>
                        <div class="inside">
                            <?php $this->display_usd_stats(); ?>
                        </div>
                    </div>
                    
                    <div class="postbox">
                        <h2 class="hndle"><span class="dashicons dashicons-money-alt"></span> Conversão de Moedas</h2>
                        <div class="inside">
                            <?php $this->display_currency_info(); ?>
                        </div>
                    </div>
                    
                    <div class="postbox">
                        <h2 class="hndle"><span class="dashicons dashicons-admin-tools"></span> Ações Rápidas</h2>
                        <div class="inside">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                                <a href="?page=ds-credits-lookup" class="button button-primary" style="text-align: center; padding: 15px;">
                                    <span class="dashicons dashicons-search"></span> Consultar Créditos
                                </a>
                                <a href="?page=ds-credits-withdrawals" class="button" style="text-align: center; padding: 15px;">
                                    <span class="dashicons dashicons-money"></span> Gerenciar Saques
                                </a>
                                <a href="?page=ds-credits-history" class="button" style="text-align: center; padding: 15px;">
                                    <span class="dashicons dashicons-list-view"></span> Ver Histórico
                                </a>
                                <a href="?page=ds-settings-usd" class="button" style="text-align: center; padding: 15px;">
                                    <span class="dashicons dashicons-admin-settings"></span> Configurações USD
                                </a>
                                <a href="?page=ds-user-management" class="button" style="text-align: center; padding: 15px;">
                                    <span class="dashicons dashicons-admin-users"></span> Gestão de Usuários
                                </a>
                                <a href="?page=ds-migration-usd" class="button" style="text-align: center; padding: 15px;">
                                    <span class="dashicons dashicons-update"></span> Migração USD
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="postbox-container" style="width: 35%;">
                    <div class="postbox">
                        <h2 class="hndle"><span class="dashicons dashicons-admin-settings"></span> Status do Sistema</h2>
                        <div class="inside">
                            <?php $this->display_system_status(); ?>
                        </div>
                    </div>
                    
                    <div class="postbox">
                        <h2 class="hndle"><span class="dashicons dashicons-info"></span> Informações USD</h2>
                        <div class="inside">
                            <?php $this->display_usd_info(); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .dsbc-stat-box {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }
        .dsbc-stat-box:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .dsbc-stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #2271b1;
            margin-bottom: 5px;
        }
        .dsbc-stat-label {
            color: #666;
            font-size: 0.9em;
        }
        .dsbc-status-ok { color: #46b450; }
        .dsbc-status-error { color: #dc3232; }
        .dsbc-status-warning { color: #ffb900; }
        .dsbc-currency-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .dsbc-currency-rate {
            font-size: 1.5em;
            font-weight: bold;
        }
        </style>
        <?php
    }

    private function display_usd_stats() {
        global $wpdb;
        
        // Total de créditos em USD
        $total_credits = $wpdb->get_var(
            "SELECT SUM(CAST(meta_value AS DECIMAL(10,2))) 
             FROM {$wpdb->usermeta} 
             WHERE meta_key = '_dsbc_credit_balance'"
        );

        // Usuários com créditos
        $users_with_credits = $wpdb->get_var(
            "SELECT COUNT(*) 
             FROM {$wpdb->usermeta} 
             WHERE meta_key = '_dsbc_credit_balance' AND CAST(meta_value AS DECIMAL(10,2)) > 0"
        );

        // Produtos com créditos
        $products_with_credits = $wpdb->get_var(
            "SELECT COUNT(*) 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = '_dsbc_credits_amount' AND CAST(meta_value AS DECIMAL(10,2)) > 0"
        );

        // Pedidos hoje
        $orders_today = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) 
                 FROM {$wpdb->posts} 
                 WHERE post_type = 'shop_order' 
                 AND post_status IN ('wc-completed', 'wc-processing')
                 AND DATE(post_date) = %s",
                current_time( 'Y-m-d' )
            )
        );

        // Conversão para BRL
        $exchange_rate = class_exists( 'DS_Credit_Converter' ) ? DS_Credit_Converter::get_exchange_rate() : 5.67;
        $total_brl = $total_credits * $exchange_rate;

        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">';
        
        echo '<div class="dsbc-stat-box">';
        echo '<div class="dsbc-stat-number">' . number_format( $total_credits ?: 0, 2 ) . '</div>';
        echo '<div class="dsbc-stat-label">Créditos USD</div>';
        echo '<small>≈ R$ ' . number_format( $total_brl ?: 0, 2, ',', '.' ) . '</small>';
        echo '</div>';
        
        echo '<div class="dsbc-stat-box">';
        echo '<div class="dsbc-stat-number">' . number_format( $users_with_credits ?: 0 ) . '</div>';
        echo '<div class="dsbc-stat-label">Usuários Ativos</div>';
        echo '</div>';
        
        echo '<div class="dsbc-stat-box">';
        echo '<div class="dsbc-stat-number">' . number_format( $products_with_credits ?: 0 ) . '</div>';
        echo '<div class="dsbc-stat-label">Produtos Ativos</div>';
        echo '</div>';
        
        echo '<div class="dsbc-stat-box">';
        echo '<div class="dsbc-stat-number">' . number_format( $orders_today ?: 0 ) . '</div>';
        echo '<div class="dsbc-stat-label">Pedidos Hoje</div>';
        echo '</div>';
        
        echo '</div>';
    }

    private function display_currency_info() {
        $exchange_rate = class_exists( 'DS_Credit_Converter' ) ? DS_Credit_Converter::get_exchange_rate() : 5.67;
        
        echo '<div class="dsbc-currency-box">';
        echo '<div class="dsbc-currency-rate">US$ 1,00 = R$ ' . number_format( $exchange_rate, 2, ',', '.' ) . '</div>';
        echo '<div>Taxa de câmbio atual</div>';
        echo '</div>';
        
        echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px;">';
        
        echo '<div style="text-align: center; padding: 10px; background: #f0f8ff; border-radius: 5px;">';
        echo '<strong>10 créditos</strong><br>';
        echo '<small>US$ 10,00 = R$ ' . number_format( 10 * $exchange_rate, 2, ',', '.' ) . '</small>';
        echo '</div>';
        
        echo '<div style="text-align: center; padding: 10px; background: #f0fff0; border-radius: 5px;">';
        echo '<strong>100 créditos</strong><br>';
        echo '<small>US$ 100,00 = R$ ' . number_format( 100 * $exchange_rate, 2, ',', '.' ) . '</small>';
        echo '</div>';
        
        echo '</div>';
        
        echo '<p style="margin-top: 15px; font-size: 0.9em; color: #666;">';
        echo '<strong>Como funciona:</strong> Todos os créditos são baseados em USD. ';
        echo 'Brasileiros pagam em BRL (convertido automaticamente) e recebem créditos USD.';
        echo '</p>';
    }

    private function display_system_status() {
        echo '<ul style="margin: 0; list-style: none;">';
        
        // WooCommerce
        $wc_status = class_exists( 'WooCommerce' );
        echo '<li style="margin-bottom: 8px;"><span class="dashicons ' . ( $wc_status ? 'dashicons-yes-alt dsbc-status-ok' : 'dashicons-dismiss dsbc-status-error' ) . '"></span> WooCommerce</li>';
        
        // Sistema USD
        $usd_migrated = get_option( 'dsbc_usd_migration_completed' );
        echo '<li style="margin-bottom: 8px;"><span class="dashicons ' . ( $usd_migrated ? 'dashicons-yes-alt dsbc-status-ok' : 'dashicons-warning dsbc-status-warning' ) . '"></span> Sistema USD ' . ( $usd_migrated ? 'Ativo' : 'Pendente' ) . '</li>';
        
        // Gateway Asaas
        $asaas_settings = get_option( 'woocommerce_ds_asaas_settings', [] );
        $asaas_enabled = ! empty( $asaas_settings['enabled'] ) && $asaas_settings['enabled'] === 'yes';
        echo '<li style="margin-bottom: 8px;"><span class="dashicons ' . ( $asaas_enabled ? 'dashicons-yes-alt dsbc-status-ok' : 'dashicons-dismiss dsbc-status-error' ) . '"></span> Gateway Asaas</li>';
        
        // Gateway PIX
        $pix_settings = get_option( 'woocommerce_ds_pix_settings', [] );
        $pix_enabled = ! empty( $pix_settings['enabled'] ) && $pix_settings['enabled'] === 'yes';
        echo '<li style="margin-bottom: 8px;"><span class="dashicons ' . ( $pix_enabled ? 'dashicons-yes-alt dsbc-status-ok' : 'dashicons-dismiss dsbc-status-error' ) . '"></span> Gateway PIX</li>';
        
        // Conversão BRL
        $helper_loaded = class_exists( 'DS_BRL_Gateway_Helper' );
        echo '<li style="margin-bottom: 8px;"><span class="dashicons ' . ( $helper_loaded ? 'dashicons-yes-alt dsbc-status-ok' : 'dashicons-dismiss dsbc-status-error' ) . '"></span> Conversão BRL</li>';
        
        echo '</ul>';
    }

    private function display_usd_info() {
        $migration_completed = get_option( 'dsbc_usd_migration_completed' );
        
        if ( $migration_completed ) {
            $migration_date = get_option( 'dsbc_usd_migration_completed' );
            $migration_rate = get_option( 'dsbc_migration_rate_used' );
            
            echo '<div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; padding: 10px; margin-bottom: 10px;">';
            echo '<strong>✅ Sistema Migrado</strong><br>';
            echo '<small>Data: ' . date( 'd/m/Y H:i', strtotime( $migration_date ) ) . '</small><br>';
            echo '<small>Taxa: R$ ' . number_format( $migration_rate, 2, ',', '.' ) . '</small>';
            echo '</div>';
        } else {
            echo '<div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px; padding: 10px; margin-bottom: 10px;">';
            echo '<strong>⚠️ Migração Pendente</strong><br>';
            echo '<small>Execute a migração para ativar o sistema USD</small>';
            echo '</div>';
        }
        
        echo '<ul style="margin: 0; list-style: none; font-size: 0.9em;">';
        echo '<li style="margin-bottom: 5px;"><span class="dashicons dashicons-arrow-right"></span> Créditos = USD</li>';
        echo '<li style="margin-bottom: 5px;"><span class="dashicons dashicons-arrow-right"></span> Conversão automática BRL</li>';
        echo '<li style="margin-bottom: 5px;"><span class="dashicons dashicons-arrow-right"></span> Gateways universais</li>';
        echo '<li style="margin-bottom: 5px;"><span class="dashicons dashicons-arrow-right"></span> Sistema unificado</li>';
        echo '</ul>';
        
        if ( ! $migration_completed ) {
            echo '<div style="margin-top: 15px;">';
            echo '<a href="' . admin_url( 'admin.php?page=ds-migration-usd' ) . '" class="button button-primary button-small">Executar Migração</a>';
            echo '</div>';
        }
    }
}