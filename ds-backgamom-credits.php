<?php
/**
 * Plugin Name:         DS Backgamom Credits
 * Plugin URI:          https://dsantosinfo.com.br/
 * Description:         Sistema de créditos para a plataforma Backgamom Brasil com integração ao gateway de pagamento Asaas.
 * Version:             2.0.0
 * Author:              DSantos Info
 * Author URI:          https://dsantosinfo.com.br/
 * License:             GPL v2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:         ds-backgamom-credits
 * Domain Path:         /languages
 * Requires PHP:        7.4
 * Requires at least:   5.0
 * WC requires at least: 5.0
 * WC tested up to:      8.2
 */

// Previne o acesso direto ao arquivo.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Declaração de compatibilidade com High-Performance Order Storage (HPOS).
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

/**
 * Classe principal do plugin
 */
final class DS_Backgamom_Credits {

    /**
     * Versão do plugin.
     *
     * @var string
     */
    const VERSION = '2.1.0';

    /**
     * Instância única da classe.
     *
     * @var DS_Backgamom_Credits|null
     */
    private static $instance = null;

    /**
     * Método estático para obter a instância da classe (Singleton).
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado.
     */
    private function __construct() {
        $this->define_constants();
        add_action( 'plugins_loaded', [ $this, 'init_plugin' ] );
    }

