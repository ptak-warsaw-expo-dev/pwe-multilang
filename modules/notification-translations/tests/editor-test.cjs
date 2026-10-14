/* Lightweight DOM interaction test. Run with node; no dependencies or network. */
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
class Element {
    constructor(tag = 'div') { this.tagName = tag; this.children = []; this.value = ''; this.textContent = ''; this.disabled = false; this.checked = false; this.classList = {add() {}, toggle() {}}; }
    append(...items) { this.children.push(...items); }
    replaceChildren(...items) { this.children = items; }
    setAttribute() {}
    click() {}
    remove() {}
    get firstChild() { return this.children[0]; }
    querySelectorAll(selector) {
        const tags = selector.split(',').map(s => s.trim());
        return this.children.flatMap(child => [...(tags.includes(child.tagName) ? [child] : []), ...child.querySelectorAll(selector)]);
    }
}
async function run() {
    const ids = ['data','notification','search','conflicts','export','save','status','separate','table','summary'];
    const elements = Object.fromEntries(ids.map(id => ['pwe-nt-' + id, new Element(id === 'notification' ? 'select' : 'div')]));
    elements['pwe-nt'] = new Element(); elements['pwe-nt'].append(...Object.values(elements).filter(e => e !== elements['pwe-nt']));
    const lookup = id => {
        const walk = element => element.id === id ? element : element.children.map(walk).find(Boolean);
        return elements[id] || walk(elements['pwe-nt']) || null;
    };
    const get = id => lookup('pwe-nt-' + id);
    const a = 'form/notifications/a', b = 'form/notifications/b';
    const fixture = {url: '/ajax', nonce: 'test', revision: 'revision', notifications: {
        [a]: {id:a,label:'A',languages:['pl','en']}, [b]: {id:b,label:'B',languages:['pl','en','de']}
    }, rows: [
        {key:'thanks',values:{pl:'Dziękujemy za rejestrację',en:'Thank you'},refs:[{id:a,key:'thanks'}]},
        {key:'title',values:{pl:'Tytuł A',en:'Title A'},refs:[{id:a,key:'title'}]},
        {key:'title',values:{pl:'Tytuł B',en:'Title B'},refs:[{id:b,key:'title'}]}
    ]};
    get('data').textContent = JSON.stringify(fixture);
    let submitted, exportedBlob, fail = false;
    const context = {
        Blob, URL: {createObjectURL: blob => { exportedBlob = blob; return 'blob:test'; }, revokeObjectURL() {}}, setTimeout: callback => callback(),
        document: {body: new Element(), getElementById: lookup, createElement: tag => new Element(tag)},
        window: {confirm: () => true, addEventListener() {}}, URLSearchParams,
        fetch: async (url, options) => {
            submitted = JSON.parse(options.body.get('payload'));
            if (fail) throw Error('Offline');
            return {json: async () => ({success: true, data:{changed:[a,b],state:{...fixture,...submitted}}})};
        }
    };
    vm.runInNewContext(fs.readFileSync(__dirname + '/../assets/editor.js','utf8'), context);
    assert(get('status').textContent.includes('2 powiadomień'));
    assert.equal(get('table').children[0].children[0].children.length, 2);
    get('notification').value = a; get('search').value = 'thanks';
    get('export').onclick();
    const exported = JSON.parse(await exportedBlob.text());
    assert.equal(exported.variables.length, 3);
    assert.equal(exported.variables[2].translations.en, 'Title B');
    assert.equal(exported.variables[2].occurrences[0].notification, 'b');
    assert.equal(exported.variables[2].occurrences[0].form, 'form');
    assert.equal(exported.nonce, undefined);
    get('notification').value = ''; get('search').value = '';
    const polish = get('table').querySelectorAll('input').filter(input => input.className === 'pwe-nt-value')[0];
    polish.value = 'Zmienione PL'; polish.oninput();
    const expand = get('table').querySelectorAll('button').find(button => button.className === 'pwe-nt-expand-languages');
    assert.equal(get('table').querySelectorAll('input').filter(input => input.className === 'pwe-nt-value').length, 3);
    expand.onclick();
    const english = get('table').querySelectorAll('input').filter(input => input.className === 'pwe-nt-value')[1];
    assert.equal(english.value, 'Thank you');
    english.value = 'Title B'; english.oninput();
    assert.equal(get('table').querySelectorAll('div').filter(div => div.className === 'pwe-nt-suggestions').reduce((n, div) => n + div.children.length, 0), 0);
    english.value = 'Updated EN'; english.oninput();
    expand.onclick(); expand.onclick();
    assert.equal(english.value, 'Updated EN');
    english.value = 'Thank you'; english.oninput();
    const restored = get('table').querySelectorAll('input').filter(input => input.className === 'pwe-nt-value')[0];
    assert.equal(restored.value, 'Zmienione PL');
    restored.value = 'Dziękujemy za rejestrację'; restored.oninput();
    await get('save').onclick(); assert.equal(submitted, undefined); assert(get('status').textContent.includes('konflikty'));
    get('separate').onclick();
    get('notification').value = b; get('notification').onchange();
    get('new-text').value = 'Dziękujemy za reje'; get('new-text').oninput();
    assert.equal(get('new-suggestions').children.length, 1);
    get('new-suggestions').children[0].onclick();
    const languageToggles = get('table').querySelectorAll('button').filter(button => button.className === 'pwe-nt-expand-languages');
    languageToggles.forEach(button => button.onclick());
    assert(get('table').querySelectorAll('input').some(input => (input.id || '').endsWith('-de') && input.value === ''));
    await get('save').onclick();
    assert(!('de' in submitted.rows.find(row => row.values.pl === 'Tytuł A').values));
    assert.equal(submitted.rows.find(row => row.values.pl === 'Tytuł A').values.en, 'Title A');
    let thanks = submitted.rows.find(row => row.key === 'thanks');
    assert.equal(thanks.refs.length, 2); assert.equal(thanks.values.en, 'Thank you'); assert.equal(thanks.values.de, undefined);
    assert.equal(new Set(submitted.rows.map(row => row.key)).size, submitted.rows.length);
    assert(get('status').textContent.includes('Zapisano'));
    get('new-key').value = 'duplicate'; get('new-key').oninput(); get('new-text').value = 'Dziękujemy za rejestrację'; get('new-text').oninput(); get('add').onclick();
    assert(get('status').textContent.includes('już istnieje')); assert.equal(get('new-suggestions').children.length, 1);
    get('new-key').value = 'aaa_unique'; get('new-key').oninput(); get('new-text').value = 'Nowa treść'; get('new-text').oninput();
    const draftRow = get('table').children[1].children.at(-1);
    const draftExpand = draftRow.querySelectorAll('button').find(button => button.className === 'pwe-nt-expand-languages');
    draftExpand.onclick();
    get('new-en').value = 'New English translation'; get('new-en').oninput();
    get('add').onclick();
    assert.equal(get('new-key').value, ''); assert.equal(get('new-text').value, '');
    const addedRow = get('table').children[1].children.at(-2);
    assert.equal(addedRow.querySelectorAll('input')[0].value, 'aaa_unique');
    await get('save').onclick(); assert(submitted.rows.some(row => row.key === 'aaa_unique' && row.values.en === 'New English translation' && row.refs[0].id === b));
    fail = true; await get('save').onclick(); assert.equal(get('status').textContent, 'Offline');
    // Renaming an existing variable to an occupied name keeps both records.
    get('data').textContent = JSON.stringify(fixture); fail = false;
    vm.runInNewContext(fs.readFileSync(__dirname + '/../assets/editor.js','utf8'), context);
    get('notification').value = ''; get('notification').onchange();
    const source = get('table').querySelectorAll('input').find(input => input.className === 'pwe-nt-key' && input.value === 'thanks');
    source.value = 'title'; source.onchange();
    const keys = get('table').querySelectorAll('input').filter(input => input.className === 'pwe-nt-key');
    assert.equal(keys.length, 3);
    assert(keys.every(input => input.value === 'title'));
    assert.equal(get('table').querySelectorAll('button').filter(button => button.textContent === 'Wybierz tę wersję').length, 3);
    assert.equal(get('table').querySelectorAll('div').filter(div => div.className === 'pwe-nt-merge-choices').length, 0);
    // A pending rename reserves its original reference until saved.
    const renameFixture = {...fixture, rows: [{key:'old_name', values:{pl:'Original'}, refs:[{id:a,key:'old_name'}]}]};
    get('data').textContent = JSON.stringify(renameFixture);
    vm.runInNewContext(fs.readFileSync(__dirname + '/../assets/editor.js','utf8'), context);
    get('notification').value = a; get('notification').onchange();
    const renamed = get('table').querySelectorAll('input').find(input => input.className === 'pwe-nt-key' && input.value === 'old_name');
    renamed.value = 'new_name'; renamed.onchange();
    get('new-key').value = 'old_name'; get('new-key').oninput();
    get('new-text').value = 'Different content'; get('new-text').oninput(); get('add').onclick();
    assert(get('status').textContent.includes('zarezerwowana'));
    assert.equal(get('table').children[1].children.length, 2);
    assert.equal(get('new-text').value, 'Different content');
    get('new-suggestions').children[0].onclick();
    await get('save').onclick();
    assert.equal(submitted.rows.length, 1);
    assert.equal(submitted.rows[0].key, 'new_name');
    assert.equal(submitted.rows[0].refs[0].key, 'old_name');
    console.log('PASS: editor rendering, conflict blocking and separation, live suggestions, reuse, automatic language fields, duplicate prevention, save and failure reporting.');
}
run().catch(error => { console.error(error); process.exitCode = 1; });
