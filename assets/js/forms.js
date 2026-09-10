(function () {
    'use strict';

    function initTemplateSelector() {
        const form = document.getElementById('pwe-forms-sync-form');

        if (!form) {
            return;
        }

        function updateOverwriteConfirmations(row, checked) {
            row.querySelectorAll('.pwe-form-template-overwrite input').forEach(function (confirmation) {
                confirmation.disabled = !checked;

                if (!checked) {
                    confirmation.checked = false;
                }
            });
        }

        function setTemplateSelection(mode) {
            form.querySelectorAll('.pwe-form-template-row').forEach(function (row) {
                const checkbox = row.querySelector('.pwe-checkbox-container input[type="checkbox"]');

                if (!checkbox || checkbox.disabled) {
                    return;
                }

                if (mode === 'all') {
                    checkbox.checked = true;
                } else if (mode === 'none') {
                    checkbox.checked = false;
                } else if (mode === 'missing') {
                    checkbox.checked = row.getAttribute('data-pwe-exists') === '0';
                } else if (mode === 'existing') {
                    checkbox.checked = row.getAttribute('data-pwe-exists') === '1';
                }

                updateOverwriteConfirmations(row, checkbox.checked);
            });
        }

        form.querySelectorAll('[data-pwe-select]').forEach(function (button) {
            button.addEventListener('click', function () {
                setTemplateSelection(button.getAttribute('data-pwe-select'));
            });
        });

        form.querySelectorAll('.pwe-form-template-row').forEach(function (row) {
            const checkbox = row.querySelector('.pwe-checkbox-container input[type="checkbox"]');
            const confirmations = row.querySelectorAll('.pwe-form-template-overwrite input');

            if (!checkbox || !confirmations.length) {
                return;
            }

            checkbox.addEventListener('change', function () {
                updateOverwriteConfirmations(row, checkbox.checked);
            });

            confirmations.forEach(function (confirmation) {
                confirmation.addEventListener('click', function (event) {
                    event.stopPropagation();
                });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTemplateSelector);
    } else {
        initTemplateSelector();
    }
}());
