'use strict';
// v0.9.0: the menu builder. Drives the real page in jsdom, saves through the
// server, and checks what Omeka stored. Restores the site's menu afterward.
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');
const H = require('../lib/harness');

const MB = path.join(H.MODULE_DIR, 'asset/menu-builder/menu-builder.js');

async function openBuilder(cookie) {
  const url = `${H.BASE}/admin/visual-page-builder/menu/${H.SITE}/edit`;
  const html = await (await fetch(url, { headers: { Cookie: cookie } })).text();
  const dom = new JSDOM(html.replace(/<script src="[^"]*menu-builder\.js[^"]*"><\/script>/, ''), { url, runScripts: 'outside-only', pretendToBeVisual: true });
  const w = dom.window, errs = [];
  w.addEventListener('error', e => errs.push(e.message));
  w.fetch = (u, o = {}) => fetch(new URL(u, H.BASE).href, Object.assign({}, o, { headers: Object.assign({}, o.headers, { Cookie: cookie }) }))
    .then(async r => { const t = await r.text(); return { ok: r.ok, status: r.status, json: async () => JSON.parse(t) }; });
  w.confirm = () => true;
  w.eval(fs.readFileSync(MB, 'utf8'));
  const cfg = JSON.parse(w.document.getElementById('vpb-menu-config').textContent);
  return { w, d: w.document, errs, cfg, M: w.VisualPageBuilderMenu };
}

