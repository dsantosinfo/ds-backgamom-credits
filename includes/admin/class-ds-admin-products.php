<?php
/**
 * Classe para gerenciar produtos com créditos no painel administrativo
 */

if (!defined('ABSPATH')) {
    exit;
}

class DS_Admin_Products extends DS_Admin_Base {
    
    public function __construct() {
        add_action('wp_ajax_dsbc_create_credit_product', [$this, 'ajax_create_product']);
        add_action('wp_ajax_dsbc_delete_credit_product', [$this, 'ajax_delete_product']);
        add_action('wp_ajax_dsbc_update_credit_product', [$this, 'ajax_update_product']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_media_uploader']);
    }
    
    public function enqueue_media_uploader() {
        wp_enqueue_media();
    }
    
    public function render_page() {
        // Debug: verificar se chegou aqui
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão para acessar esta página.');
        }
        
        $products = $this->get_credit_products();
        
        echo '<div class="wrap">';
        echo '<h1>Produtos de Crédito</h1>';
        echo '<button class="button button-primary" id="dsbc-open-modal" style="margin-bottom: 15px;">Criar Novo Produto</button>';
        
        // Lista de produtos
        echo '<div class="dsbc-card">';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Imagem</th><th>ID</th><th>Nome</th><th>Créditos USD</th><th>Preço BRL</th><th>Status</th><th>Ações</th></tr></thead>';
        echo '<tbody>';
        
        if (empty($products)) {
            echo '<tr><td colspan="7" style="text-align: center; padding: 20px;">Nenhum produto de crédito encontrado.</td></tr>';
        } else {
            foreach ($products as $product) {
                $credits = get_post_meta($product->ID, '_dsbc_credits_amount', true);
                $price = get_post_meta($product->ID, '_regular_price', true);
                $thumbnail_id = get_post_thumbnail_id($product->ID);
                $thumbnail = $thumbnail_id ? wp_get_attachment_image_url($thumbnail_id, 'thumbnail') : '';
                
                echo '<tr>';
                echo '<td>' . ($thumbnail ? '<img src="' . esc_url($thumbnail) . '" style="width:50px;height:50px;object-fit:cover;">' : '—') . '</td>';
                echo '<td>' . $product->ID . '</td>';
                echo '<td><strong>' . esc_html($product->post_title) . '</strong></td>';
                echo '<td>' . number_format($credits, 2) . ' USD</td>';
                echo '<td>R$ ' . number_format($price, 2, ',', '.') . '</td>';
                echo '<td>' . ($product->post_status === 'publish' ? 'Ativo' : 'Inativo') . '</td>';
                echo '<td>';
                echo '<button class="button button-small dsbc-edit-product" data-product-id="' . $product->ID . '" data-name="' . esc_attr($product->post_title) . '" data-credits="' . $credits . '" data-price="' . $price . '" data-description="' . esc_attr($product->post_content) . '" data-thumbnail="' . $thumbnail_id . '">Editar</button> ';
                echo '<button class="button button-small dsbc-delete-product" data-product-id="' . $product->ID . '">Excluir</button>';
                echo '</td>';
                echo '</tr>';
            }
        }
        
        echo '</tbody></table>';
        echo '</div>';
        echo '</div>';
        
        // Modal
        ?>
        <div id="dsbc-product-modal" style="display:none;">
            <div class="dsbc-modal-overlay"></div>
            <div class="dsbc-modal-content">
                <span class="dsbc-modal-close">&times;</span>
                <h2 id="dsbc-modal-title">Criar Novo Produto</h2>
                <form id="dsbc-product-form">
                    <input type="hidden" id="product_id" value="">
                    <table class="form-table">
                        <tr>
                            <th><label for="product_name">Nome do Produto</label></th>
                            <td><input type="text" id="product_name" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th><label for="credit_amount">Quantidade de Créditos (USD)</label></th>
                            <td>
                                <input type="number" id="credit_amount" min="0.01" step="0.01" class="regular-text" required>
                                <p class="description">1 crédito = US$ 1,00</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="product_price">Preço (R$)</label></th>
                            <td><input type="number" id="product_price" min="0.01" step="0.01" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th><label for="product_description">Descrição</label></th>
                            <td><textarea id="product_description" rows="3" class="large-text"></textarea></td>
                        </tr>
                        <tr>
                            <th><label>Imagem Destacada</label></th>
                            <td>
                                <div id="product_image_preview" style="margin-bottom:10px;"></div>
                                <input type="hidden" id="product_image_id" value="">
                                <button type="button" class="button" id="upload_image_button">Selecionar Imagem</button>
                                <button type="button" class="button" id="remove_image_button" style="display:none;">Remover Imagem</button>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary" id="dsbc-submit-btn">Criar Produto</button>
                        <button type="button" class="button" id="dsbc-cancel-btn">Cancelar</button>
                    </p>
                </form>
            </div>
        </div>
        <?php
        
        // JavaScript
        ?>
        <script>
        jQuery(document).ready(function($) {
            let mediaUploader;
            
            // Abrir modal para criar
            $(document).on('click', '#dsbc-open-modal', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $('#product_id').val('');
                $('#product_name').val('');
                $('#credit_amount').val('');
                $('#product_price').val('');
                $('#product_description').val('');
                $('#product_image_id').val('');
                $('#product_image_preview').html('');
                $('#remove_image_button').hide();
                $('#dsbc-modal-title').text('Criar Novo Produto');
                $('#dsbc-submit-btn').text('Criar Produto');
                $('#dsbc-product-modal').show();
            });
            
            // Abrir modal para editar
            $('.dsbc-edit-product').on('click', function() {
                const productId = $(this).data('product-id');
                const thumbnailId = $(this).data('thumbnail');
                
                $('#product_id').val(productId);
                $('#product_name').val($(this).data('name'));
                $('#credit_amount').val($(this).data('credits'));
                $('#product_price').val($(this).data('price'));
                $('#product_description').val($(this).data('description'));
                $('#product_image_id').val(thumbnailId);
                
                if (thumbnailId) {
                    $.post(ajaxurl, {
                        action: 'dsbc_get_image_preview',
                        image_id: thumbnailId
                    }, function(response) {
                        if (response.success) {
                            $('#product_image_preview').html('<img src="' + response.data + '" style="max-width:150px;height:auto;">');
                            $('#remove_image_button').show();
                        }
                    });
                } else {
                    $('#product_image_preview').html('');
                    $('#remove_image_button').hide();
                }
                
                $('#dsbc-modal-title').text('Editar Produto');
                $('#dsbc-submit-btn').text('Atualizar Produto');
                $('#dsbc-product-modal').fadeIn();
            });
            
            // Fechar modal
            $('.dsbc-modal-close, #dsbc-cancel-btn').on('click', function() {
                $('#dsbc-product-modal').fadeOut();
            });
            
            $('.dsbc-modal-overlay').on('click', function() {
                $('#dsbc-product-modal').fadeOut();
            });
            
            // Upload de imagem
            $('#upload_image_button').on('click', function(e) {
                e.preventDefault();
                
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }
                
                mediaUploader = wp.media({
                    title: 'Selecionar Imagem do Produto',
                    button: { text: 'Usar esta imagem' },
                    multiple: false
                });
                
                mediaUploader.on('select', function() {
                    const attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#product_image_id').val(attachment.id);
                    $('#product_image_preview').html('<img src="' + attachment.url + '" style="max-width:150px;height:auto;">');
                    $('#remove_image_button').show();
                });
                
                mediaUploader.open();
            });
            
            // Remover imagem
            $('#remove_image_button').on('click', function() {
                $('#product_image_id').val('');
                $('#product_image_preview').html('');
                $(this).hide();
            });
            
            // Submeter formulário
            $('#dsbc-product-form').on('submit', function(e) {
                e.preventDefault();
                
                const productId = $('#product_id').val();
                const action = productId ? 'dsbc_update_credit_product' : 'dsbc_create_credit_product';
                
                const formData = {
                    action: action,
                    nonce: '<?php echo wp_create_nonce('dsbc_admin_nonce'); ?>',
                    product_name: $('#product_name').val(),
                    credit_amount: $('#credit_amount').val(),
                    product_price: $('#product_price').val(),
                    product_description: $('#product_description').val(),
                    product_image_id: $('#product_image_id').val()
                };
                
                if (productId) {
                    formData.product_id = productId;
                }
                
                $.post(ajaxurl, formData, function(response) {
                    if (response.success) {
                        alert(productId ? 'Produto atualizado com sucesso!' : 'Produto criado com sucesso!');
                        location.reload();
                    } else {
                        alert('Erro: ' + response.data);
                    }
                });
            });
            
            // Excluir produto
            $('.dsbc-delete-product').on('click', function() {
                if (!confirm('Tem certeza que deseja excluir este produto?')) {
                    return;
                }
                
                const productId = $(this).data('product-id');
                
                $.post(ajaxurl, {
                    action: 'dsbc_delete_credit_product',
                    nonce: '<?php echo wp_create_nonce('dsbc_admin_nonce'); ?>',
                    product_id: productId
                }, function(response) {
                    if (response.success) {
                        alert('Produto excluído com sucesso!');
                        location.reload();
                    } else {
                        alert('Erro: ' + response.data);
                    }
                });
            });
        });
        </script>
        
        <style>
        .dsbc-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin-bottom: 20px; }
        #dsbc-product-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 100000; }
        .dsbc-modal-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); }
        .dsbc-modal-content { position: relative; background: #fff; max-width: 600px; margin: 50px auto; padding: 30px; border-radius: 4px; max-height: 90vh; overflow-y: auto; }
        .dsbc-modal-close { position: absolute; top: 10px; right: 15px; font-size: 28px; font-weight: bold; color: #666; cursor: pointer; }
        .dsbc-modal-close:hover { color: #000; }
        </style>
        <?php
    }
    
    public function ajax_create_product() {
        check_ajax_referer('dsbc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão');
        }
        
        $name = sanitize_text_field($_POST['product_name']);
        $credits = floatval($_POST['credit_amount']);
        $price = floatval($_POST['product_price']);
        $description = sanitize_textarea_field($_POST['product_description']);
        $image_id = intval($_POST['product_image_id']);
        
        if (empty($name) || $credits <= 0 || $price <= 0) {
            wp_send_json_error('Dados inválidos');
        }
        
        $product_data = [
            'post_title' => $name,
            'post_content' => $description,
            'post_status' => 'publish',
            'post_type' => 'product'
        ];
        
        $product_id = wp_insert_post($product_data);
        
        if (is_wp_error($product_id)) {
            wp_send_json_error('Erro ao criar produto');
        }
        
        wp_set_object_terms($product_id, 'simple', 'product_type');
        update_post_meta($product_id, '_virtual', 'yes');
        update_post_meta($product_id, '_regular_price', $price);
        update_post_meta($product_id, '_price', $price);
        update_post_meta($product_id, '_dsbc_credits_amount', $credits);
        update_post_meta($product_id, '_manage_stock', 'no');
        update_post_meta($product_id, '_sold_individually', 'no');
        
        if ($image_id) {
            set_post_thumbnail($product_id, $image_id);
        }
        
        wp_send_json_success('Produto criado com sucesso');
    }
    
    public function ajax_update_product() {
        check_ajax_referer('dsbc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão');
        }
        
        $product_id = intval($_POST['product_id']);
        $name = sanitize_text_field($_POST['product_name']);
        $credits = floatval($_POST['credit_amount']);
        $price = floatval($_POST['product_price']);
        $description = sanitize_textarea_field($_POST['product_description']);
        $image_id = intval($_POST['product_image_id']);
        
        if (!$product_id || empty($name) || $credits <= 0 || $price <= 0) {
            wp_send_json_error('Dados inválidos');
        }
        
        $product_data = [
            'ID' => $product_id,
            'post_title' => $name,
            'post_content' => $description
        ];
        
        $result = wp_update_post($product_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error('Erro ao atualizar produto');
        }
        
        update_post_meta($product_id, '_regular_price', $price);
        update_post_meta($product_id, '_price', $price);
        update_post_meta($product_id, '_dsbc_credits_amount', $credits);
        
        if ($image_id) {
            set_post_thumbnail($product_id, $image_id);
        } else {
            delete_post_thumbnail($product_id);
        }
        
        wp_send_json_success('Produto atualizado com sucesso');
    }
    
    public function ajax_delete_product() {
        check_ajax_referer('dsbc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão');
        }
        
        $product_id = intval($_POST['product_id']);
        
        if (!$product_id) {
            wp_send_json_error('ID do produto inválido');
        }
        
        $result = wp_delete_post($product_id, true);
        
        if ($result) {
            wp_send_json_success('Produto excluído com sucesso');
        } else {
            wp_send_json_error('Erro ao excluir produto');
        }
    }
    
    private function get_credit_products() {
        $args = [
            'post_type' => 'product',
            'post_status' => ['publish', 'draft'],
            'meta_query' => [
                [
                    'key' => '_dsbc_credits_amount',
                    'compare' => 'EXISTS'
                ]
            ],
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        return get_posts($args);
    }
}