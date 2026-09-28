document.addEventListener('wpcf7mailsent', function (event) {
    var inputs = event.detail.inputs;
    var rawEmail = '';
    var rawPhone = '';

    inputs.forEach(function (input) {
        if (input.name.indexOf('email') !== -1 || input.name.indexOf('mail') !== -1) {
            rawEmail = input.value;
        }
        if (input.name.indexOf('tel') !== -1 ||
            input.name.indexOf('phone') !== -1 ||
            input.name.indexOf('celular') !== -1) {
            rawPhone = input.value;
        }
    });

    bridgeBuildEventData({
        email: rawEmail,
        phone: rawPhone,
        form_name: 'Contact Form 7',
        form_id: event.detail.contactFormId
    });
}, false);