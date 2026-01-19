<?php
/**
 * Gestão de Templates de Notificação
 * DS Backgamom Credits
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once DSBC_PLUGIN_PATH . 'includes/admin/class-ds-admin-base.php';

class DS_Admin_Templates extends DS_Admin_Base {

    public function templates_page() {
        // Processar salvamento
        if (isset($_POST['save_templates']) && wp_verify_nonce($_POST['nonce'], 'save_templates')) {
            $this->save_templates();
        }

        // Processar reset
        if (isset($_POST['reset_templates']) && wp_verify_nonce($_POST['nonce'], 'reset_templates')) {
            $this->reset_templates();
        }

        $templates = DS_Notification_i18n::get_all_templates();
        $languages = DS_Notification_i18n::get_supported_languages();
        $template_types = DS_Notification_i18n::get_template_types();
        
        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-format-chat"></span> Templates de Notificação</h1>
            
            <div class="notice notice-info">
                <p><strong>Sistema Multi-idioma:</strong> As mensagens são enviadas automaticamente no idioma do usuário baseado no país de cobrança (WooCommerce).</p>
            </div>

            <form method="post" action="">
                <?php wp_nonce_field('save_templates', 'nonce'); ?>
                
                <div class="postbox">
                    <h2 class="hndle">Configuração de Templates</h2>
                    <div class="inside">
                        
                        <?php foreach ($template_types as $type => $label): ?>
                            <div class="template-section" style="margin-bottom: 30px; border: 1px solid #ddd; padding: 20px; border-radius: 5px;">
                                <h3 style="margin-top: 0; color: #0073aa;"><?php echo esc_html($label); ?></h3>
                                
                                <div class="template-info" style="background: #f8f9fa; padding: 10px; margin-bottom: 15px; border-radius: 3px;">
                                    <strong>Variáveis disponíveis:</strong>
                                    <?php 
                                    $variables = DS_Notification_i18n::get_template_variables($type);
                                    if ($variables): ?>
                                        <ul style="margin: 5px 0 0 20px;">
                                            <?php foreach ($variables as $var => $desc): ?>
                                                <li><code>{<?php echo $var; ?>}</code> - <?php echo esc_html($desc); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <em>Nenhuma variável específica</em>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="language-tabs">
                                    <?php foreach ($languages as $lang_code => $lang_name): ?>
                                        <div class="language-tab" style="margin-bottom: 15px;">
                                            <label style="font-weight: bold; color: #333;">
                                                <?php echo esc_html($lang_name); ?>
                                            </label>
                                            <textarea 
                                                name="templates[<?php echo $type; ?>][<?php echo $lang_code; ?>]" 
                                                rows="4" 
                                                style="width: 100%; margin-top: 5px;"
                                                placeholder="Digite a mensagem em <?php echo esc_html($lang_name); ?>..."
                                            ><?php 
                                                $template_content = $templates[$type][$lang_code] ?? '';
                                                echo esc_textarea(is_string($template_content) ? $template_content : '');
                                            ?></textarea>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div style="text-align: center; margin-top: 30px;">
                            <button type="submit" name="save_templates" class="button button-primary button-large">
                                <span class="dashicons dashicons-yes"></span> Salvar Templates
                            </button>
                            
                            <button type="submit" name="reset_templates" class="button button-secondary" 
                                    onclick="return confirm('Tem certeza? Isso irá restaurar todos os templates para os valores padrão.')">
                                <span class="dashicons dashicons-undo"></span> Restaurar Padrões
                            </button>
                        </div>
                    </div>
                </div>
            </form>
            
            <div class="postbox">
                <h2 class="hndle">Teste de Detecção de Idioma</h2>
                <div class="inside">
                    <form method="post" action="">
                        <?php wp_nonce_field('test_language', 'test_nonce'); ?>
                        <table class="form-table">
                            <tr>
                                <th><label for="test_user_id">ID do Usuário</label></th>
                                <td>
                                    <input type="number" id="test_user_id" name="test_user_id" min="1" style="width: 100px;">
                                    <button type="submit" name="test_language" class="button">Testar</button>
                                </td>
                            </tr>
                        </table>
                    </form>
                    
                    <?php if (isset($_POST['test_language']) && wp_verify_nonce($_POST['test_nonce'], 'test_language')): ?>
                        <?php $this->show_language_test_result(intval($_POST['test_user_id'])); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <style>
        .template-section {
            background: #fff;
        }
        .language-tab textarea {
            font-family: monospace;
            font-size: 13px;
        }
        .template-info code {
            background: #e1e1e1;
            padding: 2px 4px;
            border-radius: 2px;
        }
        </style>
        <?php
    }

    private function save_templates() {
        if (!isset($_POST['templates']) || !is_array($_POST['templates'])) {
            $this->add_admin_notice('Erro: Dados de templates inválidos.', 'error');
            return;
        }

        $templates = [];
        foreach ($_POST['templates'] as $type => $languages) {
            if (is_array($languages)) {
                foreach ($languages as $lang => $content) {
                    $templates[$type][$lang] = sanitize_textarea_field($content);
                }
            }
        }

        if (DS_Notification_i18n::save_templates($templates)) {
            $this->add_admin_notice('Templates salvos com sucesso!', 'success');
        } else {
            $this->add_admin_notice('Erro ao salvar templates.', 'error');
        }
    }

    private function reset_templates() {
        if (delete_option('ds_notification_templates')) {
            $this->add_admin_notice('Templates restaurados para os valores padrão!', 'success');
        } else {
            $this->add_admin_notice('Erro ao restaurar templates.', 'error');
        }
    }

    private function show_language_test_result($user_id) {
        if ($user_id <= 0) {
            echo '<div class="notice notice-error inline"><p>ID de usuário inválido.</p></div>';
            return;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            echo '<div class="notice notice-error inline"><p>Usuário não encontrado.</p></div>';
            return;
        }

        $detected_lang = DS_Notification_i18n::get_user_language($user_id);
        $billing_country = get_user_meta($user_id, 'billing_country', true);
        $user_locale = get_user_locale($user_id);

        echo '<div class="notice notice-info inline">';
        echo '<h4>Resultado do Teste para: ' . esc_html($user->display_name) . '</h4>';
        echo '<ul>';
        echo '<li><strong>País de Cobrança:</strong> ' . ($billing_country ?: 'Não definido') . '</li>';
        echo '<li><strong>Locale do WordPress:</strong> ' . ($user_locale ?: 'Não definido') . '</li>';
        echo '<li><strong>Idioma Detectado:</strong> <code>' . $detected_lang . '</code></li>';
        echo '</ul>';
        echo '</div>';
    }

    private function add_admin_notice($message, $type = 'info') {
        echo '<div class="notice notice-' . $type . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }
}