    /**
     * Define as constantes do plugin.
     */
    private function define_constants() {
        define( 'DSBC_PLUGIN_FILE', __FILE__ );
        define( 'DSBC_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
        define( 'DSBC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
        define( 'DSBC_VERSION', self::VERSION );
    }

    /**
     * Inicializa o plugin.
     */
    public function init_plugin() {
        // Carrega textdomain
        load_plugin_textdomain('ds-backgamom-credits', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        
        // Carrega os arquivos principais imediatamente para dependências
        $this->include_files();
        
        // Força criação das tabelas
        $this->create_tables();
        
        // Verifica se o WooCommerce está ativo.
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', [ $this, 'missing_woocommerce_notice' ] );
            return;
        }

        // Inicializa as classes
        $this->init_classes();
        // Adiciona o gateway ao WooCommerce
        add_filter( 'woocommerce_payment_gateways', [ $this, 'add_asaas_gateway' ] );
    }
    
    /**
     * Cria as tabelas necessárias.
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabela de logs de créditos
        $table_name = $wpdb->prefix . 'dsbc_credit_logs';
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            amount decimal(10,2) NOT NULL,
            type varchar(50) NOT NULL,
            observation text,
            admin_id int(11),
            admin_name varchar(255),
            old_balance decimal(10,2) NOT NULL DEFAULT 0,
            new_balance decimal(10,2) NOT NULL DEFAULT 0,
            payment_due_date date NULL,
            payment_status varchar(20) DEFAULT 'paid',
            payment_method varchar(50) NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY admin_id (admin_id),
            KEY created_at (created_at),
            KEY payment_status (payment_status),
            KEY payment_due_date (payment_due_date)
        ) $charset_collate;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
        
        // Verificar e adicionar colunas se não existirem
        $columns = $wpdb->get_col( "DESCRIBE $table_name" );
        if ( ! in_array( 'payment_due_date', $columns ) ) {
            $wpdb->query( "ALTER TABLE $table_name ADD COLUMN payment_due_date date NULL" );
        }
        if ( ! in_array( 'payment_status', $columns ) ) {
            $wpdb->query( "ALTER TABLE $table_name ADD COLUMN payment_status varchar(20) DEFAULT 'paid'" );
        }
        if ( ! in_array( 'payment_method', $columns ) ) {
            $wpdb->query( "ALTER TABLE $table_name ADD COLUMN payment_method varchar(50) NULL" );
        }
        if ( ! in_array( 'payment_receipt', $columns ) ) {
            $wpdb->query( "ALTER TABLE $table_name ADD COLUMN payment_receipt varchar(255) NULL" );
        }
        
        // Verificar se as colunas de valor são int e converter para decimal
        $column_info = $wpdb->get_results( "SHOW COLUMNS FROM $table_name LIKE 'amount'" );
        if ( ! empty( $column_info ) && strpos( $column_info[0]->Type, 'int' ) !== false ) {
            $wpdb->query( "ALTER TABLE $table_name MODIFY COLUMN amount decimal(10,2) NOT NULL" );
            $wpdb->query( "ALTER TABLE $table_name MODIFY COLUMN old_balance decimal(10,2) NOT NULL DEFAULT 0" );
            $wpdb->query( "ALTER TABLE $table_name MODIFY COLUMN new_balance decimal(10,2) NOT NULL DEFAULT 0" );
        }
        
        // Tabela de solicitações de saque
        $withdrawal_table = $wpdb->prefix . 'ds_withdrawal_requests';
        $sql2 = "CREATE TABLE IF NOT EXISTS $withdrawal_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            amount decimal(10,2) NOT NULL,
            method varchar(50) NOT NULL,
            notes text,
            status varchar(20) DEFAULT 'pending',
            processed_by int(11),
            processed_at datetime,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";
        
        dbDelta( $sql2 );
        
        // Tabela de comprovantes WISE
        $wise_table = $wpdb->prefix . 'dsbc_wise_receipts';
        $sql3 = "CREATE TABLE IF NOT EXISTS $wise_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            order_id int(11) NOT NULL,
            user_id int(11) NOT NULL,
            file_path varchar(255) NOT NULL,
            file_name varchar(255) NOT NULL,
            notes text,
            status varchar(20) DEFAULT 'pending',
            processed_by int(11),
            processed_at datetime,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";
        
        dbDelta( $sql3 );
    }

    /**
     * Adiciona os gateways de pagamento à lista de gateways do WooCommerce.
     *
     * @param array $gateways Gateways existentes.
     * @return array Gateways com os gateways adicionados.
     */
    public function add_asaas_gateway( $gateways ) {
        $gateways[] = 'DS_Asaas_Gateway';
        $gateways[] = 'DS_Pix_Gateway';
        $gateways[] = 'DS_Wise_Gateway';
        return $gateways;
    }

    /**
     * Carrega os arquivos necessários.
     */
    private function include_files() {
        require_once DSBC_PLUGIN_PATH . 'includes/class-ds-notification-i18n.php';
        require_once DSBC_PLUGIN_PATH . 'includes/class-ds-credit-manager.php';
        require_once DSBC_PLUGIN_PATH . 'includes/class-ds-credit-converter.php';
        require_once DSBC_PLUGIN_PATH . 'includes/class-ds-exchange-rate-manager.php';
        require_once DSBC_PLUGIN_PATH . 'includes/class-ds-migration-usd.php';
        require_once DSBC_PLUGIN_PATH . 'includes/class-ds-brl-gateway-helper.php';
        require_once DSBC_PLUGIN_PATH . 'includes/class-ds-asaas-gateway.php';
        require_once DSBC_PLUGIN_PATH . 'includes/class-ds-asaas-api-client.php';
        require_once DSBC_PLUGIN_PATH . 'includes/class-ds-pix-gateway.php';
        require_once DSBC_PLUGIN_PATH . 'includes/class-ds-wise-gateway.php';
        require_once DSBC_PLUGIN_PATH . 'includes/class-ds-currency-manager.php';
        require_once DSBC_PLUGIN_PATH . 'includes/class-ds-gateway-fees.php';
        require_once DSBC_PLUGIN_PATH . 'includes/class-ds-payment-manager.php';
        require_once DSBC_PLUGIN_PATH . 'includes/class-ds-webhook-handler.php';
        require_once DSBC_PLUGIN_PATH . 'includes/class-ds-admin-settings.php';
        require_once DSBC_PLUGIN_PATH . 'includes/class-ds-withdrawal-handler.php';
        require_once DSBC_PLUGIN_PATH . 'includes/class-ds-simple-withdrawals.php';
        require_once DSBC_PLUGIN_PATH . 'includes/class-ds-checkout-manager.php';
        require_once DSBC_PLUGIN_PATH . 'includes/class-ds-frontend-shortcodes.php';
        require_once DSBC_PLUGIN_PATH . 'includes/class-ds-pix-receipt-handler.php';
        require_once DSBC_PLUGIN_PATH . 'includes/admin/class-ds-admin-base.php';
        require_once DSBC_PLUGIN_PATH . 'includes/admin/class-ds-admin-products.php';
        require_once DSBC_PLUGIN_PATH . 'includes/admin/class-ds-admin-wise-receipts.php';
        require_once DSBC_PLUGIN_PATH . 'includes/admin/class-ds-admin-pix-receipts.php';
        require_once DSBC_PLUGIN_PATH . 'includes/admin/class-ds-admin-migration.php';
        require_once DSBC_PLUGIN_PATH . 'includes/admin/class-ds-admin-notices.php';
        require_once DSBC_PLUGIN_PATH . 'includes/admin/class-ds-admin-settings-usd.php';
        require_once DSBC_PLUGIN_PATH . 'includes/admin/class-ds-admin-user-management.php';
        require_once DSBC_PLUGIN_PATH . 'includes/admin/class-ds-admin-exchange-rates.php';
        
        // Elementor Widgets - carrega sempre e verifica internamente
        require_once DSBC_PLUGIN_PATH . 'includes/class-ds-elementor-widgets.php';
        
        // Arquivo de teste (remover em produção)
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( DSBC_PLUGIN_PATH . 'teste-shortcodes.php' ) ) {
            require_once DSBC_PLUGIN_PATH . 'teste-shortcodes.php';
        }
        
        // Debug de notificações (remover em produção)
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( DSBC_PLUGIN_PATH . 'debug-notifications.php' ) ) {
            // require_once DSBC_PLUGIN_PATH . 'debug-notifications.php';
        }
        
        // Teste de notificações (remover em produção)
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( DSBC_PLUGIN_PATH . 'test-notifications.php' ) ) {
            // require_once DSBC_PLUGIN_PATH . 'test-notifications.php';
        }
        
