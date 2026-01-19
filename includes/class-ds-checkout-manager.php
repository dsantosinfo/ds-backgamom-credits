<?php
/**
 * Gerenciador de Checkout - DS Backgamom Credits
 */

if (!defined('ABSPATH')) {
    exit;
}

class DS_Checkout_Manager
{

    public function __construct()
    {
        add_filter('woocommerce_billing_fields', [$this, 'add_billing_cpf_field']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_cpf_field']);
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_cpf_in_admin']);
        add_action('woocommerce_checkout_process', [$this, 'validate_cpf_field']);
        add_filter('woocommerce_checkout_posted_data', [$this, 'auto_fill_cpf_data']);
        add_filter('woocommerce_checkout_get_value', [$this, 'get_cpf_value'], 10, 2);
        add_action('wp_footer', [$this, 'enqueue_checkout_scripts']);
        add_action('woocommerce_checkout_update_user_meta', [$this, 'save_cpf_to_user_meta']);
        add_shortcode('ds_checkout_fluido', [$this, 'shortcode_checkout_fluido']);
    }

    /**
     * Adiciona campo CPF ao checkout.
     */
    public function add_billing_cpf_field($fields)
    {
        $default_cpf = '';
        if (is_user_logged_in()) {
            $default_cpf = get_user_meta(get_current_user_id(), 'billing_cpf', true);
        }

        $fields['billing_cpf'] = [
            'label' => 'CPF',
            'placeholder' => '000.000.000-00',
            'required' => false,
            'class' => ['form-row-wide'],
            'priority' => 25,
            'default' => $default_cpf,
            'custom_attributes' => [
                'data-mask' => '000.000.000-00'
            ]
        ];
        return $fields;
    }

    /**
     * Valida CPF obrigatório para Brasil.
     */
    public function validate_cpf_field()
    {
        if (($_POST['billing_country'] ?? '') !== 'BR') {
            return;
        }

        $cpf = $_POST['billing_cpf'] ?? '';

        if (empty($cpf) && is_user_logged_in()) {
            $saved_cpf = get_user_meta(get_current_user_id(), 'billing_cpf', true);
            if (empty($saved_cpf)) {
                $saved_cpf = get_user_meta(get_current_user_id(), 'user_cpf', true);
            }

            if (!empty($saved_cpf)) {
                $_POST['billing_cpf'] = $saved_cpf;
                return;
            }
        }

        if (empty($cpf)) {
            wc_add_notice('CPF é obrigatório para clientes do Brasil.', 'error');
        }
    }

    /**
     * Salva CPF no pedido.
     */
    public function save_cpf_field($order_id)
    {
        $cpf = $_POST['billing_cpf'] ?? '';

        if (empty($cpf) && is_user_logged_in()) {
            $cpf = get_user_meta(get_current_user_id(), 'billing_cpf', true);
            if (empty($cpf)) {
                $cpf = get_user_meta(get_current_user_id(), 'user_cpf', true);
            }
        }

        if (!empty($cpf)) {
            $cpf_clean = preg_replace('/[^0-9]/', '', $cpf);
            update_post_meta($order_id, '_billing_cpf', $cpf_clean);
        }
    }

    /**
     * Salva dados de cobrança no perfil do usuário.
     */
    public function save_cpf_to_user_meta($user_id)
    {
        // Salvar nome
        if (!empty($_POST['billing_first_name'])) {
            update_user_meta($user_id, 'billing_first_name', sanitize_text_field($_POST['billing_first_name']));
        }
        
        // Salvar sobrenome
        if (!empty($_POST['billing_last_name'])) {
            update_user_meta($user_id, 'billing_last_name', sanitize_text_field($_POST['billing_last_name']));
        }
        
        // Salvar CPF
        if (!empty($_POST['billing_cpf'])) {
            $cpf = preg_replace('/[^0-9]/', '', $_POST['billing_cpf']);
            update_user_meta($user_id, 'billing_cpf', $cpf);
            update_user_meta($user_id, 'user_cpf', $cpf);
        }
    }

    /**
     * Exibe CPF no admin do pedido.
     */
    public function display_cpf_in_admin($order)
    {
        $cpf = get_post_meta($order->get_id(), '_billing_cpf', true);
        if ($cpf) {
            echo '<p><strong>CPF:</strong> ' . esc_html($cpf) . '</p>';
        }
    }

    /**
     * Scripts para o checkout.
     */
    public function enqueue_checkout_scripts()
    {
        if (!is_checkout()) {
            return;
        }
        
        // Buscar CPF do usuário
        $user_cpf = '';
        if (is_user_logged_in()) {
            $user_cpf = get_user_meta(get_current_user_id(), 'billing_cpf', true);
            if (empty($user_cpf)) {
                $user_cpf = get_user_meta(get_current_user_id(), 'user_cpf', true);
            }
            // Formatar CPF se tiver apenas números
            if (!empty($user_cpf) && strlen($user_cpf) === 11) {
                $user_cpf = preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $user_cpf);
            }
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var savedCpf = <?php echo json_encode($user_cpf); ?>;
            
            function toggleCPFField() {
                var country = $("#billing_country").val();
                var cpfField = $("#billing_cpf_field");
                var cpfInput = $("#billing_cpf");

                if (country === "BR") {
                    cpfField.show();
                    // Preencher com CPF salvo se o campo estiver vazio
                    if (savedCpf && !cpfInput.val()) {
                        cpfInput.val(savedCpf);
                    }
                } else {
                    cpfField.hide();
                    cpfInput.val("");
                }
            }

            toggleCPFField();
            $(document.body).on("change", "#billing_country", toggleCPFField);
            $(document.body).on("updated_checkout", toggleCPFField);

            $("#billing_cpf").on("input", function() {
                var value = this.value.replace(/\D/g, "");
                value = value.replace(/(\d{3})(\d)/, "$1.$2");
                value = value.replace(/(\d{3})(\d)/, "$1.$2");
                value = value.replace(/(\d{3})(\d{1,2})$/, "$1-$2");
                this.value = value;
            });
        });
        </script>
        <?php
    }

    /**
     * Preenche automaticamente dados do usuário no checkout.
     */
    public function auto_fill_cpf_data($data)
    {
        if (!is_user_logged_in()) {
            return $data;
        }
        
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        
        // Auto-preencher nome e sobrenome se vazios
        if (empty($data['billing_first_name'])) {
            $first_name = get_user_meta($user_id, 'billing_first_name', true) ?: $user->first_name;
            if ($first_name) {
                $data['billing_first_name'] = $first_name;
                $_POST['billing_first_name'] = $first_name;
            }
        }
        
        if (empty($data['billing_last_name'])) {
            $last_name = get_user_meta($user_id, 'billing_last_name', true) ?: $user->last_name;
            if ($last_name) {
                $data['billing_last_name'] = $last_name;
                $_POST['billing_last_name'] = $last_name;
            }
        }
        
        // Auto-preencher CPF para Brasil
        if (empty($data['billing_cpf']) && $data['billing_country'] === 'BR') {
            $user_cpf = get_user_meta($user_id, 'billing_cpf', true);
            if (empty($user_cpf)) {
                $user_cpf = get_user_meta($user_id, 'user_cpf', true);
            }
            if ($user_cpf) {
                $data['billing_cpf'] = $user_cpf;
                $_POST['billing_cpf'] = $user_cpf;
            }
        }
        
        return $data;
    }

    /**
     * Retorna valores salvos para campos do checkout.
     */
    public function get_cpf_value($value, $input)
    {
        if (!is_user_logged_in() || !empty($value)) {
            return $value;
        }
        
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        
        switch ($input) {
            case 'billing_first_name':
                return get_user_meta($user_id, 'billing_first_name', true) ?: $user->first_name;
                
            case 'billing_last_name':
                return get_user_meta($user_id, 'billing_last_name', true) ?: $user->last_name;
                
            case 'billing_cpf':
                $user_cpf = get_user_meta($user_id, 'billing_cpf', true);
                if (empty($user_cpf)) {
                    $user_cpf = get_user_meta($user_id, 'user_cpf', true);
                }
                if ($user_cpf && strlen($user_cpf) === 11) {
                    return preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $user_cpf);
                }
                return $user_cpf;
        }
        
        return $value;
    }

    /**
     * Shortcode para checkout fluido.
     */
    public function shortcode_checkout_fluido($atts = [])
    {
        if (!is_user_logged_in()) {
            return '';
        }

        $user_id = get_current_user_id();
        $user_info = get_userdata($user_id);

        $billing_cpf = get_user_meta($user_id, 'billing_cpf', true);
        $billing_first_name = get_user_meta($user_id, 'billing_first_name', true) ?: $user_info->first_name;
        $billing_last_name = get_user_meta($user_id, 'billing_last_name', true) ?: $user_info->last_name;

        ob_start();
        ?>
        <style>
            .ds-hidden-checkout-field {
                display: none !important;
            }
        </style>

        <?php
        return ob_get_clean();
    }
}