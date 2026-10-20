'use strict';
// v1.0.6: "Visual Builder" under Modules: one page per site with its pages,
// header, footer, menu, and theme.
const { JSDOM } = require('jsdom');
const H = require('../lib/harness');

(async () => {
  const t = H.suite('page builder site and page list');
  const cookie = H.readCookie();
  const get = (path, c = cookie) => fetch(H.BASE + path, { headers: { Cookie: c }, redirect: 'manual' });
  const admin = await (await get('/admin')).text();
  const nav = new JSDOM(admin).window.document;
  const labels = [...nav.querySelectorAll('a')].map(a => a.textContent.trim());
  t.ok(labels.includes('Visual Builder') && !labels.includes('Page builder') && !labels.includes('Header & footer') && !labels.includes('Menu builder'), 'one "Visual Builder" entry under Modules');

  const list = new JSDOM(await (await get('/admin/visual-builder')).text()).window.document;
  t.ok([...list.querySelectorAll('td a')].some(a => a.textContent === 'DHS') && list.querySelector('a[href$="/visual-builder/dhs"]'), 'lists the sites with a link to each');
  t.ok((await get('/admin/visual-builder?site=legacy')).headers.get('location').endsWith('/visual-builder/legacy'), 'the site switcher goes to the chosen site');
  for (const old of ['/admin/visual-page-builder/menu', '/admin/visual-page-builder/region']) {
    t.ok((await get(old)).headers.get('location').endsWith('/admin/visual-builder'), old + ' now leads to the Visual Builder');
  }

  const word = 'Zebra' + Date.now().toString(36); // unique, so earlier runs don't match
  const pageId = await H.createPage(word + ' list test page', [{ 'o:layout': 'html', 'o:data': { html: '<p>x</p>' } }]);
  const res = await get('/admin/visual-builder/dhs?q=' + word);
  const d = new JSDOM(await res.text()).window.document;
  t.ok(res.status === 200, 'site page opens');
  const editorLink = [...d.querySelectorAll('a.button')].find(a => a.textContent === 'Open visual editor');
  t.ok(editorLink && editorLink.getAttribute('href') === `/admin/visual-page-builder/dhs/${pageId}`, 'page has "Open visual editor"');
  t.ok(d.querySelectorAll('tbody tr').length === 1 && d.querySelector('tbody').textContent.includes(word + ' list test page'), '"Find a page" narrows the list');
  t.ok(['Edit header', 'Edit footer', 'Edit menu', 'Theme settings', 'Add new page'].every(l => [...d.querySelectorAll('a.button')].some(a => a.textContent === l)), 'header, footer, menu, theme, and pages all on one page');
  t.ok(d.querySelector('#vpb-site option[selected]') && /links ·/.test(d.body.textContent), 'site switcher and menu summary');
  const all = new JSDOM(await (await get('/admin/visual-builder/dhs')).text()).window.document;
  t.ok(![...all.querySelectorAll('tbody small')].some(s => /vpb-site-(header|footer)/.test(s.textContent)), 'the hidden header and footer pages aren\u2019t listed');
  const none = new JSDOM(await (await get('/admin/visual-builder/dhs?q=nothing-matches-this')).text()).window.document;
  t.ok(/No pages match/.test(none.body.textContent), 'says when nothing matches');
  const ed = await get(editorLink.getAttribute('href'));
  t.ok(ed.status === 200, 'the link opens the editor');
  const edDoc = new JSDOM(await ed.text()).window.document;
  const back = edDoc.getElementById('backToHub');
  t.ok(back && back.getAttribute('href') === '/admin/visual-builder/dhs' && /Visual Builder/.test(back.textContent), 'the editor has a button back to this site\u2019s Visual Builder page');
  const mb = new JSDOM(await (await get('/admin/visual-page-builder/menu/dhs/edit')).text()).window.document;
  t.ok(mb.querySelector('a.hub-back[href="/admin/visual-builder/dhs"]'), 'so does the menu builder');
  const rc = await H.login('researcher@example.com', 'Test-password-123');
  t.ok((await get('/admin/visual-builder/dhs', rc)).status === 403, 'a researcher is refused');
  t.ok((await get('/admin/visual-builder/no-such-site')).status === 404, 'unknown site: not found');
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
