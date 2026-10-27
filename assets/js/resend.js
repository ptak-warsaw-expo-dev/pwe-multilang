(function ($) {
    function initResendAutorun() {
        const autorun = document.querySelector('.pwe-resend-autorun');

        if (!autorun) {
            return;
        }

        const delay = parseInt(autorun.getAttribute('data-delay'), 10);
        const action = autorun.getAttribute('data-action');
        const nonceName = autorun.getAttribute('data-nonce-name');
        const nonceValue = autorun.getAttribute('data-nonce-value');

        if (!delay || !action || !nonceName || !nonceValue) {
            return;
        }

        window.setTimeout(function () {
            const form = document.createElement('form');
            const fields = {
                pwe_resend_action: 'run'
            };

            fields[nonceName] = nonceValue;
            form.method = 'post';
            form.action = action;

            Object.keys(fields).forEach(function (name) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = fields[name];
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
        }, delay);
    }

    $(document).ready(function () {
        initResendAutorun();
    });
})(jQuery);

