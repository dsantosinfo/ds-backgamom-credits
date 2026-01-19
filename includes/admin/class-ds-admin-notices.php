<?php
/**
 * Sistema de Avisos Administrativos para Migração
 * 
 * @package DS_Backgamom_Credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DS_Admin_Notices {

    public function __construct() {
        add_action( 'admin_notices', [ $this, 'show_migration_notice' ] );
        add_action( 'wp_ajax_ds_dismiss_migration_notice', [ $this, 'dismiss_migration_notice' ] );
    }

    /**
     * Exibe aviso de migração se necessário
     */
    public function show_migration_notice() {
        // Só para administradores
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Verificar se foi dispensado
        if ( get_user_meta( get_current_user_id(), 'ds_migration_notice_dismissed', true ) ) {
            return;
        }

        // Verificar se migração já foi feita
        if ( get_option( 'dsbc_usd_migration_completed' ) ) {
            return;
        }

        // Verificar se há dados para migrar
        if ( ! class_exists( 'DS_Migration_USD' ) || ! DS_Migration_USD::needs_migration() ) {
            return;
        }

        ?>
        <div class="notice notice-info is-dismissible ds-migration-notice">
            <h3>🚀 DS Backgamom Credits - Sistema USD Disponível</h3>
            <p>
                <strong>Nova funcionalidade:</strong> O sistema de créditos agora suporta USD como base universal. 
                Todos os créditos serão baseados em dólares (1 crédito = US$ 1,00) com conversão automática para BRL nos pagamentos.
            </p>
            <p>
                <strong>Benefícios:</strong>
                • Unificação global de créditos<br>
                • Conversão automática BRL nos gateways brasileiros<br>
                • Compatibilidade com gateways internacionais<br>
                • Sistema mais robusto e escalável
            </p>
            <p>
                <a href="<?php echo admin_url( 'admin.php?page=ds-migration-usd' ); ?>" class="button button-primary">
                    📋 Acessar Painel de Migração
                </a>
                <button type="button" class="button button-secondary ds-dismiss-notice">
                    Dispensar Aviso
                </button>
            </p>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.ds-dismiss-notice').on('click', function() {
                $.post(ajaxurl, {
                    action: 'ds_dismiss_migration_notice',
                    _ajax_nonce: '<?php echo wp_create_nonce( 'ds_dismiss_migration' ); ?>'
                });
                $('.ds-migration-notice').fadeOut();
            });
        });
        </script>

        <style>
        .ds-migration-notice h3 {
            margin-top: 0;
            color: #2271b1;
        }
        .ds-migration-notice p {
            margin: 10px 0;
        }
        .ds-migration-notice .button-primary {
            margin-right: 10px;
        }
        </style>
        <?php
    }

    /**
     * Dispensa aviso de migração
     */
    public function dismiss_migration_notice() {
        check_ajax_referer( 'ds_dismiss_migration' );
        
        if ( current_user_can( 'manage_options' ) ) {
            update_user_meta( get_current_user_id(), 'ds_migration_notice_dismissed', true );
        }
        
        wp_die();
    }
}
