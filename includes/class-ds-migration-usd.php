<?php
/**
 * Script de Migração para Sistema USD
 * Converte dados existentes para o novo sistema baseado em USD
 *
 * @package DS_Backgamom_Credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DS_Migration_USD {

    /**
     * Taxa de câmbio fixa para migração
     */
    private static $migration_rate = 5.67;

    /**
     * Executa migração completa
     */
    public static function run_migration() {
        // Verificar se já foi executada
        if ( get_option( 'dsbc_usd_migration_completed' ) ) {
            return ['success' => false, 'message' => 'Migração já foi executada anteriormente.'];
        }

        $results = [];
        
        try {
            // 1. Migrar saldos de usuários
            $results['users'] = self::migrate_user_balances();
            
            // 2. Migrar produtos
            $results['products'] = self::migrate_products();
            
            // 3. Migrar logs
            $results['logs'] = self::migrate_credit_logs();
            
            // 4. Limpar meta fields obsoletos
            $results['cleanup'] = self::cleanup_old_meta_fields();
            
            // 5. Definir taxa de câmbio
            if ( class_exists( 'DS_Credit_Converter' ) ) {
                DS_Credit_Converter::update_exchange_rate( self::$migration_rate );
            }
            
            // Marcar migração como concluída
            update_option( 'dsbc_usd_migration_completed', current_time( 'mysql' ) );
            update_option( 'dsbc_migration_rate_used', self::$migration_rate );
            
            return ['success' => true, 'results' => $results];
            
        } catch ( Exception $e ) {
            return ['success' => false, 'message' => 'Erro durante migração: ' . $e->getMessage()];
        }
    }

    /**
     * Migra saldos de usuários de BRL para USD
     */
    private static function migrate_user_balances() {
        global $wpdb;
        
        $users_updated = 0;
        $total_brl_converted = 0;
        
        // Buscar todos os usuários com saldo
        $users_with_balance = $wpdb->get_results(
            "SELECT user_id, meta_value as balance_brl 
             FROM {$wpdb->usermeta} 
             WHERE meta_key = '_dsbc_credit_balance' 
             AND meta_value > 0"
        );
        
        if ( ! $users_with_balance ) {
            return [
                'users_updated' => 0,
                'total_brl_converted' => 0,
                'total_usd_created' => 0
            ];
        }
        
        foreach ( $users_with_balance as $user ) {
            $balance_brl = floatval( $user->balance_brl );
            $balance_usd = $balance_brl / self::$migration_rate;
            
            // Atualizar saldo para USD
            update_user_meta( $user->user_id, '_dsbc_credit_balance', $balance_usd );
            
            // Salvar backup do saldo original
            update_user_meta( $user->user_id, '_dsbc_balance_brl_backup', $balance_brl );
            
            $users_updated++;
            $total_brl_converted += $balance_brl;
        }
        
        return [
            'users_updated' => $users_updated,
            'total_brl_converted' => $total_brl_converted,
            'total_usd_created' => $total_brl_converted / self::$migration_rate
        ];
    }

    /**
     * Migra produtos para usar apenas créditos USD
     */
    private static function migrate_products() {
        global $wpdb;
        
        $products_updated = 0;
        
        // Buscar produtos com preços em múltiplas moedas
        $products = $wpdb->get_results(
            "SELECT DISTINCT post_id 
             FROM {$wpdb->postmeta} 
             WHERE meta_key IN ('_dsbc_price_brl', '_dsbc_price_usd') 
             AND meta_value > 0"
        );
        
        if ( ! $products ) {
            return ['products_updated' => 0];
        }
        
        foreach ( $products as $product ) {
            $product_id = $product->post_id;
            
            // Buscar preços existentes
            $price_brl = get_post_meta( $product_id, '_dsbc_price_brl', true );
            $price_usd = get_post_meta( $product_id, '_dsbc_price_usd', true );
            
            // Determinar créditos baseado no preço BRL (prioridade)
            $credits = 0;
            if ( ! empty( $price_brl ) ) {
                $credits = $price_brl / self::$migration_rate;
            } elseif ( ! empty( $price_usd ) ) {
                $credits = floatval( $price_usd );
            }
            
            if ( $credits > 0 ) {
                // Definir créditos USD
                update_post_meta( $product_id, '_dsbc_credits_amount', $credits );
                
                // Backup dos preços antigos
                if ( $price_brl ) {
                    update_post_meta( $product_id, '_dsbc_price_brl_backup', $price_brl );
                }
                if ( $price_usd ) {
                    update_post_meta( $product_id, '_dsbc_price_usd_backup', $price_usd );
                }
                
                $products_updated++;
            }
        }
        
        return ['products_updated' => $products_updated];
    }

    /**
     * Migra logs de créditos para USD
     */
    private static function migrate_credit_logs() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dsbc_credit_logs';
        
        // Verificar se tabela existe
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) {
            return ['logs_updated' => 0, 'message' => 'Tabela de logs não existe'];
        }
        
        // Converter valores para USD
        $logs_updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table_name} 
                 SET amount = ROUND(amount / %f, 2),
                     old_balance = ROUND(old_balance / %f, 2),
                     new_balance = ROUND(new_balance / %f, 2)
                 WHERE amount != 0",
                self::$migration_rate,
                self::$migration_rate,
                self::$migration_rate
            )
        );
        
        return ['logs_updated' => $logs_updated];
    }

    /**
     * Remove meta fields obsoletos
     */
    private static function cleanup_old_meta_fields() {
        global $wpdb;
        
        $obsolete_fields = [
            '_dsbc_price_brl',
            '_dsbc_price_usd', 
            '_dsbc_price_eur',
            '_dsbc_price_gbp'
        ];
        
        $deleted_count = 0;
        
        foreach ( $obsolete_fields as $field ) {
            $deleted = $wpdb->delete(
                $wpdb->postmeta,
                ['meta_key' => $field],
                ['%s']
            );
            $deleted_count += $deleted;
        }
        
        return ['meta_fields_deleted' => $deleted_count];
    }

    /**
     * Reverte migração (emergência)
     */
    public static function rollback_migration() {
        global $wpdb;
        
        if ( ! get_option( 'dsbc_usd_migration_completed' ) ) {
            return ['success' => false, 'message' => 'Nenhuma migração para reverter.'];
        }
        
        try {
            // Restaurar saldos de usuários
            $users_restored = 0;
            $users_with_backup = $wpdb->get_results(
                "SELECT user_id, meta_value as balance_brl_backup 
                 FROM {$wpdb->usermeta} 
                 WHERE meta_key = '_dsbc_balance_brl_backup'"
            );
            
            foreach ( $users_with_backup as $user ) {
                update_user_meta( $user->user_id, '_dsbc_credit_balance', $user->balance_brl_backup );
                delete_user_meta( $user->user_id, '_dsbc_balance_brl_backup' );
                $users_restored++;
            }
            
            // Restaurar produtos
            $products_restored = 0;
            $products_with_backup = $wpdb->get_results(
                "SELECT DISTINCT post_id 
                 FROM {$wpdb->postmeta} 
                 WHERE meta_key LIKE '_dsbc_price_%_backup'"
            );
            
            foreach ( $products_with_backup as $product ) {
                $product_id = $product->post_id;
                
                // Restaurar preços
                $backup_brl = get_post_meta( $product_id, '_dsbc_price_brl_backup', true );
                $backup_usd = get_post_meta( $product_id, '_dsbc_price_usd_backup', true );
                
                if ( $backup_brl ) {
                    update_post_meta( $product_id, '_dsbc_price_brl', $backup_brl );
                    delete_post_meta( $product_id, '_dsbc_price_brl_backup' );
                }
                if ( $backup_usd ) {
                    update_post_meta( $product_id, '_dsbc_price_usd', $backup_usd );
                    delete_post_meta( $product_id, '_dsbc_price_usd_backup' );
                }
                
                // Remover créditos USD
                delete_post_meta( $product_id, '_dsbc_credits_amount' );
                $products_restored++;
            }
            
            // Remover flags de migração
            delete_option( 'dsbc_usd_migration_completed' );
            delete_option( 'dsbc_migration_rate_used' );
            
            return [
                'success' => true,
                'users_restored' => $users_restored,
                'products_restored' => $products_restored
            ];
            
        } catch ( Exception $e ) {
            return ['success' => false, 'message' => 'Erro durante rollback: ' . $e->getMessage()];
        }
    }

    /**
     * Relatório de migração
     */
    public static function get_migration_report() {
        if ( ! get_option( 'dsbc_usd_migration_completed' ) ) {
            return ['migrated' => false];
        }
        
        global $wpdb;
        
        $report = [
            'migrated' => true,
            'migration_date' => get_option( 'dsbc_usd_migration_completed' ),
            'rate_used' => get_option( 'dsbc_migration_rate_used' ),
            'current_users_with_balance' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->usermeta} 
                 WHERE meta_key = '_dsbc_credit_balance' AND meta_value > 0"
            ),
            'current_products_with_credits' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} 
                 WHERE meta_key = '_dsbc_credits_amount' AND meta_value > 0"
            ),
            'total_credits_in_circulation' => $wpdb->get_var(
                "SELECT SUM(meta_value) FROM {$wpdb->usermeta} 
                 WHERE meta_key = '_dsbc_credit_balance'"
            )
        ];
        
        return $report;
    }

    /**
     * Verifica se migração é necessária
     */
    public static function needs_migration() {
        global $wpdb;
        
        // Se já foi migrado, não precisa
        if ( get_option( 'dsbc_usd_migration_completed' ) ) {
            return false;
        }
        
        // Verificar se há dados antigos para migrar
        $old_data = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key IN ('_dsbc_price_brl', '_dsbc_price_usd')"
        );
        
        return $old_data > 0;
    }
}