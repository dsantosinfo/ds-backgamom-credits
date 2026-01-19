<?php
/**
 * Manipulador Completo de Saques DS Backgamom Credits
 * Movido do plugin backgammon-challonge
 */

if (!defined('ABSPATH')) {
    exit;
}

class DS_Withdrawal_Handler {

    private $form_id = null;
    private $admin_phone;

    const STATUS_PENDING = 'Pendente';
    const STATUS_APPROVED = 'Aprovado';
    const STATUS_REJECTED = 'Rejeitado';

    public function __construct() {
        add_action('init', [$this, 'init_withdrawal_handler']);
    }

    public function init_withdrawal_handler() {
        $settings = get_option('ds_backgamom_credits_settings', []);
        $this->form_id = $settings['withdrawal_form_id'] ?? null;
        $this->admin_phone = $this->get_admin_phone();

        if (!empty($this->form_id) && class_exists('\GFCommon') && class_exists('DS_Credit_Manager')) {
            add_filter('gform_pre_render_' . $this->form_id, [$this, 'populate_current_balance']);
            add_filter('gform_validation_' . $this->form_id, [$this, 'validate_withdrawal']);
            add_action('gform_after_submission_' . $this->form_id, [$this, 'process_withdrawal_request'], 10, 2);
            add_filter('gform_entry_list_action_links_' . $this->form_id, [$this, 'add_entry_list_actions_filter'], 10, 3);
            add_action('wp_ajax_ds_approve_withdrawal', [$this, 'ajax_approve_withdrawal']);
            add_action('wp_ajax_ds_reject_withdrawal', [$this, 'ajax_reject_withdrawal']);
        }
    }

    public function populate_current_balance($form) {
        if (!class_exists('DS_Credit_Manager')) {
            return $form;
        }
        
        $settings = get_option('ds_backgamom_credits_settings', []);
        $field_mapping = $settings['field_mapping'] ?? [];
        $balance_field_id = $field_mapping['balance'] ?? null;
        $user_id_field_id = $field_mapping['user_id'] ?? null;
        
        if (!$balance_field_id || !$user_id_field_id) {
            return $form;
        }
        
        $user_id = get_current_user_id();
        $current_balance = DS_Credit_Manager::get_balance($user_id);

        foreach ($form['fields'] as &$field) {
            // Preencher saldo atual
            if ($field->id == $balance_field_id) {
                $field->defaultValue = $current_balance;
            }
            // Preencher ID do usuário
            if ($field->id == $user_id_field_id) {
                $field->defaultValue = $user_id;
            }
        }
        return $form;
    }

