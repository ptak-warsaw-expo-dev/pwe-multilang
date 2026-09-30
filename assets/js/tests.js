(function ($) {
    function initTestsFilters() {
        const $list = $('#pwe-notifications-list');
        const $rows = $list.find('.pwe-tests-notification-card');

        if (!$rows.length) {
            return;
        }

        const $formFilter = $('#pwe-tests-form-filter');
        const $langTabs = $('#pwe-tests-lang-tabs');
        const $titleTabs = $('#pwe-tests-title-tabs');
        const $formFilterValue = $('#pwe-tests-form-filter-value');
        const $langFilterValue = $('#pwe-tests-lang-filter-value');
        const $notificationFilterValue = $('#pwe-tests-notification-filter-value');
        const $counter = $('#pwe-tests-counter');
        const $checkAll = $('#pwe-check-all');

        function matchingRows(lang, title) {
            const activeForm = String($formFilter.val() || 'ALL');
            const activeLang = String(lang || 'ALL');
            const activeTitle = String(title || 'ALL');

            return $rows.filter(function () {
                const rowForm = String($(this).attr('data-pwe-form-id') || '');
                const rowLang = String($(this).attr('data-pwe-lang') || 'OTHER').toUpperCase();
                const rowTitle = String($(this).attr('data-pwe-title') || '__OTHER__');

                const formMatch = activeForm === 'ALL' || rowForm === activeForm;
                const langMatch = activeLang === 'ALL' || rowLang === activeLang;
                const titleMatch = activeTitle === 'ALL' || rowTitle === activeTitle;

                return formMatch && langMatch && titleMatch;
            });
        }

        function currentLang() {
            return String($langTabs.find('.pwe-gf-lang-tab.is-active').data('lang') || 'ALL');
        }

        function tabButton(className, dataName, value, label, active) {
            return $('<button type="button"></button>')
                .addClass(className)
                .toggleClass('is-active', active)
                .attr(dataName, value)
                .attr('aria-pressed', String(active))
                .text(label);
        }

        function rebuildLangTabs(preferredLang) {
            const current = String(preferredLang || currentLang() || 'ALL');
            const langs = new Set();

            matchingRows('ALL', 'ALL').each(function () {
                const lang = String($(this).attr('data-pwe-lang') || 'OTHER').toUpperCase();

                if (lang) {
                    langs.add(lang);
                }
            });

            const active = current === 'ALL' || !langs.has(current) ? 'ALL' : current;

            $langTabs.empty();
            $langTabs.append(tabButton('pwe-gf-lang-tab', 'data-lang', 'ALL', 'All', active === 'ALL'));

            if (langs.has('OTHER')) {
                $langTabs.append(tabButton('pwe-gf-lang-tab', 'data-lang', 'OTHER', 'Other', active === 'OTHER'));
            }

            Array.from(langs).sort().forEach(function (lang) {
                if (lang === 'OTHER') {
                    return;
                }

                $langTabs.append(tabButton('pwe-gf-lang-tab', 'data-lang', lang, lang, active === lang));
            });
        }

        function rebuildTitleTabs() {
            const titles = new Set();
            const lang = currentLang();

            matchingRows(lang, 'ALL').each(function () {
                const title = String($(this).attr('data-pwe-title') || '__OTHER__');

                if (title) {
                    titles.add(title);
                }
            });


            $titleTabs.empty();
            $titleTabs.append(tabButton('pwe-gf-title-tab', 'data-title', 'ALL', 'All', true));

            if (titles.has('__OTHER__')) {
                $titleTabs.append(tabButton('pwe-gf-title-tab', 'data-title', '__OTHER__', 'Other', true));
            }

            Array.from(titles).sort().forEach(function (title) {
                if (title === '__OTHER__') {
                    return;
                }

                $titleTabs.append(tabButton('pwe-gf-title-tab', 'data-title', title, title, true));
            });
        }

        function syncCheckAllState() {
            const $visibleCheckboxes = $rows
                .not('.pwe-gf-notification-hidden')
                .find('.pwe-notification-checkbox');

            const checked = $visibleCheckboxes.filter(':checked').length;
            const total = $visibleCheckboxes.length;

            $checkAll.prop('checked', total > 0 && checked === total);
            $checkAll.prop('indeterminate', checked > 0 && checked < total);
            const selected = $rows.find('.pwe-notification-checkbox:checked').length;
            $counter.text('Wyświetlono: ' + total + ' / ' + $rows.length + '. Zaznaczono do wysłania: ' + selected);
            $list.closest('form').find('[type="submit"]').prop('disabled', selected === 0);
        }

        function applyFilters() {
            const activeLang = currentLang();
            const titles = new Set($titleTabs.find('.is-active').map(function () {
                return $(this).attr('data-title');
            }).get());
            const $matchingRows = matchingRows(activeLang, 'ALL').filter(function () {
                return titles.has(String($(this).attr('data-pwe-title') || '__OTHER__'));
            });

            $rows.each(function () {
                const hidden = $matchingRows.index(this) === -1;
                $(this).toggleClass('pwe-gf-notification-hidden', hidden);
                $(this).find('.pwe-notification-checkbox').prop('disabled', hidden);
                if (hidden) {
                    $(this).find('.pwe-notification-checkbox').prop('checked', false);
                }
            });

            $formFilterValue.val(String($formFilter.val() || 'ALL'));
            $langFilterValue.val(activeLang);
            $notificationFilterValue.val(Array.from(titles).join('|'));

            $counter.text('Wyswietlono: ' + $matchingRows.length + ' / ' + $rows.length);
            syncCheckAllState();
        }

        $formFilter.on('change', function () {
            rebuildLangTabs('ALL');
            rebuildTitleTabs('ALL');
            applyFilters();
        });

        $(document).on('click', '.pwe-gf-lang-tab', function () {
            $('.pwe-gf-lang-tab').removeClass('is-active');
            $(this).addClass('is-active');
            rebuildTitleTabs('ALL');
            applyFilters();
        });

        $titleTabs.on('click', '.pwe-gf-title-tab', function () {
            const $button = $(this);
            const active = !$button.hasClass('is-active');
            const $buttons = $titleTabs.find('.pwe-gf-title-tab');
            if ($button.attr('data-title') === 'ALL') {
                $buttons.toggleClass('is-active', active).attr('aria-pressed', String(active));
            } else {
                $button.toggleClass('is-active', active).attr('aria-pressed', String(active));
                const $individual = $buttons.filter(function () { return $(this).attr('data-title') !== 'ALL'; });
                const allActive = $individual.length > 0 && $individual.filter('.is-active').length === $individual.length;
                $buttons.filter('[data-title="ALL"]').toggleClass('is-active', allActive).attr('aria-pressed', String(allActive));
            }
            applyFilters();
        });
        $checkAll.on('change', function () {
            const checked = $(this).is(':checked');

            $rows
                .not('.pwe-gf-notification-hidden')
                .find('.pwe-notification-checkbox')
                .prop('checked', checked);

            syncCheckAllState();
        });

        $(document).on('change', '.pwe-notification-checkbox', function () {
            syncCheckAllState();
        });

        rebuildLangTabs('ALL');
        rebuildTitleTabs('ALL');
        applyFilters();
    }

    function initTestRecipients() {
        const $list = $('#pwe-tests-email-list');
        const $add = $('#pwe-tests-add-email');
        let nextId = 1;

        $add.on('click', function () {
            const id = 'pwe-test-email-' + nextId++;
            const $row = $('<div class="pwe-tests-email-row"></div>');
            const $input = $('<input type="email" name="test_emails[]" class="regular-text" required>')
                .attr({id: id, placeholder: 'test@example.com', 'aria-label': 'Adres kolejnego odbiorcy testowego'});
            const $remove = $('<button type="button" class="pwe-tests-remove-email"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>')
                .attr({'aria-label': 'Usuń adres odbiorcy', title: 'Usuń adres odbiorcy'});
            $row.append($input, $remove).appendTo($list);
            $input.trigger('focus');
        });

        $list.on('click', '.pwe-tests-remove-email', function () {
            $(this).closest('.pwe-tests-email-row').remove();
            $add.trigger('focus');
        });
    }

    $(document).ready(function () {
        initTestRecipients();
        initTestsFilters();
    });
})(jQuery);

