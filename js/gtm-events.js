document.addEventListener('submit', function (event) {
    var form = event.target;

    if (form.classList.contains('elementor-form') ||
        form.classList.contains('wpcf7-form') ||
        form.classList.contains('wpforms-form')) {
        return;
    }

    // Evita submits duplos no mesmo form
    if (form.dataset.bridgeSubmitted === '1') return;

    // Se o browser suportar, segura o submit até o push terminar
    var submitter = event.submitter;
    var nativeSubmit = form.submit.bind(form);

    event.preventDefault();
    form.dataset.bridgeSubmitted = '1';

    var emailInput = form.querySelector('input[type="email"]');
    var phoneInput = form.querySelector('input[type="tel"]');
    var formName = form.getAttribute('name') || form.getAttribute('id') || 'Generic Form';

    bridgeBuildEventData({
        email: emailInput ? emailInput.value : '',
        phone: phoneInput ? phoneInput.value : '',
        form_name: formName,
        form_id: form.getAttribute('id') || ''
    }).then(function () {
        // Deixa o submit continuar (bypassa nosso próprio preventDefault)
        nativeSubmit();
    }).catch(function () {
        nativeSubmit();
    });
});