    public function validate_withdrawal($validation_result) {
        if (!function_exists('get_field') || !class_exists('DS_Credit_Manager')) {
            $validation_result['is_valid'] = false;
            return $validation_result;
        }

        $form = $validation_result['form'];
        
        // Usar mapeamento de campos
        $settings = get_option('ds_backgamom_credits_settings', []);
        $field_mapping = $settings['field_mapping'] ?? [];
        
        $amount_field_id = $field_mapping['amount'] ?? null;
        $method_field_id = $field_mapping['method'] ?? null;
        $user_id_field_id = $field_mapping['user_id'] ?? null;
        $balance_field_id = $field_mapping['balance'] ?? null;

        if (!$amount_field_id || !$method_field_id || !$user_id_field_id || !$balance_field_id) {
            $this->set_form_error($form, "Erro interno: Campos obrigatórios não encontrados no formulário.");
            $validation_result['is_valid'] = false;
            $validation_result['form'] = $form;
            return $validation_result;
        }

        $user_id = rgpost('input_' . $user_id_field_id);
        $requested_amount = (float) preg_replace('/[^\d\.\,]/', '', str_replace(',', '.', rgpost('input_' . $amount_field_id)));
        $payment_method = rgpost('input_' . $method_field_id);
        $current_balance = (float) rgpost('input_' . $balance_field_id);

        if (empty($user_id) || !is_user_logged_in() || get_current_user_id() != $user_id) {
            $this->set_form_error($form, "Erro de autenticação. Por favor, faça login novamente.");
            $validation_result['is_valid'] = false;
            $validation_result['form'] = $form;
            return $validation_result;
        }

        if ($requested_amount <= 0) {
            $this->set_field_error($form, $amount_field_id, "O valor do saque deve ser maior que zero.");
            $validation_result['is_valid'] = false;
            $validation_result['form'] = $form;
            return $validation_result;
        }

        if ($requested_amount > $current_balance) {
            $formatted_balance = number_format($current_balance, 2, ',', '.');
            $this->set_field_error($form, $amount_field_id, "Saldo insuficiente. Seu saldo atual é de {$formatted_balance} créditos.");
            $validation_result['is_valid'] = false;
            $validation_result['form'] = $form;
            return $validation_result;
        }

        if ($payment_method == 'Pix') {
            // Verificar se usuário é do Brasil
            $user_country = '';
            if ( function_exists( 'get_field' ) ) {
                $user_country = get_field( 'user_country', 'user_' . $user_id );
            }
            if ( empty( $user_country ) ) {
                $user_country = get_user_meta( $user_id, 'billing_country', true );
            }
            
            if ( $user_country !== 'BR' ) {
                $this->set_field_error($form, $method_field_id, 'PIX disponível apenas para usuários do Brasil.');
                $validation_result['is_valid'] = false;
                $validation_result['form'] = $form;
                return $validation_result;
            }
            
            $pix_key = get_field('user_pix', 'user_' . $user_id);
            if (empty($pix_key)) {
                $profile_url = add_query_arg('updated', 'false', get_edit_user_link($user_id));
                $message = 'Você precisa cadastrar sua Chave PIX em seu <a href="' . esc_url($profile_url) . '" target="_blank">perfil</a> antes de solicitar um saque por este método.';
                $this->set_field_error($form, $method_field_id, $message);
                $validation_result['is_valid'] = false;
                $validation_result['form'] = $form;
                return $validation_result;
            }
        } elseif ($payment_method == 'Wise') {
            $wise_email = get_field('user_wise', 'user_' . $user_id);
            if (empty($wise_email)) {
                $profile_url = add_query_arg('updated', 'false', get_edit_user_link($user_id));
                $message = 'Você precisa cadastrar seu E-mail Wise em seu <a href="' . esc_url($profile_url) . '" target="_blank">perfil</a> antes de solicitar um saque por este método.';
                $this->set_field_error($form, $method_field_id, $message);
                $validation_result['is_valid'] = false;
                $validation_result['form'] = $form;
                return $validation_result;
            }
        } else {
            $this->set_field_error($form, $method_field_id, "Método de pagamento inválido selecionado.");
            $validation_result['is_valid'] = false;
            $validation_result['form'] = $form;
            return $validation_result;
        }

        return $validation_result;
    }

    public function process_withdrawal_request($entry, $form) {
        if (!class_exists('\GFAPI')) return;

        \GFAPI::add_note($entry['id'], 0, 'Sistema', 'Solicitação de saque recebida. Status: ' . self::STATUS_PENDING);
        
        // Enviar notificação de solicitação criada
        $this->send_withdrawal_created_notification($entry);

        // Usar mapeamento de campos
        $settings = get_option('ds_backgamom_credits_settings', []);
        $field_mapping = $settings['field_mapping'] ?? [];
        
        $user_id_field_id = $field_mapping['user_id'] ?? null;
        $amount_field_id = $field_mapping['amount'] ?? null;
        $method_field_id = $field_mapping['method'] ?? null;
        $notes_field_id = $field_mapping['notes'] ?? null;

        if (!$user_id_field_id || !$amount_field_id || !$method_field_id) {
            \GFAPI::add_note($entry['id'], 0, 'Sistema', 'ERRO: Não foi possível processar a notificação.');
            return;
        }

        $user_id = rgar($entry, (string) $user_id_field_id);
        $requested_amount = (float) preg_replace('/[^\d\.\,]/', '', str_replace(',', '.', rgar($entry, (string) $amount_field_id)));
        $payment_method = rgar($entry, (string) $method_field_id);
        $notes = $notes_field_id ? rgar($entry, (string) $notes_field_id) : '';
        $user_data = get_userdata($user_id);

        if (!$user_data || !$this->admin_phone) {
            \GFAPI::add_note($entry['id'], 0, 'Sistema', "ERRO: Não foi possível notificar o admin.");
            return;
        }

        $message = sprintf(
            "💸 *Nova Solicitação de Saque (#%d):*\n\n" .
            "👤 *Usuário:* %s (ID: %d)\n" .
            "💰 *Valor:* %s créditos\n" .
            "💳 *Método:* %s\n",
            $entry['id'],
            $user_data->display_name ?: $user_data->user_login,
            $user_id,
            number_format($requested_amount, 2, ',', '.'),
            $payment_method
        );

        if ($payment_method == 'Pix') {
            $pix_key = get_field('user_pix', 'user_' . $user_id);
            $message .= "🔑 *Chave PIX:* " . ($pix_key ? esc_html($pix_key) : 'NÃO CADASTRADA!') . "\n";
        } elseif ($payment_method == 'Wise') {
            $wise_email = get_field('user_wise', 'user_' . $user_id);
            $message .= "📧 *E-mail Wise:* " . ($wise_email ? esc_html($wise_email) : 'NÃO CADASTRADO!') . "\n";
        }

        if (!empty($notes)) {
            $message .= "\n📝 *Observações:* " . esc_html($notes);
        }

        if (class_exists('\WhatsApp_Connector')) {
            $queued = \WhatsApp_Connector::send_message([
                'recipient' => $this->admin_phone,
                'message' => $message,
                'priority' => 'high',
                'source' => 'ds-backgamom-credits'
            ]);

            if ($queued) {
                \GFAPI::add_note($entry['id'], 0, 'Sistema', 'Notificação para o administrador foi agendada com sucesso.');
            } else {
                \GFAPI::add_note($entry['id'], 0, 'Sistema', 'ERRO: Falha ao agendar a notificação para o administrador.');
            }
        }
    }

