'use strict';
// The Companion Theme: renders builder pages with columns, draws the menu
// in each of the five styles, shows visit info in the footer, and builder
// pages drop a theme page template when saved. Restores the site afterward.
const { JSDOM } = require('jsdom');
const H = require('../lib/harness');

(async () => {
  const t = H.suite('Companion Theme');
  const cookie = H.readCookie();
  const site = (await H.api('GET', 'sites?slug=' + H.SITE))[0];
  const original = { theme: site['o:theme'], nav: site['o:navigation'] };
  const parent = await H.createPage('Theme parent', [{ 'o:layout': 'pageTitle', 'o:data': {} }]);
  const child = await H.createPage('Theme child', [{ 'o:layout': 'pageTitle', 'o:data': {} }]);
  const grand = await H.createPage('Theme grandchild', [{ 'o:layout': 'pageTitle', 'o:data': {} }]);
  const nav = (id, links = []) => ({ type: 'page', data: { id, label: '' }, links });
  try {
    await H.api('PATCH', 'sites/' + site['o:id'], { 'o:theme': 'visual-page-builder-companion', 'o:navigation': [nav(parent, [nav(child, [nav(grand)])]), { type: 'url', data: { label: 'Donate', url: 'https://example.org' }, links: [] }] });

    // A builder page with two columns.
    const pageId = await H.createPage('Theme columns', [{ 'o:layout': 'html', 'o:data': { html: '<p>Left</p>' } }, { 'o:layout': 'html', 'o:data': { html: '<p>Right</p>' } }],
      { 'o:layout_data': { template_name: 'homepage' } });
    const ed = await H.openEditor(pageId);
    t.ok(/page template/.test(ed.d.getElementById('notice').textContent), 'editor warns about the theme page template');
    const s = ed.V.state.sections[0];
    ed.d.querySelector('.sec').dispatchEvent(new ed.w.PointerEvent('pointerdown', { bubbles: true }));
    ed.d.querySelector('.presets button[aria-label="2 columns"]').click();
    s.cols[1].blocks.push(s.cols[0].blocks.pop());
    const res = await H.saveBlocks(ed, ed.V.exportBlocks());
    t.ok(res.body.ok && res.body.page['o:layout_data'].template_name === undefined, 'saving drops the page template');

    const styles = ['bar', 'dropdown', 'mega', 'centered', 'sidebar', 'centered-band', 'masthead', 'centered-dividers', 'centered-caps', 'split', 'right', 'pills', 'tabs', 'two-tier', 'button'];
    for (const style of styles) {
      // Save the style the way the menu builder does.
      const bhtml = await (await fetch(`${H.BASE}/admin/visual-page-builder/menu/${H.SITE}/edit`, { headers: { Cookie: cookie } })).text();
      const cfg = JSON.parse(new JSDOM(bhtml).window.document.getElementById('vpb-menu-config').textContent);
      await fetch(H.BASE + cfg.saveUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', Cookie: cookie }, body: JSON.stringify({ csrf: cfg.csrf, navigation: cfg.navigation, style }) });
      const html = await (await fetch(`${H.BASE}/s/${H.SITE}/page/${res.body.page['o:slug']}`)).text();
      const d = new JSDOM(html).window.document;
      t.ok(d.body.classList.contains('pb-menu-' + style) && d.querySelector('.pb-nav-' + style), style + ': menu drawn in this style');
      const depth = d.querySelector('.pb-level-3') ? 3 : d.querySelector('.pb-level-2') ? 2 : 1;
      t.ok(depth === ({ bar: 1, mega: 3, sidebar: 3 }[style] || 2), style + ': shows ' + depth + ' level(s)');
      t.ok(d.querySelectorAll('.pb-brand').length === 1 && d.getElementById('pb-menu') && d.querySelector('.pb-nav-toggle[aria-controls="pb-menu"]'), style + ': one logo, and the phone Menu button controls the menu');
      if (style === 'split') {
        const l = d.querySelector('.pb-split-left'), r = d.querySelector('.pb-split-right'), brand = d.querySelector('.pb-nav-split > .pb-brand');
        t.ok(l && r && brand && /Theme parent/.test(l.textContent) && /Donate/.test(r.textContent), 'split: logo between the two halves of the menu');
      }
      if (style === 'two-tier') t.ok(d.querySelector('.pb-topbar .pb-search') && d.querySelectorAll('.pb-search').length === 1, 'two rows: search moves to the top strip');
      const toggles = [...d.querySelectorAll('.pb-sub-toggle')];
      t.ok(toggles.every(b => d.getElementById(b.getAttribute('aria-controls'))), style + ': every sub-menu button controls a real list');
      if (style === 'dropdown') {
        t.ok(d.querySelector('.pb-skip[href="#content"]') && d.getElementById('content'), 'skip link to the content');
        const main = d.querySelector('.pb-main');
        t.ok(main.querySelectorAll('.vpb-column').length === 2 && /Left/.test(main.textContent) && /Right/.test(main.textContent), 'builder columns render in the theme');
        t.ok(/Donate/.test(d.querySelector('.pb-menu').textContent) && /Theme child/.test(d.querySelector('.pb-level-2').textContent), 'menu has its links and sub-pages');
        t.ok(!/fonts\.googleapis|<script[^>]+src="https?:\/\/(?!127)/.test(html), 'no outside fonts or scripts');
        t.ok(!/Fatal error|Warning:|Notice:/.test(html), 'no PHP errors');
      }
    }
    // Footer visit info from the module's site settings (saved by another test).
    const legacy = (await H.api('GET', 'sites?slug=legacy'))[0];
    await H.api('PATCH', 'sites/' + legacy['o:id'], { 'o:theme': 'visual-page-builder-companion' });
    const lhtml = await (await fetch(`${H.BASE}/s/legacy`)).text();
    t.ok(/class="pb-footer-visit"/.test(lhtml) && /100 Legacy Lane/.test(lhtml) && /Monday to Friday/.test(lhtml), 'footer shows the site\u2019s visit info');
    await H.api('PATCH', 'sites/' + legacy['o:id'], { 'o:theme': 'default' });
  } finally {
    await H.api('PATCH', 'sites/' + site['o:id'], { 'o:theme': original.theme, 'o:navigation': original.nav });
  }
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
