'use strict';
// Pages made with the old, separate Society Blocks module (layouts named
// society*) are switched to this module's Special Blocks when the module is
// installed or upgraded (Module::renameOldLayouts). This test uninstalls and
// reinstalls the module through Omeka's admin, which runs install().
const { JSDOM } = require('jsdom');
const H = require('../lib/harness');

async function adminPost(path, fields, cookie) {
  return fetch(H.BASE + path, { method: 'POST', redirect: 'manual', headers: { Cookie: cookie, 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(fields) });
}
async function csrfFrom(path, name, cookie) {
  const d = new JSDOM(await (await fetch(H.BASE + path, { headers: { Cookie: cookie } })).text()).window.document;
  const el = d.querySelector(`[name="${name}"]`);
  return el ? el.value : null;
}

(async () => {
  const t = H.suite('upgrade from Society Blocks');
  const cookie = H.readCookie();
  const pageId = await H.createPage('old-society', [
    { 'o:layout': 'societyQuote', 'o:data': { text: 'An old quote.', attribution: 'Someone', style: 'boxed' } },
    { 'o:layout': 'societyAccordion', 'o:data': { panels: [{ title: 'Old question', body: '<p>Old answer</p>' }] } },
    { 'o:layout': 'html', 'o:data': { html: '<p>Untouched</p>' } }
  ]);

  // Uninstall, then install again (install() renames old layouts).
  const un = await csrfFrom('/admin/module/uninstall-confirm?id=VisualPageBuilder', 'confirmform_csrf', cookie);
  t.ok((await adminPost('/admin/module/uninstall?id=VisualPageBuilder', { confirmform_csrf: un, submit: 'Confirm' }, cookie)).status === 302, 'module uninstalled');
  const inst = await csrfFrom('/admin/module', 'csrf', cookie);
  t.ok((await adminPost('/admin/module/install?id=VisualPageBuilder', { csrf: inst }, cookie)).status === 302, 'module installed again');

  const page = await H.api('GET', 'site_pages/' + pageId);
  t.eq(page['o:block'].map(b => b['o:layout']), ['specialQuote', 'specialAccordion', 'html'], 'old layouts renamed, others untouched');
  t.eq(page['o:block'][0]['o:data'].text, 'An old quote.', 'settings kept');
  const html = await (await fetch(`${H.BASE}/s/${H.SITE}/page/${page['o:slug']}`)).text();
  t.ok(/special-blocks-quote-boxed/.test(html) && /An old quote\./.test(html) && /Old question/.test(html), 'renamed blocks render');
  const modules = await (await fetch(H.BASE + '/admin/module', { headers: { Cookie: cookie } })).text();
  t.ok(/Visual Page Builder/.test(modules), 'module is back');
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
