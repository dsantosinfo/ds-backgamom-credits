<?php
/**
 * Gateway de Pagamento WISE (Manual)
 *
 * @package DS_Backgamom_Credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DS_Wise_Gateway extends WC_Payment_Gateway {

    private $wise_email;
    private $wise_instructions;
    private $max_file_size;
    private $allowed_formats;
    private $auto_approve;
    private $admin_notification;
    private $admin_email;
    private $supported_currencies;

    public function __construct() {
        $this->id                 = 'ds_wise';
        $this->icon               = '';
        $this->has_fields         = true;
        $this->method_title       = __( 'WISE - Transferência Internacional', 'ds-backgamom-credits' );
        $this->method_description = __( 'Pagamento manual via WISE com upload de comprovante. Aceita múltiplas moedas.', 'ds-backgamom-credits' );
        $this->supports           = ['products'];

        $this->init_form_fields();
        $this->init_settings();

        $this->title              = $this->get_option( 'title' );
        $this->description        = $this->get_option( 'description' );
        $this->wise_email         = $this->get_option( 'wise_email', 'payments@backgamombrasil.com' );
        $this->wise_instructions  = $this->get_option( 'wise_instructions' );
        $this->max_file_size      = $this->get_option( 'max_file_size', '5' );
        $this->allowed_formats    = $this->get_option( 'allowed_formats', ['jpg', 'png', 'pdf'] );
        $this->auto_approve       = $this->get_option( 'auto_approve', 'no' ) === 'yes';
        $this->admin_notification = $this->get_option( 'admin_notification', 'yes' ) === 'yes';
        $this->admin_email        = $this->get_option( 'admin_email', get_option( 'admin_email' ) );
        $this->supported_currencies = $this->get_option( 'supported_currencies', ['BRL', 'USD'] );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
        add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'thankyou_page' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );
    }
    
    public function is_available() {
        if ( 'no' === $this->enabled ) {
            return false;
        }
        
        // Verificar se a moeda do carrinho é suportada
        if ( class_exists( 'DS_Currency_Manager' ) && WC()->cart ) {
            $currency_manager = new DS_Currency_Manager();
            $cart_currency = $currency_manager->get_cart_currency();
            $supported = is_array( $this->supported_currencies ) ? $this->supported_currencies : ['BRL', 'USD'];
            
            if ( ! in_array( $cart_currency, $supported ) ) {
                return false;
            }
        }
        
        return true;
    }

    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title'   => __( 'Habilitar/Desabilitar', 'ds-backgamom-credits' ),
                'type'    => 'checkbox',
                'label'   => __( 'Habilitar Gateway WISE', 'ds-backgamom-credits' ),
                'default' => 'no',
            ],
            'title' => [
                'title'       => __( 'Título', 'ds-backgamom-credits' ),
                'type'        => 'text',
                'description' => __( 'Título que o cliente verá durante o checkout.', 'ds-backgamom-credits' ),
                'default'     => __( 'WISE - Transferência Internacional', 'ds-backgamom-credits' ),
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => __( 'Descrição', 'ds-backgamom-credits' ),
                'type'        => 'textarea',
                'description' => __( 'Descrição que o cliente verá durante o checkout.', 'ds-backgamom-credits' ),
                'default'     => __( 'Realize a transferência via WISE e envie o comprovante.', 'ds-backgamom-credits' ),
            ],
            'wise_email' => [
                'title'       => __( 'Email WISE para Recebimento', 'ds-backgamom-credits' ),
                'type'        => 'email',
                'description' => __( 'Email da sua conta WISE que receberá os pagamentos. Este email será exibido para os clientes.', 'ds-backgamom-credits' ),
                'default'     => 'payments@backgamombrasil.com',
                'desc_tip'    => true,
                'placeholder' => 'seu-email@wise.com',
            ],
            'wise_instructions' => [
                'title'       => __( 'Instruções de Pagamento', 'ds-backgamom-credits' ),
                'type'        => 'textarea',
                'description' => __( 'Instruções adicionais que aparecerão no checkout. Use {email} para inserir o email WISE automaticamente.', 'ds-backgamom-credits' ),
                'default'     => __( 'Envie o pagamento para o email {email} via WISE e faça upload do comprovante abaixo.', 'ds-backgamom-credits' ),
                'css'         => 'min-height: 100px;',
            ],
            'max_file_size' => [
                'title'       => __( 'Tamanho Máximo do Arquivo', 'ds-backgamom-credits' ),
                'type'        => 'select',
                'description' => __( 'Tamanho máximo permitido para upload de comprovantes.', 'ds-backgamom-credits' ),
                'default'     => '5',
                'options'     => [
                    '2'  => '2 MB',
                    '5'  => '5 MB',
                    '10' => '10 MB',
                ],
                'desc_tip'    => true,
            ],
            'allowed_formats' => [
                'title'       => __( 'Formatos Permitidos', 'ds-backgamom-credits' ),
                'type'        => 'multiselect',
                'description' => __( 'Formatos de arquivo aceitos para comprovantes.', 'ds-backgamom-credits' ),
                'default'     => ['jpg', 'png', 'pdf'],
                'options'     => [
                    'jpg'  => 'JPG/JPEG',
                    'png'  => 'PNG',
                    'pdf'  => 'PDF',
                    'gif'  => 'GIF',
                    'webp' => 'WebP',
                ],
                'desc_tip'    => true,
            ],
            'auto_approve' => [
                'title'       => __( 'Aprovação Automática', 'ds-backgamom-credits' ),
                'type'        => 'checkbox',
                'label'       => __( 'Aprovar pagamentos automaticamente (não recomendado)', 'ds-backgamom-credits' ),
                'description' => __( 'Se ativado, os pedidos serão aprovados automaticamente após o upload do comprovante, sem revisão manual.', 'ds-backgamom-credits' ),
                'default'     => 'no',
            ],
            'admin_notification' => [
                'title'       => __( 'Notificar Admin', 'ds-backgamom-credits' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enviar email ao admin quando houver novo comprovante', 'ds-backgamom-credits' ),
                'default'     => 'yes',
            ],
            'admin_email' => [
                'title'       => __( 'Email do Administrador', 'ds-backgamom-credits' ),
                'type'        => 'email',
                'description' => __( 'Email que receberá notificações de novos comprovantes. Deixe em branco para usar o email padrão do WordPress.', 'ds-backgamom-credits' ),
                'default'     => get_option( 'admin_email' ),
                'desc_tip'    => true,
            ],
            'supported_currencies' => [
                'title'       => __( 'Moedas Aceitas', 'ds-backgamom-credits' ),
                'type'        => 'multiselect',
                'description' => __( 'Selecione as moedas que este gateway aceita.', 'ds-backgamom-credits' ),
                'default'     => ['BRL', 'USD'],
                'options'     => [
                    'BRL' => 'Real Brasileiro (R$)',
                    'USD' => 'Dólar Americano ($)',
                    'EUR' => 'Euro (€)',
                    'GBP' => 'Libra Esterlina (£)',
                ],
                'desc_tip'    => true,
            ],
        ];
    }

    public function payment_fields() {
        if ( $this->description ) {
            echo wpautop( wptexturize( $this->description ) );
        }
        
        // Exibir instruções personalizadas
        if ( $this->wise_instructions ) {
            $instructions = str_replace( '{email}', $this->wise_email, $this->wise_instructions );
            echo '<div class="wise-instructions">' . wpautop( wptexturize( $instructions ) ) . '</div>';
        }
        ?>
        <div id="ds-wise-payment-form">
            <p class="wise-email-display"><strong><?php _e( 'Email para pagamento:', 'ds-backgamom-credits' ); ?></strong> <code><?php echo esc_html( $this->wise_email ); ?></code></p>
            
            <p class="form-row form-row-wide">
                <label><?php _e( 'Comprovante de Pagamento', 'ds-backgamom-credits' ); ?> <span class="required">*</span></label>
                <input type="file" name="wise_receipt" id="wise_receipt" accept="<?php echo esc_attr( $this->get_accept_attribute() ); ?>" required />
                <small><?php echo esc_html( $this->get_file_requirements_text() ); ?></small>
            </p>
            
            <p class="form-row form-row-wide">
                <label><?php _e( 'Observações (opcional)', 'ds-backgamom-credits' ); ?></label>
                <textarea name="wise_notes" id="wise_notes" rows="3" placeholder="<?php _e( 'Informações adicionais sobre o pagamento', 'ds-backgamom-credits' ); ?>"></textarea>
            </p>
        </div>
        <?php
    }

    public function payment_scripts() {
        if ( ! is_checkout() ) {
            return;
        }
        ?>
        <style>
        .wise-email-display code { background: #f0f0f0; padding: 5px 10px; border-radius: 3px; font-size: 14px; }
        .wise-instructions { background: #e7f3ff; border-left: 4px solid #2196F3; padding: 12px; margin: 10px 0; }
        </style>
        <script>
        jQuery(document).ready(function($) {
            var maxSize = <?php echo intval( $this->max_file_size ) * 1048576; ?>;
            $('#wise_receipt').on('change', function() {
                var file = this.files[0];
                if (file) {
                    if (file.size > maxSize) {
                        alert('<?php printf( __( 'Arquivo muito grande. Máximo %sMB.', 'ds-backgamom-credits' ), $this->max_file_size ); ?>');
                        $(this).val('');
                    }
                }
            });
        });
        </script>
        <?php
    }

    public function validate_fields() {
        if ( empty( $_FILES['wise_receipt']['name'] ) ) {
            wc_add_notice( __( 'Por favor, envie o comprovante de pagamento.', 'ds-backgamom-credits' ), 'error' );
            return false;
        }

        $file = $_FILES['wise_receipt'];
        $allowed = $this->get_allowed_mime_types();
        
        if ( ! in_array( $file['type'], $allowed ) ) {
            wc_add_notice( __( 'Formato de arquivo não permitido.', 'ds-backgamom-credits' ), 'error' );
            return false;
        }

        $max_size = intval( $this->max_file_size ) * 1048576;
        if ( $file['size'] > $max_size ) {
            wc_add_notice( sprintf( __( 'Arquivo muito grande. Máximo %sMB.', 'ds-backgamom-credits' ), $this->max_file_size ), 'error' );
            return false;
        }

        return true;
    }

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

        // Upload do comprovante
        $upload = $this->handle_receipt_upload( $order_id );
        
        if ( is_wp_error( $upload ) ) {
            wc_add_notice( $upload->get_error_message(), 'error' );
            return ['result' => 'failure'];
        }

        // Salvar dados no pedido
        $order->update_meta_data( '_wise_receipt_path', $upload['file'] );
        $order->update_meta_data( '_wise_receipt_url', $upload['url'] );
        $order->update_meta_data( '_wise_notes', sanitize_textarea_field( $_POST['wise_notes'] ?? '' ) );
        $order->update_meta_data( '_wise_email', $this->wise_email );
        
        // Salvar moeda do pedido
        if ( class_exists( 'DS_Currency_Manager' ) ) {
            $currency_manager = new DS_Currency_Manager();
            $currency = $currency_manager->get_cart_currency();
            $order->update_meta_data( '_order_currency', $currency );
        }
        
        $order->update_status( 'on-hold', __( 'Aguardando aprovação do comprovante WISE.', 'ds-backgamom-credits' ) );
        $order->save();

        // Salvar na tabela de comprovantes
        $this->save_receipt_record( $order_id, $upload );

        // Aprovação automática se configurado
        if ( $this->auto_approve ) {
            $order->payment_complete();
            $order->add_order_note( __( 'Pagamento WISE aprovado automaticamente.', 'ds-backgamom-credits' ) );
        } else {
            // Notificar admin apenas se não for aprovação automática
            if ( $this->admin_notification ) {
                $this->notify_admin_new_receipt( $order_id );
            }
        }

        wc_reduce_stock_levels( $order_id );
        WC()->cart->empty_cart();

        return [
            'result'   => 'success',
            'redirect' => $this->get_return_url( $order ),
        ];
    }

    private function handle_receipt_upload( $order_id ) {
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
        }

        $file = $_FILES['wise_receipt'];
        $upload_overrides = ['test_form' => false];
        
        add_filter( 'upload_dir', function( $dir ) use ( $order_id ) {
            $subdir = '/wise-receipts/' . date( 'Y/m' );
            $dir['path']   = $dir['basedir'] . $subdir;
            $dir['url']    = $dir['baseurl'] . $subdir;
            $dir['subdir'] = $subdir;
            return $dir;
        });

        $upload = wp_handle_upload( $file, $upload_overrides );

        if ( isset( $upload['error'] ) ) {
            return new WP_Error( 'upload_error', $upload['error'] );
        }

        return $upload;
    }

    private function save_receipt_record( $order_id, $upload ) {
        global $wpdb;
        
        $order = wc_get_order( $order_id );
        $table = $wpdb->prefix . 'dsbc_wise_receipts';
        
        $wpdb->insert(
            $table,
            [
                'order_id'   => $order_id,
                'user_id'    => $order->get_customer_id(),
                'file_path'  => $upload['file'],
                'file_name'  => basename( $upload['file'] ),
                'notes'      => sanitize_textarea_field( $_POST['wise_notes'] ?? '' ),
                'status'     => 'pending',
                'created_at' => current_time( 'mysql' ),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s']
        );
    }

    private function notify_admin_new_receipt( $order_id ) {
        $order = wc_get_order( $order_id );
        
        $subject = sprintf( __( 'Novo comprovante WISE - Pedido #%s', 'ds-backgamom-credits' ), $order->get_order_number() );
        $message = sprintf(
            __( 'Um novo comprovante WISE foi enviado para o pedido #%s.\n\nCliente: %s\nValor: %s\n\nAcesse o painel administrativo para aprovar ou rejeitar:\n%s', 'ds-backgamom-credits' ),
            $order->get_order_number(),
            $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            $order->get_formatted_order_total(),
            admin_url( 'admin.php?page=ds-wise-receipts' )
        );
        
        wp_mail( $this->admin_email, $subject, $message );
    }

    public function thankyou_page( $order_id ) {
        $order = wc_get_order( $order_id );
        $message = $this->auto_approve 
            ? __( 'Seu comprovante foi recebido e o pagamento foi aprovado automaticamente. Seus créditos já estão disponíveis!', 'ds-backgamom-credits' )
            : __( 'Seu comprovante foi recebido e está em análise. Você será notificado quando o pagamento for aprovado.', 'ds-backgamom-credits' );
        ?>
        <div class="woocommerce-order-wise-info" style="background: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; margin: 20px 0;">
            <h2><?php _e( 'Comprovante Enviado', 'ds-backgamom-credits' ); ?></h2>
            <p><?php echo esc_html( $message ); ?></p>
        </div>
        <?php
    }
    
    private function get_accept_attribute() {
        $formats = is_array( $this->allowed_formats ) ? $this->allowed_formats : ['jpg', 'png', 'pdf'];
        $accept = [];
        
        foreach ( $formats as $format ) {
            switch ( $format ) {
                case 'jpg':
                    $accept[] = 'image/jpeg';
                    $accept[] = 'image/jpg';
                    break;
                case 'png':
                    $accept[] = 'image/png';
                    break;
                case 'pdf':
                    $accept[] = 'application/pdf';
                    break;
                case 'gif':
                    $accept[] = 'image/gif';
                    break;
                case 'webp':
                    $accept[] = 'image/webp';
                    break;
            }
        }
        
        return implode( ',', array_unique( $accept ) );
    }
    
    private function get_allowed_mime_types() {
        $formats = is_array( $this->allowed_formats ) ? $this->allowed_formats : ['jpg', 'png', 'pdf'];
        $mimes = [];
        
        foreach ( $formats as $format ) {
            switch ( $format ) {
                case 'jpg':
                    $mimes[] = 'image/jpeg';
                    $mimes[] = 'image/jpg';
                    break;
                case 'png':
                    $mimes[] = 'image/png';
                    break;
                case 'pdf':
                    $mimes[] = 'application/pdf';
                    break;
                case 'gif':
                    $mimes[] = 'image/gif';
                    break;
                case 'webp':
                    $mimes[] = 'image/webp';
                    break;
            }
        }
        
        return array_unique( $mimes );
    }
    
    private function get_file_requirements_text() {
        $formats = is_array( $this->allowed_formats ) ? $this->allowed_formats : ['jpg', 'png', 'pdf'];
        $formats_text = strtoupper( implode( ', ', $formats ) );
        
        return sprintf(
            __( 'Formatos aceitos: %s (máx. %sMB)', 'ds-backgamom-credits' ),
            $formats_text,
            $this->max_file_size
        );
    }
}