    public function add_entry_list_actions_filter($action_links, $entry, $form) {
        if ($entry['form_id'] != $this->form_id) {
            return $action_links;
        }

        $links_html = $this->generate_action_links_html($entry['id']);
        if (!empty($links_html)) {
            $action_links['ds_withdrawal_actions'] = $links_html;
        }

        return $action_links;
    }

    public function ajax_approve_withdrawal() {
        $entry_id = isset($_GET['entry_id']) ? intval($_GET['entry_id']) : 0;

        if (!check_ajax_referer('approve_withdrawal_' . $entry_id, '_ajax_nonce', false) || !current_user_can('manage_options') || !$entry_id) {
            wp_send_json_error(['message' => 'Permissão negada.'], 403);
        }

        if (!class_exists('\GFAPI') || !class_exists('DS_Credit_Manager')) {
            wp_send_json_error(['message' => 'Dependências não encontradas.'], 500);
        }

        $entry = \GFAPI::get_entry($entry_id);
        if (is_wp_error($entry) || !isset($entry['form_id']) || $entry['form_id'] != $this->form_id) {
            wp_send_json_error(['message' => 'Entrada não encontrada.'], 404);
        }

        if ($this->is_entry_processed($entry_id)) {
            wp_send_json_error(['message' => 'Esta solicitação já foi processada.'], 400);
        }

        $admin_user = wp_get_current_user();
        \GFAPI::add_note(
            $entry_id,
            $admin_user->ID,
            $admin_user->user_login,
            'Saque APROVADO. Status: ' . self::STATUS_APPROVED . '. (Débito será processado manualmente.)'
        );

        $this->send_user_withdrawal_notification($entry, self::STATUS_APPROVED);

        wp_send_json_success(['message' => 'Saque aprovado com sucesso.']);
    }

