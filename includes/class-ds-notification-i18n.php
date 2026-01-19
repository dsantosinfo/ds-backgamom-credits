<?php
/**
 * Sistema de Notificações Multi-idioma
 * DS Backgamom Credits
 */

if (!defined('ABSPATH')) {
    exit;
}

class DS_Notification_i18n {

    /**
     * Detecta idioma do usuário (3 níveis de prioridade)
     */
    public static function get_user_language($user_id) {
        // Nível 1: billing_country (WooCommerce)
        $country = get_user_meta($user_id, 'billing_country', true);
        if (!empty($country)) {
            return self::country_to_language($country);
        }

        // Nível 2: locale do WordPress
        $locale = get_user_locale($user_id);
        if ($locale && $locale !== '') {
            return $locale;
        }

        // Nível 3: fallback padrão
        return 'pt_BR';
    }

    /**
     * Mapeia código do país para código de idioma
     */
    private static function country_to_language($country) {
        $map = [
            // Português
            'BR' => 'pt_BR',
            'PT' => 'pt_PT',
            'AO' => 'pt_PT',
            'MZ' => 'pt_PT',
            
            // Inglês
            'US' => 'en_US',
            'GB' => 'en_US',
            'CA' => 'en_US',
            'AU' => 'en_US',
            
            // Espanhol
            'ES' => 'es_ES',
            'MX' => 'es_ES',
            'AR' => 'es_ES',
            'CO' => 'es_ES',
            'CL' => 'es_ES',
        ];

        return $map[strtoupper($country)] ?? 'pt_BR';
    }

    /**
     * Busca template no idioma correto
     */
    public static function get_template($type, $lang, $vars = []) {
        // Normaliza código do idioma
        $lang = str_replace('-', '_', $lang);
        
        $templates = self::get_all_templates();
        
        if (!isset($templates[$type])) {
            return null;
        }

        // Busca template no idioma, fallback para pt_BR
        $template = $templates[$type][$lang] ?? $templates[$type]['pt_BR'];

        // Substitui variáveis {nome_variavel}
        foreach ($vars as $key => $value) {
            $template = str_replace('{' . $key . '}', (string) $value, $template);
        }

        return $template;
    }

