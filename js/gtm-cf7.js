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

    document.addEventListener('wpcf7mailsent', async function(event) {
        try {
            var formId = event.detail.contactFormId || '';
            var formTitle = 'Contact Form 7';
            var userEmail = '';
            
            if (event.detail && event.detail.inputs) {
                var inputs = event.detail.inputs;
                for (var i = 0; i < inputs.length; i++) {
                    if (inputs[i].name.toLowerCase().indexOf('email') !== -1 && inputs[i].value) {
                        userEmail = inputs[i].value;
                        break;
                    }
                }
            }

            var eventData = {
                'event': 'form_submission',
                'form_provider': 'contact_form_7',
                'form_id': 'wpcf7-' + formId,
                'form_name': formTitle,
                'form_type': 'ajax'
            };

            if (userEmail) {
                var hashedEmail = await hashSHA256(userEmail);
                if (hashedEmail) {
                    eventData['user_email_sha256'] = hashedEmail;
                }
            }

            window.dataLayer.push(eventData);

        } catch (err) {
            console.error('GTM CF7 Tracker Error:', err);
        }
    }, false);
});