<?php
/**
 * Relatórios administrativos do DS Backgamom Credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once DSBC_PLUGIN_PATH . 'includes/admin/class-ds-admin-base.php';

class DS_Admin_Reports extends DS_Admin_Base {

    public function reports_page() {
        $active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'overview';
        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-chart-bar"></span> Relatórios de Créditos USD</h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=ds-credits-reports&tab=overview" class="nav-tab <?php echo $active_tab == 'overview' ? 'nav-tab-active' : ''; ?>">Visão Geral</a>
                <a href="?page=ds-credits-reports&tab=sales" class="nav-tab <?php echo $active_tab == 'sales' ? 'nav-tab-active' : ''; ?>">Vendas</a>
                <a href="?page=ds-credits-reports&tab=users" class="nav-tab <?php echo $active_tab == 'users' ? 'nav-tab-active' : ''; ?>">Usuários</a>
            </nav>

            <?php
            switch ( $active_tab ) {
                case 'sales':
                    $this->render_sales_report();
                    break;
                case 'users':
                    $this->render_users_report();
                    break;
                default:
                    $this->render_overview_report();
            }
            ?>
        </div>
        <?php
    }

    private function render_overview_report() {
        ?>
        <div class="postbox">
            <h2 class="hndle"><span class="dashicons dashicons-chart-pie"></span> Resumo Executivo</h2>
            <div class="inside">
                <?php $this->display_executive_summary(); ?>
            </div>
        </div>
        
        <div class="postbox">
            <h2 class="hndle"><span class="dashicons dashicons-calendar-alt"></span> Vendas dos Últimos 30 Dias</h2>
            <div class="inside">
                <?php $this->display_general_report(); ?>
            </div>
        </div>
        <?php
    }

    private function render_sales_report() {
        ?>
        <div class="postbox">
            <h2 class="hndle"><span class="dashicons dashicons-money-alt"></span> Relatório de Vendas Detalhado</h2>
            <div class="inside">
                <?php $this->display_detailed_sales(); ?>
            </div>
        </div>
        <?php
    }

    private function render_users_report() {
        ?>
        <div class="postbox">
            <h2 class="hndle"><span class="dashicons dashicons-groups"></span> Top Usuários por Créditos</h2>
            <div class="inside">
                <?php $this->display_top_users(); ?>
            </div>
        </div>
        <?php
    }

    private function display_executive_summary() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dsbc_credit_logs';
        
        $total_credits = $wpdb->get_var(
            "SELECT SUM(CAST(meta_value AS DECIMAL(10,2))) FROM {$wpdb->usermeta} WHERE meta_key = '_dsbc_credit_balance'"
        );
        
        $total_deposits = $wpdb->get_var(
            "SELECT SUM(amount) FROM {$table_name} WHERE type = 'deposit' AND amount > 0"
        );
        
        $monthly_deposits = $wpdb->get_var(
            "SELECT SUM(amount) FROM {$table_name} WHERE type = 'deposit' AND amount > 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        $total_withdrawals = $wpdb->get_var(
            "SELECT SUM(ABS(amount)) FROM {$table_name} WHERE type = 'withdrawal' AND amount < 0"
        );
        
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">';
        
        echo '<div class="dsbc-stat-box">';
        echo '<div class="dsbc-stat-number">' . number_format( $total_credits ?: 0, 2 ) . '</div>';
        echo '<div>Créditos USD Ativos</div>';
        $exchange_rate = class_exists( 'DS_Credit_Converter' ) ? DS_Credit_Converter::get_exchange_rate() : 5.67;
        echo '<small>≈ R$ ' . number_format( ($total_credits ?: 0) * $exchange_rate, 2, ',', '.' ) . '</small>';
        echo '</div>';
        
        echo '<div class="dsbc-stat-box">';
        echo '<div class="dsbc-stat-number">' . number_format( $total_deposits ?: 0, 2 ) . '</div>';
        echo '<div>Total Depositado USD</div>';
        echo '</div>';
        
        echo '<div class="dsbc-stat-box">';
        echo '<div class="dsbc-stat-number">' . number_format( $monthly_deposits ?: 0, 2 ) . '</div>';
        echo '<div>Depósitos USD (30 dias)</div>';
        echo '</div>';
        
        echo '<div class="dsbc-stat-box">';
        echo '<div class="dsbc-stat-number">' . number_format( $total_withdrawals ?: 0, 2 ) . '</div>';
        echo '<div>Total Sacado USD</div>';
        echo '</div>';
        
        echo '</div>';
    }

    private function display_detailed_sales() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dsbc_credit_logs';
        
        // Buscar pedidos com créditos dos últimos 30 dias
        $results = $wpdb->get_results(
            "SELECT DATE(l.created_at) as date, 
                    COUNT(DISTINCT SUBSTRING_INDEX(l.observation, '#', -1)) as orders,
                    SUM(l.amount) as total_credits,
                    GROUP_CONCAT(DISTINCT SUBSTRING_INDEX(l.observation, '#', -1) ORDER BY l.created_at DESC) as order_ids
             FROM {$table_name} l
             WHERE l.type = 'deposit' AND l.amount > 0
             AND l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             AND l.observation LIKE '%pedido #%'
             GROUP BY DATE(l.created_at) ORDER BY date DESC LIMIT 15"
        );

        if ( $results ) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Data</th><th>Pedidos</th><th>Créditos Vendidos</th><th>Pedidos</th></tr></thead><tbody>';
            foreach ( $results as $row ) {
                echo '<tr>';
                echo '<td>' . date( 'd/m/Y', strtotime( $row->date ) ) . '</td>';
                echo '<td><span class="dsbc-stat-number" style="font-size: 16px;">' . $row->orders . '</span></td>';
                echo '<td><span class="dsbc-stat-number" style="font-size: 16px;">' . number_format( $row->total_credits, 2 ) . ' USD</span></td>';
                echo '<td><small>' . str_replace( ',', ', #', '#' . $row->order_ids ) . '</small></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<div class="notice notice-info inline"><p>Nenhuma venda de créditos encontrada nos últimos 30 dias.</p></div>';
        }
    }

    private function display_general_report() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dsbc_credit_logs';
        
        $results = $wpdb->get_results(
            "SELECT DATE(l.created_at) as date, COUNT(*) as transactions, SUM(l.amount) as total_credits
             FROM {$table_name} l
             WHERE l.type IN ('deposit', 'manual_addition') AND l.amount > 0
             AND l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(l.created_at) ORDER BY date DESC LIMIT 10"
        );

        if ( $results ) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Data</th><th>Transações</th><th>Créditos Adicionados</th></tr></thead><tbody>';
            foreach ( $results as $row ) {
                echo '<tr>';
                echo '<td>' . date( 'd/m/Y', strtotime( $row->date ) ) . '</td>';
                echo '<td><span class="dsbc-stat-number" style="font-size: 16px;">' . $row->transactions . '</span></td>';
                echo '<td><span class="dsbc-stat-number" style="font-size: 16px;">' . number_format( $row->total_credits ?: 0, 2 ) . ' USD</span></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<div class="notice notice-info inline"><p>Nenhuma movimentação encontrada nos últimos 30 dias.</p></div>';
        }
    }

    private function display_top_users() {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT u.display_name, u.user_email, CAST(um.meta_value AS UNSIGNED) as credits
             FROM {$wpdb->users} u
             INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
             WHERE um.meta_key = '_dsbc_credit_balance' AND CAST(um.meta_value AS DECIMAL(10,2)) > 0
             ORDER BY CAST(um.meta_value AS DECIMAL(10,2)) DESC LIMIT 15"
        );

        if ( $results ) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Posição</th><th>Usuário</th><th>Créditos</th></tr></thead><tbody>';
            $position = 1;
            foreach ( $results as $row ) {
                $medal = $position <= 3 ? ['🥇', '🥈', '🥉'][$position - 1] : $position . 'º';
                echo '<tr>';
                echo '<td style="text-align: center; font-size: 16px;">' . $medal . '</td>';
                echo '<td><strong>' . esc_html( $row->display_name ) . '</strong><br><small style="color: #666;">' . esc_html( $row->user_email ) . '</small></td>';
                echo '<td><span class="dsbc-stat-number" style="font-size: 18px;">' . number_format( $row->credits, 2 ) . ' USD</span></td>';
                echo '</tr>';
                $position++;
            }
            echo '</tbody></table>';
        } else {
            echo '<div class="notice notice-info inline"><p>Nenhum usuário com créditos encontrado.</p></div>';
        }
    }
}