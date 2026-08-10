document.addEventListener('DOMContentLoaded', function() {
    window.dataLayer = window.dataLayer || [];

    async function hashSHA256(text) {
        if (!crypto || !crypto.subtle) return null;
        
        const cleanText = text.trim().toLowerCase();
        const encoder = new TextEncoder();
        const data = encoder.encode(cleanText);
        const hashBuffer = await crypto.subtle.digest('SHA-256', data);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    }

    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('wpformsAjaxSubmitSuccess', async function(event, response) {
            try {
                var $form = jQuery(event.target);
                var formId = '';
                var formTitle = 'WPForms';
                var userEmail = '';

                // 1. Identifica o formulário e captura o e-mail
                if ($form.length && $form.hasClass('wpforms-form')) {
                    formId = $form.attr('data-formid') || $form.find('input[name="wpforms[id]"]').val() || '';
                    formTitle = $form.find('.wpforms-title').text().trim() || formTitle;
                    
                    var $emailField = $form.find('input[type="email"], .wpforms-field-email input');
                    if ($emailField.length > 0) {
                        userEmail = $emailField.first().val();
                    }
                } 
                else if (response && response.data && response.data.form_id) {
                    formId = response.data.form_id;
                }

                // 2. Monta o Payload principal
                var eventData = {
                    'event': 'form_submission',
                    'form_provider': 'wpforms',
                    'form_id': 'wpforms-' + formId,
                    'form_name': formTitle,
                    'form_type': 'ajax'
                };

                // 3. Se houver e-mail, gera o Hash e anexa (apenas em HTTPS)
                if (userEmail) {
                    var hashedEmail = await hashSHA256(userEmail);
                    if (hashedEmail) {
                        eventData['user_email_sha256'] = hashedEmail;
                    }
                }

                // 4. Envia tudo para o Tag Manager
                window.dataLayer.push(eventData);

            } catch (err) {
                console.error('GTM WPForms Tracker Error:', err);
            }
        });
    }
});