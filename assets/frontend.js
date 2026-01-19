jQuery(document).ready(function($) {
    
    // Carregar mais histórico
    $(document).on('click', '.ds-load-more', function(e) {
        e.preventDefault();
        
        const $btn = $(this);
        const $historyList = $('.history-list');
        const page = $btn.data('page');
        const limit = $btn.data('limit');
        
        $btn.text('Carregando...').prop('disabled', true);
        
        $.ajax({
            url: dsbc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ds_load_more_history',
                page: page,
                limit: limit,
                type: 'all',
                nonce: dsbc_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $historyList.append(response.data.html);
                    $btn.data('page', response.data.next_page);
                    $btn.text('Ver Mais Transações').prop('disabled', false);
                } else {
                    $btn.text('Não há mais transações').prop('disabled', true);
                }
            },
            error: function() {
                $btn.text('Erro ao carregar').prop('disabled', false);
            }
        });
    });
    
    // Carregar mais histórico detalhado
    $(document).on('click', '.ds-load-more-detailed', function(e) {
        e.preventDefault();
        
        const $btn = $(this);
        const $historyList = $('.history-list');
        const page = $btn.data('page');
        const limit = $btn.data('limit');
        const type = $btn.data('type');
        
        $btn.text('Carregando...').prop('disabled', true);
        
        $.ajax({
            url: dsbc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ds_load_more_history',
                page: page,
                limit: limit,
                type: type,
                nonce: dsbc_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $historyList.append(response.data.html);
                    $btn.data('page', response.data.next_page);
                    $btn.text('Carregar Mais').prop('disabled', false);
                } else {
                    $btn.text('Não há mais transações').prop('disabled', true);
                }
            },
            error: function() {
                $btn.text('Erro ao carregar').prop('disabled', false);
            }
        });
    });
    
    // Filtro de tipo de transação
    $(document).on('change', '.filter-type', function() {
        const type = $(this).val();
        const $historyList = $('.history-list');
        const $loadMoreBtn = $('.ds-load-more-detailed');
        
        // Reset pagination
        $loadMoreBtn.data('page', 2).data('type', type);
        
        // Reload history with new filter
        $historyList.html('<div class="loading">Carregando...</div>');
        
        $.ajax({
            url: dsbc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ds_filter_history',
                type: type,
                limit: 10,
                nonce: dsbc_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $historyList.html(response.data.html);
                } else {
                    $historyList.html('<p class="no-history">Nenhuma transação encontrada.</p>');
                }
            },
            error: function() {
                $historyList.html('<p class="error">Erro ao carregar histórico.</p>');
            }
        });
    });
    
    // Modal de saque (se existir formulário)
    $(document).on('click', '.ds-withdrawal-btn', function(e) {
        e.preventDefault();
        
        // Verifica se existe modal de saque
        if ($('#ds-withdrawal-modal').length) {
            $('#ds-withdrawal-modal').show();
        } else {
            // Redireciona para página de saque ou exibe alerta
            alert('Funcionalidade de saque em desenvolvimento. Entre em contato com o suporte.');
        }
    });
    
    // Atualização automática de saldo (opcional)
    function updateBalance() {
        const $balanceElements = $('.balance-amount, .ds-credit-badge');
        
        if ($balanceElements.length === 0) return;
        
        $.ajax({
            url: dsbc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ds_get_current_balance',
                nonce: dsbc_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $balanceElements.each(function() {
                        const $el = $(this);
                        if ($el.hasClass('balance-amount')) {
                            $el.text(response.data.formatted);
                        } else if ($el.hasClass('ds-credit-badge')) {
                            $el.html(response.data.formatted + ' créditos');
                        }
                    });
                }
            }
        });
    }
    
    // Atualizar saldo a cada 30 segundos (opcional)
    // setInterval(updateBalance, 30000);
    
    // Animações suaves para elementos
    $('.ds-credit-dashboard').hide().fadeIn(500);
    
    // Tooltip para estatísticas (se necessário)
    $('.stat-item').hover(
        function() {
            $(this).css('transform', 'translateY(-2px)');
        },
        function() {
            $(this).css('transform', 'translateY(0)');
        }
    );
    
    // Responsividade para tabelas em mobile
    function makeTablesResponsive() {
        $('.history-item').each(function() {
            const $item = $(this);
            if ($(window).width() < 768) {
                $item.addClass('mobile-view');
            } else {
                $item.removeClass('mobile-view');
            }
        });
    }
    
    $(window).resize(makeTablesResponsive);
    makeTablesResponsive();
});