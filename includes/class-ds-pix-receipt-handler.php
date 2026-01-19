<?php
/**
 * Handler para upload de comprovantes PIX
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DS_Pix_Receipt_Handler {

    public function __construct() {
        // Checkout - adicionar campo de upload
        add_action( 'woocommerce_after_order_notes', [ $this, 'add_receipt_field_checkout' ] );
        add_action( 'woocommerce_checkout_process', [ $this, 'validate_receipt_field' ] );
        add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'save_receipt_checkout' ] );
        
        // Thank you page - adicionar upload
        add_action( 'woocommerce_thankyou', [ $this, 'add_receipt_upload_thankyou' ], 20 );
        
        // Perfil do usuário - adicionar seção de pagamentos pendentes
        add_action( 'woocommerce_account_dashboard', [ $this, 'add_pending_payments_section' ] );
        
        // AJAX handlers
        add_action( 'wp_ajax_dsbc_upload_receipt', [ $this, 'ajax_upload_receipt' ] );
        add_action( 'wp_ajax_dsbc_upload_order_receipt', [ $this, 'ajax_upload_order_receipt' ] );
        
        // Scripts
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    public function enqueue_scripts() {
        if ( is_account_page() || is_checkout() || is_order_received_page() ) {
            wp_enqueue_script( 'dsbc-receipt-upload', DSBC_PLUGIN_URL . 'assets/js/receipt-upload.js', ['jquery'], DSBC_VERSION, true );
            wp_localize_script( 'dsbc-receipt-upload', 'dsbcReceipt', [
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'dsbc_receipt_nonce' )
            ]);
        }
    }

    public function add_receipt_field_checkout( $checkout ) {
        $chosen_payment = WC()->session->get( 'chosen_payment_method' );
        
        if ( $chosen_payment === 'ds_pix' || $chosen_payment === 'dsantos_pix' ) {
            echo '<div id="dsbc-receipt-field" style="margin-top: 20px;">';
            woocommerce_form_field( 'pix_receipt', [
                'type' => 'file',
                'label' => __( 'Comprovante PIX (Opcional)', 'ds-backgamom-credits' ),
                'description' => __( 'Se já realizou o pagamento, anexe o comprovante', 'ds-backgamom-credits' ),
                'required' => false,
            ], $checkout->get_value( 'pix_receipt' ) );
            echo '</div>';
        }
    }

    public function validate_receipt_field() {
        // Validação opcional - apenas verifica formato se enviado
        if ( isset( $_FILES['pix_receipt'] ) && $_FILES['pix_receipt']['error'] === UPLOAD_ERR_OK ) {
            $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
            $ext = strtolower( pathinfo( $_FILES['pix_receipt']['name'], PATHINFO_EXTENSION ) );
            if ( ! in_array( $ext, $allowed ) ) {
                wc_add_notice( __( 'Formato de arquivo inválido. Use JPG, PNG ou PDF.', 'ds-backgamom-credits' ), 'error' );
            }
        }
    }

    public function save_receipt_checkout( $order_id ) {
        if ( isset( $_FILES['pix_receipt'] ) && $_FILES['pix_receipt']['error'] === UPLOAD_ERR_OK ) {
            $upload = wp_handle_upload( $_FILES['pix_receipt'], ['test_form' => false] );
            if ( $upload && ! isset( $upload['error'] ) ) {
                update_post_meta( $order_id, '_pix_receipt_url', $upload['url'] );
                $order = wc_get_order( $order_id );
                if ( $order ) {
                    $order->update_status( 'on-hold', 'Comprovante PIX enviado no checkout - Aguardando aprovação.' );
                }
            }
        }
    }

    public function add_receipt_upload_thankyou( $order_id ) {
        $order = wc_get_order( $order_id );
        $payment_method = $order->get_payment_method();
        
        if ( ! $order || ( $payment_method !== 'ds_pix' && $payment_method !== 'dsantos_pix' ) ) {
            return;
        }

        $receipt = $order->get_meta( '_pix_receipt_url' );
        ?>
        <div class="dsbc-receipt-upload-section" style="margin-top: 20px; padding: 20px; background: #f9f9f9; border: 1px solid #ddd;">
            <h3><?php _e( 'Comprovante de Pagamento', 'ds-backgamom-credits' ); ?></h3>
            <?php if ( $receipt ) : ?>
                <p style="color: green;">✓ Comprovante enviado com sucesso!</p>
                <a href="<?php echo esc_url( $receipt ); ?>" target="_blank" class="button"><?php _e( 'Ver Comprovante', 'ds-backgamom-credits' ); ?></a>
            <?php else : ?>
                <p><?php _e( 'Envie o comprovante do pagamento PIX:', 'ds-backgamom-credits' ); ?></p>
                <form id="dsbc-order-receipt-form" enctype="multipart/form-data">
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                    <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf" required>
                    <button type="submit" class="button alt"><?php _e( 'Enviar Comprovante', 'ds-backgamom-credits' ); ?></button>
                    <span class="dsbc-upload-feedback"></span>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    public function add_pending_payments_section() {
        $user_id = get_current_user_id();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'dsbc_credit_logs';
        $pending = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND payment_status IN ('pending', 'overdue') ORDER BY created_at DESC",
            $user_id
        ));

        if ( empty( $pending ) ) {
            return;
        }
        ?>
        <div class="dsbc-pending-payments" style="margin: 20px 0; padding: 20px; background: #fff3cd; border-left: 4px solid #ffc107;">
            <h3><?php _e( 'Pagamentos Pendentes', 'ds-backgamom-credits' ); ?></h3>
            <?php foreach ( $pending as $payment ) : ?>
                <div class="payment-item" style="margin: 15px 0; padding: 15px; background: white; border: 1px solid #ddd;">
                    <p><strong><?php echo number_format( $payment->amount, 0, ',', '.' ); ?> créditos</strong></p>
                    <p><?php echo esc_html( $payment->observation ); ?></p>
                    <?php if ( $payment->payment_due_date ) : ?>
                        <p><small>Vencimento: <?php echo date_i18n( 'd/m/Y', strtotime( $payment->payment_due_date ) ); ?></small></p>
                    <?php endif; ?>
                    
                    <?php if ( $payment->payment_receipt ) : ?>
                        <p style="color: green;">✓ Comprovante enviado</p>
                        <a href="<?php echo esc_url( $payment->payment_receipt ); ?>" target="_blank" class="button button-small"><?php _e( 'Ver Comprovante', 'ds-backgamom-credits' ); ?></a>
                    <?php else : ?>
                        <form class="dsbc-receipt-form" data-log-id="<?php echo $payment->id; ?>" enctype="multipart/form-data">
                            <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf" required>
                            <button type="submit" class="button"><?php _e( 'Enviar Comprovante', 'ds-backgamom-credits' ); ?></button>
                            <span class="upload-feedback"></span>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    public function ajax_upload_receipt() {
        check_ajax_referer( 'dsbc_receipt_nonce', 'nonce' );
        
        $log_id = intval( $_POST['log_id'] );
        if ( ! $log_id ) {
            wp_send_json_error( 'ID inválido' );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'dsbc_credit_logs';
        $log = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $log_id ) );
        
        if ( ! $log || $log->user_id != get_current_user_id() ) {
            wp_send_json_error( 'Permissão negada' );
        }

        if ( isset( $_FILES['receipt'] ) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK ) {
            $upload = wp_handle_upload( $_FILES['receipt'], ['test_form' => false] );
            if ( $upload && ! isset( $upload['error'] ) ) {
                $wpdb->update(
                    $table_name,
                    ['payment_receipt' => $upload['url']],
                    ['id' => $log_id],
                    ['%s'],
                    ['%d']
                );
                wp_send_json_success( 'Comprovante enviado com sucesso!' );
            }
        }
        
        wp_send_json_error( 'Erro no upload' );
    }

    public function ajax_upload_order_receipt() {
        check_ajax_referer( 'dsbc_receipt_nonce', 'nonce' );
        
        $order_id = intval( $_POST['order_id'] );
        if ( ! $order_id ) {
            wp_send_json_error( 'ID inválido' );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order || $order->get_user_id() != get_current_user_id() ) {
            wp_send_json_error( 'Permissão negada' );
        }

        if ( isset( $_FILES['receipt'] ) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK ) {
            $upload = wp_handle_upload( $_FILES['receipt'], ['test_form' => false] );
            if ( $upload && ! isset( $upload['error'] ) ) {
                update_post_meta( $order_id, '_pix_receipt_url', $upload['url'] );
                $order->update_status( 'on-hold', 'Comprovante PIX enviado pelo cliente - Aguardando aprovação.' );
                $order->add_order_note( 'Comprovante PIX enviado pelo cliente.' );
                wp_send_json_success( 'Comprovante enviado com sucesso!' );
            }
        }
        
        wp_send_json_error( 'Erro no upload' );
    }
}
