(function ($) {
    function initReplaceContent() {
        const $form = $('#pwe-replace-content-form');

        if (!$form.length) {
            return;
        }

        const ajaxUrl = String($form.data('ajax-url') || '');
        const startAction = String($form.data('start-action') || '');
        const stepAction = String($form.data('step-action') || '');
        const nonce = String($form.find('[name="pwe_replace_content_nonce"]').val() || '');
        const confirmMessage = String($form.data('confirm') || 'Czy na pewno chcesz wykonać operację?');
        const $button = $('#pwe-replace-content-submit');
        const $buttonLabel = $button.find('.pwe-replace-button-label');
        const $progress = $('#pwe-replace-progress');
        const $progressTitle = $('#pwe-replace-progress-title');
        const $progressPercent = $('#pwe-replace-progress-percent');
        const $progressTrack = $('#pwe-replace-progress-track');
        const $progressBar = $('#pwe-replace-progress-bar');
        const $progressDetail = $('#pwe-replace-progress-detail');
        const $result = $('#pwe-replace-result');
        const maxStepRetries = 4;
        let running = false;
        let resumableJobToken = '';
        let lastKnownResult = null;
        let lastKnownProcessed = 0;

        if (!ajaxUrl || !startAction || !stepAction || !nonce) {
            return;
        }

        function getErrorMessage(xhr, fallback) {
            const response = xhr && xhr.responseJSON;
            const message = response && response.data && response.data.message;

            return String(message || fallback || 'Wystąpił nieznany błąd.');
        }

        function updateProgress(percent, processed, total, detail) {
            const safePercent = Math.max(0, Math.min(100, parseInt(percent, 10) || 0));

            $progress.removeAttr('hidden');
            $progressBar.css('width', safePercent + '%');
            $progressPercent.text(safePercent + '%');
            $progressTrack.attr('aria-valuenow', safePercent);

            if (total > 0) {
                $progressTitle.text('Przetwarzanie stron: ' + processed + ' / ' + total);
            }

            if (detail) {
                $progressDetail.text(detail);
            }
        }

        function renderResult(result, transportMessage) {
            const data = result || {};
            const updated = parseInt(data.updated, 10) || 0;
            const unchanged = parseInt(data.unchanged, 10) || 0;
            const failed = parseInt(data.failed, 10) || 0;
            const errors = Array.isArray(data.errors) ? data.errors.slice() : [];

            if (transportMessage) {
                errors.unshift(transportMessage);
            }

            const hasErrors = failed > 0 || errors.length > 0;
            const $notice = $('<div></div>')
                .addClass(hasErrors ? 'pwe-notice-error' : 'pwe-notice-success')
                .append(
                    $('<span></span>').addClass(
                        'dashicons ' + (hasErrors ? 'dashicons-warning' : 'dashicons-yes-alt')
                    )
                );
            const $content = $('<div></div>');
            const $summary = $('<p></p>');

            $summary.append(document.createTextNode('Operacja zakończona. Zmieniono: '));
            $summary.append($('<strong></strong>').text(updated));
            $summary.append(document.createTextNode(', bez zmian: '));
            $summary.append($('<strong></strong>').text(unchanged));
            $summary.append(document.createTextNode(', błędy: '));
            $summary.append($('<strong></strong>').text(failed));
            $summary.append(document.createTextNode('.'));
            $content.append($summary);

            if (errors.length) {
                const $list = $('<ul></ul>');

                errors.forEach(function (error) {
                    $list.append($('<li></li>').text(String(error)));
                });

                $content.append($list);
            }

            $notice.append($content);
            $result.empty().append($notice);
        }

        function updatePageRow(page) {
            if (!page || !page.post_id) {
                return;
            }

            const $rows = $('.pwe-replace-translation[data-post-id="' + page.post_id + '"]');
            const $statuses = $rows.find('.pwe-replace-status');
            const $icons = $statuses.find('.dashicons');
            const $labels = $statuses.find('.pwe-replace-status-label');

            $statuses.removeClass('is-pending is-ready is-error');
            $icons.removeClass('dashicons-edit dashicons-yes-alt dashicons-warning');

            if (page.status === 'failed') {
                $statuses.addClass('is-error');
                $icons.addClass('dashicons-warning');
                $labels.text('Błąd zapisu');
                syncPendingCount();
                return;
            }

            $statuses.addClass('is-ready');
            $icons.addClass('dashicons-yes-alt');
            $labels.text('Już zgodna');
            syncPendingCount();
        }

        function syncPendingCount() {
            const postIds = new Set();

            $('.pwe-replace-status.is-pending, .pwe-replace-status.is-error').each(function () {
                const postId = String($(this).closest('.pwe-replace-translation').data('post-id') || '');

                if (postId) {
                    postIds.add(postId);
                }
            });

            $('#pwe-replace-change-count').text(postIds.size);
        }

        function finish(data) {
            const failed = parseInt(data.result && data.result.failed, 10) || 0;

            running = false;
            resumableJobToken = '';
            lastKnownResult = data.result || lastKnownResult;
            $button.prop('disabled', false).removeClass('is-loading');
            $buttonLabel.text('Uruchom ponownie');
            updateProgress(
                100,
                data.processed || data.total || 0,
                data.total || 0,
                failed > 0 ? 'Operacja zakończyła się z błędami.' : 'Wszystkie strony zostały przetworzone.'
            );
            $progress.toggleClass('is-error', failed > 0).toggleClass('is-complete', failed === 0);
            renderResult(data.result || {});
        }

        function stopWithError(message, result, jobToken, retryable) {
            running = false;
            resumableJobToken = retryable && jobToken ? String(jobToken) : '';
            $button.prop('disabled', false).removeClass('is-loading');
            $buttonLabel.text(resumableJobToken ? 'Wznów operację' : 'Spróbuj ponownie');
            $progress.removeAttr('hidden').addClass('is-error').removeClass('is-complete');
            $progressTitle.text('Operacja została przerwana');
            $progressDetail.text(message);
            renderResult(result || lastKnownResult || {updated: 0, unchanged: 0, failed: 1, errors: []}, message);
        }

        function processStep(jobToken, retryAttempt) {
            retryAttempt = parseInt(retryAttempt, 10) || 0;

            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: stepAction,
                    nonce: nonce,
                    job: jobToken,
                    processed: lastKnownProcessed
                }
            }).done(function (response) {
                if (!response || !response.success) {
                    const rejectedData = response && response.data ? response.data : {};

                    stopWithError(
                        String(rejectedData.message || 'Serwer odrzucił kolejny etap operacji.'),
                        rejectedData.result || lastKnownResult,
                        jobToken,
                        Boolean(rejectedData.retryable)
                    );
                    return;
                }

                const data = response.data || {};
                const page = data.page || {};
                lastKnownResult = data.result || lastKnownResult;
                lastKnownProcessed = Math.max(
                    lastKnownProcessed,
                    parseInt(data.processed, 10) || 0
                );
                const pageLabel = [String(page.language || '').toUpperCase(), String(page.title || '')]
                    .filter(Boolean)
                    .join(' · ');

                updatePageRow(page);
                updateProgress(
                    data.percent,
                    data.processed,
                    data.total,
                    pageLabel ? 'Ostatnia strona: ' + pageLabel : 'Przetwarzanie kolejnej strony…'
                );

                if (data.complete) {
                    finish(data);
                    return;
                }

                window.setTimeout(function () {
                    processStep(jobToken, 0);
                }, 80);
            }).fail(function (xhr) {
                const response = xhr && xhr.responseJSON;
                const errorData = response && response.data ? response.data : {};
                const status = parseInt(xhr && xhr.status, 10) || 0;
                const retryable = Boolean(errorData.retryable)
                    || status === 0
                    || status === 409
                    || status >= 500;

                lastKnownResult = errorData.result || lastKnownResult;

                if (retryable && retryAttempt < maxStepRetries) {
                    const retryDelay = Math.min(2000, 250 * Math.pow(2, retryAttempt));

                    updateProgress(
                        errorData.percent || 0,
                        errorData.processed || 0,
                        errorData.total || 0,
                        'Ponawianie tego samego etapu operacji…'
                    );

                    window.setTimeout(function () {
                        processStep(jobToken, retryAttempt + 1);
                    }, retryDelay);
                    return;
                }

                stopWithError(
                    getErrorMessage(xhr, 'Nie udało się wykonać kolejnego etapu operacji.'),
                    errorData.result || lastKnownResult,
                    jobToken,
                    retryable
                );
            });
        }

        $form.on('submit', function (event) {
            if (running) {
                event.preventDefault();
                return;
            }

            if (!window.confirm(confirmMessage)) {
                event.preventDefault();
                return;
            }

            event.preventDefault();
            const resumeToken = resumableJobToken;

            resumableJobToken = '';
            running = true;
            $result.empty();
            $button.prop('disabled', true).addClass('is-loading');
            $buttonLabel.text('Przetwarzanie…');
            $progress.removeClass('is-complete is-error');
            $progressTitle.text('Przygotowywanie operacji…');
            updateProgress(0, 0, 0, 'Tworzenie kolejki stron i tłumaczeń WPML…');

            if (resumeToken) {
                $progressTitle.text('Wznawianie operacji…');
                updateProgress(0, 0, 0, 'Odczytywanie zapisanego postępu…');
                processStep(resumeToken, 0);
                return;
            }

            lastKnownResult = null;
            lastKnownProcessed = 0;

            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: startAction,
                    nonce: nonce
                }
            }).done(function (response) {
                if (!response || !response.success) {
                    stopWithError('Serwer nie pozwolił rozpocząć operacji.');
                    return;
                }

                const data = response.data || {};
                lastKnownResult = data.result || lastKnownResult;
                lastKnownProcessed = parseInt(data.processed, 10) || 0;

                if (data.complete) {
                    finish(data);
                    return;
                }

                updateProgress(0, 0, data.total, 'Rozpoczynanie podmiany treści…');
                processStep(String(data.job || ''), 0);
            }).fail(function (xhr) {
                stopWithError(getErrorMessage(xhr, 'Nie udało się rozpocząć operacji.'));
            });
        });
    }

    $(document).ready(function () {
        initReplaceContent();
    });
})(jQuery);
