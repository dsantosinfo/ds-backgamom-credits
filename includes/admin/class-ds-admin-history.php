<?php
/**
 * Histórico de créditos do DS Backgamom Credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once DSBC_PLUGIN_PATH . 'includes/admin/class-ds-admin-base.php';

class DS_Admin_History extends DS_Admin_Base {

    public function __construct() {
        add_action('wp_ajax_dsbc_search_users_history', [$this, 'ajax_search_users']);
    }
    
    public function ajax_search_users() {
        check_ajax_referer('dsbc_search_users', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sem permissão');
        }
        
        $query = sanitize_text_field($_POST['query']);
        
        $users = get_users([
            'search' => '*' . $query . '*',
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'number' => 10
        ]);
        
        $results = [];
        foreach ($users as $user) {
            $balance = get_user_meta($user->ID, '_dsbc_credit_balance', true) ?: 0;
            $results[] = [
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'balance' => number_format($balance, 2) . ' USD'
            ];
        }
        
        wp_send_json_success($results);
    }

    public function history_page() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dsbc_credit_logs';
        $per_page = 25;
        $current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $offset = ( $current_page - 1 ) * $per_page;
        
        $search = isset( $_GET['search_user_id'] ) ? intval( $_GET['search_user_id'] ) : 0;
        $search_name = isset( $_GET['search_user_name'] ) ? sanitize_text_field( $_GET['search_user_name'] ) : '';
        $type_filter = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : '';
        $date_filter = isset( $_GET['date_filter'] ) ? sanitize_text_field( $_GET['date_filter'] ) : '';
        
        $where_clause = "WHERE 1=1";
        $search_params = [];
        
        if ( $search ) {
            $where_clause .= " AND l.user_id = %d";
            $search_params[] = $search;
        }
        
        if ( ! empty( $type_filter ) ) {
            $where_clause .= " AND l.type = %s";
            $search_params[] = $type_filter;
        }
        
        if ( ! empty( $date_filter ) ) {
            switch ( $date_filter ) {
                case 'today':
                    $where_clause .= " AND DATE(l.created_at) = CURDATE()";
                    break;
                case 'week':
                    $where_clause .= " AND l.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    break;
                case 'month':
                    $where_clause .= " AND l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    break;
            }
        }
        
        $count_query = "SELECT COUNT(*) FROM {$table_name} l LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID {$where_clause}";
        $total_logs = empty( $search_params ) ? $wpdb->get_var( $count_query ) : $wpdb->get_var( $wpdb->prepare( $count_query, $search_params ) );
        $total_pages = ceil( $total_logs / $per_page );
        
        $main_query = "SELECT l.*, u.display_name as user_name, u.user_email
                      FROM {$table_name} l LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID 
                      {$where_clause} ORDER BY l.created_at DESC LIMIT %d OFFSET %d";
        
        $query_params = array_merge( $search_params, [ $per_page, $offset ] );
        $logs = $wpdb->get_results( $wpdb->prepare( $main_query, $query_params ) );
        
        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-backup"></span> Histórico de Créditos USD</h1>
            
            <div class="postbox">
                <h2 class="hndle"><span class="dashicons dashicons-filter"></span> Filtros Avançados</h2>
                <div class="inside">
                    <form method="get" style="display: grid; grid-template-columns: 1fr 1fr 1fr auto auto; gap: 10px; align-items: end;">
                        <input type="hidden" name="page" value="ds-credits-history" />
                        <input type="hidden" id="search_user_id" name="search_user_id" value="<?php echo esc_attr( $search ); ?>" />
                        
                        <div>
                            <label>Buscar Usuário:</label>
                            <input type="text" id="user-search-input" value="<?php echo esc_attr( $search_name ); ?>" placeholder="Digite o nome ou email..." autocomplete="off" style="width: 100%;" />
                            <input type="hidden" name="search_user_name" id="search_user_name" value="<?php echo esc_attr( $search_name ); ?>" />
                            <div id="user-search-results" style="display:none; border: 1px solid #ddd; max-height: 200px; overflow-y: auto; background: #fff; position: absolute; z-index: 1000; width: 300px;"></div>
                        </div>
                        
                        <div>
                            <label>Tipo:</label>
                            <select name="type">
                                <option value="">Todos os tipos</option>
                                <option value="manual_addition" <?php selected( $type_filter, 'manual_addition' ); ?>>Adição Manual</option>
                                <option value="deposit" <?php selected( $type_filter, 'deposit' ); ?>>Depósito</option>
                                <option value="purchase" <?php selected( $type_filter, 'purchase' ); ?>>Compra</option>
                                <option value="tournament" <?php selected( $type_filter, 'tournament' ); ?>>Torneio</option>
                                <option value="withdrawal" <?php selected( $type_filter, 'withdrawal' ); ?>>Saque</option>
                            </select>
                        </div>
                        
                        <div>
                            <label>Período:</label>
                            <select name="date_filter">
                                <option value="">Todos</option>
                                <option value="today" <?php selected( $date_filter, 'today' ); ?>>Hoje</option>
                                <option value="week" <?php selected( $date_filter, 'week' ); ?>>Últimos 7 dias</option>
                                <option value="month" <?php selected( $date_filter, 'month' ); ?>>Últimos 30 dias</option>
                            </select>
                        </div>
                        
                        <input type="submit" class="button button-primary" value="Filtrar" />
                        
                        <?php if ( $search || $type_filter || $date_filter ): ?>
                            <a href="<?php echo admin_url( 'admin.php?page=ds-credits-history' ); ?>" class="button">Limpar</a>
                        <?php endif; ?>
                    </form>
                    
                    <script>
                    jQuery(document).ready(function($) {
                        let searchTimeout;
                        
                        $('#user-search-input').on('input', function() {
                            clearTimeout(searchTimeout);
                            const query = $(this).val();
                            
                            if (query.length < 2) {
                                $('#user-search-results').hide();
                                return;
                            }
                            
                            searchTimeout = setTimeout(function() {
                                $.post(ajaxurl, {
                                    action: 'dsbc_search_users_history',
                                    query: query,
                                    nonce: '<?php echo wp_create_nonce('dsbc_search_users'); ?>'
                                }, function(response) {
                                    if (response.success && response.data.length > 0) {
                                        let html = '';
                                        response.data.forEach(function(user) {
                                            html += '<div class="user-result-item" data-user-id="' + user.id + '" data-user-name="' + user.name + '" style="padding: 10px; cursor: pointer; border-bottom: 1px solid #eee;">';
                                            html += '<strong>' + user.name + '</strong><br>';
                                            html += '<small style="color: #666;">' + user.email + ' | ' + user.balance + ' créditos</small>';
                                            html += '</div>';
                                        });
                                        $('#user-search-results').html(html).show();
                                    } else {
                                        $('#user-search-results').html('<div style="padding: 10px; color: #666;">Nenhum usuário encontrado</div>').show();
                                    }
                                });
                            }, 300);
                        });
                        
                        $(document).on('click', '.user-result-item', function() {
                            const userId = $(this).data('user-id');
                            const userName = $(this).data('user-name');
                            
                            $('#search_user_id').val(userId);
                            $('#search_user_name').val(userName);
                            $('#user-search-input').val(userName);
                            $('#user-search-results').hide();
                        });
                        
                        $(document).on('click', function(e) {
                            if (!$(e.target).closest('#user-search-input, #user-search-results').length) {
                                $('#user-search-results').hide();
                            }
                        });
                    });
                    </script>
                    
                    <div style="margin-top: 10px; color: #666;">
                        <strong><?php echo number_format( $total_logs ); ?></strong> registros encontrados
                    </div>
                </div>
            </div>
            
            <div class="postbox">
                <h2 class="hndle"><span class="dashicons dashicons-list-view"></span> Registros de Movimentação</h2>
                <div class="inside">
                    <?php if ( $logs ): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th style="width: 120px;">Data/Hora</th>
                                    <th>Usuário</th>
                                    <th style="width: 100px;">Valor</th>
                                    <th style="width: 120px;">Tipo</th>
                                    <th>Observação</th>
                                    <th style="width: 100px;">Saldo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $logs as $log ): ?>
                                    <?php
                                    $type_labels = [
                                        'manual_addition' => ['Adição Manual', '#0073aa'],
                                        'deposit' => ['Depósito', '#00a32a'],
                                        'purchase' => ['Compra', '#00a32a'],
                                        'tournament' => ['Torneio', '#8c8f94'],
                                        'withdrawal' => ['Saque', '#d63638']
                                    ];
                                    $type_info = $type_labels[ $log->type ] ?? [$log->type, '#666'];
                                    $amount_color = $log->amount > 0 ? '#00a32a' : '#d63638';
                                    $amount_prefix = $log->amount > 0 ? '+' : '';
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo date( 'd/m/Y', strtotime( $log->created_at ) ); ?></strong>
                                            <br><small><?php echo date( 'H:i:s', strtotime( $log->created_at ) ); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo esc_html( $log->user_name ?: 'Usuário #' . $log->user_id ); ?></strong>
                                            <?php if ( $log->user_email ): ?>
                                                <br><small style="color: #666;"><?php echo esc_html( $log->user_email ); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="dsbc-stat-number" style="color: <?php echo $amount_color; ?>; font-size: 16px;">
                                                <?php echo $amount_prefix . number_format( abs($log->amount), 2 ); ?> USD
                                            </span>
                                        </td>
                                        <td>
                                            <span style="background: <?php echo $type_info[1]; ?>; color: white; padding: 3px 8px; border-radius: 3px; font-size: 11px;">
                                                <?php echo $type_info[0]; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo esc_html( $log->observation ); ?>
                                            <?php if ( $log->admin_name ): ?>
                                                <br><small style="color: #666;">por <?php echo esc_html( $log->admin_name ); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small style="color: #666;">
                                                <?php echo number_format( $log->old_balance, 2 ); ?> →
                                            </small>
                                            <br><strong><?php echo number_format( $log->new_balance, 2 ); ?> USD</strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="notice notice-info inline">
                            <p>Nenhum registro encontrado com os filtros aplicados.</p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ( $total_pages > 1 ): ?>
                        <div class="tablenav bottom">
                            <div class="tablenav-pages">
                                <span class="displaying-num"><?php echo number_format( $total_logs ); ?> itens</span>
                                <?php
                                $page_links = paginate_links( [
                                    'base' => add_query_arg( 'paged', '%#%' ),
                                    'format' => '',
                                    'prev_text' => '&laquo;',
                                    'next_text' => '&raquo;',
                                    'total' => $total_pages,
                                    'current' => $current_page,
                                    'type' => 'plain'
                                ] );
                                echo $page_links;
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}