<?php
/**
 * Admin handler para visualização de comprovantes PIX
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DS_Admin_Pix_Receipts {

    public function __construct() {
        // Adicionar metabox no pedido
        add_action( 'add_meta_boxes', [ $this, 'add_receipt_metabox' ] );
        
        // Adicionar coluna na lista de pedidos
        add_filter( 'manage_edit-shop_order_columns', [ $this, 'add_receipt_column' ] );
        add_action( 'manage_shop_order_posts_custom_column', [ $this, 'receipt_column_content' ], 10, 2 );
        
        // HPOS compatibility
        add_filter( 'manage_woocommerce_page_wc-orders_columns', [ $this, 'add_receipt_column' ] );
        add_action( 'manage_woocommerce_page_wc-orders_custom_column', [ $this, 'receipt_column_content_hpos' ], 10, 2 );
        
        // AJAX para aprovar pagamento
        add_action( 'wp_ajax_ds_approve_pix_payment', [ $this, 'ajax_approve_payment' ] );
    }

    public function add_receipt_metabox() {
        add_meta_box(
            'ds_pix_receipt',
            'Comprovante PIX',
            [ $this, 'render_receipt_metabox' ],
            'shop_order',
            'side',
            'high'
        );
        
        // HPOS
        add_meta_box(
            'ds_pix_receipt',
            'Comprovante PIX',
            [ $this, 'render_receipt_metabox' ],
            'woocommerce_page_wc-orders',
            'side',
            'high'
        );
    }

    public function render_receipt_metabox( $post_or_order ) {
        $order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
        $payment_method = $order->get_payment_method();
        
        if ( $payment_method !== 'ds_pix' && $payment_method !== 'dsantos_pix' ) {
            echo '<p>Pedido não é PIX</p>';
            return;
        }

        $receipt = get_post_meta( $order->get_id(), '_pix_receipt_url', true );
        
        if ( $receipt ) {
            echo '<div style="text-align: center;">';
            
            $ext = strtolower( pathinfo( $receipt, PATHINFO_EXTENSION ) );
            if ( in_array( $ext, ['jpg', 'jpeg', 'png'] ) ) {
                echo '<img src="' . esc_url( $receipt ) . '" style="max-width: 100%; margin-bottom: 10px;">';
            }
            
            echo '<p><a href="' . esc_url( $receipt ) . '" target="_blank" class="button">Ver Comprovante</a></p>';
            
            if ( $order->get_status() === 'on-hold' ) {
                echo '<button type="button" class="button button-primary" onclick="dsApprovePixPayment(' . $order->get_id() . ')">Aprovar Pagamento</button>';
            }
            
            echo '</div>';
        } else {
            echo '<p>Nenhum comprovante enviado</p>';
        }
    }

    public function add_receipt_column( $columns ) {
        $new_columns = [];
        foreach ( $columns as $key => $value ) {
            $new_columns[$key] = $value;
            if ( $key === 'order_status' ) {
                $new_columns['pix_receipt'] = 'Comprovante';
            }
        }
        return $new_columns;
    }

    public function receipt_column_content( $column, $post_id ) {
        if ( $column === 'pix_receipt' ) {
            $order = wc_get_order( $post_id );
            $receipt = get_post_meta( $post_id, '_pix_receipt_url', true );
            
            if ( $receipt ) {
                echo '<a href="' . esc_url( $receipt ) . '" target="_blank" title="Ver comprovante">📄</a>';
            } else {
                echo '-';
            }
        }
    }

    public function receipt_column_content_hpos( $column, $order ) {
        if ( $column === 'pix_receipt' ) {
            $receipt = get_post_meta( $order->get_id(), '_pix_receipt_url', true );
            
            if ( $receipt ) {
                echo '<a href="' . esc_url( $receipt ) . '" target="_blank" title="Ver comprovante">📄</a>';
            } else {
                echo '-';
            }
        }
    }

    public function ajax_approve_payment() {
        check_ajax_referer( 'ds-admin-nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Sem permissão' );
        }

        $order_id = intval( $_POST['order_id'] );
        $order = wc_get_order( $order_id );
        
        if ( ! $order ) {
            wp_send_json_error( 'Pedido não encontrado' );
        }

        $order->payment_complete();
        $order->add_order_note( 'Pagamento PIX aprovado manualmente pelo administrador.' );
        
        wp_send_json_success( 'Pagamento aprovado!' );
    }
}

// Adicionar script inline no admin
add_action( 'admin_footer', function() {
    if ( get_current_screen()->id !== 'shop_order' && get_current_screen()->id !== 'woocommerce_page_wc-orders' ) {
        return;
    }
    ?>
    <script>
    function dsApprovePixPayment(orderId) {
        if (!confirm('Confirmar aprovação do pagamento?')) return;
        
        jQuery.post(ajaxurl, {
            action: 'ds_approve_pix_payment',
            nonce: '<?php echo wp_create_nonce('ds-admin-nonce'); ?>',
            order_id: orderId
        }, function(response) {
            if (response.success) {
                alert(response.data);
                location.reload();
            } else {
                alert('Erro: ' + response.data);
            }
        });
    }
    </script>
    <?php
});