(async () => {
  const t = H.suite('menu builder');
  const cookie = H.readCookie();
  const site = (await H.api('GET', 'sites?slug=' + H.SITE))[0];
  const original = site['o:navigation'];
  const pageA = await H.createPage('Menu page A', [{ 'o:layout': 'pageTitle', 'o:data': {} }]);
  const pageB = await H.createPage('Menu page B', [{ 'o:layout': 'pageTitle', 'o:data': {} }]);
  try {
    const list = await (await fetch(`${H.BASE}/admin/visual-builder/${H.SITE}`, { headers: { Cookie: cookie } })).text();
    t.ok(/Edit menu/.test(list), 'site page in the Visual Builder links to the menu builder');
    const admin = await (await fetch(`${H.BASE}/admin`, { headers: { Cookie: cookie } })).text();
    t.ok(/Visual Builder/.test(admin), '"Visual Builder" in Omeka\u2019s admin sidebar');

    const b = await openBuilder(cookie);
    const { w, d, M } = b;
    t.ok(!b.errs.length, 'builder loads ' + b.errs);
    t.ok(d.querySelectorAll('.mb-style input').length === 19, 'nineteen menu styles (five vertical)');
    ['split', 'two-tier', 'button', 'masthead'].forEach(st => {
      const r = d.getElementById('mb-style-' + st); r.click(); r.dispatchEvent(new w.Event('change'));
    });
    t.ok(/style-masthead/.test(d.getElementById('mbPreview').className), 'preview switches between the new styles');
    // v1.15.0: every style card carries a small picture of the menu in that style.
    const cards = [...d.querySelectorAll('#mbStyles .mb-style')];
    t.ok(cards.length === 19 && cards.every(c => c.querySelector('.mb-mini-frame[aria-hidden="true"] .mb-mini')) && cards.every(c => c.querySelector('.mb-mini').className.includes('style-' + c.querySelector('input').value)), 'each of the 19 styles has a picture of the menu in that style');
    t.ok(/Each picture shows/.test(d.getElementById('mbStylesHint').textContent), 'with a line saying what the pictures show');
    t.ok(/pb-|style-/.test(d.getElementById('mbPreview').className), 'preview shown');
    // Start from an empty menu.
    M.state.links.length = 0;
    const click = (root, text) => [...root.querySelectorAll('button')].find(x => x.textContent === text).click();
    const add = d.getElementById('mbAdd');
    const pagesSel = d.getElementById('mbAddPage');
    pagesSel.value = String(pageA); click(add, 'Add page');
    pagesSel.value = String(pageB); click(add, 'Add page');
    d.getElementById('mbAddLabel').value = 'Donate'; d.getElementById('mbAddUrl').value = 'https://example.org/donate'; click(add, 'Add link');
    d.getElementById('mbAddLabel').value = 'Bad'; d.getElementById('mbAddUrl').value = 'javascript:alert(1)'; click(add, 'Add link');
    click(add, 'Add \u201CCollections\u201D');
    t.eq(M.state.links.map(l => l.type), ['page', 'page', 'url', 'url', 'browseItemSets'], 'items added');
    // Indent page B under page A, then move Donate up.
    d.querySelector('[aria-label="Indent Menu page B (make it a sub-item)"]').click();
    t.ok(M.state.links[0].links.length === 1 && M.state.links[0].links[0].data.id === pageB, 'indent makes a sub-item');
    d.querySelector('[aria-label="Move Donate up"]').click();
    t.eq(M.state.links.map(l => l.data.label || l.data.id), ['Donate', pageA, 'Bad', 'Collections'], 'reorder');
    t.ok(d.querySelectorAll('.mb-list .mb-list .mb-item').length === 1, 'tree shows the sub-item indented');
    // Rename a page link, choose a style.
    d.querySelector('.mb-item:nth-child(2) > .mb-row .mb-name').click();
    const label = d.querySelector('.mb-edit input[type="text"]');
    label.value = 'History'; label.dispatchEvent(new w.Event('input')); label.dispatchEvent(new w.Event('change'));
    d.getElementById('mb-style-mega').click(); d.getElementById('mb-style-mega').dispatchEvent(new w.Event('change'));
    t.ok(/style-mega/.test(d.getElementById('mbPreview').className) && /History/.test(d.getElementById('mbPreview').textContent), 'preview follows the style and labels');
    t.ok(/Unsaved/.test(d.getElementById('status').textContent), 'unsaved changes shown');
    M.save(); await H.sleep(2000);
    t.ok(/All changes saved/.test(d.getElementById('status').textContent), 'saved: ' + d.getElementById('toast').textContent);

    const saved = await H.api('GET', 'sites/' + site['o:id']);
    const nav = saved['o:navigation'];
    t.eq(nav.map(l => l.type), ['url', 'page', 'browseItemSets'], 'unsafe link dropped; others stored');
    t.eq([nav[1].data.label, nav[1].data.id, nav[1].links[0].data.id], ['History', pageA, pageB], 'labels and sub-items stored');

    // Server-side guards.
    const post = (body, c = cookie) => fetch(H.BASE + b.cfg.saveUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', Cookie: c }, body: JSON.stringify(body) }).then(r => r.status);
    const b2 = await openBuilder(cookie);
    t.ok(await post({ csrf: 'bad', navigation: [], style: 'bar' }) === 403, 'bad token refused');
    t.ok(await post({ csrf: b2.cfg.csrf, navigation: 'nope', style: 'bar' }) === 400, 'malformed menu refused');
    const deep = { type: 'url', data: { label: 'x', url: '/x' }, links: [{ type: 'url', data: { label: 'x', url: '/x' }, links: [{ type: 'url', data: { label: 'x', url: '/x' }, links: [{ type: 'url', data: { label: 'x', url: '/x' }, links: [{ type: 'url', data: { label: 'x', url: '/x' }, links: [] }] }] }] }] };
    t.ok(await post({ csrf: b2.cfg.csrf, navigation: [deep], style: 'bar' }) === 400, 'more than four levels refused');
    t.ok(await post({ csrf: b2.cfg.csrf, navigation: [{ type: 'page', data: { label: '', id: 99999999 }, links: [] }], style: 'nonsense' }) === 200, 'a missing page is dropped, an unknown style falls back');
    const after = await H.api('GET', 'sites/' + site['o:id']);
    t.eq(after['o:navigation'], [], 'missing page not stored');
    const rc = await H.login('researcher@example.com', 'Test-password-123');
    t.ok((await fetch(`${H.BASE}/admin/visual-page-builder/menu/${H.SITE}/edit`, { headers: { Cookie: rc }, redirect: 'manual' })).status === 403, 'researcher refused');
  } finally {
    await H.api('PATCH', 'sites/' + site['o:id'], { 'o:navigation': original });
  }
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
