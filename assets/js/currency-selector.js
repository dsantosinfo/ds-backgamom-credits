/**
 * Multi-Currency Display Enhancement
 * DS Backgamom Credits - Elementor Compatible
 */

jQuery(document).ready(function($) {
    
    // Garantir que preços multi-moeda sejam visíveis no Elementor
    function enhanceMultiCurrencyDisplay() {
        $('.dsbc-multi-price').each(function() {
            var $priceContainer = $(this);
            
            // Adicionar classe para identificação
            $priceContainer.addClass('dsbc-enhanced');
            
            // Melhorar espaçamento em layouts de grid
            if ($priceContainer.closest('.elementor-widget').length > 0) {
                $priceContainer.addClass('elementor-optimized');
            }
        });
    }
    
    // Executar na inicialização
    enhanceMultiCurrencyDisplay();
    
    // Executar após atualizações do Elementor
    $(document).on('elementor/popup/show', enhanceMultiCurrencyDisplay);
    
    // Executar após carregamento AJAX do WooCommerce
    $(document.body).on('updated_wc_div', enhanceMultiCurrencyDisplay);
    
});

// Compatibilidade com Elementor Pro
if (typeof elementorFrontend !== 'undefined') {
    elementorFrontend.hooks.addAction('frontend/element_ready/global', function() {
        jQuery('.dsbc-multi-price').addClass('elementor-ready');
    });
}