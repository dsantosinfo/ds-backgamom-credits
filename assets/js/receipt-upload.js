jQuery(document).ready(function($) {
    
    // Upload de comprovante no perfil do usuário
    $('.dsbc-receipt-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var logId = form.data('log-id');
        var feedback = form.find('.upload-feedback');
        var submitBtn = form.find('button[type="submit"]');
        
        var formData = new FormData();
        formData.append('action', 'dsbc_upload_receipt');
        formData.append('nonce', dsbcReceipt.nonce);
        formData.append('log_id', logId);
        formData.append('receipt', form.find('input[type="file"]')[0].files[0]);
        
        submitBtn.prop('disabled', true).text('Enviando...');
        
        $.ajax({
            url: dsbcReceipt.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    feedback.html('<span style="color: green;">✓ ' + response.data + '</span>');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    feedback.html('<span style="color: red;">✗ ' + response.data + '</span>');
                    submitBtn.prop('disabled', false).text('Enviar Comprovante');
                }
            }
        });
    });
    
    // Upload de comprovante na thank you page
    $('#dsbc-order-receipt-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var feedback = form.find('.dsbc-upload-feedback');
        var submitBtn = form.find('button[type="submit"]');
        
        var formData = new FormData();
        formData.append('action', 'dsbc_upload_order_receipt');
        formData.append('nonce', dsbcReceipt.nonce);
        formData.append('order_id', form.find('input[name="order_id"]').val());
        formData.append('receipt', form.find('input[type="file"]')[0].files[0]);
        
        submitBtn.prop('disabled', true).text('Enviando...');
        
        $.ajax({
            url: dsbcReceipt.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    feedback.html('<span style="color: green; margin-left: 10px;">✓ ' + response.data + '</span>');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    feedback.html('<span style="color: red; margin-left: 10px;">✗ ' + response.data + '</span>');
                    submitBtn.prop('disabled', false).text('Enviar Comprovante');
                }
            }
        });
    });
});
