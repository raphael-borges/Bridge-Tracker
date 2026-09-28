document.addEventListener('elementorSubmitSuccess_native', function (event) {
    var form = event.detail.form;

    var formName = form.getAttribute('name') || 'Elementor Form';
    var formId = form.getAttribute('id') || '';

    var emailInput = form.querySelector('input[type="email"]');
    var phoneInput = form.querySelector('input[type="tel"]');

    bridgeBuildEventData({
        email: emailInput ? emailInput.value : '',
        phone: phoneInput ? phoneInput.value : '',
        form_name: formName,
        form_id: formId
    });
});