    /**
     * Define todos os templates de mensagens
     */
    public static function get_all_templates() {
        // Templates padrão
        $default_templates = [
            'deposit' => [
                'pt_BR' => "💰 Olá {name}! Você recebeu {amount}. Seu saldo atual é de {balance}.",
                'en_US' => "💰 Hello {name}! You received {amount}. Your current balance is {balance}.",
                'es_ES' => "💰 ¡Hola {name}! Recibiste {amount}. Tu saldo actual es de {balance}."
            ],
            
            'withdrawal_processed' => [
                'pt_BR' => "✅ Olá {name}, foi processado um saque de {amount} em sua conta. Motivo: {reason}",
                'en_US' => "✅ Hello {name}, a withdrawal of {amount} has been processed from your account. Reason: {reason}",
                'es_ES' => "✅ Hola {name}, se procesó un retiro de {amount} de tu cuenta. Motivo: {reason}"
            ],
            
            'withdrawal_request_created' => [
                'pt_BR' => "📝 Olá {name}, sua solicitação de saque de {amount} foi recebida e está em análise. Você será notificado sobre o resultado.",
                'en_US' => "📝 Hello {name}, your withdrawal request for {amount} has been received and is under review. You will be notified of the result.",
                'es_ES' => "📝 Hola {name}, tu solicitud de retiro de {amount} ha sido recibida y está en revisión. Serás notificado del resultado."
            ],
            
            'withdrawal_approved' => [
                'pt_BR' => "✅ Ótima notícia {name}! Seu saque de {amount} foi aprovado e será processado em breve.",
                'en_US' => "✅ Great news {name}! Your withdrawal of {amount} has been approved and will be processed soon.",
                'es_ES' => "✅ ¡Buenas noticias {name}! Tu retiro de {amount} ha sido aprobado y será procesado pronto."
            ],
            
            'withdrawal_rejected' => [
                'pt_BR' => "❌ Olá {name}, infelizmente seu saque de {amount} foi rejeitado. Motivo: {reason}",
                'en_US' => "❌ Hello {name}, unfortunately your withdrawal of {amount} has been rejected. Reason: {reason}",
                'es_ES' => "❌ Hola {name}, lamentablemente tu retiro de {amount} ha sido rechazado. Motivo: {reason}"
            ],
            
            'admin_new_withdrawal' => [
                'pt_BR' => "💸 *Nova Solicitação de Saque (#{request_id}):*\n\n👤 *Usuário:* {user_name} (ID: {user_id})\n💰 *Valor:* {amount}\n💳 *Método:* {method}\n{payment_info}",
                'en_US' => "💸 *New Withdrawal Request (#{request_id}):*\n\n👤 *User:* {user_name} (ID: {user_id})\n💰 *Amount:* {amount}\n💳 *Method:* {method}\n{payment_info}",
                'es_ES' => "💸 *Nueva Solicitud de Retiro (#{request_id}):*\n\n👤 *Usuario:* {user_name} (ID: {user_id})\n💰 *Cantidad:* {amount}\n💳 *Método:* {method}\n{payment_info}"
            ],
            
            'wise_approved' => [
                'pt_BR' => "✅ Olá! Seu pagamento WISE do pedido #{order_number} no valor de {amount} foi aprovado. Seu saldo foi atualizado!",
                'en_US' => "✅ Hello! Your WISE payment for order #{order_number} in the amount of {amount} has been approved. Your balance has been updated!",
                'es_ES' => "✅ ¡Hola! Tu pago WISE del pedido #{order_number} por {amount} ha sido aprobado. ¡Tu saldo ha sido actualizado!"
            ],
            
            'wise_rejected' => [
                'pt_BR' => "❌ Olá! Seu pagamento WISE do pedido #{order_number} no valor de {amount} foi rejeitado. Entre em contato para mais informações.",
                'en_US' => "❌ Hello! Your WISE payment for order #{order_number} in the amount of {amount} has been rejected. Please contact us for more information.",
                'es_ES' => "❌ ¡Hola! Tu pago WISE del pedido #{order_number} por {amount} ha sido rechazado. Contacta con nosotros para más información."
            ],
            
            'payment_reminder' => [
                'pt_BR' => "💰 Olá {name}! Lembrete: Você tem um pagamento de {amount} com vencimento em {due_date}. Motivo: {observation}",
                'en_US' => "💰 Hello {name}! Reminder: You have a payment of {amount} due on {due_date}. Reason: {observation}",
                'es_ES' => "💰 ¡Hola {name}! Recordatorio: Tienes un pago de {amount} con vencimiento el {due_date}. Motivo: {observation}"
            ],
            
            'credits_scheduled' => [
                'pt_BR' => "💰 Olá {name}! Foram adicionados {amount} à sua conta. Pagamento previsto para {due_date} via {payment_method}. Observação: {observation}. Seu saldo atual é de {balance}.",
                'en_US' => "💰 Hello {name}! {amount} have been added to your account. Payment scheduled for {due_date} via {payment_method}. Note: {observation}. Your current balance is {balance}.",
                'es_ES' => "💰 ¡Hola {name}! Se agregaron {amount} a tu cuenta. Pago programado para {due_date} vía {payment_method}. Observación: {observation}. Tu saldo actual es de {balance}."
            ]
        ];
        
        // Buscar templates salvos no banco
        $saved_templates = get_option('ds_notification_templates', []);
        
        // Mesclar templates salvos com padrões (salvos têm prioridade)
        $final_templates = $default_templates;
        
        if (!empty($saved_templates) && is_array($saved_templates)) {
            foreach ($saved_templates as $type => $languages) {
                if (is_array($languages)) {
                    foreach ($languages as $lang => $content) {
                        if (!empty($content)) {
                            $final_templates[$type][$lang] = $content;
                        }
                    }
                }
            }
        }
        
        return $final_templates;
    }

    /**
     * Envia notificação individual no idioma do usuário
     */
    public static function send($user_id, $type, $vars = []) {
        error_log( "DS Notification: Iniciando envio para usuário {$user_id}, tipo {$type}" );
        
        // Detecta idioma
        $lang = self::get_user_language($user_id);
        error_log( "DS Notification: Idioma detectado: {$lang}" );
        
        // Busca template
        $message = self::get_template($type, $lang, $vars);
        
        if (!$message) {
            error_log("DS Notification: Template '$type' não encontrado para idioma '$lang'");
            return false;
        }
        
        error_log( "DS Notification: Template encontrado: {$message}" );

        // Busca telefone
        $phone = self::get_user_phone($user_id);
        if (!$phone) {
            error_log("DS Notification: Telefone não encontrado para usuário $user_id");
            return false;
        }
        
        error_log( "DS Notification: Telefone encontrado: {$phone}" );

        // Envia via WhatsApp Connector
        if (class_exists('\WhatsApp_Connector')) {
            error_log( "DS Notification: Classe WhatsApp_Connector encontrada" );
            
            $data = [
                'recipient' => $phone,
                'message' => $message,
                'priority' => $vars['priority'] ?? 'high',
                'source' => 'ds-backgamom-credits'
            ];
            
            // Suporte a agendamento
            if (isset($vars['scheduled_at'])) {
                $data['scheduled_at'] = $vars['scheduled_at'];
            }
            
            $result = \WhatsApp_Connector::send_message($data);
            error_log( "DS Notification: Resultado do envio: " . ($result ? 'sucesso' : 'falha') );
            
            return $result;
        }

        error_log("DS Notification: WhatsApp_Connector não disponível");
        return false;
    }

