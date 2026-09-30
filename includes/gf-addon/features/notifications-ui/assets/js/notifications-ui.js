(function ($) {
    $(document).ready(function () {
        const $rows = $('.gform-settings-panel__content table tbody tr');
        if (!$rows.length) return;

        const langs = new Set();
        const titles = new Set();
        let hasOther = false;

        function getRowName($row) {
            const $nameCell = $row.find('.column-name').first();

            if (!$nameCell.length) {
                return '';
            }

            const $primaryLink = $nameCell.children('a').first();
            const linkedStrongName = $primaryLink.find('strong').first().text().trim();

            if (linkedStrongName) {
                return linkedStrongName;
            }

            const linkedName = $primaryLink.text().trim();

            if (linkedName) {
                return linkedName;
            }

            return $nameCell.find('strong').first().text().trim();
        }

        $rows.each(function () {
            const fullName = getRowName($(this));
            const match = fullName.match(/^(.*?) - ([A-Z]{2})$/);

            if (match) {
                const title = match[1].trim();
                const lang = match[2];

                langs.add(lang);
                titles.add(title);

                $(this).attr({
                    'data-pwe-lang': lang,
                    'data-pwe-title': title
                });
            } else {
                hasOther = true;

                $(this).attr({
                    'data-pwe-lang': 'OTHER',
                    'data-pwe-title': '__OTHER__'
                });
            }
        });

        if (!langs.size && !hasOther) return;

        const $langTabs = $('<div class="pwe-gf-lang-tabs"></div>')
            .append(
                '<button type="button" class="pwe-gf-lang-tab is-active" data-lang="ALL">All</button>'
            );

        if (hasOther) {
            $langTabs.append(
                '<button type="button" class="pwe-gf-lang-tab" data-lang="OTHER">Other</button>'
            );
        }

        Array.from(langs).sort().forEach(function (lang) {
            $langTabs.append(
                $('<button>', {
                    type: 'button',
                    class: 'pwe-gf-lang-tab',
                    'data-lang': lang,
                    text: lang
                })
            );
        });

        const $titleTabs = $('<div class="pwe-gf-title-tabs"></div>')
            .append(
                '<button type="button" class="pwe-gf-title-tab is-active" data-title="ALL">All</button>'
            );

        Array.from(titles).sort().forEach(function (title) {
            $titleTabs.append(
                $('<button>', {
                    type: 'button',
                    class: 'pwe-gf-title-tab',
                    'data-title': title,
                    text: title
                })
            );
        });

        const $counter = $('<div class="pwe-gf-counter"></div>');

        $('.gform-settings-panel__content')
            .first()
            .prepend($counter)
            .prepend($titleTabs)
            .prepend($langTabs);

        function applyFilters() {
            const activeLang = $('.pwe-gf-lang-tab.is-active').data('lang');
            const activeTitle = $('.pwe-gf-title-tab.is-active').data('title');
            let visible = 0;

            $rows.each(function () {
                const matchesLang =
                    activeLang === 'ALL' ||
                    $(this).attr('data-pwe-lang') === activeLang;

                const matchesTitle =
                    activeTitle === 'ALL' ||
                    $(this).attr('data-pwe-title') === activeTitle;

                const hidden = !(matchesLang && matchesTitle);

                $(this).toggleClass('pwe-gf-notification-hidden', hidden);

                if (!hidden) {
                    visible++;
                }
            });

            $counter.text('Wyświetlono: ' + visible + ' / ' + $rows.length);
        }

        $(document).on('click', '.pwe-gf-lang-tab', function () {
            $('.pwe-gf-lang-tab').removeClass('is-active');
            $(this).addClass('is-active');
            applyFilters();
        });

        $(document).on('click', '.pwe-gf-title-tab', function () {
            $('.pwe-gf-title-tab').removeClass('is-active');
            $(this).addClass('is-active');
            applyFilters();
        });

        applyFilters();
    });
}(jQuery));
