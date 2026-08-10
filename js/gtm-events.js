document.addEventListener('DOMContentLoaded', function() {
    window.dataLayer = window.dataLayer || [];

    // Função de criptografia SHA-256 nativa (requer HTTPS em produção)
    async function hashSHA256(text) {
        if (!crypto || !crypto.subtle) return null;
        
        const cleanText = text.trim().toLowerCase();
        const encoder = new TextEncoder();
        const data = encoder.encode(cleanText);
        const hashBuffer = await crypto.subtle.digest('SHA-256', data);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    }

    // O listener agora é assíncrono (async) para permitir o await do Hash
    document.addEventListener('submit', async function(event) {
        var form = event.target;

        // IGNORA formulários do WPForms para evitar duplicidade ou conflitos de AJAX
        if (form && (form.classList.contains('wpforms-form') || (form.id && form.id.indexOf('wpforms-') === 0))) {
            return;
        }

        var formName = form.getAttribute('name') || 
                       form.getAttribute('id') || 
                       form.getAttribute('data-name') || 
                       'formulario_desconhecido';
                       
        var userEmail = '';

        // 1. Tenta encontrar o campo de e-mail padronizado no formulário
        var emailField = form.querySelector('input[type="email"]');
        if (emailField && emailField.value) {
            userEmail = emailField.value;
        } else {
            // Fallback: procura qualquer input que tenha "email" no atributo name (ex: name="user_email")
            var possibleEmail = form.querySelector('input[name*="email" i]');
            if (possibleEmail && possibleEmail.value) {
                userEmail = possibleEmail.value;
            }
        }

        // 2. Monta o Payload principal
        var eventData = {
            'event': 'form_submission',
            'form_provider': 'generic_html',
            'form_id': form.id || '',
            'form_name': formName,
            'form_class': form.className || '',
            'form_action': form.action || window.location.href
        };

        // 3. Se um e-mail foi encontrado, gera o Hash e anexa
        if (userEmail) {
            var hashedEmail = await hashSHA256(userEmail);
            if (hashedEmail) {
                eventData['user_email_sha256'] = hashedEmail;
            }
        }

        // 4. Envia para o Tag Manager
        window.dataLayer.push(eventData);
    });
});