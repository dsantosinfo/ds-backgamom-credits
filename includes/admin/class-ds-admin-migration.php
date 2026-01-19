<?php
/**
 * Interface Administrativa para Migração USD
 * 
 * @package DS_Backgamom_Credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DS_Admin_Migration {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ], 20 );
        add_action( 'admin_init', [ $this, 'handle_migration_actions' ] );
        add_action( 'admin_notices', [ $this, 'show_migration_notices' ] );
    }

    /**
     * Adiciona menu de migração
     */
    public function add_admin_menu() {
        add_submenu_page(
            'ds-backgamom-credits',
            'Migração USD',
            'Migração USD',
            'manage_options',
            'ds-migration-usd',
            [ $this, 'migration_page' ]
        );
    }

    /**
     * Processa ações de migração
     */
    public function handle_migration_actions() {
        if ( ! isset( $_POST['ds_migration_action'] ) || ! wp_verify_nonce( $_POST['ds_migration_nonce'], 'ds_migration_action' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $action = sanitize_text_field( $_POST['ds_migration_action'] );

        switch ( $action ) {
            case 'run_migration':
                $this->run_migration();
                break;
            case 'rollback_migration':
                $this->rollback_migration();
                break;
            case 'clear_migration_flag':
                $this->clear_migration_flag();
                break;
        }
    }

    /**
     * Executa migração
     */
    private function run_migration() {
        if ( ! class_exists( 'DS_Migration_USD' ) ) {
            set_transient( 'ds_migration_error', 'Classe de migração não encontrada.', 30 );
            return;
        }

        $result = DS_Migration_USD::run_migration();
        
        if ( $result['success'] ) {
            set_transient( 'ds_migration_success', 'Migração executada com sucesso!', 30 );
        } else {
            set_transient( 'ds_migration_error', $result['message'], 30 );
        }
    }

    /**
     * Reverte migração
     */
    private function rollback_migration() {
        if ( ! class_exists( 'DS_Migration_USD' ) ) {
            set_transient( 'ds_migration_error', 'Classe de migração não encontrada.', 30 );
            return;
        }

        $result = DS_Migration_USD::rollback_migration();
        
        if ( $result['success'] ) {
            set_transient( 'ds_migration_success', 'Rollback executado com sucesso!', 30 );
        } else {
            set_transient( 'ds_migration_error', $result['message'], 30 );
        }
    }

    /**
     * Limpa flag de migração
     */
    private function clear_migration_flag() {
        delete_option( 'dsbc_usd_migration_completed' );
        delete_option( 'dsbc_migration_rate_used' );
        set_transient( 'ds_migration_success', 'Flag de migração removida. Você pode executar a migração novamente.', 30 );
    }

    /**
     * Exibe notificações
     */
    public function show_migration_notices() {
        $screen = get_current_screen();
        if ( $screen->id !== 'woocommerce_page_ds-migration-usd' ) {
            return;
        }

        if ( $success = get_transient( 'ds_migration_success' ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $success ) . '</p></div>';
            delete_transient( 'ds_migration_success' );
        }

        if ( $error = get_transient( 'ds_migration_error' ) ) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
            delete_transient( 'ds_migration_error' );
        }
    }

    /**
     * Página de migração
     */
    public function migration_page() {
        $migration_completed = get_option( 'dsbc_usd_migration_completed' );
        $migration_report = class_exists( 'DS_Migration_USD' ) ? DS_Migration_USD::get_migration_report() : null;
        ?>
        <div class="wrap">
            <h1>Migração para Sistema USD</h1>
            
            <div class="card" style="max-width: 800px;">
                <h2>Status da Migração</h2>
                
                <?php if ( $migration_completed ): ?>
                    <div class="notice notice-success inline">
                        <p><strong>✅ Migração Concluída</strong></p>
                        <p>Data: <?php echo esc_html( $migration_completed ); ?></p>
                        <?php if ( $migration_report ): ?>
                            <ul>
                                <li>Taxa usada: R$ <?php echo number_format( $migration_report['rate_used'], 2, ',', '.' ); ?></li>
                                <li>Usuários com saldo: <?php echo intval( $migration_report['current_users_with_balance'] ); ?></li>
                                <li>Produtos com créditos: <?php echo intval( $migration_report['current_products_with_credits'] ); ?></li>
                                <li>Total de créditos: <?php echo number_format( $migration_report['total_credits_in_circulation'], 2, ',', '.' ); ?> USD</li>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="notice notice-warning inline">
                        <p><strong>⚠️ Migração Pendente</strong></p>
                        <p>O sistema ainda não foi migrado para USD. Execute a migração para converter todos os dados.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card" style="max-width: 800px;">
                <h2>Sobre a Migração USD</h2>
                <p><strong>O que faz:</strong></p>
                <ul>
                    <li>Converte saldos de usuários de BRL para USD (taxa: R$ 5,67 = US$ 1,00)</li>
                    <li>Atualiza produtos para usar apenas créditos USD</li>
                    <li>Converte histórico de transações</li>
                    <li>Remove campos obsoletos de múltiplas moedas</li>
                    <li>Cria backup automático dos dados originais</li>
                </ul>
                
                <p><strong>⚠️ Importante:</strong></p>
                <ul>
                    <li>Faça backup completo do banco antes de executar</li>
                    <li>A migração é irreversível (exceto via rollback)</li>
                    <li>Teste em ambiente de desenvolvimento primeiro</li>
                </ul>
            </div>

            <div class="card" style="max-width: 800px;">
                <h2>Ações Disponíveis</h2>
                
                <?php if ( ! $migration_completed ): ?>
                    <form method="post" style="margin-bottom: 20px;">
                        <?php wp_nonce_field( 'ds_migration_action', 'ds_migration_nonce' ); ?>
                        <input type="hidden" name="ds_migration_action" value="run_migration">
                        <button type="submit" class="button button-primary button-large" 
                                onclick="return confirm('Tem certeza? Esta ação irá converter todos os dados para USD. Faça backup antes!')">
                            🚀 Executar Migração USD
                        </button>
                        <p class="description">Converte todo o sistema para usar créditos baseados em USD.</p>
                    </form>
                <?php else: ?>
                    <form method="post" style="margin-bottom: 20px;">
                        <?php wp_nonce_field( 'ds_migration_action', 'ds_migration_nonce' ); ?>
                        <input type="hidden" name="ds_migration_action" value="rollback_migration">
                        <button type="submit" class="button button-secondary" 
                                onclick="return confirm('ATENÇÃO: Isso irá reverter todos os dados para o sistema anterior. Confirma?')">
                            ↩️ Reverter Migração (Rollback)
                        </button>
                        <p class="description">Reverte para o sistema anterior (apenas se houver backup).</p>
                    </form>

                    <form method="post" style="margin-bottom: 20px;">
                        <?php wp_nonce_field( 'ds_migration_action', 'ds_migration_nonce' ); ?>
                        <input type="hidden" name="ds_migration_action" value="clear_migration_flag">
                        <button type="submit" class="button button-secondary" 
                                onclick="return confirm('Isso permitirá executar a migração novamente. Confirma?')">
                            🔄 Limpar Flag de Migração
                        </button>
                        <p class="description">Remove a marcação de migração concluída (para re-executar).</p>
                    </form>
                <?php endif; ?>
            </div>

            <?php if ( $migration_report && $migration_report['migrated'] ): ?>
            <div class="card" style="max-width: 800px;">
                <h2>Relatório Detalhado</h2>
                <table class="widefat">
                    <tr>
                        <td><strong>Data da Migração:</strong></td>
                        <td><?php echo esc_html( $migration_report['migration_date'] ); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Taxa Utilizada:</strong></td>
                        <td>R$ <?php echo number_format( $migration_report['rate_used'], 2, ',', '.' ); ?> = US$ 1,00</td>
                    </tr>
                    <tr>
                        <td><strong>Usuários com Saldo:</strong></td>
                        <td><?php echo intval( $migration_report['current_users_with_balance'] ); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Produtos com Créditos:</strong></td>
                        <td><?php echo intval( $migration_report['current_products_with_credits'] ); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Total em Circulação:</strong></td>
                        <td><?php echo number_format( $migration_report['total_credits_in_circulation'], 2, ',', '.' ); ?> créditos (USD)</td>
                    </tr>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <style>
        .card { 
            background: #fff; 
            border: 1px solid #ccd0d4; 
            box-shadow: 0 1px 1px rgba(0,0,0,.04); 
            padding: 20px; 
            margin: 20px 0; 
        }
        .notice.inline { 
            margin: 5px 0 15px; 
            padding: 12px; 
        }
        .button-large { 
            height: auto; 
            line-height: 1.5; 
            padding: 12px 24px; 
            font-size: 14px; 
        }
        </style>
        <?php
    }
}