        // Debug Elementor (temporário)
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( DSBC_PLUGIN_PATH . 'debug-elementor.php' ) ) {
            require_once DSBC_PLUGIN_PATH . 'debug-elementor.php';
        }
        
        // Limpeza de templates (temporário)
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( DSBC_PLUGIN_PATH . 'clean-templates.php' ) ) {
            require_once DSBC_PLUGIN_PATH . 'clean-templates.php';
        }
    }

    /**
     * Instancia as classes principais.
     */
    private function init_classes() {
        new DS_Credit_Manager();
        new DS_Currency_Manager();
        new DS_Gateway_Fees();
        new DS_Payment_Manager();
        new DS_Webhook_Handler();
        new DS_Withdrawal_Handler();
        new DS_Simple_Withdrawals();
        new DS_Checkout_Manager();
        new DS_Frontend_Shortcodes();
        new DS_Pix_Receipt_Handler();
        
        if ( is_admin() ) {
            // Classe principal de admin (prioridade 10)
            DS_Backgamom_Credits_Admin::instance();
            
            // Classes administrativas adicionais
            new DS_Admin_Pix_Receipts();
            new DS_Admin_Wise_Receipts();
            
            // Classes USD (prioridades 20, 30, 40)
            new DS_Admin_Migration();
            new DS_Admin_Settings_USD();
            new DS_Admin_User_Management();
            new DS_Admin_Notices();
        }
        
        // Inicializar widgets do Elementor
        if ( class_exists( 'DS_Elementor_Widgets' ) ) {
            new DS_Elementor_Widgets();
        }
    }

    /**
     * Mostra um aviso no admin se o WooCommerce não estiver ativo.
     */
    public function missing_woocommerce_notice() {
        echo '<div class="notice notice-error"><p>';
        esc_html_e('O plugin DS Backgamom Credits requer que o WooCommerce esteja instalado e ativo.', 'ds-backgamom-credits');
        echo '</p></div>';
    }
}

