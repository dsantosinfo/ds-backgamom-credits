<?php
/**
 * Teste dos Shortcodes - DS Backgamom Credits
 * 
 * Para testar, adicione este código em uma página ou post do WordPress:
 */

// Teste básico dos shortcodes
function ds_test_shortcodes_page() {
    if ( ! is_user_logged_in() ) {
        return '<p>Você precisa estar logado para testar os shortcodes.</p>';
    }
    
    ob_start();
    ?>
    <div style="max-width: 1000px; margin: 20px auto; padding: 20px;">
        <h1>🧪 Teste dos Shortcodes - DS Backgamom Credits</h1>
        
        <div style="background: #f0f0f0; padding: 15px; margin: 20px 0; border-radius: 5px;">
            <h3>1. Saldo Simples (Número)</h3>
            <?php echo do_shortcode('[ds_credit_balance]'); ?>
        </div>
        
        <div style="background: #f0f0f0; padding: 15px; margin: 20px 0; border-radius: 5px;">
            <h3>2. Saldo Badge</h3>
            <?php echo do_shortcode('[ds_credit_balance format="badge"]'); ?>
        </div>
        
        <div style="background: #f0f0f0; padding: 15px; margin: 20px 0; border-radius: 5px;">
            <h3>3. Saldo Card</h3>
            <?php echo do_shortcode('[ds_credit_balance format="card"]'); ?>
        </div>
        
        <div style="background: #f0f0f0; padding: 15px; margin: 20px 0; border-radius: 5px;">
            <h3>4. Widget Padrão</h3>
            <?php echo do_shortcode('[ds_credit_widget]'); ?>
        </div>
        
        <div style="background: #f0f0f0; padding: 15px; margin: 20px 0; border-radius: 5px;">
            <h3>5. Widget Card</h3>
            <?php echo do_shortcode('[ds_credit_widget style="card"]'); ?>
        </div>
        
        <div style="background: #f0f0f0; padding: 15px; margin: 20px 0; border-radius: 5px;">
            <h3>6. Estatísticas</h3>
            <?php echo do_shortcode('[ds_credit_stats period="30"]'); ?>
        </div>
        
        <div style="background: #f0f0f0; padding: 15px; margin: 20px 0; border-radius: 5px;">
            <h3>7. Dashboard Completo</h3>
            <?php echo do_shortcode('[ds_credit_dashboard]'); ?>
        </div>
        
        <div style="background: #f0f0f0; padding: 15px; margin: 20px 0; border-radius: 5px;">
            <h3>8. Histórico</h3>
            <?php echo do_shortcode('[ds_credit_history limit="5"]'); ?>
        </div>
    </div>
    
    <style>
    /* Força carregamento do CSS se não estiver carregado */
    .ds-credit-badge {
        background: #28a745 !important;
        color: white !important;
        padding: 6px 12px !important;
        border-radius: 20px !important;
        font-weight: 600 !important;
        display: inline-block !important;
    }
    </style>
    <?php
    return ob_get_clean();
}

// Adicionar shortcode de teste (temporário)
add_shortcode('ds_test_shortcodes', 'ds_test_shortcodes_page');

/**
 * Para usar este teste:
 * 1. Adicione este arquivo ao plugin (temporariamente)
 * 2. Crie uma página no WordPress
 * 3. Adicione o shortcode: [ds_test_shortcodes]
 * 4. Visualize a página logado como usuário
 */
?>