    /**
     * Envia notificação para admin
     */
    public static function send_admin($type, $vars = []) {
        $admin_phone = self::get_admin_phone();
        if (!$admin_phone) {
            error_log("DS Notification: Telefone do admin não encontrado");
            return false;
        }

        // Admin sempre recebe em português
        $message = self::get_template($type, 'pt_BR', $vars);
        
        if (!$message) {
            error_log("DS Notification: Template '$type' não encontrado");
            return false;
        }

        if (class_exists('\WhatsApp_Connector')) {
            return \WhatsApp_Connector::send_message([
                'recipient' => $admin_phone,
                'message' => $message,
                'priority' => $vars['priority'] ?? 'high',
                'source' => 'ds-backgamom-credits'
            ]);
        }

        return false;
    }

    /**
     * Busca telefone do usuário
     */
    private static function get_user_phone($user_id) {
        $phone = null;
        
        // Tentar ACF primeiro
        if (function_exists('get_field')) {
            $phone = get_field('user_whatsapp', 'user_' . $user_id);
        }
        
        // Fallback para meta do usuário
        if (empty($phone)) {
            $phone = get_user_meta($user_id, 'billing_phone', true);
        }
        
        if (empty($phone)) {
            return null;
        }
        
        // Usar formatador se disponível
        if (class_exists('\WhatsApp_Phone_Formatter')) {
            return \WhatsApp_Phone_Formatter::format_for_storage($phone);
        }
        
        // Fallback simples
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) <= 11) {
            return '55' . $digits;
        }
        return $digits;
    }

    /**
     * Busca telefone do admin
     */
    private static function get_admin_phone() {
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

    /**
     * Salva templates personalizados
     */
    public static function save_templates($templates) {
        return update_option('ds_notification_templates', $templates);
    }

    /**
     * Obtém lista de idiomas suportados
     */
    public static function get_supported_languages() {
        return [
            'pt_BR' => 'Português (Brasil)',
            'en_US' => 'English (US)',
            'es_ES' => 'Español (España)'
        ];
    }

    /**
     * Obtém lista de tipos de template
     */
    public static function get_template_types() {
        return [
            'deposit' => 'Depósito de Créditos',
            'withdrawal_processed' => 'Saque Processado',
            'withdrawal_request_created' => 'Solicitação de Saque Criada',
            'withdrawal_approved' => 'Saque Aprovado',
            'withdrawal_rejected' => 'Saque Rejeitado',
            'admin_new_withdrawal' => 'Nova Solicitação (Admin)',
            'wise_approved' => 'Pagamento WISE Aprovado',
            'wise_rejected' => 'Pagamento WISE Rejeitado',
            'payment_reminder' => 'Lembrete de Pagamento',
            'credits_scheduled' => 'Créditos com Pagamento Agendado'
        ];
    }

    /**
     * Obtém variáveis disponíveis por tipo de template
     */
    public static function get_template_variables($type) {
        $variables = [
            'deposit' => [
                'name' => 'Nome do usuário',
                'amount' => 'Quantidade de créditos',
                'balance' => 'Saldo atual'
            ],
            'withdrawal_processed' => [
                'name' => 'Nome do usuário',
                'amount' => 'Quantidade de créditos',
                'reason' => 'Motivo do saque'
            ],
            'withdrawal_request_created' => [
                'name' => 'Nome do usuário',
                'amount' => 'Quantidade de créditos'
            ],
            'withdrawal_approved' => [
                'name' => 'Nome do usuário',
                'amount' => 'Quantidade de créditos'
            ],
            'withdrawal_rejected' => [
                'name' => 'Nome do usuário',
                'amount' => 'Quantidade de créditos',
                'reason' => 'Motivo da rejeição'
            ],
            'admin_new_withdrawal' => [
                'request_id' => 'ID da solicitação',
                'user_name' => 'Nome do usuário',
                'user_id' => 'ID do usuário',
                'amount' => 'Quantidade de créditos',
                'method' => 'Método de pagamento',
                'payment_info' => 'Informações de pagamento'
            ],
            'wise_approved' => [
                'order_number' => 'Número do pedido',
                'amount' => 'Valor do pedido'
            ],
            'wise_rejected' => [
                'order_number' => 'Número do pedido',
                'amount' => 'Valor do pedido'
            ],
            'payment_reminder' => [
                'name' => 'Nome do usuário',
                'amount' => 'Quantidade de créditos',
                'due_date' => 'Data de vencimento',
                'observation' => 'Observação/motivo'
            ],
            'credits_scheduled' => [
                'name' => 'Nome do usuário',
                'amount' => 'Quantidade de créditos',
                'due_date' => 'Data de vencimento',
                'payment_method' => 'Método de pagamento',
                'observation' => 'Observação/motivo',
                'balance' => 'Saldo atual'
            ]
        ];

        return $variables[$type] ?? [];
    }
}