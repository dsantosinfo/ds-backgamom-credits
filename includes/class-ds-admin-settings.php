<?php
/**
 * Classe principal de administração - Sistema USD
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DS_Backgamom_Credits_Admin {

    private static $instance = null;
    private $option_name = 'ds_backgamom_credits_settings';
    private $dashboard;
    private $ajax_handler;
    private $reports;
    private $lookup;
    private $history;
    private $withdrawals;
    private $templates;
    private $products;
    private $gateway_fees;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_components();
        $this->init_hooks();
    }

    private function load_dependencies() {
        require_once DSBC_PLUGIN_PATH . 'includes/admin/class-ds-admin-base.php';
        require_once DSBC_PLUGIN_PATH . 'includes/admin/class-ds-admin-dashboard.php';
        require_once DSBC_PLUGIN_PATH . 'includes/admin/class-ds-admin-ajax.php';
        require_once DSBC_PLUGIN_PATH . 'includes/admin/class-ds-admin-reports.php';
        require_once DSBC_PLUGIN_PATH . 'includes/admin/class-ds-admin-lookup.php';
        require_once DSBC_PLUGIN_PATH . 'includes/admin/class-ds-admin-history.php';
        require_once DSBC_PLUGIN_PATH . 'includes/admin/class-ds-admin-withdrawals.php';
        require_once DSBC_PLUGIN_PATH . 'includes/admin/class-ds-admin-templates.php';
        require_once DSBC_PLUGIN_PATH . 'includes/admin/class-ds-admin-products.php';
        require_once DSBC_PLUGIN_PATH . 'includes/admin/class-ds-admin-gateway-fees.php';
    }

    private function init_components() {
        $this->dashboard = new DS_Admin_Dashboard();
        $this->ajax_handler = new DS_Admin_Ajax();
        $this->reports = new DS_Admin_Reports();
        $this->lookup = new DS_Admin_Lookup();
        $this->history = new DS_Admin_History();
        $this->withdrawals = new DS_Admin_Withdrawals();
        $this->templates = new DS_Admin_Templates();
        $this->products = new DS_Admin_Products();
        $this->gateway_fees = new DS_Admin_Gateway_Fees();
    }

    private function init_hooks() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ], 10 );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this->dashboard, 'enqueue_admin_assets' ] );
    }

    public function add_admin_menu() {
        // Menu principal
        add_menu_page(
            'DS Backgamom Credits',
            'Créditos USD',
            'manage_options',
            'ds-backgamom-credits',
            [ $this, 'admin_page' ],
            'dashicons-money-alt',
            25
        );

        // Submenus organizados
        add_submenu_page(
            'ds-backgamom-credits',
            'Dashboard USD',
            'Dashboard',
            'manage_options',
            'ds-backgamom-credits',
            [ $this, 'admin_page' ]
        );

        add_submenu_page(
            'ds-backgamom-credits',
            'Consultar Usuários',
            'Consultar Usuários',
            'edit_shop_orders',
            'ds-credits-lookup',
            [ $this, 'lookup_page' ]
        );
        
        add_submenu_page(
            'ds-backgamom-credits',
            'Histórico de Transações',
            'Histórico',
            'manage_options',
            'ds-credits-history',
            [ $this, 'history_page' ]
        );
        
        add_submenu_page(
            'ds-backgamom-credits',
            'Relatórios',
            'Relatórios',
            'manage_options',
            'ds-credits-reports',
            [ $this, 'reports_page' ]
        );
        
        add_submenu_page(
            'ds-backgamom-credits',
            'Saques',
            'Saques',
            'manage_options',
            'ds-credits-withdrawals',
            [ $this, 'simple_withdrawals_page' ]
        );
        
        add_submenu_page(
            'ds-backgamom-credits',
            'Produtos de Crédito',
            'Produtos',
            'manage_options',
            'ds-credits-products',
            [ $this, 'products_page' ]
        );
        
        add_submenu_page(
            'ds-backgamom-credits',
            'Templates WhatsApp',
            'Templates',
            'manage_options',
            'ds-credits-templates',
            [ $this, 'templates_page' ]
        );
        
        add_submenu_page(
            'ds-backgamom-credits',
            'Taxas dos Gateways',
            'Taxas dos Gateways',
            'manage_options',
            'ds-gateway-fees',
            [ $this, 'gateway_fees_page' ]
        );
        
        add_submenu_page(
            'ds-backgamom-credits',
            'Configurações USD',
            'Configurações USD',
            'manage_options',
            'ds-settings-usd',
            [ $this, 'settings_usd_page' ]
        );
        
        add_submenu_page(
            'ds-backgamom-credits',
            'Taxa de Câmbio',
            'Taxa de Câmbio',
            'manage_options',
            'ds-exchange-rates',
            [ $this, 'exchange_rates_page' ]
        );
    }

    
    public function gateway_fees_page() {
        if ( class_exists( 'DS_Admin_Gateway_Fees' ) ) {
            $gateway_fees = new DS_Admin_Gateway_Fees();
            $gateway_fees->render_page();
        } else {
            echo '<div class="wrap"><h1>Taxas dos Gateways não disponíveis</h1></div>';
        }
    }
    
    public function settings_usd_page() {
        if ( class_exists( 'DS_Admin_Settings_USD' ) ) {
            $settings_usd = new DS_Admin_Settings_USD();
            $settings_usd->render_page();
        } else {
            echo '<div class="wrap"><h1>Configurações USD não disponíveis</h1></div>';
        }
    }
    
    public function exchange_rates_page() {
        if ( class_exists( 'DS_Admin_Exchange_Rates' ) ) {
            $exchange_rates = new DS_Admin_Exchange_Rates();
            $exchange_rates->render_page();
        } else {
            echo '<div class="wrap"><h1>Taxa de Câmbio não disponível</h1></div>';
        }
    }

    public function register_settings() {
        register_setting( 'ds_backgamom_credits_group', $this->option_name );
        register_setting( 'ds_backgamom_credits_group', 'dsbc_asaas_webhook_token' );

        add_settings_section(
            'general_section',
            'Configurações Gerais - Sistema USD',
            null,
            'ds-backgamom-credits'
        );

        add_settings_field(
            'min_withdrawal',
            'Saque Mínimo (Créditos USD)',
            [ $this, 'min_withdrawal_field' ],
            'ds-backgamom-credits',
            'general_section'
        );

        add_settings_field(
            'negative_balance_limit',
            'Limite de Saldo Negativo (USD)',
            [ $this, 'negative_balance_limit_field' ],
            'ds-backgamom-credits',
            'general_section'
        );

        add_settings_field(
            'allow_negative_balance',
            'Permitir Saldos Negativos',
            [ $this, 'allow_negative_balance_field' ],
            'ds-backgamom-credits',
            'general_section'
        );

        add_settings_field(
            'auto_complete_orders',
            'Completar Pedidos Automaticamente',
            [ $this, 'auto_complete_field' ],
            'ds-backgamom-credits',
            'general_section'
        );

        add_settings_field(
            'enable_notifications',
            'Notificações WhatsApp',
            [ $this, 'notifications_field' ],
            'ds-backgamom-credits',
            'general_section'
        );

        add_settings_field(
            'dsbc_asaas_webhook_token',
            'Token de Segurança do Webhook',
            [ $this, 'webhook_token_field' ],
            'ds-backgamom-credits',
            'webhook_section'
        );
        
        add_settings_field(
            'withdrawal_form_id',
            'Formulário de Saque (Gravity Forms)',
            [ $this, 'withdrawal_form_field' ],
            'ds-backgamom-credits',
            'general_section'
        );
    }

    public function admin_page() {
        $active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'dashboard';
        ?>
        <div class="wrap">
            <h1>🚀 DS Backgamom Credits - Sistema USD</h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=ds-backgamom-credits&tab=dashboard" class="nav-tab <?php echo $active_tab == 'dashboard' ? 'nav-tab-active' : ''; ?>">Dashboard</a>
                <a href="?page=ds-backgamom-credits&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">Configurações</a>
                <a href="?page=ds-backgamom-credits&tab=webhook" class="nav-tab <?php echo $active_tab == 'webhook' ? 'nav-tab-active' : ''; ?>">Webhook</a>
            </nav>

            <?php
            switch ( $active_tab ) {
                case 'settings':
                    $this->render_settings_tab();
                    break;
                case 'webhook':
                    $this->render_webhook_tab();
                    break;
                default:
                    $this->dashboard->render_dashboard_tab();
            }
            ?>
        </div>
        <?php
    }

    private function render_settings_tab() {
        ?>
        <div class="notice notice-info">
            <p><strong>Sistema USD:</strong> Todos os créditos são baseados em dólares americanos (1 crédito = US$ 1,00). A conversão para BRL é automática nos gateways brasileiros.</p>
        </div>
        
        <form method="post" action="options.php">
            <?php settings_fields( 'ds_backgamom_credits_group' ); ?>
            
            <div class="postbox">
                <h2 class="hndle">💰 Configurações do Sistema USD</h2>
                <div class="inside">
                    <table class="form-table">
                        <tr>
                            <th scope="row">Taxa de Câmbio Atual</th>
                            <td>
                                <?php 
                                $exchange_rate = class_exists( 'DS_Credit_Converter' ) ? DS_Credit_Converter::get_exchange_rate() : 5.67;
                                ?>
                                <div style="background: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; margin-bottom: 15px;">
                                    <strong>US$ 1,00 = R$ <?php echo number_format( $exchange_rate, 2, ',', '.' ); ?></strong>
                                    <br><small>Configure a taxa em <a href="<?php echo admin_url( 'admin.php?page=ds-settings-usd' ); ?>">Configurações USD</a></small>
                                    <br><small>Gerencie usuários em <a href="<?php echo admin_url( 'admin.php?page=ds-user-management' ); ?>">Gestão de Usuários</a></small>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Saque Mínimo (Créditos USD)</th>
                            <td><?php $this->min_withdrawal_field(); ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Permitir Saldos Negativos</th>
                            <td><?php $this->allow_negative_balance_field(); ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Limite de Saldo Negativo (USD)</th>
                            <td><?php $this->negative_balance_limit_field(); ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Completar Pedidos Automaticamente</th>
                            <td><?php $this->auto_complete_field(); ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Notificações WhatsApp</th>
                            <td><?php $this->notifications_field(); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="postbox">
                <h2 class="hndle">📝 Formulário de Saque</h2>
                <div class="inside">
                    <table class="form-table">
                        <tr>
                            <th scope="row">Formulário Gravity Forms</th>
                            <td><?php $this->withdrawal_form_field(); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="postbox">
                <h2 class="hndle">ℹ️ Como Funciona o Sistema USD</h2>
                <div class="inside">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <h4>🎯 Conceito</h4>
                            <ul>
                                <li>• 1 crédito = US$ 1,00 sempre</li>
                                <li>• Saldo unificado em USD</li>
                                <li>• Conversão automática BRL</li>
                                <li>• Compatível globalmente</li>
                            </ul>
                        </div>
                        <div>
                            <h4>💳 Pagamentos</h4>
                            <ul>
                                <li>• Brasileiros: Pagam em BRL</li>
                                <li>• Internacionais: Pagam em USD</li>
                                <li>• Recebem créditos USD</li>
                                <li>• Conversão transparente</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div style="background: #f0fff0; border-left: 4px solid #46b450; padding: 15px; margin-top: 15px;">
                        <h4 style="margin-top: 0;">📊 Exemplo Prático</h4>
                        <p><strong>Produto:</strong> 10 créditos</p>
                        <p><strong>Cliente Brasileiro:</strong> Paga R$ <?php echo number_format( 10 * $exchange_rate, 2, ',', '.' ); ?> → Recebe 10 créditos USD</p>
                        <p><strong>Cliente Internacional:</strong> Paga US$ 10,00 → Recebe 10 créditos USD</p>
                    </div>
                </div>
            </div>

            <?php submit_button( 'Salvar Configurações' ); ?>
        </form>
        <?php
    }

    private function render_webhook_tab() {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'ds_backgamom_credits_group' ); ?>
            
            <div class="postbox">
                <h2 class="hndle">🔗 Configuração do Webhook Asaas</h2>
                <div class="inside">
                    <?php $this->webhook_section_callback(); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Token de Segurança</th>
                            <td><?php $this->webhook_token_field(); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <?php submit_button( 'Salvar Token' ); ?>
        </form>
        <?php
    }

    public function min_withdrawal_field() {
        $settings = get_option( $this->option_name, [] );
        $value = $settings['min_withdrawal'] ?? '10';
        echo '<input type="number" name="' . $this->option_name . '[min_withdrawal]" value="' . esc_attr( $value ) . '" step="0.01" min="0.01" />';
        echo '<p class="description">Quantidade mínima de créditos USD para solicitar saque.</p>';
        
        if ( $value > 0 ) {
            $exchange_rate = class_exists( 'DS_Credit_Converter' ) ? DS_Credit_Converter::get_exchange_rate() : 5.67;
            $value_brl = $value * $exchange_rate;
            echo '<small style="color: #666;">Equivalente: R$ ' . number_format( $value_brl, 2, ',', '.' ) . '</small>';
        }
    }

    public function auto_complete_field() {
        $settings = get_option( $this->option_name, [] );
        $checked = isset( $settings['auto_complete'] ) ? checked( $settings['auto_complete'], 1, false ) : 'checked';
        echo '<input type="checkbox" name="' . $this->option_name . '[auto_complete]" value="1" ' . $checked . ' />';
        echo '<label> Marcar pedidos como concluídos automaticamente quando contêm apenas produtos de créditos</label>';
    }

    public function notifications_field() {
        $settings = get_option( $this->option_name, [] );
        $checked = isset( $settings['notifications'] ) ? checked( $settings['notifications'], 1, false ) : 'checked';
        echo '<input type="checkbox" name="' . $this->option_name . '[notifications]" value="1" ' . $checked . ' />';
        echo '<label> Enviar notificações via WhatsApp quando créditos forem adicionados</label>';
    }
    
    public function allow_negative_balance_field() {
        $settings = get_option( $this->option_name, [] );
        $checked = isset( $settings['allow_negative_balance'] ) ? checked( $settings['allow_negative_balance'], 1, false ) : '';
        echo '<input type="checkbox" name="' . $this->option_name . '[allow_negative_balance]" value="1" ' . $checked . ' />';
        echo '<label> Permitir que usuários tenham saldo negativo</label>';
        echo '<p class="description">Quando ativo, usuários podem ter saldo negativo até o limite definido abaixo.</p>';
    }
    
    public function negative_balance_limit_field() {
        $settings = get_option( $this->option_name, [] );
        $value = $settings['negative_balance_limit'] ?? '100';
        echo '<input type="number" name="' . $this->option_name . '[negative_balance_limit]" value="' . esc_attr( $value ) . '" step="0.01" min="0" />';
        echo '<p class="description">Valor máximo que o saldo pode ser negativado (ex: 100 = até -$100.00).</p>';
        
        if ( $value > 0 ) {
            $exchange_rate = class_exists( 'DS_Credit_Converter' ) ? DS_Credit_Converter::get_exchange_rate() : 5.67;
            $value_brl = $value * $exchange_rate;
            echo '<small style="color: #666;">Equivalente: até -R$ ' . number_format( $value_brl, 2, ',', '.' ) . '</small>';
        }
    }
    
    public function withdrawal_form_field() {
        $settings = get_option( $this->option_name, [] );
        $selected_form = $settings['withdrawal_form_id'] ?? '';
        
        if ( class_exists( 'GFAPI' ) ) {
            $forms = \GFAPI::get_forms();
            echo '<select name="' . $this->option_name . '[withdrawal_form_id]" id="withdrawal_form_select">';
            echo '<option value="">Selecione um formulário...</option>';
            foreach ( $forms as $form ) {
                $selected = selected( $selected_form, $form['id'], false );
                echo '<option value="' . $form['id'] . '" ' . $selected . '>' . esc_html( $form['title'] ) . ' (ID: ' . $form['id'] . ')</option>';
            }
            echo '</select>';
            echo '<p class="description">Selecione o formulário Gravity Forms usado para solicitações de saque.</p>';
        } else {
            echo '<p style="color: red;">Gravity Forms não está ativo. Instale e ative o plugin para configurar formulários de saque.</p>';
        }
    }

    public function webhook_section_callback() {
        $webhook_url = home_url( '/wp-json/ds-backgamom-credits/v1/asaas-webhook' );
        ?>
        <div class="notice notice-info inline">
            <p><strong>O que é o Webhook?</strong> O webhook permite que o Asaas notifique automaticamente seu site quando um pagamento é processado, garantindo que os créditos sejam adicionados imediatamente.</p>
        </div>
        
        <h4>1. URL do Webhook</h4>
        <p>Copie esta URL e configure no painel do Asaas:</p>
        <div style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; border-radius: 4px; margin: 10px 0;">
            <code style="font-size: 14px; word-break: break-all;"><?php echo esc_url( $webhook_url ); ?></code>
            <button type="button" class="button button-small" onclick="navigator.clipboard.writeText('<?php echo esc_js( $webhook_url ); ?>')" style="margin-left: 10px;">Copiar</button>
        </div>
        
        <h4>2. Configuração no Asaas</h4>
        <ol>
            <li>Acesse o painel do Asaas</li>
            <li>Vá em <strong>Configurações > Webhooks</strong></li>
            <li>Adicione a URL acima</li>
            <li>No campo <strong>"Access Token"</strong>, use o token gerado abaixo</li>
            <li>Selecione os eventos: PAYMENT_CONFIRMED, PAYMENT_RECEIVED, PAYMENT_OVERDUE, PAYMENT_REFUNDED</li>
        </ol>
        
        <div class="notice notice-warning inline">
            <p><strong>Segurança:</strong> O token abaixo protege seu webhook contra acessos não autorizados. Mantenha-o seguro!</p>
        </div>
        <?php
    }

    public function webhook_token_field() {
        $token = get_option( 'dsbc_asaas_webhook_token', '' );
        if ( empty( $token ) ) {
            $token = wp_generate_password( 32, false );
        }
        echo '<input type="text" name="dsbc_asaas_webhook_token" value="' . esc_attr( $token ) . '" style="width: 400px;" readonly />';
        echo '<button type="button" class="button" onclick="generateNewToken()">Gerar Novo Token</button>';
        echo '<p class="description">Token usado para validar requisições do webhook. Copie este valor para o campo "Access Token" no painel do Asaas.</p>';
        echo '<script>
        function generateNewToken() {
            if (confirm("Gerar novo token? O token atual será invalidado.")) {
                var newToken = "";
                var chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
                for (var i = 0; i < 32; i++) {
                    newToken += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                document.querySelector("input[name=dsbc_asaas_webhook_token]").value = newToken;
            }
        }
        </script>';
    }

    public function reports_page() {
        $this->reports->reports_page();
    }

    public function lookup_page() {
        $this->lookup->lookup_page();
    }

    public function history_page() {
        $this->history->history_page();
    }

    public function simple_withdrawals_page() {
        $this->withdrawals->simple_withdrawals_page();
    }
    
    public function templates_page() {
        $this->templates->templates_page();
    }
    
    public function products_page() {
        $this->products->render_page();
    }
}