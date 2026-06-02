(function ($) {
    $(document).ready(function () {
        const $rows = $('.gform-settings-panel__content table tbody tr');

        if (!$rows.length) {
            return;
        }

        const langs = new Set();
        const titles = new Set();

        $rows.each(function () {
            const fullName = $(this).find('td.column-name strong').text().trim();
            const match = fullName.match(/^(.*?) - ([A-Z]{2})$/);

            if (match) {
                const baseTitle = match[1].trim();
                const lang = match[2];

                langs.add(lang);
                titles.add(baseTitle);

                $(this).attr('data-pwe-lang', lang);
                $(this).attr('data-pwe-title', baseTitle);
            } else {
                $(this).attr('data-pwe-lang', 'OTHER');
                $(this).attr('data-pwe-title', '__OTHER__');
            }
        });

        if (!langs.size) {
            return;
        }

        // --- Pasek języków ---
        const $langTabs = $('<div class="pwe-gf-lang-tabs"></div>');

        $langTabs.append('<button type="button" class="pwe-gf-lang-tab is-active" data-lang="ALL">All</button>');
        $langTabs.append('<button type="button" class="pwe-gf-lang-tab" data-lang="OTHER">Other</button>');

        Array.from(langs).sort().forEach(function (lang) {
            $langTabs.append(
                '<button type="button" class="pwe-gf-lang-tab" data-lang="' + lang + '">' + lang + '</button>'
            );
        });

        // --- Pasek tytułów ---
        const $titleTabs = $('<div class="pwe-gf-title-tabs"></div>');

        $titleTabs.append('<button type="button" class="pwe-gf-title-tab is-active" data-title="ALL">All</button>');

        Array.from(titles).sort().forEach(function (title) {
            $titleTabs.append(
                '<button type="button" class="pwe-gf-title-tab" data-title="' + title + '">' + title + '</button>'
            );
        });

        const $container = $('.gform-settings-panel__content').first();
        const $counter = $('<div class="pwe-gf-counter"></div>');
        $container.prepend($counter);
        $container.prepend($titleTabs);
        $container.prepend($langTabs)

        // --- Wspólna funkcja filtrująca ---
        function applyFilters() {
            const activeLang  = $('.pwe-gf-lang-tab.is-active').data('lang');
            const activeTitle = $('.pwe-gf-title-tab.is-active').data('title');

            let visible = 0; // ← tutaj, wewnątrz funkcji

            $rows.each(function () {
                const rowLang  = $(this).attr('data-pwe-lang');
                const rowTitle = $(this).attr('data-pwe-title');

                const langMatch  = activeLang  === 'ALL' || rowLang  === activeLang;
                const titleMatch = activeTitle === 'ALL' || rowTitle === activeTitle;

                const hidden = !(langMatch && titleMatch);
                $(this).toggleClass('pwe-gf-notification-hidden', hidden);

                if (!hidden) visible++; // ← zliczanie
            });

            $counter.text('Wyświetlono: ' + visible + ' / ' + $rows.length);
        }

        // --- Kliknięcia: języki ---
        $(document).on('click', '.pwe-gf-lang-tab', function () {
            $('.pwe-gf-lang-tab').removeClass('is-active');
            $(this).addClass('is-active');
            applyFilters();
        });

        // --- Kliknięcia: tytuły ---
        $(document).on('click', '.pwe-gf-title-tab', function () {
            $('.pwe-gf-title-tab').removeClass('is-active');
            $(this).addClass('is-active');
            applyFilters();
        });

        // --- Inicjalizacja licznika ---
        applyFilters();
    });
})(jQuery);