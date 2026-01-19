<?php
/**
 * Administração de Comprovantes WISE
 *
 * @package DS_Backgamom_Credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DS_Admin_Wise_Receipts {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ], 50 );
        add_action( 'admin_post_ds_approve_wise_receipt', [ $this, 'approve_receipt' ] );
        add_action( 'admin_post_ds_reject_wise_receipt', [ $this, 'reject_receipt' ] );
    }

    public function add_menu() {
        add_submenu_page(
            'ds-backgamom-credits',
            'Comprovantes WISE',
            'Comprovantes WISE',
            'manage_woocommerce',
            'ds-wise-receipts',
            [ $this, 'render_page' ]
        );
    }

    public function render_page() {
        $receipts = $this->get_pending_receipts();
        ?>
        <div class="wrap">
            <h1><?php _e( 'Comprovantes WISE Pendentes', 'ds-backgamom-credits' ); ?></h1>
            
            <?php if ( empty( $receipts ) ) : ?>
                <p><?php _e( 'Nenhum comprovante pendente.', 'ds-backgamom-credits' ); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e( 'Pedido', 'ds-backgamom-credits' ); ?></th>
                            <th><?php _e( 'Cliente', 'ds-backgamom-credits' ); ?></th>
                            <th><?php _e( 'Valor', 'ds-backgamom-credits' ); ?></th>
                            <th><?php _e( 'Data', 'ds-backgamom-credits' ); ?></th>
                            <th><?php _e( 'Comprovante', 'ds-backgamom-credits' ); ?></th>
                            <th><?php _e( 'Ações', 'ds-backgamom-credits' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $receipts as $receipt ) : 
                            $order = wc_get_order( $receipt->order_id );
                            if ( ! $order ) continue;
                        ?>
                        <tr>
                            <td><a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">#<?php echo $order->get_order_number(); ?></a></td>
                            <td><?php echo esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ); ?></td>
                            <td><?php echo $order->get_formatted_order_total(); ?></td>
                            <td><?php echo date_i18n( 'd/m/Y H:i', strtotime( $receipt->created_at ) ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( wp_get_upload_dir()['baseurl'] . str_replace( wp_get_upload_dir()['basedir'], '', $receipt->file_path ) ); ?>" target="_blank" class="button button-small">
                                    <?php _e( 'Ver Comprovante', 'ds-backgamom-credits' ); ?>
                                </a>
                            </td>
                            <td>
                                <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" style="display:inline;">
                                    <?php wp_nonce_field( 'ds_wise_receipt_action' ); ?>
                                    <input type="hidden" name="action" value="ds_approve_wise_receipt">
                                    <input type="hidden" name="receipt_id" value="<?php echo $receipt->id; ?>">
                                    <input type="hidden" name="order_id" value="<?php echo $receipt->order_id; ?>">
                                    <button type="submit" class="button button-primary"><?php _e( 'Aprovar', 'ds-backgamom-credits' ); ?></button>
                                </form>
                                
                                <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" style="display:inline;">
                                    <?php wp_nonce_field( 'ds_wise_receipt_action' ); ?>
                                    <input type="hidden" name="action" value="ds_reject_wise_receipt">
                                    <input type="hidden" name="receipt_id" value="<?php echo $receipt->id; ?>">
                                    <input type="hidden" name="order_id" value="<?php echo $receipt->order_id; ?>">
                                    <button type="submit" class="button" onclick="return confirm('<?php _e( 'Tem certeza que deseja rejeitar?', 'ds-backgamom-credits' ); ?>');"><?php _e( 'Rejeitar', 'ds-backgamom-credits' ); ?></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    private function get_pending_receipts() {
        global $wpdb;
        $table = $wpdb->prefix . 'dsbc_wise_receipts';
        return $wpdb->get_results( "SELECT * FROM $table WHERE status = 'pending' ORDER BY created_at DESC" );
    }

    public function approve_receipt() {
        check_admin_referer( 'ds_wise_receipt_action' );
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'Sem permissão.', 'ds-backgamom-credits' ) );
        }

        $receipt_id = intval( $_POST['receipt_id'] );
        $order_id = intval( $_POST['order_id'] );
        
        global $wpdb;
        $table = $wpdb->prefix . 'dsbc_wise_receipts';
        
        $wpdb->update(
            $table,
            [
                'status'       => 'approved',
                'processed_by' => get_current_user_id(),
                'processed_at' => current_time( 'mysql' ),
            ],
            ['id' => $receipt_id],
            ['%s', '%d', '%s'],
            ['%d']
        );

        $order = wc_get_order( $order_id );
        if ( $order ) {
            $order->payment_complete();
            $order->add_order_note( __( 'Comprovante WISE aprovado.', 'ds-backgamom-credits' ) );
            
            // Notificar cliente
            $this->notify_customer( $order, 'approved' );
        }

        wp_redirect( admin_url( 'admin.php?page=ds-wise-receipts&approved=1' ) );
        exit;
    }

    public function reject_receipt() {
        check_admin_referer( 'ds_wise_receipt_action' );
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'Sem permissão.', 'ds-backgamom-credits' ) );
        }

        $receipt_id = intval( $_POST['receipt_id'] );
        $order_id = intval( $_POST['order_id'] );
        
        global $wpdb;
        $table = $wpdb->prefix . 'dsbc_wise_receipts';
        
        $wpdb->update(
            $table,
            [
                'status'       => 'rejected',
                'processed_by' => get_current_user_id(),
                'processed_at' => current_time( 'mysql' ),
            ],
            ['id' => $receipt_id],
            ['%s', '%d', '%s'],
            ['%d']
        );

        $order = wc_get_order( $order_id );
        if ( $order ) {
            $order->update_status( 'failed', __( 'Comprovante WISE rejeitado.', 'ds-backgamom-credits' ) );
            
            // Notificar cliente
            $this->notify_customer( $order, 'rejected' );
        }

        wp_redirect( admin_url( 'admin.php?page=ds-wise-receipts&rejected=1' ) );
        exit;
    }

    private function notify_customer( $order, $status ) {
        $user_id = $order->get_customer_id();
        if ( ! $user_id ) return;

        if ( class_exists( 'DS_Notification_i18n' ) ) {
            $template = $status === 'approved' ? 'wise_approved' : 'wise_rejected';
            DS_Notification_i18n::send( $user_id, $template, [
                'order_number' => $order->get_order_number(),
                'amount' => $order->get_formatted_order_total(),
                'priority' => 'high'
            ] );
        }
    }
}
