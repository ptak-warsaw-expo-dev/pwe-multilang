(() => {
    'use strict';
    const data = document.getElementById('pwe-nt-data');
    if (!data) return;
    let state = JSON.parse(data.textContent);
    const url = state.url, nonce = state.nonce;
    const el = id => document.getElementById('pwe-nt-' + id);
    const root = el('table');
    let dirty = false, busy = false;
    const expandedRows = new WeakSet();
    const drafts = new Map(), appended = new Map();
    const draftFor = id => {
        if (!drafts.has(id)) drafts.set(id, {key: '', values: {}, refs: [], draft: true});
        return drafts.get(id);
    };
    const rememberAdded = (id, key) => { if (!appended.has(id)) appended.set(id, []); appended.get(id).push(key); };
    const clearDraft = id => { drafts.delete(id); el('search').value = ''; el('conflicts').checked = false; };
    const hasDraft = () => [...drafts.values()].some(d => d.key.trim() || Object.values(d.values).some(v => v.trim()));
    const node = (tag, text, cls) => {
        const element = document.createElement(tag);
        if (text !== undefined) element.textContent = text;
        if (cls) element.className = cls;
        return element;
    };
    const button = (text, action) => {
        const b = node('button', text, 'button'); b.type = 'button'; b.onclick = action; return b;
    };
    const status = (text, error = false) => {
        el('status').textContent = text; el('status').classList.toggle('is-error', error);
    };
    const mark = () => { dirty = true; status('Niezapisane zmiany. Zapis zaktualizuje wszystkie powiadomienia korzystające ze zmienionych zmiennych.'); };
    const conflicts = () => {
        const counts = new Map(); state.rows.forEach(r => counts.set(r.key, (counts.get(r.key) || 0) + 1));
        return new Set([...counts].filter(([, count]) => count > 1).map(([key]) => key));
    };
    const languages = () => {
        const id = el('notification').value;
        const notifications = id ? [state.notifications[id]] : Object.values(state.notifications);
        const codes = new Set(['pl', ...notifications.flatMap(n => n.languages)]);
        return [...codes].sort((a, b) => (a === 'pl' ? -1 : b === 'pl' ? 1 : a === 'en' ? -1 : b === 'en' ? 1 : a.localeCompare(b)));
    };
    const merge = (target, sources) => {
        sources.forEach(source => {
            if (source === target) return;
            target.refs.push(...source.refs);
            state.rows.splice(state.rows.indexOf(source), 1);
        });
        mark(); render();
    };
    const attach = row => {
        const id = el('notification').value;
        if (!id) { status('Wybierz powiadomienie, do którego chcesz dodać tłumaczenia.', true); return; }
        if (row.refs.some(ref => ref.id === id)) { status('Ta zmienna jest już w wybranym powiadomieniu.', true); return; }
        if (state.rows.some(other => other !== row && other.refs.some(ref => ref.id === id && ref.key === row.key))) {
            status('Najpierw rozwiąż konflikt tej nazwy w wybranym powiadomieniu.', true); return;
        }
        row.refs.push({id, key: row.key}); rememberAdded(id, row.key); clearDraft(id); mark(); render();
    };
    const suggestions = (container, query, excluded, callback) => {
        container.replaceChildren();
        query = query.trim().toLocaleLowerCase();
        if (query.length < 2) return;
        state.rows.filter(r => r !== excluded && (r.key.toLocaleLowerCase().includes(query) || (r.values.pl || '').toLocaleLowerCase().includes(query)))
            .slice(0, 8).forEach(row => {
                const title = row.key + ' — ' + (row.values.pl || Object.values(row.values)[0] || '').slice(0, 100);
                container.append(button('Użyj: ' + title, () => callback(row)));
            });
    };
    const inputText = value => value.replace(/\\/g, '\\\\').replace(/\r/g, '\\r').replace(/\n/g, '\\n');
    const translationText = value => value.replace(/\\([\\nr])/g, (_, char) => char === 'n' ? '\n' : char === 'r' ? '\r' : '\\');
    const languageName = lang => {
        try { return new Intl.DisplayNames(['pl'], {type: 'language'}).of(lang); }
        catch (_) { return lang.toUpperCase(); }
    };
    function render() {
        root.replaceChildren();
        const bad = conflicts(), id = el('notification').value, query = el('search').value.toLocaleLowerCase();
        const rows = state.rows.filter(r => (!id || r.refs.some(ref => ref.id === id)) && (!el('conflicts').checked || bad.has(r.key)) &&
            (!query || r.key.toLocaleLowerCase().includes(query) || Object.values(r.values).some(v => v.toLocaleLowerCase().includes(query))))
            .sort((a, b) => {
                const order = appended.get(id) || [];
                const position = row => order.findIndex(key => row.refs.some(ref => ref.id === id && ref.key === key));
                const left = position(a), right = position(b);
                return left < 0 && right < 0 ? a.key.localeCompare(b.key) : left < 0 ? -1 : right < 0 ? 1 : left - right;
            });
        const langs = languages();
        const head = node('thead'), tr = node('tr');
        const variableHeading = node('th'); variableHeading.scope = 'col';
        variableHeading.append(node('span', 'Zmienna', 'pwe-nt-heading'), node('small', 'Nazwa · miejsca użycia'));
        tr.append(variableHeading);
        const heading = node('th'); heading.scope = 'col';
        heading.append(node('span', 'Tłumaczenia', 'pwe-nt-heading'), node('small', 'Polski · pozostałe języki po rozwinięciu'));
        tr.append(heading); head.append(tr); root.append(head);
        const body = node('tbody'); root.append(body);
        const displayRows = id ? [...rows, draftFor(id)] : rows;
        displayRows.forEach((row, index) => {
            const line = node('tr', undefined, row.draft ? 'pwe-nt-draft-row' : ''), info = node('td'); line.append(info);
            if (bad.has(row.key)) line.classList.add('pwe-nt-conflict');
            const key = node('input', undefined, 'pwe-nt-key'); key.type = 'text'; key.value = row.key; key.title = row.key; key.setAttribute('aria-label', 'Nazwa zmiennej');
            if (row.draft) { key.id = 'pwe-nt-new-key'; key.placeholder = 'Nazwa nowej zmiennej'; }
            key.onchange = () => {
                if (row.draft) return;
                const value = key.value.trim();
                if (!/^[a-zA-Z_][a-zA-Z0-9_.-]{0,127}$/.test(value) || value === 'lang') {
                    key.value = row.key; status('Nieprawidłowa lub zarezerwowana nazwa zmiennej.', true); return;
                }
                row.key = value; mark(); render();
            };
            const identity = node('div', undefined, 'pwe-nt-identity');
            identity.append(node('span', row.draft ? '+' : String(index + 1).padStart(2, '0'), 'pwe-nt-row-number'), key); info.append(identity);
            if (!row.draft) {
            const refs = node('details', undefined, 'pwe-nt-refs'), summary = node('summary', 'Miejsca użycia · ' + row.refs.length); refs.append(summary);
            row.refs.forEach(ref => refs.append(node('div', state.notifications[ref.id].label))); info.append(refs);
            if (bad.has(row.key)) {
                const conflict = node('div', undefined, 'pwe-nt-conflict-actions');
                conflict.append(node('span', 'Konflikt nazwy', 'pwe-nt-warning'));
                conflict.append(button('Wybierz tę wersję', () => {
                    if (window.confirm('Zastąpić wszystkie warianty „' + row.key + '” tłumaczeniami tej wersji? Zmiany zostaną zapisane dopiero przyciskiem Zapisz.')) {
                        merge(row, state.rows.filter(r => r.key === row.key));
                    }
                }));
                info.append(conflict);
            }
            }
            const translations = node('td');
            let updateLanguageCount = () => {};
            const createLanguageField = lang => {
                const cell = node('div', undefined, 'pwe-nt-value-cell'), input = node('input', undefined, 'pwe-nt-value'), matches = node('div', undefined, 'pwe-nt-suggestions');
                const original = row.values[lang] ?? '';
                input.type = 'text'; input.value = inputText(original); input.title = original;
                const preview = node('div', original, 'pwe-nt-value-preview');
                cell.classList.toggle('is-missing', !original.trim());
                input.placeholder = 'Brak tłumaczenia'; input.setAttribute('aria-label', row.key + ' — ' + lang.toUpperCase());
                input.oninput = () => {
                    row.values[lang] = translationText(input.value); input.title = row.values[lang]; preview.textContent = row.values[lang];
                    cell.classList.toggle('is-missing', !row.values[lang].trim());
                    updateLanguageCount();
                    cell.classList.toggle('is-edited', row.values[lang] !== original); if (!row.draft) mark();
                    if (lang === 'pl') suggestions(row.draft ? el('new-suggestions') : matches, row.values[lang], row, target => {
                        if (row.draft) { attach(target); return; }
                        if (window.confirm('Użyć wszystkich tłumaczeń „' + target.key + '” i połączyć wystąpienia obu zmiennych?')) merge(target, [row]);
                    });
                };
                const label = node('label', undefined, 'pwe-nt-language-label');
                input.id = row.draft ? (lang === 'pl' ? 'pwe-nt-new-text' : 'pwe-nt-new-' + lang) : 'pwe-nt-value-' + index + '-' + lang;
                label.htmlFor = input.id;
                label.append(node('span', lang.toUpperCase(), 'pwe-nt-language-code'), node('span', languageName(lang), 'pwe-nt-language-name'));
                const field = node('div', undefined, 'pwe-nt-field-content');
                field.append(input);
                if (lang === 'pl') field.append(preview, matches);
                cell.append(label, field);
                return cell;
            };
            translations.append(createLanguageField('pl'));
            const rowLanguages = new Set(row.draft
                ? state.notifications[id].languages
                : row.refs.flatMap(ref => state.notifications[ref.id].languages));
            const otherLanguages = langs.filter(lang => lang !== 'pl' && rowLanguages.has(lang));
            if (otherLanguages.length) {
                const panel = node('div', undefined, 'pwe-nt-extra-languages');
                panel.id = 'pwe-nt-extra-' + index;
                const inner = node('div', undefined, 'pwe-nt-extra-inner'); panel.append(inner);
                let mounted = false;
                const mount = () => {
                    if (mounted) return;
                    otherLanguages.forEach(lang => inner.append(createLanguageField(lang)));
                    mounted = true;
                };
                const toggle = button('', () => {
                    if (expandedRows.has(row)) expandedRows.delete(row); else expandedRows.add(row);
                    updateExpansion();
                });
                toggle.className = 'pwe-nt-expand-languages';
                toggle.setAttribute('aria-controls', panel.id);
                const arrow = node('span', '', 'pwe-nt-expand-arrow'); arrow.setAttribute('aria-hidden', 'true');
                const caption = node('span'), count = node('span', '', 'pwe-nt-expand-count');
                count.setAttribute('aria-live', 'polite');
                toggle.append(arrow, caption, count);
                updateLanguageCount = () => {
                    const missing = otherLanguages.filter(lang => !(row.values[lang] || '').trim());
                    count.textContent = (otherLanguages.length - missing.length) + ' / ' + otherLanguages.length;
                    count.classList.toggle('is-incomplete', missing.length > 0);
                    count.classList.toggle('is-complete', missing.length === 0);
                    const description = missing.length ? 'Brak tłumaczeń: ' + missing.map(lang => lang.toUpperCase()).join(', ') : 'Wszystkie pozostałe języki są uzupełnione';
                    count.title = description;
                    count.setAttribute('aria-label', count.textContent + ' tłumaczeń. ' + description);
                };
                updateLanguageCount();
                const updateExpansion = () => {
                    const open = expandedRows.has(row);
                    if (open) mount();
                    toggle.setAttribute('aria-expanded', String(open));
                    panel.setAttribute('aria-hidden', String(!open)); panel.inert = !open;
                    panel.classList.toggle('is-open', open); toggle.classList.toggle('is-open', open);
                    caption.textContent = (open ? 'Ukryj pozostałe języki' : 'Pokaż pozostałe języki');
                };
                updateExpansion(); translations.append(toggle, panel);
            }
            if (row.draft) {
                const matches = node('div', undefined, 'pwe-nt-suggestions'); matches.id = 'pwe-nt-new-suggestions';
                key.oninput = () => { row.key = key.value; suggestions(matches, key.value, null, attach); };
                const add = button('Dodaj', () => addDraft(id, row, matches)); add.id = 'pwe-nt-add';
                const actions = node('div', undefined, 'pwe-nt-draft-actions');
                actions.append(add, node('span', 'Dodaj wpis, a następnie zapisz zmiany.'));
                info.append(node('p', 'Nowa zmienna · ' + state.notifications[id].label, 'pwe-nt-draft-hint'), matches);
                translations.append(actions);
            }
            line.append(translations); body.append(line);
        });
        if (!displayRows.length) {
            const emptyRow = node('tr'), empty = node('td', 'Brak zmiennych pasujących do wybranych filtrów.', 'pwe-nt-empty');
            empty.colSpan = 2; emptyRow.append(empty); body.append(emptyRow);
        }
        const summary = el('summary'); summary.replaceChildren();
        summary.append(node('strong', rows.length + ' / ' + state.rows.length + ' zmiennych'), node('span', langs.length + ' języków'), node('span', bad.size + ' konfliktów nazw', bad.size ? 'pwe-nt-summary-warning' : ''));

    }
    function populate() {
        const select = el('notification'), current = select.value;
        select.replaceChildren(node('option', 'Cała biblioteka')); select.firstChild.value = '';
        Object.values(state.notifications).forEach(n => { const option = node('option', n.label); option.value = n.id; select.append(option); });
        select.value = current in state.notifications ? current : ''; render();
    }
    ['notification', 'conflicts'].forEach(id => el(id).onchange = () => { render(); });
    el('search').oninput = () => { render(); };
    el('separate').onclick = () => {
        const bad = conflicts(), used = new Set(state.rows.map(r => r.key));
        if (!bad.size) { status('Brak konfliktów nazw.'); return; }
        state.rows.forEach(row => {
            if (!bad.has(row.key)) return;
            const suffix = row.refs[0].id.replace('/notifications/', '_').replace(/[^a-zA-Z0-9_]/g, '_');
            const base = (row.key + '_' + suffix).slice(0, 115); let key = base, i = 2;
            while (used.has(key)) key = base + '_' + i++;
            row.key = key; used.add(key);
        });
        mark(); render();
    };
    function addDraft(id, draft, matches) {
        const key = draft.key.trim();
        if (!/^[a-zA-Z_][a-zA-Z0-9_.-]{0,127}$/.test(key) || key === 'lang') { status('Podaj poprawną nazwę zmiennej (litery, cyfry, _, -, .).', true); return; }
        const reserved = state.rows.find(row => row.key !== key && row.refs.some(ref => ref.id === id && ref.key === key));
        if (reserved) {
            status('Nazwa „' + key + '” jest zarezerwowana do czasu zapisania zmiany na „' + reserved.key + '”. Wyczyść nowy wpis, zapisz zmianę nazwy, a następnie dodaj zmienną pod dawną nazwą.', true);
            matches.replaceChildren(button('Wyczyść nowy wpis', () => { drafts.delete(id); render(); status('Nowy wpis wyczyszczono. Możesz zapisać zmianę nazwy.'); }));
            return;
        }
        const texts = Object.values(draft.values).map(v => v.trim().toLocaleLowerCase()).filter(Boolean);
        const existing = state.rows.filter(r => r.key === key || Object.values(r.values).some(v => texts.includes(v.trim().toLocaleLowerCase())));
        if (existing.length) {
            status('Taka nazwa lub treść już istnieje. Wybierz istniejącą zmienną, aby użyć wszystkich tłumaczeń.', true);
            matches.replaceChildren(...existing.map(r => button('Użyj: ' + r.key, () => attach(r)))); return;
        }
        const values = {}; languages().forEach(lang => { values[lang] = draft.values[lang] || ''; });
        state.rows.push({key, values, refs: [{id, key}]}); rememberAdded(id, key); clearDraft(id); mark(); render();
    }
    el('export').onclick = () => {
        const exported = {
            format: 'pwe-notification-translations',
            version: 1,
            exported_at: new Date().toISOString(),
            unsaved_changes: dirty,
            variables: state.rows.map(row => ({
                key: row.key,
                translations: {...row.values},
                occurrences: row.refs.map(ref => ({
                    form: ref.id.split('/')[0],
                    notification: ref.id.split('/').pop(),
                    path: 'modules/forms/form-templates/' + ref.id,
                    source_key: ref.key,
                    languages: [...state.notifications[ref.id].languages]
                }))
            }))
        };
        const json = JSON.stringify(exported, null, 2).replace(
            /("languages": )\[([\s\S]*?)\]/g,
            (_, prefix, values) => prefix + '[' + JSON.parse('[' + values + ']').map(value => JSON.stringify(value)).join(', ') + ']'
        );
        const blob = new Blob([json + '\n'], {type: 'application/json;charset=utf-8'});
        const downloadUrl = URL.createObjectURL(blob);
        const link = node('a');
        link.href = downloadUrl;
        link.download = 'pwe-notification-translations-' + new Date().toISOString().slice(0, 10) + '.json';
        document.body.append(link); link.click(); link.remove();
        setTimeout(() => URL.revokeObjectURL(downloadUrl), 1000);
        status('Wyeksportowano całą bibliotekę: ' + state.rows.length + ' zmiennych, wszystkie tłumaczenia i miejsca użycia. Eksport uwzględnia edycje w tabeli; pomija niewstawiony nowy wiersz.');
    };
    el('save').onclick = async () => {
        if (busy) return;
        if (hasDraft()) { status('Masz niewstawioną zmienną. Kliknij Dodaj w pustym wierszu lub wyczyść jego pola przed zapisem.', true); return; }
        if (conflicts().size) { status('Najpierw rozwiąż konflikty nazw: wybierz wspólną wersję albo nadaj osobne nazwy.', true); return; }
        busy = true;
        const controls = [...document.getElementById('pwe-nt').querySelectorAll('input, textarea, select, button')];
        const enabled = controls.filter(control => !control.disabled); enabled.forEach(control => { control.disabled = true; });
        status('Zapisywanie…');
        try {
            const body = new URLSearchParams({action: 'pwe_notification_translations_save', nonce, payload: JSON.stringify({revision: state.revision, rows: state.rows})});
            const response = await fetch(url, {method: 'POST', credentials: 'same-origin', body});
            const result = await response.json();
            if (!result.success) throw new Error(result.data?.message || 'Zapis nie powiódł się.');
            state = result.data.state; dirty = false; populate();
            status('Zapisano. Zmienione powiadomienia: ' + result.data.changed.length + '. Aby zastosować treści w istniejących formularzach, wykonaj ich synchronizację w Forms.');
        } catch (error) { status(error.message, true); }
        finally { busy = false; enabled.forEach(control => { control.disabled = false; }); }
    };
    window.addEventListener('beforeunload', event => { if (dirty || busy || hasDraft()) { event.preventDefault(); event.returnValue = ''; } });
    populate();
    status(`Wczytano ${Object.keys(state.notifications).length} powiadomień MJML i ${state.rows.length} wpisów biblioteki. Zmiany trafiają do plików dopiero po zapisaniu.`);
})();