/**
 * Inicializa o plugin.
 */
function ds_backgamom_credits() {
    return DS_Backgamom_Credits::instance();
}

// Inicia o plugin.
ds_backgamom_credits();

/**
 * API Functions for External Plugin Integration
 */

/**
 * Consulta o saldo de créditos de um usuário em USD
 * 
 * @param int $user_id ID do usuário
 * @return float Saldo atual do usuário em USD
 */
function dsbc_get_user_balance( $user_id ) {
    if ( class_exists( 'DS_Credit_Manager' ) ) {
        return DS_Credit_Manager::get_balance( $user_id );
    }
    return 0.0;
}

/**
 * Adiciona créditos a um usuário
 * 
 * @param int $user_id ID do usuário
 * @param float $amount Quantidade de créditos em USD
 * @param string $reason Motivo da adição
 * @return bool True em sucesso, false em falha
 */
function dsbc_add_credits( $user_id, $amount, $reason = '' ) {
    if ( class_exists( 'DS_Credit_Manager' ) ) {
        return DS_Credit_Manager::add_credits_manually( $user_id, $amount, $reason, get_current_user_id() );
    }
    return false;
}

/**
 * Deduz créditos de um usuário
 * 
 * @param int $user_id ID do usuário
 * @param float $amount Quantidade de créditos em USD
 * @param string $reason Motivo da dedução
 * @return bool True em sucesso, false em falha
 */
function dsbc_deduct_credits( $user_id, $amount, $reason = '' ) {
    if ( class_exists( 'DS_Credit_Manager' ) ) {
        return DS_Credit_Manager::deduct_credits( $user_id, $amount, $reason );
    }
    return false;
}

/**
 * Verifica se usuário tem saldo suficiente
 * 
 * @param int $user_id ID do usuário
 * @param float $amount Quantidade necessária em USD
 * @return bool True se tem saldo suficiente
 */
function dsbc_has_sufficient_balance( $user_id, $amount ) {
    if ( class_exists( 'DS_Credit_Manager' ) ) {
        return DS_Credit_Manager::has_sufficient_balance( $user_id, $amount );
    }
    return false;
}

/**
 * Converte valor BRL para créditos USD
 * 
 * @param float $amount_brl Valor em BRL
 * @return float Créditos em USD
 */
function dsbc_convert_brl_to_credits( $amount_brl ) {
    if ( class_exists( 'DS_Credit_Converter' ) ) {
        return DS_Credit_Converter::convert_payment_to_credits( $amount_brl );
    }
    return 0.0;
}

/**
 * Converte créditos USD para valor BRL
 * 
 * @param float $credits Créditos em USD
 * @return float Valor em BRL
 */
function dsbc_convert_credits_to_brl( $credits ) {
    if ( class_exists( 'DS_Credit_Converter' ) ) {
        return DS_Credit_Converter::convert_credits_to_brl( $credits );
    }
    return 0.0;
}

/**
 * Processa saque manual
 * 
 * @param int $user_id ID do usuário
 * @param float $amount Valor do saque em USD
 * @param string $method Método de pagamento
 * @param string $notes Observações
 * @return bool True em sucesso
 */
function dsbc_process_withdrawal( $user_id, $amount, $method, $notes = '' ) {
    if ( class_exists( 'DS_Simple_Withdrawals' ) ) {
        return DS_Simple_Withdrawals::approve_withdrawal( $user_id, $amount, $method, $notes );
    }
    return false;
}

/**
 * Hook para outros plugins se integrarem
 */
do_action( 'dsbc_plugin_loaded' );
