<?php
/**
 * Gestor de Créditos
 *
 * @package DS_Backgamom_Credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class DS_Credit_Manager {

    /**
     * Construtor.
     */
    public function __construct() {
        // Adiciona o campo de créditos na página de edição de produtos
        add_action( 'woocommerce_product_options_general_product_data', [ $this, 'add_credits_field_to_product' ] );
        add_action( 'woocommerce_process_product_meta', [ $this, 'save_credits_field' ] );

        // Exibe o saldo na página Minha Conta
        add_action( 'woocommerce_account_dashboard', [ $this, 'display_balance_on_dashboard' ] );

        // Registra o shortcode para exibir o saldo
        add_shortcode( 'ds_credit_balance', [ $this, 'credit_balance_shortcode' ] );
        add_shortcode( 'ds_credit_dashboard', [ $this, 'credit_dashboard_shortcode' ] );
        
        // Hook universal para qualquer gateway
        add_action( 'woocommerce_order_status_completed', [ $this, 'award_credits_on_completion' ] );
        add_action( 'woocommerce_payment_complete', [ $this, 'award_credits_on_payment' ] );
    }

    /**
     * Adiciona o campo de créditos na aba 'Geral' do produto.
     */
    public function add_credits_field_to_product() {
        echo '<div class="options_group">';

        woocommerce_wp_text_input(
            [
                'id'                => '_dsbc_credits_amount',
                'label'             => __( 'Créditos (USD)', 'ds-backgamom-credits' ),
                'placeholder'       => '10.00',
                'description'       => __( 'Quantidade de créditos que este produto gerará. 1 crédito = US$ 1,00', 'ds-backgamom-credits' ),
                'type'              => 'number',
                'custom_attributes' => [
                    'step' => '0.01',
                    'min'  => '0',
                ],
            ]
        );
        
        // Mostrar cálculo de preço BRL
        global $post;
        if ( $post && $post->ID ) {
            $credits = get_post_meta( $post->ID, '_dsbc_credits_amount', true );
            if ( $credits && $credits > 0 ) {
                $price_brl = DS_Credit_Converter::convert_credits_to_brl( $credits );
                $exchange_rate = DS_Credit_Converter::get_exchange_rate();
                
                echo '<p class="form-field" style="background: #e7f3ff; border-left: 4px solid #2196F3; padding: 10px; margin: 10px 12px;">';
                echo '<strong>' . __( 'Preço Calculado:', 'ds-backgamom-credits' ) . '</strong><br>';
                echo "• {$credits} créditos = US$ " . number_format( $credits, 2, '.', ',' ) . '<br>';
                echo "• Preço BRL: R$ " . number_format( $price_brl, 2, ',', '.' ) . '<br>';
                echo '<small style="color: #666;">Taxa: US$ 1,00 = R$ ' . number_format($exchange_rate, 2, ',', '.') . '</small>';
                echo '</p>';
            }
        }

        echo '</div>';
    }

    /**
     * Salva o valor do campo de créditos.
     *
     * @param int $post_id ID do produto.
     */
    public function save_credits_field( $post_id ) {
        $credits_amount = isset( $_POST['_dsbc_credits_amount'] ) ? wc_clean( wp_unslash( $_POST['_dsbc_credits_amount'] ) ) : '';
        if ( is_numeric( $credits_amount ) ) {
            update_post_meta( $post_id, '_dsbc_credits_amount', $credits_amount );
        } else {
            delete_post_meta( $post_id, '_dsbc_credits_amount' );
        }
    }

    /**
     * Exibe o saldo de créditos no dashboard da Minha Conta.
     */
    public function display_balance_on_dashboard() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return;
        }
        $balance = self::get_balance( $user_id );
        echo '<p>' . sprintf( __( 'Seu saldo: %s', 'ds-backgamom-credits' ), '<strong>$ ' . number_format( $balance, 2 ) . '</strong>' ) . '</p>';
    }

    /**
     * Gera o conteúdo para o shortcode [ds_credit_balance].
     */
    public function credit_balance_shortcode() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return __( 'Usuário não logado.', 'ds-backgamom-credits' );
        }
        $balance = self::get_balance( $user_id );
        $class = $balance < 0 ? 'negative-balance' : 'positive-balance';
        return '<span class="' . $class . '">$ ' . number_format( $balance, 2 ) . '</span>';
    }
    
    /**
     * Dashboard completo de créditos para usuários.
     */
    public function credit_dashboard_shortcode() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return '<p>' . esc_html__('Você precisa estar logado para ver seus créditos.', 'ds-backgamom-credits') . '</p>';
        }
        
        $balance = self::get_balance( $user_id );
        
        ob_start();
        ?>
        <div class="ds-credit-dashboard">
            <div class="credit-summary">
                <h3><?php esc_html_e('Meu Saldo', 'ds-backgamom-credits'); ?></h3>
                <div class="balance-display <?php echo $balance < 0 ? 'negative' : 'positive'; ?>">
                    <span class="balance-amount">$ <?php echo number_format( $balance, 2 ); ?></span>
                    <span class="balance-label"><?php echo $balance < 0 ? esc_html__('em débito', 'ds-backgamom-credits') : esc_html__('disponíveis', 'ds-backgamom-credits'); ?></span>
                </div>
                <?php if ( $balance < 0 ) : ?>
                    <div class="negative-warning">
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e('Seu saldo está negativo. Adicione créditos para regularizar.', 'ds-backgamom-credits'); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="credit-actions">
                <a href="<?php echo wc_get_page_permalink( 'shop' ); ?>" class="button"><?php esc_html_e('Comprar Créditos', 'ds-backgamom-credits'); ?></a>
            </div>
        </div>
        
        <style>
        .ds-credit-dashboard { max-width: 600px; margin: 20px 0; }
        .credit-summary { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 20px; }
        .balance-display.positive .balance-amount { font-size: 2.5em; font-weight: bold; color: #28a745; display: block; }
        .balance-display.negative .balance-amount { font-size: 2.5em; font-weight: bold; color: #dc3545; display: block; }
        .balance-label { color: #6c757d; }
        .negative-warning { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-top: 15px; }
        .negative-warning .dashicons { vertical-align: middle; margin-right: 5px; }
        .credit-actions { text-align: center; }
        .credit-actions .button { margin: 0 10px; }
        .negative-balance { color: #dc3545; font-weight: bold; }
        .positive-balance { color: #28a745; }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Obtém o saldo de um usuário em créditos USD.
     *
     * @param int $user_id ID do usuário.
     * @return float Saldo do usuário em USD.
     */
    public static function get_balance( $user_id ) {
        $balance = get_user_meta( $user_id, '_dsbc_credit_balance', true );
        return $balance ? floatval( $balance ) : 0.0;
    }

    /**
     * Adiciona créditos ao saldo de um usuário.
     *
     * @param int $user_id ID do usuário.
     * @param float $amount Quantidade a ser adicionada em USD.
     * @param int|null $order_id ID do pedido que originou os créditos.
     * @return bool True em sucesso, false em falha.
     */
    public static function add_credits( $user_id, $amount, $order_id = null ) {
        if ( ! $user_id || ! is_numeric( $amount ) || $amount <= 0 ) {
            return false;
        }

        $current_balance = self::get_balance( $user_id );
        $new_balance = $current_balance + floatval( $amount );

        $result = update_user_meta( $user_id, '_dsbc_credit_balance', $new_balance );

        // Adiciona uma nota ao pedido do WooCommerce, se aplicável
        if ( $order_id && $result ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $note = sprintf( __( '%.2f créditos (US$ %.2f) adicionados ao usuário. Novo saldo: %.2f créditos.', 'ds-backgamom-credits' ), $amount, $amount, $new_balance );
                $order->add_order_note( $note );
            }
        }
        
        // Registrar no log se créditos foram adicionados com sucesso
        if ( $result ) {
            self::log_deposit( $user_id, $amount, $order_id, $current_balance, $new_balance );
            self::send_deposit_notification( $user_id, $amount, $new_balance );
        }

        return $result !== false;
    }
    
    /**
     * Adiciona créditos manualmente (por administrador).
     *
     * @param int $user_id ID do usuário.
     * @param float $amount Quantidade a ser adicionada.
     * @param string $observation Observação do administrador.
     * @param int $admin_id ID do administrador que fez a adição.
     * @return bool True em sucesso, false em falha.
     */
    public static function add_credits_manually( $user_id, $amount, $observation, $admin_id ) {
        if ( ! $user_id || ! is_numeric( $amount ) || $amount <= 0 || ! $observation || ! $admin_id ) {
            return false;
        }

        $current_balance = self::get_balance( $user_id );
        $new_balance = $current_balance + floatval( $amount );

        $result = update_user_meta( $user_id, '_dsbc_credit_balance', $new_balance );

        if ( $result ) {
            // Registra o log da adição manual
            self::log_manual_credit_addition( $user_id, $amount, $observation, $admin_id, $current_balance, $new_balance );
            
            // Enviar notificação WhatsApp
            self::send_deposit_notification( $user_id, $amount, $new_balance );
        }

        return $result !== false;
    }
    
    /**
     * Registra log de depósito via WooCommerce.
     */
    private static function log_deposit( $user_id, $amount, $order_id, $old_balance, $new_balance ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dsbc_credit_logs';
        
        // Cria a tabela se não existir
        self::create_credit_logs_table();
        
        $observation = $order_id ? "Depósito via pedido #{$order_id}" : 'Depósito via WooCommerce';
        
        $result = $wpdb->insert(
            $table_name,
            [
                'user_id' => $user_id,
                'amount' => floatval( $amount ),
                'type' => 'deposit',
                'observation' => $observation,
                'admin_id' => null,
                'admin_name' => 'Sistema',
                'old_balance' => floatval( $old_balance ),
                'new_balance' => floatval( $new_balance ),
                'created_at' => current_time( 'mysql' )
            ],
            [ '%d', '%f', '%s', '%s', '%s', '%s', '%f', '%f', '%s' ]
        );
        

    }
    
    /**
     * Registra log de adição manual de créditos.
     */
    private static function log_manual_credit_addition( $user_id, $amount, $observation, $admin_id, $old_balance, $new_balance ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dsbc_credit_logs';
        
        // Cria a tabela se não existir
        self::create_credit_logs_table();
        
        $admin_user = get_userdata( $admin_id );
        $admin_name = $admin_user ? $admin_user->display_name : 'Admin';
        
        $wpdb->insert(
            $table_name,
            [
                'user_id' => $user_id,
                'amount' => floatval( $amount ),
                'type' => 'manual_addition',
                'observation' => $observation,
                'admin_id' => $admin_id,
                'admin_name' => $admin_name,
                'old_balance' => floatval( $old_balance ),
                'new_balance' => floatval( $new_balance ),
                'created_at' => current_time( 'mysql' )
            ],
            [ '%d', '%f', '%s', '%s', '%d', '%s', '%f', '%f', '%s' ]
        );
    }
    
    /**
     * Cria a tabela de logs de créditos.
     */
    private static function create_credit_logs_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dsbc_credit_logs';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            amount decimal(10,2) NOT NULL,
            type varchar(50) NOT NULL,
            observation text,
            admin_id int(11),
            admin_name varchar(255),
            old_balance decimal(10,2) NOT NULL DEFAULT 0,
            new_balance decimal(10,2) NOT NULL DEFAULT 0,
            payment_due_date date DEFAULT NULL,
            payment_status varchar(20) DEFAULT NULL,
            payment_method varchar(50) DEFAULT NULL,
            payment_receipt varchar(500) DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY admin_id (admin_id),
            KEY created_at (created_at),
            KEY payment_status (payment_status)
        ) $charset_collate;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    /**
     * Deduz créditos do saldo de um usuário.
     *
     * @param int $user_id ID do usuário.
     * @param float $amount Quantidade a ser deduzida.
     * @param string $reason Motivo da dedução.
     * @return bool True em sucesso, false em falha.
     */
    public static function deduct_credits( $user_id, $amount, $reason = '' ) {
        if ( ! $user_id || ! is_numeric( $amount ) || $amount <= 0 ) {
            return false;
        }

        $current_balance = self::get_balance( $user_id );
        $settings = get_option( 'ds_backgamom_credits_settings', [] );
        $allow_negative = ! empty( $settings['allow_negative_balance'] );
        $negative_limit = floatval( $settings['negative_balance_limit'] ?? 0 );
        
        // Verifica se há saldo suficiente ou se saldos negativos são permitidos
        if ( ! $allow_negative && $current_balance < $amount ) {
            return false;
        }
        
        // Se saldos negativos são permitidos, verifica o limite
        if ( $allow_negative && $negative_limit > 0 ) {
            $new_balance = $current_balance - floatval( $amount );
            if ( $new_balance < -$negative_limit ) {
                return false; // Excederia o limite negativo
            }
        }

        $new_balance = $current_balance - floatval( $amount );
        $result = update_user_meta( $user_id, '_dsbc_credit_balance', $new_balance );
        
        if ( $result ) {
            self::log_withdrawal( $user_id, $amount, $reason, $current_balance, $new_balance );
            self::send_withdrawal_notification( $user_id, $amount, $reason );
        }
        
        return $result !== false;
    }
    
    /**
     * Registra log de saque de créditos.
     */
    private static function log_withdrawal( $user_id, $amount, $reason, $old_balance, $new_balance ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dsbc_credit_logs';
        
        // Cria a tabela se não existir
        self::create_credit_logs_table();
        
        $wpdb->insert(
            $table_name,
            [
                'user_id' => $user_id,
                'amount' => -floatval( $amount ), // Negativo para saque
                'type' => 'withdrawal',
                'observation' => $reason,
                'admin_id' => get_current_user_id(),
                'admin_name' => wp_get_current_user()->display_name,
                'old_balance' => floatval( $old_balance ),
                'new_balance' => floatval( $new_balance ),
                'created_at' => current_time( 'mysql' )
            ],
            [ '%d', '%f', '%s', '%s', '%d', '%s', '%f', '%f', '%s' ]
        );
    }
    
    /**
     * Envia notificação de saque via sistema I18N.
     */
    private static function send_withdrawal_notification( $user_id, $amount, $reason ) {
        $user_data = get_userdata( $user_id );
        if ( ! $user_data ) return;
        
        $user_name = $user_data->first_name ?: $user_data->display_name;
        $user_country = get_user_meta( $user_id, 'billing_country', true );
        
        $vars = [
            'name' => $user_name,
            'amount' => '$' . number_format( $amount, 2 ),
            'reason' => $reason,
            'priority' => 'high'
        ];
        
        // Adicionar conversão BRL para brasileiros
        if ( $user_country === 'BR' ) {
            $exchange_rate = DS_Credit_Converter::get_exchange_rate();
            $vars['amount_brl'] = 'R$ ' . number_format( $amount * $exchange_rate, 2, ',', '.' );
        }
        
        // Usar sistema de notificações multi-idioma
        if ( class_exists( 'DS_Notification_i18n' ) ) {
            DS_Notification_i18n::send( $user_id, 'withdrawal_processed', $vars );
        }
    }

    /**
     * Verifica se o usuário tem saldo suficiente.
     *
     * @param int $user_id ID do usuário.
     * @param int $amount Quantidade necessária.
     * @return bool True se tem saldo suficiente, false caso contrário.
     */
    public static function has_sufficient_balance( $user_id, $amount ) {
        $settings = get_option( 'ds_backgamom_credits_settings', [] );
        $allow_negative = ! empty( $settings['allow_negative_balance'] );
        $negative_limit = floatval( $settings['negative_balance_limit'] ?? 0 );
        
        $current_balance = self::get_balance( $user_id );
        
        if ( ! $allow_negative ) {
            return $current_balance >= $amount;
        }
        
        // Se saldos negativos são permitidos, verifica o limite
        if ( $negative_limit > 0 ) {
            $new_balance = $current_balance - $amount;
            return $new_balance >= -$negative_limit;
        }
        
        return true; // Sem limite negativo
    }
    
    /**
     * Envia notificação de depósito via sistema I18N.
     *
     * @param int $user_id ID do usuário.
     * @param float $amount Quantidade adicionada em USD.
     * @param float $new_balance Novo saldo em USD.
     */
    public static function send_deposit_notification( $user_id, $amount, $new_balance ) {
        $user_data = get_userdata( $user_id );
        if ( ! $user_data ) {
            return;
        }
        
        $user_name = $user_data->first_name ?: $user_data->display_name;
        $user_country = get_user_meta( $user_id, 'billing_country', true );
        
        $vars = [
            'name' => $user_name,
            'amount' => '$' . number_format( $amount, 2 ),
            'balance' => '$' . number_format( $new_balance, 2 ),
            'priority' => 'high'
        ];
        
        // Adicionar conversão BRL para brasileiros
        if ( $user_country === 'BR' ) {
            $exchange_rate = DS_Credit_Converter::get_exchange_rate();
            $vars['amount_brl'] = 'R$ ' . number_format( $amount * $exchange_rate, 2, ',', '.' );
            $vars['balance_brl'] = 'R$ ' . number_format( $new_balance * $exchange_rate, 2, ',', '.' );
        }
        
        // Usar sistema de notificações multi-idioma
        if ( class_exists( 'DS_Notification_i18n' ) ) {
            DS_Notification_i18n::send( $user_id, 'deposit', $vars );
        }
    }
    

    
    /**
     * Concede créditos quando pedido é marcado como concluído.
     */
    public function award_credits_on_completion( $order_id ) {
        $this->process_credit_award( $order_id, 'order_completed' );
    }
    
    /**
     * Concede créditos quando pagamento é confirmado.
     */
    public function award_credits_on_payment( $order_id ) {
        $this->process_credit_award( $order_id, 'payment_complete' );
    }
    
    /**
     * Processa concessão de créditos.
     */
    private function process_credit_award( $order_id, $trigger ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;
        
        // Previne processamento duplicado
        if ( $order->get_meta( '_dsbc_credits_awarded' ) ) {
            return;
        }
        
        $total_credits = 0;
        $has_credit_products = false;
        
        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            $credits = DS_Credit_Converter::get_product_credits( $product_id );
            
            if ( $credits > 0 ) {
                $total_credits += $credits * $item->get_quantity();
                $has_credit_products = true;
            }
        }
        
        if ( $total_credits > 0 && $has_credit_products ) {
            $user_id = $order->get_customer_id();
            if ( $user_id ) {
                self::add_credits( $user_id, $total_credits, $order_id );
                $order->update_meta_data( '_dsbc_credits_awarded', true );
                $order->update_meta_data( '_dsbc_credits_trigger', $trigger );
                $order->save();
                
                $gateway = $order->get_payment_method_title();
                $order->add_order_note( "Créditos concedidos: {$total_credits} USD (Gateway: {$gateway})" );
            }
        }
    }
}
