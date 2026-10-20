'use strict';
// v1.16.0: menu colors (schemes + custom, saved as vpb_menu_colors), vertical
// styles, the Desktop/Tablet/Phone preview (drawing, and the site itself via
// a session draft), and the Site menu block following the Menu page.
// Restores the site's theme and menu settings afterward.
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const { JSDOM } = require('jsdom');
const H = require('../lib/harness');

const MB = path.join(H.MODULE_DIR, 'asset/menu-builder/menu-builder.js');
const DB = process.env.VPB_DB || 'omeka';
const sql = q => execSync(`mysql -uroot ${DB} -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

async function openBuilder(cookie) {
  const url = `${H.BASE}/admin/visual-page-builder/menu/${H.SITE}/edit`;
  const html = await (await fetch(url, { headers: { Cookie: cookie } })).text();
  const dom = new JSDOM(html.replace(/<script src="[^"]*menu-builder\.js[^"]*"><\/script>/, ''), { url, runScripts: 'outside-only', pretendToBeVisual: true });
  const w = dom.window, errs = [];
  w.addEventListener('error', e => errs.push(e.message));
  w.fetch = (u, o = {}) => fetch(new URL(u, H.BASE).href, Object.assign({}, o, { headers: Object.assign({}, o.headers, { Cookie: cookie }) }))
    .then(async r => { const t = await r.text(); return { ok: r.ok, status: r.status, json: async () => JSON.parse(t) }; });
  w.eval(fs.readFileSync(MB, 'utf8'));
  return { w, d: w.document, errs, M: w.VisualPageBuilderMenu, cfg: JSON.parse(w.document.getElementById('vpb-menu-config').textContent) };
}
const wait = ms => new Promise(r => setTimeout(r, ms));

(async () => {
  const t = H.suite('menu colors, vertical styles, device preview');
  const cookie = H.readCookie();
  const site = (await H.api('GET', 'sites?slug=' + H.SITE))[0];
  const theme = sql(`SELECT theme FROM site WHERE id=${site['o:id']}`);
  const pageId = await H.createPage('Menu look', [
    { 'o:layout': 'specialSiteMenu', 'o:data': { style: 'site', align: 'left', heading: '' } },
    { 'o:layout': 'specialSiteMenu', 'o:data': { style: 'list', align: 'left', heading: 'Explore' } }
  ]);
  try {
    // ---- The Menu page (this site's theme isn't the Companion Theme: drawings).
    let b = await openBuilder(cookie);
    let { d, M, w } = b;
    t.ok(!b.errs.length, 'loads ' + b.errs);
    t.ok(typeof b.cfg.headerWithoutMenu === 'boolean' && /region\/.+\/header/.test(b.cfg.headerUrl), 'knows whether a built header lacks a Site menu block (and links to it)');
    const groups = [...d.querySelectorAll('.mb-style-group')].map(x => x.textContent);
    t.eq(groups, ['Across the top', 'Down the side (vertical)'], 'styles in two groups');
    const vertical = [...d.querySelectorAll('.mb-style input')].map(x => x.value).filter(v => b.cfg.styles.find(s => s.value === v).vertical);
    t.eq(vertical, ['sidebar', 'sidebar-right', 'vertical-list', 'vertical-flyout', 'vertical-boxed'], 'five vertical styles, each with a picture');
    t.ok(d.querySelector('.mb-style input[value="vertical-flyout"]').closest('.mb-style').querySelector('.mb-mini.pv-side'), 'vertical pictures put the menu down the side');
    // Devices.
    const dev = name => d.querySelector(`#mbDevices [data-dev="${name}"]`);
    t.ok(dev('desktop').getAttribute('aria-pressed') === 'true', 'Desktop / Tablet / Phone at the top, Desktop first');
    dev('phone').click();
    t.ok(dev('phone').getAttribute('aria-pressed') === 'true' && d.getElementById('mbPreview').classList.contains('pv-small') && /390px/.test(d.getElementById('mbPreview').getAttribute('style')) && /Phone/.test(d.getElementById('mbPreviewSub').textContent), 'Phone: the drawing shows the phone menu (Menu button, list under it) at phone width');
    dev('desktop').click();
    t.ok(!d.getElementById('mbPreview').classList.contains('pv-small'), 'back to desktop');
    // Colors.
    t.eq([...d.querySelectorAll('.mb-scheme input')].map(x => x.value), ['theme', 'light', 'cream', 'dark', 'navy', 'bayou', 'brick', 'sky'], 'eight color schemes, "Theme colors" first');
    t.ok(d.getElementById('mb-scheme-theme').checked, 'no colors yet: Theme colors');
    d.getElementById('mb-scheme-navy').click(); d.getElementById('mb-scheme-navy').dispatchEvent(new w.Event('change'));
    await wait(30);
    t.eq(M.state.colors, { bar: '#1b2a49', link: '#ffffff', hover: '#f2c14e', dropBg: '#ffffff', dropLink: '#1b2a49' }, 'a scheme fills in the five colors');
    t.ok(/--pb-nav-bg:#1b2a49/.test(d.getElementById('mbPreview').getAttribute('style')) && /--pb-nav-bg:#1b2a49/.test(d.querySelector('.mb-mini').getAttribute('style')), 'the preview and the pictures take them');
    const link = d.getElementById('mb-color-link');
    link.value = '#1b2a49'; link.dispatchEvent(new w.Event('input')); link.dispatchEvent(new w.Event('change'));
    t.ok(!d.querySelector('.mb-scheme input:checked') || d.getElementById('mb-scheme-theme').checked === false, 'changing one makes them custom');
    t.ok(/Hard to read: links on the menu background/.test(d.getElementById('mbColors').textContent), 'warns when links won’t read on the background');
    link.value = '#ffffff'; link.dispatchEvent(new w.Event('change'));
    t.ok(d.getElementById('mb-scheme-navy').checked && !/Hard to read/.test(d.getElementById('mbColors').textContent), 'back to the scheme, no warning');
    const reset = [...d.querySelectorAll('.mb-color-row')].find(r => /Drop-downs and panels/.test(r.textContent)).querySelector('button');
    reset.click();
    t.ok(!('dropBg' in M.state.colors), '"Use theme’s" clears one color');
    d.querySelector('.mb-style input[value="vertical-list"]').click(); d.querySelector('.mb-style input[value="vertical-list"]').dispatchEvent(new w.Event('change'));
    M.save(); await wait(1500);
    t.eq(JSON.parse(sql(`SELECT value FROM site_setting WHERE id='vpb_menu_colors' AND site_id=${site['o:id']}`)), { bar: '#1b2a49', link: '#ffffff', hover: '#f2c14e', dropLink: '#1b2a49' }, 'saved as the site setting vpb_menu_colors');
    t.eq(JSON.parse(sql(`SELECT value FROM site_setting WHERE id='vpb_menu_style' AND site_id=${site['o:id']}`)), 'vertical-list', 'and the style');
    b = await openBuilder(cookie);
    t.ok(b.d.getElementById('mb-color-bar').value === '#1b2a49' && b.d.querySelector('.mb-style input[value="vertical-list"]').checked, 'reopens with them');

    // ---- Unsafe colors are dropped.
    const draftUrl = b.cfg.draftUrl;
    const post = (u, body, ck = cookie) => fetch(H.BASE + u, { method: 'POST', headers: { Cookie: ck, 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(async r => ({ status: r.status, body: await r.json().catch(() => null) }));
    const saveUrl = b.cfg.saveUrl;
    const bad = await post(saveUrl, { csrf: b.cfg.csrf, navigation: site['o:navigation'], style: 'vertical-list', colors: { bar: 'red;background:url(x)', link: '#ffffff' } });
    t.ok(bad.status === 200 && JSON.stringify(bad.body.colors) === '{"link":"#ffffff"}', 'only real colors are kept');
    await post(saveUrl, { csrf: b.cfg.csrf, navigation: site['o:navigation'], style: 'vertical-list', colors: { bar: '#1b2a49', link: '#ffffff' } });

    // ---- The site with the Companion Theme: real menu, colors, side layout.
    sql(`UPDATE site SET theme='visual-page-builder-companion' WHERE id=${site['o:id']}`);
    const home = new JSDOM(await (await fetch(`${H.BASE}/s/${H.SITE}`)).text()).window.document;
    const nav = home.querySelector('.pb-header nav.pb-nav');
    t.ok(nav.classList.contains('pb-nav-vertical-list') && nav.classList.contains('pb-nav-has-bg') && /--pb-nav-bg:#1b2a49/.test(nav.getAttribute('style')), 'the theme draws the saved style with the colors: ' + nav.className);
    t.ok(home.body.className.includes('pb-menu-side'), 'vertical styles put the theme’s header down the side');
    // Draft preview: only for the editor, only with ?vpb_menu_draft=1.
    b = await openBuilder(cookie);
    t.ok(b.d.querySelector('iframe.mb-frame'), 'with the Companion Theme, the preview is the site itself');
    const dr = await post(draftUrl, { csrf: b.cfg.csrf, navigation: [{ type: 'url', data: { label: 'Draft link', url: 'https://example.org/' }, links: [] }], style: 'pills', colors: { bar: '#8a2f1d' } });
    t.ok(dr.status === 200 && /vpb_menu_draft=1/.test(dr.body.url), 'the unsaved menu is kept as a draft: ' + (dr.body && dr.body.url));
    const asEditor = new JSDOM(await (await fetch(H.BASE + dr.body.url, { headers: { Cookie: cookie } })).text()).window.document;
    const dn = asEditor.querySelector('.pb-header nav.pb-nav');
    t.ok(dn.classList.contains('pb-nav-pills') && /--pb-nav-bg:#8a2f1d/.test(dn.getAttribute('style')) && /Draft link/.test(dn.textContent), 'the editor sees the draft (style, colors, links)');
    t.ok(!asEditor.getElementById('user-bar'), 'without Omeka’s editor bar');
    const publicView = new JSDOM(await (await fetch(H.BASE + dr.body.url)).text()).window.document;
    t.ok(publicView.querySelector('.pb-header nav.pb-nav').classList.contains('pb-nav-vertical-list') && !/Draft link/.test(publicView.body.textContent), 'visitors never see a draft');
    t.ok(!/Draft link/.test(await (await fetch(`${H.BASE}/s/${H.SITE}`, { headers: { Cookie: cookie } })).text()), 'nor the editor without ?vpb_menu_draft');
    const refused = await post(draftUrl, { csrf: 'nope', navigation: [], style: 'bar', colors: {} });
    t.eq(refused.status, 403, 'a draft needs the page’s token');

    // ---- Site menu block: follows the Menu page.
    const ids = sql(`SELECT id FROM site_page_block WHERE page_id=${pageId} ORDER BY position`).split('\n');
    sql(`UPDATE site_page_block SET data='{"style":"mega","align":"left","heading":""}' WHERE id=${ids[0]}`);
    const p = (await H.api('GET', 'site_pages/' + pageId))['o:slug'];
    const pd = new JSDOM(await (await fetch(`${H.BASE}/s/${H.SITE}/page/${p}`)).text()).window.document;
    const blocks = [...pd.querySelectorAll('.special-blocks-site-menu nav')];
    t.ok(blocks[0].classList.contains('pb-nav-vertical-list') && /--pb-nav-bg:#1b2a49/.test(blocks[0].getAttribute('style')), 'a block saved with its own style (before 1.16) now follows the Menu page');
    t.ok(blocks[1].classList.contains('pb-nav-list') && !blocks[1].getAttribute('style'), 'a footer’s plain list stays a plain list');
    const ed = await H.openEditor(pageId);
    const cfgFields = ed.w.VPB.cfg.layouts.find(l => l.name === 'specialSiteMenu').schema.fields.find(f => f.key === 'style');
    t.eq((Array.isArray(cfgFields.options) ? cfgFields.options.map(o => o.value !== undefined ? o.value : o[0]) : Object.keys(cfgFields.options)), ['site', 'list'], 'its only choices: the Menu page’s style, or a plain list');
    const u = H.ui(ed);
    u.select(u.blk('specialSiteMenu'));
    const a = u.side().querySelector('.site-menu-link a');
    t.ok(a && /\/menu\/.+\/edit$/.test(a.getAttribute('href')), 'its settings link to the Menu page');
    t.ok(!b.errs.length && !ed.errs.length, 'no errors');
  } finally {
    sql(`UPDATE site SET theme='${theme}' WHERE id=${site['o:id']}`);
    sql(`DELETE FROM site_setting WHERE site_id=${site['o:id']} AND id IN ('vpb_menu_colors','vpb_menu_style')`);
    await H.api('PATCH', 'sites/' + site['o:id'], { 'o:navigation': site['o:navigation'] }).catch(() => {});
    await H.api('DELETE', 'site_pages/' + pageId).catch(() => {});
  }
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