    public function ajax_reject_withdrawal() {
        $entry_id = isset($_GET['entry_id']) ? intval($_GET['entry_id']) : 0;
        $reason = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])) : 'Motivo não especificado.';

        if (!check_ajax_referer('reject_withdrawal_' . $entry_id, '_ajax_nonce', false) || !current_user_can('manage_options') || !$entry_id) {
            wp_send_json_error(['message' => 'Permissão negada.'], 403);
        }

        if (!class_exists('\GFAPI')) {
            wp_send_json_error(['message' => 'GFAPI não encontrado.'], 500);
        }

        $entry = \GFAPI::get_entry($entry_id);
        if (is_wp_error($entry) || !isset($entry['form_id']) || $entry['form_id'] != $this->form_id) {
            wp_send_json_error(['message' => 'Entrada não encontrada.'], 404);
        }

        if ($this->is_entry_processed($entry_id)) {
            wp_send_json_error(['message' => 'Esta solicitação já foi processada.'], 400);
        }

        $admin_user = wp_get_current_user();
        \GFAPI::add_note(
            $entry_id,
            $admin_user->ID,
            $admin_user->user_login,
            'Saque REJEITADO. Motivo: ' . esc_html($reason) . '. Status: ' . self::STATUS_REJECTED
        );

        $this->send_user_withdrawal_notification($entry, self::STATUS_REJECTED, $reason);

        wp_send_json_success(['message' => 'Saque rejeitado com sucesso.']);
    }

    private function send_user_withdrawal_notification($entry, $status, $reason = '') {
        if (!class_exists('\GFAPI') || !class_exists('DS_Credit_Manager')) {
            return;
        }

        $settings = get_option('ds_backgamom_credits_settings', []);
        $field_mapping = $settings['field_mapping'] ?? [];
        
        $user_id_field_id = $field_mapping['user_id'] ?? null;
        $amount_field_id = $field_mapping['amount'] ?? null;

        if (!$user_id_field_id || !$amount_field_id) {
            \GFAPI::add_note($entry['id'], 0, 'Sistema', 'ERRO: Falha ao notificar usuário (campos não encontrados).');
            return;
        }

        $user_id = rgar($entry, (string) $user_id_field_id);
        $amount = (float) preg_replace('/[^\d\.\,]/', '', str_replace(',', '.', rgar($entry, (string) $amount_field_id)));
        $user_data = get_userdata($user_id);

        if (!$user_data) {
            \GFAPI::add_note($entry['id'], 0, 'Sistema', 'ERRO: Dados do usuário não encontrados.');
            return;
        }

        $user_name = $user_data->first_name ?: $user_data->display_name;
        
        // Usar sistema de notificações multi-idioma
        if (class_exists('DS_Notification_i18n')) {
            $queued = false;
            if ($status === self::STATUS_APPROVED) {
                $queued = DS_Notification_i18n::send($user_id, 'withdrawal_approved', [
                    'name' => $user_name,
                    'amount' => number_format($amount, 2, ',', '.'),
                    'priority' => 'high'
                ]);
            } elseif ($status === self::STATUS_REJECTED) {
                $queued = DS_Notification_i18n::send($user_id, 'withdrawal_rejected', [
                    'name' => $user_name,
                    'amount' => number_format($amount, 2, ',', '.'),
                    'reason' => $reason ?: 'Não especificado',
                    'priority' => 'high'
                ]);
            }
            
            if (!$queued) {
                \GFAPI::add_note($entry['id'], 0, 'Sistema', 'ERRO: Falha ao agendar notificação para o usuário.');
            }
        }
    }

    private function generate_action_links_html($entry_id) {
        if (!current_user_can('manage_options')) {
            return '';
        }

        $current_status = $this->get_entry_status($entry_id);

        if ($current_status === self::STATUS_PENDING) {
            $approve_nonce = wp_create_nonce('approve_withdrawal_' . $entry_id);
            $reject_nonce = wp_create_nonce('reject_withdrawal_' . $entry_id);

            $approve_url = admin_url('admin-ajax.php?action=ds_approve_withdrawal&entry_id=' . $entry_id . '&_ajax_nonce=' . $approve_nonce);
            $reject_url = admin_url('admin-ajax.php?action=ds_reject_withdrawal&entry_id=' . $entry_id . '&_ajax_nonce=' . $reject_nonce);

            $approve_onclick = "
                event.preventDefault();
                if(confirm('Tem certeza que deseja APROVAR este saque?')) {
                    var container = jQuery(this).closest('td');
                    container.html('<span>Processando...</span>');
                    jQuery.post('{$approve_url}')
                        .done(function(response) {
                            if(response.success) {
                                alert('Saque Aprovado!');
                                location.reload();
                            } else {
                                alert('Erro: ' + (response.data.message || 'Erro desconhecido.'));
                                container.html('<span style=\"color:red;\">Falha. Recarregue a página.</span>');
                            }
                        })
                        .fail(function() {
                            alert('Erro de comunicação.');
                            container.html('<span style=\"color:red;\">Erro. Recarregue a página.</span>');
                        });
                }";

            $reject_onclick = "
                event.preventDefault();
                var reason = prompt('Motivo da rejeição (opcional):');
                if (reason === null) return;
                if(confirm('Tem certeza que deseja REJEITAR este saque?')) {
                    var container = jQuery(this).closest('td');
                    container.html('<span>Processando...</span>');
                    jQuery.post('{$reject_url}', { reason: reason })
                        .done(function(response) {
                            if(response.success) {
                                alert('Saque Rejeitado!');
                                location.reload();
                            } else {
                                alert('Erro: ' + (response.data.message || 'Erro desconhecido.'));
                                container.html('<span style=\"color:red;\">Falha. Recarregue a página.</span>');
                            }
                        })
                        .fail(function() {
                            alert('Erro de comunicação.');
                            container.html('<span style=\"color:red;\">Erro. Recarregue a página.</span>');
                        });
                }";

            return sprintf(
                '<a href="#" onclick="%s" class="action-link">Aprovar</a> | <a href="#" onclick="%s" class="action-link">Rejeitar</a>',
                esc_js($approve_onclick),
                esc_js($reject_onclick)
            );
        } elseif ($current_status === self::STATUS_APPROVED) {
            return '<span style="color:green; font-weight:bold;">Aprovado</span>';
        } elseif ($current_status === self::STATUS_REJECTED) {
            return '<span style="color:red; font-weight:bold;">Rejeitado</span>';
        }

        return '<span style="color:gray;">Status Desconhecido</span>';
    }

    private function get_entry_status($entry_id) {
        if (!class_exists('\GFAPI')) {
            return self::STATUS_PENDING;
        }

        $notes = \GFAPI::get_notes($entry_id);
        if (empty($notes)) {
            return self::STATUS_PENDING;
        }

        for ($i = count($notes) - 1; $i >= 0; $i--) {
            $note_value = $notes[$i]->value;
            if (str_contains($note_value, 'Status: ' . self::STATUS_APPROVED)) {
                return self::STATUS_APPROVED;
            }
            if (str_contains($note_value, 'Status: ' . self::STATUS_REJECTED)) {
                return self::STATUS_REJECTED;
            }
            if (str_contains($note_value, 'Status: ' . self::STATUS_PENDING)) {
                return self::STATUS_PENDING;
            }
        }

        return self::STATUS_PENDING;
    }

    private function is_entry_processed($entry_id) {
        $status = $this->get_entry_status($entry_id);
        return $status === self::STATUS_APPROVED || $status === self::STATUS_REJECTED;
    }

    private function get_field_id_by_label($label, $form) {
        if (!is_array($form) || !isset($form['fields'])) {
            return null;
        }
        
        foreach ($form['fields'] as $field) {
            if (isset($field->label) && strcasecmp($field->label, $label) == 0) {
                return (int) $field->id;
            }
        }
        return null;
    }

    private function set_form_error(&$form, $message) {
        if (!is_array($form) || !isset($form['fields'])) return;
        
        foreach ($form['fields'] as &$field) {
            if (isset($field->visibility) && $field->visibility == 'visible' && isset($field->type) && $field->type != 'page') {
                $field->failed_validation = true;
                $field->validation_message = $message;
                break;
            }
        }
    }

    private function set_field_error(&$form, $field_id, $message) {
        if (!is_array($form) || !isset($form['fields']) || !$field_id) return;
        
        foreach ($form['fields'] as &$field) {
            if ($field->id == $field_id) {
                $field->failed_validation = true;
                $field->validation_message = $message;
                break;
            }
        }
    }

    private function get_admin_phone() {
        $phone = null;

        if (function_exists('get_field')) {
            $phone = get_field('user_whatsapp', 'user_1');
        }
        if (empty($phone)) {
            $phone = get_user_meta(1, 'billing_phone', true);
        }

        if (!empty($phone)) {
            if (class_exists('\WhatsApp_Phone_Formatter')) {
                return \WhatsApp_Phone_Formatter::format_for_storage($phone);
            }
        }

        return null;
    }


    
    private function send_withdrawal_created_notification($entry) {
        $settings = get_option('ds_backgamom_credits_settings', []);
        $field_mapping = $settings['field_mapping'] ?? [];
        
        $user_id = rgar($entry, (string) ($field_mapping['user_id'] ?? ''));
        $amount = rgar($entry, (string) ($field_mapping['amount'] ?? ''));
        
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $user_name = $user->first_name ?: $user->display_name;
        
        if (class_exists('DS_Notification_i18n')) {
            DS_Notification_i18n::send($user_id, 'withdrawal_request_created', [
                'name' => $user_name,
                'amount' => $amount,
                'priority' => 'high'
            ]);
        }
    }
}