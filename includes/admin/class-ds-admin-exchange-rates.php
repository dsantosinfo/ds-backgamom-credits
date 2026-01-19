<?php
/**
 * Interface Admin para Taxa de Câmbio
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DS_Admin_Exchange_Rates {

    public function __construct() {
        add_action( 'wp_ajax_ds_update_exchange_rate', array( $this, 'ajax_update_rate' ) );
        add_action( 'wp_ajax_ds_force_bcb_update', array( $this, 'ajax_force_bcb_update' ) );
    }

    public function render_page() {
        $action = $_GET['action'] ?? 'list';
        
        switch ( $action ) {
            case 'list':
            default:
                $this->render_list_page();
                break;
        }
    }

    private function render_list_page() {
        // Parâmetros de paginação e filtros
        $per_page = 20;
        $current_page = max( 1, intval( isset($_GET['paged']) ? $_GET['paged'] : 1 ) );
        $offset = ( $current_page - 1 ) * $per_page;
        
        $date_from = sanitize_text_field( isset($_GET['date_from']) ? $_GET['date_from'] : '' );
        $date_to = sanitize_text_field( isset($_GET['date_to']) ? $_GET['date_to'] : '' );
        
        // Busca dados
        $rates = DS_Exchange_Rate_Manager::get_rates_history( $per_page, $offset, $date_from, $date_to );
        $total_items = DS_Exchange_Rate_Manager::count_rates( $date_from, $date_to );
        $total_pages = ceil( $total_items / $per_page );
        
        $active_rate = DS_Exchange_Rate_Manager::get_active_rate();
        
        ?>
        <div class="wrap">
            <h1>Taxa de Câmbio USD/BRL</h1>
            
            <!-- Status Atual -->
            <div class="notice notice-info">
                <p>
                    <strong>Taxa Ativa:</strong> 
                    <?php if ( $active_rate ): ?>
                        R$ <?php echo number_format( $active_rate->rate, 4, ',', '.' ); ?>
                        <small>(atualizada em <?php echo date( 'd/m/Y H:i', strtotime( $active_rate->date_created ) ); ?>)</small>
                    <?php else: ?>
                        Nenhuma taxa configurada
                    <?php endif; ?>
                </p>
            </div>
            
            <!-- Ações -->
            <div class="tablenav top">
                <div class="alignleft actions">
                    <button type="button" class="button button-primary" id="force-bcb-update">
                        Atualizar do BCB Agora
                    </button>
                    <button type="button" class="button" onclick="jQuery('#manual-rate-form').toggle()">
                        Definir Taxa Manual
                    </button>
                </div>
            </div>
            
            <!-- Formulário Taxa Manual -->
            <div id="manual-rate-form" style="display:none; margin: 20px 0; padding: 15px; border: 1px solid #ddd; background: #f9f9f9;">
                <h3>Definir Taxa Manualmente</h3>
                <form id="manual-rate-form-inner">
                    <table class="form-table">
                        <tr>
                            <th><label for="manual_rate">Taxa USD/BRL:</label></th>
                            <td>
                                <input type="number" id="manual_rate" step="0.0001" min="1" max="20" required>
                                <p class="description">Ex: 5.6750</p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary">Salvar Taxa</button>
                        <button type="button" class="button" onclick="jQuery('#manual-rate-form').hide()">Cancelar</button>
                    </p>
                </form>
            </div>
            
            <!-- Filtros -->
            <div class="tablenav top">
                <form method="get" class="alignleft actions">
                    <input type="hidden" name="page" value="ds-exchange-rates">
                    <input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" placeholder="Data inicial">
                    <input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" placeholder="Data final">
                    <input type="submit" class="button" value="Filtrar">
                    <?php if ( $date_from || $date_to ): ?>
                        <a href="?page=ds-exchange-rates" class="button">Limpar</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Tabela -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Taxa</th>
                        <th>Fonte</th>
                        <th>Data/Hora</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $rates ) ): ?>
                        <tr>
                            <td colspan="4">Nenhuma taxa encontrada.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ( $rates as $rate ): ?>
                            <tr>
                                <td>
                                    <strong>R$ <?php echo number_format( $rate->rate, 4, ',', '.' ); ?></strong>
                                </td>
                                <td>
                                    <?php 
                                    $sources = array(
                                        'bcb' => 'Banco Central',
                                        'manual' => 'Manual'
                                    );
                                    echo isset($sources[ $rate->source ]) ? $sources[ $rate->source ] : $rate->source;
                                    ?>
                                </td>
                                <td>
                                    <?php echo date( 'd/m/Y H:i:s', strtotime( $rate->date_created ) ); ?>
                                </td>
                                <td>
                                    <?php if ( $rate->is_active ): ?>
                                        <span class="dashicons dashicons-yes-alt" style="color: green;"></span> Ativa
                                    <?php else: ?>
                                        <span class="dashicons dashicons-minus" style="color: #ccc;"></span> Inativa
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Paginação -->
            <?php if ( $total_pages > 1 ): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php echo $total_items; ?> itens</span>
                        <?php
                        $page_links = paginate_links( array(
                            'base' => add_query_arg( 'paged', '%#%' ),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $current_page,
                            'type' => 'plain'
                        ) );
                        echo $page_links;
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Força atualização BCB
            $('#force-bcb-update').click(function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('Atualizando...');
                
                $.post(ajaxurl, {
                    action: 'ds_force_bcb_update',
                    nonce: '<?php echo wp_create_nonce( 'ds_exchange_rate' ); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Taxa atualizada com sucesso!');
                        location.reload();
                    } else {
                        alert('Erro: ' + response.data);
                    }
                }).always(function() {
                    $btn.prop('disabled', false).text('Atualizar do BCB Agora');
                });
            });
            
            // Taxa manual
            $('#manual-rate-form-inner').submit(function(e) {
                e.preventDefault();
                
                var rate = $('#manual_rate').val();
                if (!rate || rate <= 0) {
                    alert('Digite uma taxa válida');
                    return;
                }
                
                $.post(ajaxurl, {
                    action: 'ds_update_exchange_rate',
                    rate: rate,
                    nonce: '<?php echo wp_create_nonce( 'ds_exchange_rate' ); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Taxa salva com sucesso!');
                        location.reload();
                    } else {
                        alert('Erro: ' + response.data);
                    }
                });
            });
        });
        </script>
        <?php
    }

    public function ajax_update_rate() {
        check_ajax_referer( 'ds_exchange_rate', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Sem permissão' );
        }
        
        $rate = floatval( isset($_POST['rate']) ? $_POST['rate'] : 0 );
        
        if ( $rate <= 0 || $rate > 20 ) {
            wp_send_json_error( 'Taxa inválida' );
        }
        
        $success = DS_Exchange_Rate_Manager::save_rate( $rate, 'manual', true );
        
        if ( $success ) {
            wp_send_json_success( 'Taxa atualizada' );
        } else {
            wp_send_json_error( 'Erro ao salvar' );
        }
    }

    public function ajax_force_bcb_update() {
        check_ajax_referer( 'ds_exchange_rate', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Sem permissão' );
        }
        
        $success = DS_Exchange_Rate_Manager::force_update();
        
        if ( $success ) {
            wp_send_json_success( 'Taxa atualizada do BCB' );
        } else {
            wp_send_json_error( 'Erro ao buscar cotação do BCB' );
        }
    }
}
