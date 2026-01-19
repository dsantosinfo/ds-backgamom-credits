jQuery(document).ready(function($) {
    var container = document.getElementById('ds-pix-qrcode');
    
    if (container) {
        var payload = container.getAttribute('title');
        
        if (payload) {
            try {
                container.innerHTML = '';
                new QRCode(container, {
                    text: payload,
                    width: 250,
                    height: 250,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.L
                });
            } catch (e) {
                console.error('Erro ao gerar QR Code:', e);
            }

            $('#btn-copy-pix').on('click', function(e) {
                e.preventDefault();
                var copyText = document.getElementById("pix-copia-cola");
                copyText.select();
                copyText.setSelectionRange(0, 99999);
                
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(copyText.value).then(function() {
                        $('#copy-feedback').fadeIn().delay(2000).fadeOut();
                    });
                } else {
                    document.execCommand('copy');
                    $('#copy-feedback').fadeIn().delay(2000).fadeOut();
                }
            });
        }
    }
});
