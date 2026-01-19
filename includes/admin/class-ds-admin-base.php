<?php
/**
 * Classe base para administração do DS Backgamom Credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class DS_Admin_Base {
    
    protected $option_name = 'ds_backgamom_credits_settings';
    
    /**
     * Enfileira assets do admin
     */
    public function enqueue_admin_assets( $hook ) {
        if ( strpos( $hook, 'ds-backgamom-credits' ) === false ) {
            return;
        }
        
        wp_add_inline_style( 'wp-admin', '
            .dashboard-widgets { display: flex; gap: 20px; }
            .postbox-container { flex: 1; }
            .postbox { margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            .postbox .inside { padding: 20px; }
            .postbox .hndle { background: #f8f9fa; border-bottom: 1px solid #e1e1e1; padding: 12px 20px; }
            .nav-tab-wrapper { margin-bottom: 20px; }
            .dsbc-stat-box { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 20px; border-left: 4px solid #0073aa; margin-bottom: 15px; border-radius: 4px; }
            .dsbc-stat-number { font-size: 28px; font-weight: bold; color: #0073aa; line-height: 1; }
            .dsbc-status-ok { color: #00a32a; }
            .dsbc-status-error { color: #d63638; }
            .wp-list-table th { background: #f8f9fa; font-weight: 600; }
            .wp-list-table tbody tr:hover { background: #f8f9fa; }
            .notice.inline { margin: 15px 0; }
            .form-table th { width: 150px; }
            .button.button-large { padding: 8px 20px; height: auto; }
        ' );
    }
    
    /**
     * Verifica nonce de segurança
     */
    protected function verify_nonce( $nonce, $action ) {
        return wp_verify_nonce( $nonce, $action );
    }
    
    /**
     * Verifica permissões do usuário
     */
    protected function check_permissions( $capability = 'manage_options' ) {
        return current_user_can( $capability );
    }
    
    /**
     * Retorna resposta AJAX
     */
    protected function ajax_response( $success, $data ) {
        wp_die( json_encode( [ 'success' => $success, 'data' => $data ] ) );
    }
}