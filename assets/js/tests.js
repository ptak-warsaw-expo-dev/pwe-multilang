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

        function currentTitle() {
            return String($titleTabs.find('.pwe-gf-title-tab.is-active').data('title') || 'ALL');
        }

        function tabButton(className, dataName, value, label, active) {
            return $('<button type="button"></button>')
                .addClass(className)
                .toggleClass('is-active', active)
                .attr(dataName, value)
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

        function rebuildTitleTabs(preferredTitle) {
            const current = String(preferredTitle || currentTitle() || 'ALL');
            const titles = new Set();
            const lang = currentLang();

            matchingRows(lang, 'ALL').each(function () {
                const title = String($(this).attr('data-pwe-title') || '__OTHER__');

                if (title) {
                    titles.add(title);
                }
            });

            const active = current === 'ALL' || !titles.has(current) ? 'ALL' : current;

            $titleTabs.empty();
            $titleTabs.append(tabButton('pwe-gf-title-tab', 'data-title', 'ALL', 'All', active === 'ALL'));

            if (titles.has('__OTHER__')) {
                $titleTabs.append(tabButton('pwe-gf-title-tab', 'data-title', '__OTHER__', 'Other', active === '__OTHER__'));
            }

            Array.from(titles).sort().forEach(function (title) {
                if (title === '__OTHER__') {
                    return;
                }

                $titleTabs.append(tabButton('pwe-gf-title-tab', 'data-title', title, title, active === title));
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
        }

        function applyFilters() {
            const activeLang = currentLang();
            const activeTitle = currentTitle();
            const $matchingRows = matchingRows(activeLang, activeTitle);

            $rows.each(function () {
                $(this).toggleClass('pwe-gf-notification-hidden', $matchingRows.index(this) === -1);
            });

            $rows
                .filter('.pwe-gf-notification-hidden')
                .find('.pwe-notification-checkbox')
                .prop('checked', false);

            $formFilterValue.val(String($formFilter.val() || 'ALL'));
            $langFilterValue.val(activeLang);
            $notificationFilterValue.val(activeTitle);

            $counter.text('Wyswietlono: ' + $matchingRows.length + ' / ' + $rows.length);
            $checkAll.prop('checked', false);
            $checkAll.prop('indeterminate', false);
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

        $(document).on('click', '.pwe-gf-title-tab', function () {
            $('.pwe-gf-title-tab').removeClass('is-active');
            $(this).addClass('is-active');
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

    $(document).ready(function () {
        initTestsFilters();
    });
})(jQuery);

