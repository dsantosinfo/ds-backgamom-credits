<?php
/**
 * Shortcodes Frontend Otimizados
 *
 * @package DS_Backgamom_Credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DS_Frontend_Shortcodes {

    private static $scripts_loaded = false;

    public function __construct() {
        add_shortcode( 'ds_credit_balance', [ $this, 'balance_shortcode' ] );
        add_shortcode( 'ds_credit_dashboard', [ $this, 'dashboard_shortcode' ] );
        add_shortcode( 'ds_credit_history', [ $this, 'history_shortcode' ] );
        add_shortcode( 'ds_credit_stats', [ $this, 'stats_shortcode' ] );
        add_shortcode( 'ds_credit_widget', [ $this, 'widget_shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'wp_ajax_ds_load_more_history', [ $this, 'ajax_load_more_history' ] );
        add_action( 'wp_ajax_ds_filter_history', [ $this, 'ajax_filter_history' ] );
        add_action( 'wp_ajax_ds_get_current_balance', [ $this, 'ajax_get_current_balance' ] );
    }

    public function enqueue_scripts() {
        // Sempre carregar CSS para shortcodes
        wp_enqueue_style( 'ds-frontend-credits-css', DSBC_PLUGIN_URL . 'assets/css/frontend.css', [], DSBC_VERSION );
        
        // Carregar JS apenas se necessário
        global $post;
        if ( is_a( $post, 'WP_Post' ) && (
             has_shortcode( $post->post_content, 'ds_credit_dashboard' ) ||
             has_shortcode( $post->post_content, 'ds_credit_history' ) ) ) {
            wp_enqueue_script( 'ds-frontend-credits', DSBC_PLUGIN_URL . 'assets/js/frontend.js', [ 'jquery' ], DSBC_VERSION, true );
            wp_localize_script( 'ds-frontend-credits', 'dsbc_ajax', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'ds_frontend_nonce' )
            ]);
        }
    }

    private function ensure_scripts_loaded() {
        if ( ! self::$scripts_loaded ) {
            wp_enqueue_style( 'ds-frontend-credits-css', DSBC_PLUGIN_URL . 'assets/css/frontend.css', [], DSBC_VERSION );
            wp_enqueue_script( 'ds-frontend-credits', DSBC_PLUGIN_URL . 'assets/js/frontend.js', [ 'jquery' ], DSBC_VERSION, true );
            wp_localize_script( 'ds-frontend-credits', 'dsbc_ajax', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'ds_frontend_nonce' )
            ]);
            self::$scripts_loaded = true;
        }
    }

    /**
     * Shortcode para saldo simples
     */
    public function balance_shortcode( $atts ) {
        $atts = shortcode_atts( [
            'format' => 'number', // number, badge, card
            'show_label' => 'true'
        ], $atts );

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return '<span class="ds-credit-error">Faça login para ver seu saldo</span>';
        }

        $balance = DS_Credit_Manager::get_balance( $user_id );
        
        switch ( $atts['format'] ) {
            case 'badge':
                return sprintf( 
                    '<span class="ds-credit-badge">$ %s</span>',
                    number_format( $balance, 2 )
                );
            
            case 'card':
                return sprintf(
                    '<div class="ds-credit-card"><div class="amount">$ %s</div><div class="label">disponível</div></div>',
                    number_format( $balance, 2 )
                );
            
            default:
                return $atts['show_label'] === 'true' 
                    ? sprintf( '$ %s disponível', number_format( $balance, 2 ) )
                    : '$ ' . number_format( $balance, 2 );
        }
    }

    /**
     * Dashboard completo otimizado
     */
    public function dashboard_shortcode( $atts ) {
        $this->ensure_scripts_loaded();
        
        $atts = shortcode_atts( [
            'show_history' => 'true',
            'history_limit' => '5',
            'show_stats' => 'true'
        ], $atts );

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return '<div class="ds-credit-login-required">Você precisa estar logado para acessar seus créditos.</div>';
        }

        $balance = DS_Credit_Manager::get_balance( $user_id );
        $stats = $this->get_user_stats( $user_id );
        
        ob_start();
        ?>
        <div class="ds-credit-dashboard">
            <!-- Saldo Principal -->
            <div class="ds-balance-section">
                <div class="balance-card">
                    <div class="balance-amount">$ <?php echo number_format( $balance, 2 ); ?></div>
                    <div class="balance-label">disponível</div>
                </div>
                <div class="balance-actions">
                    <a href="<?php echo $this->get_credits_shop_url(); ?>" class="btn btn-primary">Comprar Créditos</a>
                    <?php if ( $balance > 0 ): ?>
                        <a href="#" class="btn btn-secondary ds-withdrawal-btn">Solicitar Saque</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ( $atts['show_stats'] === 'true' ): ?>
            <!-- Estatísticas Rápidas -->
            <div class="ds-stats-section">
                <div class="stat-item">
                    <span class="stat-value">$ <?php echo number_format( $stats['total_earned'], 2 ); ?></span>
                    <span class="stat-label">Total Ganho</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value">$ <?php echo number_format( $stats['total_spent'], 2 ); ?></span>
                    <span class="stat-label">Total Gasto</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?php echo $stats['transactions_count']; ?></span>
                    <span class="stat-label">Transações</span>
                </div>
            </div>
            <?php endif; ?>

            <?php if ( $atts['show_history'] === 'true' ): ?>
            <!-- Histórico Recente -->
            <div class="ds-history-section">
                <h4>Últimas Transações</h4>
                <?php echo $this->render_history_list( $user_id, (int) $atts['history_limit'] ); ?>
                <div class="history-actions">
                    <button class="btn btn-link ds-load-more" data-page="2" data-limit="<?php echo $atts['history_limit']; ?>">
                        Ver Mais Transações
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Histórico detalhado de transações
     */
    public function history_shortcode( $atts ) {
        $this->ensure_scripts_loaded();
        
        $atts = shortcode_atts( [
            'limit' => '10',
            'type' => 'all', // all, deposits, withdrawals, manual
            'show_pagination' => 'true'
        ], $atts );

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return '<p>Você precisa estar logado para ver o histórico.</p>';
        }

        ob_start();
        ?>
        <div class="ds-credit-history">
            <div class="history-filters">
                <select class="filter-type" data-filter="type">
                    <option value="all" <?php selected( $atts['type'], 'all' ); ?>>Todas as Transações</option>
                    <option value="deposit" <?php selected( $atts['type'], 'deposit' ); ?>>Depósitos</option>
                    <option value="withdrawal" <?php selected( $atts['type'], 'withdrawal' ); ?>>Saques</option>
                    <option value="manual_addition" <?php selected( $atts['type'], 'manual_addition' ); ?>>Adições Manuais</option>
                </select>
            </div>
            
            <div class="history-list">
                <?php echo $this->render_detailed_history( $user_id, (int) $atts['limit'], $atts['type'] ); ?>
            </div>
            
            <?php if ( $atts['show_pagination'] === 'true' ): ?>
            <div class="history-pagination">
                <button class="btn ds-load-more-detailed" data-page="2" data-limit="<?php echo $atts['limit']; ?>" data-type="<?php echo $atts['type']; ?>">
                    Carregar Mais
                </button>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Estatísticas do usuário
     */
    public function stats_shortcode( $atts ) {
        $atts = shortcode_atts( [
            'period' => '30', // dias
            'show_chart' => 'false'
        ], $atts );

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return '<p>Faça login para ver suas estatísticas.</p>';
        }

        $stats = $this->get_period_stats( $user_id, (int) $atts['period'] );
        
        ob_start();
        ?>
        <div class="ds-credit-stats">
            <h4>Estatísticas dos Últimos <?php echo $atts['period']; ?> Dias</h4>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number">$ <?php echo number_format( $stats['deposits'], 2 ); ?></div>
                    <div class="stat-label">Créditos Recebidos</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number">$ <?php echo number_format( abs( $stats['withdrawals'] ), 2 ); ?></div>
                    <div class="stat-label">Créditos Gastos</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['transactions']; ?></div>
                    <div class="stat-label">Transações</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number">$ <?php echo number_format( $stats['net_change'], 2 ); ?></div>
                    <div class="stat-label">Saldo Líquido</div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Widget compacto de créditos
     */
    public function widget_shortcode( $atts ) {
        $atts = shortcode_atts( [
            'style' => 'default', // default, minimal, card
            'show_actions' => 'true',
            'show_last_transaction' => 'false'
        ], $atts );

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return '<div class="ds-credit-widget-login">Faça login para ver seus créditos</div>';
        }

        $balance = DS_Credit_Manager::get_balance( $user_id );
        $last_transaction = $this->get_last_transaction( $user_id );
        
        ob_start();
        ?>
        <div class="ds-credit-widget ds-widget-<?php echo esc_attr( $atts['style'] ); ?>">
            <div class="widget-header">
                <span class="widget-title">Meu Saldo</span>
                <span class="widget-balance">$ <?php echo number_format( $balance, 2 ); ?></span>
            </div>
            
            <?php if ( $atts['show_last_transaction'] === 'true' && $last_transaction ): ?>
            <div class="widget-last-transaction">
                <small>
                    Última: <?php echo esc_html( $this->get_transaction_type_label( $last_transaction->type ) ); ?>
                    (<?php echo date( 'd/m', strtotime( $last_transaction->created_at ) ); ?>)
                </small>
            </div>
            <?php endif; ?>
            
            <?php if ( $atts['show_actions'] === 'true' ): ?>
            <div class="widget-actions">
                <a href="<?php echo $this->get_credits_shop_url(); ?>" class="widget-btn widget-btn-primary">Comprar</a>
                <?php if ( $balance > 0 ): ?>
                    <a href="#" class="widget-btn widget-btn-secondary ds-withdrawal-btn">Sacar</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Obtém a última transação do usuário
     */
    private function get_last_transaction( $user_id ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dsbc_credit_logs';
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY created_at DESC LIMIT 1",
            $user_id
        ) );
    }

    /**
     * Renderiza lista de histórico
     */
    private function render_history_list( $user_id, $limit = 5 ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dsbc_credit_logs';
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            $user_id, $limit
        ) );

        if ( empty( $results ) ) {
            return '<p class="no-history">Nenhuma transação encontrada.</p>';
        }

        $output = '<div class="history-list">';
        foreach ( $results as $transaction ) {
            $type_label = $this->get_transaction_type_label( $transaction->type );
            $amount_class = $transaction->amount > 0 ? 'positive' : 'negative';
            $amount_prefix = $transaction->amount > 0 ? '+' : '';
            
            $output .= sprintf(
                '<div class="history-item">
                    <div class="history-info">
                        <div class="history-type">%s</div>
                        <div class="history-date">%s</div>
                    </div>
                    <div class="history-amount %s">%s$ %s</div>
                </div>',
                esc_html( $type_label ),
                esc_html( date( 'd/m/Y H:i', strtotime( $transaction->created_at ) ) ),
                $amount_class,
                $amount_prefix,
                number_format( abs( $transaction->amount ), 2 )
            );
        }
        $output .= '</div>';

        return $output;
    }

    /**
     * Renderiza histórico detalhado
     */
    private function render_detailed_history( $user_id, $limit = 10, $type = 'all' ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dsbc_credit_logs';
        $where_clause = "WHERE user_id = %d";
        $params = [ $user_id ];
        
        if ( $type !== 'all' ) {
            $where_clause .= " AND type = %s";
            $params[] = $type;
        }
        
        $params[] = $limit;
        
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table_name} {$where_clause} ORDER BY created_at DESC LIMIT %d",
            ...$params
        ) );

        if ( empty( $results ) ) {
            return '<p class="no-history">Nenhuma transação encontrada.</p>';
        }

        $output = '';
        foreach ( $results as $transaction ) {
            $type_label = $this->get_transaction_type_label( $transaction->type );
            $badge_class = 'badge-' . str_replace( '_', '-', $transaction->type );
            $amount_prefix = $transaction->amount > 0 ? '+' : '';
            
            $output .= sprintf(
                '<div class="history-detailed-item">
                    <div class="history-header">
                        <span class="history-type-badge %s">%s</span>
                        <span class="history-amount">%s$ %s</span>
                    </div>
                    <div class="history-meta">
                        <small>%s</small>
                    </div>
                    %s
                </div>',
                $badge_class,
                esc_html( $type_label ),
                $amount_prefix,
                number_format( abs( $transaction->amount ), 2 ),
                esc_html( date( 'd/m/Y H:i', strtotime( $transaction->created_at ) ) ),
                $transaction->observation ? '<div class="history-observation">' . esc_html( $transaction->observation ) . '</div>' : ''
            );
        }

        return $output;
    }

    /**
     * Obtém estatísticas do usuário
     */
    private function get_user_stats( $user_id ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dsbc_credit_logs';
        $stats = $wpdb->get_row( $wpdb->prepare(
            "SELECT 
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_earned,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_spent,
                COUNT(*) as transactions_count
            FROM {$table_name} 
            WHERE user_id = %d",
            $user_id
        ) );

        return [
            'total_earned' => $stats->total_earned ?: 0,
            'total_spent' => $stats->total_spent ?: 0,
            'transactions_count' => $stats->transactions_count ?: 0
        ];
    }

    /**
     * Obtém estatísticas por período
     */
    private function get_period_stats( $user_id, $days ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dsbc_credit_logs';
        $date_from = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
        $stats = $wpdb->get_row( $wpdb->prepare(
            "SELECT 
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as deposits,
                SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END) as withdrawals,
                COUNT(*) as transactions
            FROM {$table_name} 
            WHERE user_id = %d AND created_at >= %s",
            $user_id, $date_from
        ) );

        return [
            'deposits' => $stats->deposits ?: 0,
            'withdrawals' => $stats->withdrawals ?: 0,
            'transactions' => $stats->transactions ?: 0,
            'net_change' => ( $stats->deposits ?: 0 ) + ( $stats->withdrawals ?: 0 )
        ];
    }

    /**
     * Obtém label do tipo de transação
     */
    private function get_transaction_type_label( $type ) {
        $labels = [
            'deposit' => 'Depósito',
            'withdrawal' => 'Saque',
            'manual_addition' => 'Adição Manual',
            'deduction' => 'Dedução',
            'refund' => 'Reembolso'
        ];
        
        return $labels[ $type ] ?? ucfirst( $type );
    }

    /**
     * Obtém URL da loja de créditos
     */
    private function get_credits_shop_url() {
        // Procura por produtos com créditos
        $products = get_posts([
            'post_type' => 'product',
            'meta_query' => [
                [
                    'key' => '_dsbc_credits_amount',
                    'value' => 0,
                    'compare' => '>'
                ]
            ],
            'posts_per_page' => 1
        ]);

        if ( ! empty( $products ) ) {
            return get_permalink( $products[0]->ID );
        }

        return wc_get_page_permalink( 'shop' );
    }

    /**
     * AJAX: Carregar mais histórico
     */
    public function ajax_load_more_history() {
        check_ajax_referer( 'ds_frontend_nonce', 'nonce' );
        
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_die( 'Unauthorized' );
        }

        $page = (int) $_POST['page'];
        $limit = (int) $_POST['limit'];
        $type = sanitize_text_field( $_POST['type'] ?? 'all' );
        $offset = ( $page - 1 ) * $limit;

        global $wpdb;
        $table_name = $wpdb->prefix . 'dsbc_credit_logs';
        
        $where_clause = "WHERE user_id = %d";
        $params = [ $user_id ];
        
        if ( $type !== 'all' ) {
            $where_clause .= " AND type = %s";
            $params[] = $type;
        }
        
        $params[] = $limit;
        $params[] = $offset;
        
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table_name} {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            ...$params
        ) );

        if ( empty( $results ) ) {
            wp_send_json_error( 'Não há mais transações.' );
        }

        $html = '';
        foreach ( $results as $transaction ) {
            $type_label = $this->get_transaction_type_label( $transaction->type );
            $amount_class = $transaction->amount > 0 ? 'positive' : 'negative';
            $amount_prefix = $transaction->amount > 0 ? '+' : '';
            
            $html .= sprintf(
                '<div class="history-item">
                    <div class="history-info">
                        <div class="history-type">%s</div>
                        <div class="history-date">%s</div>
                    </div>
                    <div class="history-amount %s">%s$ %s</div>
                </div>',
                esc_html( $type_label ),
                esc_html( date( 'd/m/Y H:i', strtotime( $transaction->created_at ) ) ),
                $amount_class,
                $amount_prefix,
                number_format( abs( $transaction->amount ), 2 )
            );
        }

        wp_send_json_success( [ 'html' => $html, 'next_page' => $page + 1 ] );
    }

    /**
     * AJAX: Filtrar histórico por tipo
     */
    public function ajax_filter_history() {
        check_ajax_referer( 'ds_frontend_nonce', 'nonce' );
        
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_die( 'Unauthorized' );
        }

        $type = sanitize_text_field( $_POST['type'] ?? 'all' );
        $limit = (int) ( $_POST['limit'] ?? 10 );
        
        $html = $this->render_detailed_history( $user_id, $limit, $type );
        
        wp_send_json_success( [ 'html' => $html ] );
    }

    /**
     * AJAX: Obter saldo atual
     */
    public function ajax_get_current_balance() {
        check_ajax_referer( 'ds_frontend_nonce', 'nonce' );
        
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_die( 'Unauthorized' );
        }

        $balance = DS_Credit_Manager::get_balance( $user_id );
        
        wp_send_json_success( [
            'balance' => $balance,
            'formatted' => number_format( $balance )
        ] );
    }
}