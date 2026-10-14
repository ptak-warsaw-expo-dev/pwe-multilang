(() => {
    'use strict';
    const list = document.getElementById('pwe-notifications-list');
    if (!list) return;
    const $ = id => document.getElementById(id);
    const cards = [...list.querySelectorAll('.pwe-tools-notification-card')];
    const formFilter = $('pwe-tools-form-filter');
    const all = $('pwe-check-all');
    const languages = [...new Set(cards.map(card => card.dataset.pweLang))].sort();
    const titles = [...new Set(cards.map(card => card.dataset.pweTitle))].sort();
    let language = 'ALL';
    const selectedTitles = new Set(titles);
    function tabs(id, values, selected, onClick, cls) {
        const container = $(id);
        container.replaceChildren();
        ['ALL', ...values].forEach(value => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = cls;
            button.textContent = value === 'ALL' ? 'All' : value === '__OTHER__' ? 'Other' : cls === 'pwe-gf-lang-tab' ? value.toUpperCase() : value;
            const active = selected(value);
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', String(active));
            button.addEventListener('click', () => { onClick(value); refresh(); });
            container.append(button);
        });
    }
    function count() {
        const visible = cards.filter(card => !card.hidden);
        const checked = visible.filter(card => card.querySelector('input').checked).length;
        $('pwe-tools-counter').textContent = `Widoczne: ${visible.length} · Zaznaczone: ${checked}`;
        all.checked = visible.length > 0 && checked === visible.length;
        all.indeterminate = checked > 0 && checked < visible.length;
    }
    function refresh() {
        cards.forEach(card => {
            card.hidden = !((formFilter.value === 'ALL' || card.dataset.pweFormId === formFilter.value) &&
                (language === 'ALL' || card.dataset.pweLang === language) && selectedTitles.has(card.dataset.pweTitle));
            // The existing card CSS declares display; explicitly hide filtered cards.
            card.style.display = card.hidden ? 'none' : '';
            if (card.hidden) card.querySelector('input').checked = false;
        });
        $('pwe-tools-form-filter-value').value = formFilter.value;
        $('pwe-tools-lang-filter-value').value = language;
        $('pwe-tools-notification-filter-value').value = selectedTitles.size === titles.length ? 'ALL' : [...selectedTitles].join(',');
        tabs('pwe-tools-lang-tabs', languages, value => value === language, value => { language = value; }, 'pwe-gf-lang-tab');
        tabs('pwe-tools-title-tabs', titles, value => value === 'ALL' ? selectedTitles.size === titles.length : selectedTitles.has(value), value => {
            if (value === 'ALL') {
                if (selectedTitles.size === titles.length) selectedTitles.clear();
                else titles.forEach(title => selectedTitles.add(title));
            } else if (selectedTitles.has(value)) selectedTitles.delete(value);
            else selectedTitles.add(value);
        }, 'pwe-gf-title-tab');
        count();
    }
    formFilter.addEventListener('change', refresh);
    cards.forEach(card => card.querySelector('input').addEventListener('change', count));
    all.addEventListener('change', () => {
        cards.filter(card => !card.hidden).forEach(card => { card.querySelector('input').checked = all.checked; });
        count();
    });
    $('pwe-tools-add-email').addEventListener('click', () => {
        const row = document.createElement('div'); row.className = 'pwe-tools-email-row';
        const input = document.createElement('input');
        input.type = 'email'; input.name = 'test_emails[]'; input.className = 'regular-text'; input.placeholder = 'test@example.com'; input.required = true; input.setAttribute('aria-label', 'Adres odbiorcy testowego');
        const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'pwe-tools-remove-email'; remove.setAttribute('aria-label', 'Usuń odbiorcę');
        const icon = document.createElement('span'); icon.className = 'dashicons dashicons-no-alt'; icon.setAttribute('aria-hidden', 'true'); remove.append(icon);
        remove.addEventListener('click', () => row.remove());
        row.append(input, remove); $('pwe-tools-email-list').append(row); input.focus();
    });
    refresh();
})();
