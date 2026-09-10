(function ($) {
    'use strict';

    $(function () {
        var config = document.getElementById('pwe-mlg-form-lock-config');

        if (!config) {
            return;
        }

        var MSG = config.getAttribute('data-message') || '';
        var SCOPE = config.getAttribute('data-scope') || '';
        var managedIds = [];

        try {
            managedIds = JSON.parse(config.getAttribute('data-managed-ids') || '[]');
        } catch (error) {
            managedIds = [];
        }

        function pweMlgMatches(el, selector) {
            if (!el || el.nodeType !== 1) {
                return false;
            }

            if (el.matches && el.matches(selector)) {
                return true;
            }

            return Boolean(el.closest && el.closest(selector));
        }

        function pweMlgCaptureBlock(selector) {
            document.addEventListener('click', function (event) {
                if (!pweMlgMatches(event.target, selector)) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();
                pweMlgToast(MSG);

                return false;
            }, true);

            document.addEventListener('keypress', function (event) {
                if (!pweMlgMatches(event.target, selector)) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();
                pweMlgToast(MSG);

                return false;
            }, true);
        }

        function pweMlgToast(message) {
            $('.pwe-mlg-toast').remove();

            var $toast = $(
                '<div class="pwe-mlg-toast">' +
                    '<span class="dashicons dashicons-lock"></span>' +
                    '<span class="pwe-mlg-toast__msg"></span>' +
                    '<button type="button" class="pwe-mlg-toast__close" aria-label="Zamknij">&times;</button>' +
                '</div>'
            );

            $toast.find('.pwe-mlg-toast__msg').text(message);
            $toast.find('.pwe-mlg-toast__close').on('click', function () {
                $toast.remove();
            });
            $('body').append($toast);

            setTimeout(function () {
                $toast.fadeOut(400, function () {
                    $(this).remove();
                });
            }, 8000);
        }

        function pweMlgBlockClick(event) {
            event.preventDefault();
            event.stopImmediatePropagation();
            pweMlgToast(MSG);
            return false;
        }

        function pweMlgMark($elements) {
            $elements.each(function () {
                var $element = $(this);
                $element.addClass('pwe-mlg-blocked-save-button');

                if (!$element.parent().is('span[title]')) {
                    $element.wrap('<span class="pwe-mlg-blocked-save-wrapper" title="' + MSG + '"></span>');
                }
            });
        }

        if (SCOPE !== '') {
            $('body').addClass('pwe-mlg-gf-locked');
        }

        if (SCOPE === 'form_editor') {
            var editorButtons = [
                '#gform_save',
                '#gform_save_button',
                '#ajax-save-form-menu-bar',
                '.gform_save_link',
                '.update-form-ajax',
                '[data-js="save-form-button"]',
                '[data-js="ajax-save-form"]',
                'button[aria-label="Zapisz formularz"]',
                'button[aria-label="Save Form"]'
            ].join(',');

            pweMlgMark($(editorButtons));
            $(document).on('click', editorButtons, pweMlgBlockClick);
            pweMlgCaptureBlock(editorButtons);
            $(document).on('submit', 'form#gform_form_editor, form#gform_editor_form', function (event) {
                event.preventDefault();
                event.stopImmediatePropagation();
                pweMlgToast(MSG);
                return false;
            });
        }

        if (
            SCOPE === 'form_settings' ||
            SCOPE === 'confirmation_existing' ||
            SCOPE === 'confirmation_new' ||
            SCOPE === 'notification_existing' ||
            SCOPE === 'notification_new' ||
            SCOPE === 'qr_existing' ||
            SCOPE === 'qr_new'
        ) {
            var settingsButtons = [
                '#gform-settings-save',
                'button[name="gform-settings-save"][value="save"]',
                'input[name="gform-settings-save"][value="save"]'
            ].join(',');
            var addNewButtons = [
                'a.button[href*="page=gf_edit_forms"][href*="view=settings"][href*="subview=confirmation"][href*="cid=0"]',
                'a.button[href*="page=gf_edit_forms"][href*="view=settings"][href*="subview=notification"][href*="nid=0"]',
                'a.button[href*="page=gf_edit_forms"][href*="view=settings"][href*="subview=pwe_qr"][href*="fid=0"]',
                'a.button[href*="page=gf_edit_forms"][href*="view=settings"][href*="subview=qr-code"][href*="fid=0"]'
            ].join(',');
            var duplicateDeleteButtons = [
                'a[onclick*="DuplicateNotification("]',
                'a[onkeypress*="DuplicateNotification("]',
                'a[onclick*="DeleteNotification("]',
                'a[onkeypress*="DeleteNotification("]',
                'a[href*="page=gf_edit_forms"][href*="view=settings"][href*="subview=confirmation"][href*="cid=0"][href*="duplicatedcid="]',
                'a[onclick*="DuplicateConfirmation("]',
                'a[onkeypress*="DuplicateConfirmation("]',
                'a[onclick*="DeleteConfirmation("]',
                'a[onkeypress*="DeleteConfirmation("]',
                'a[onclick*="deleteconfirmation("]',
                'a[onkeypress*="deleteconfirmation("]'
            ].join(',');
            var blockedButtons = settingsButtons + ',' + addNewButtons + ',' + duplicateDeleteButtons;

            pweMlgMark($(blockedButtons));
            $(document).on('click', blockedButtons, pweMlgBlockClick);
            pweMlgCaptureBlock(blockedButtons);
            $(document).on('submit', 'form#gform-settings', function (event) {
                event.preventDefault();
                event.stopImmediatePropagation();
                pweMlgToast(MSG);
                return false;
            });
        }

        var badge = '<span class="pwe-mlg-readonly-badge"><span class="dashicons dashicons-lock"></span> Multilang</span>';
        var formLinks = $('a[href*="page=gf_edit_forms"][href*="id="], a[href*="page=gf_entries"][href*="id="]');

        managedIds.forEach(function (formId) {
            formLinks.filter(function () {
                try {
                    return new URL(this.href, window.location.href).searchParams.get('id') === String(formId);
                } catch (error) {
                    return false;
                }
            }).each(function () {
                var $link = $(this);

                if ($link.closest('tr').find('.pwe-mlg-readonly-badge').length) {
                    return;
                }

                $link.after(badge);
            });
        });
    });
}(jQuery));
