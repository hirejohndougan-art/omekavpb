'use strict';
// v0.10.0: headers and footers built in the visual editor, shown by the
// Companion Theme. Restores the site's theme afterward.
const { JSDOM } = require('jsdom');
const H = require('../lib/harness');

(async () => {
  const t = H.suite('header and footer');
  const cookie = H.readCookie();
  const site = (await H.api('GET', 'sites?slug=' + H.SITE))[0];
  const originalTheme = site['o:theme'];
  const list = await (await fetch(`${H.BASE}/admin/visual-builder/${H.SITE}`, { headers: { Cookie: cookie } })).text();
  t.ok(/Edit header/.test(list) && /Edit footer/.test(list), 'the Visual Builder page links to the header and footer');
  const admin = await (await fetch(`${H.BASE}/admin`, { headers: { Cookie: cookie } })).text();
  t.ok(/Visual Builder/.test(admin), '"Visual Builder" in Omeka\u2019s admin sidebar');

  const open = async (region) => {
    const r = await fetch(`${H.BASE}/admin/visual-page-builder/region/${H.SITE}/${region}`, { headers: { Cookie: cookie }, redirect: 'manual' });
    return +r.headers.get('location').split('/').pop();
  };
  const headerId = await open('header');
  t.ok(headerId > 0 && headerId === await open('header'), 'opening the header makes its page once and reuses it');
  const footerId = await open('footer');

  try {
    await H.api('PATCH', 'sites/' + site['o:id'], { 'o:theme': 'visual-page-builder-companion' });
    // ---- header
    let ed = await H.openEditor(headerId);
    const { w, d, V } = ed;
    t.ok(ed.cfg.region === 'header' && /site\u2019s header/.test(d.getElementById('notice').textContent), 'editor knows it is the header');
    t.ok(!ed.cfg.sitePages.some(p => /^vpb-site-/.test(p.slug || '') || p.id === headerId || p.id === footerId), 'header and footer left out of page lists');
    t.ok(!d.body.classList.contains('side-collapsed'), 'the sidebar starts open');
    t.ok(d.body.classList.contains('editing-region') && d.body.classList.contains('editing-header'), 'header editing fits the canvas to the header (no page-sized minimum height)');
    const labels = [...d.querySelectorAll('.pal-label')].map(x => x.textContent);
    t.ok(['Logo', 'Site title', 'Site menu', 'Copyright line', 'Social links', 'Search box', 'Breadcrumbs', 'Announcement bar'].every(l => labels.includes(l)), 'header palette has the header and footer elements');
    t.ok(!['Gallery', 'Item table', 'Media embed', 'Browse preview', 'Hero banner', 'Milestones'].some(l => labels.includes(l)), 'header palette leaves out page-only blocks: ' + labels.filter(l => ['Gallery', 'Item table', 'Media embed', 'Browse preview', 'Hero banner', 'Milestones'].includes(l)));
    t.ok([...d.querySelectorAll('.pal-group h3, .pal-group-title, .pal-head')].some(x => /Header & footer/.test(x.textContent)) || /Header & footer/.test(d.getElementById('side').textContent), 'grouped as "Header & footer"');
    [...d.querySelectorAll('[role="tab"]')].find(b => b.textContent === 'Templates').click();
    t.eq([...d.querySelectorAll('#tplType option')].map(o => o.value), ['header'], 'only header templates offered');
    t.ok(d.querySelectorAll('.tpl-card').length === 5, 'five header templates');
    // An empty starting section: "Add to the end" fills it instead of leaving a gap.
    V.state.sections = [w.VPB.model.newSection([12])];
    w.VPB.templatesPanel.apply(w.VPB.templates.ofType('header')[0], false);
    t.ok(V.state.sections.length === 1, 'adding a template to an empty header leaves no empty section');
    // Empty sections show a remove button, and aren't saved anyway.
    V.state.sections.push(w.VPB.model.newSection([6, 6]));
    w.VPB.app.renderAll();
    const rm = d.querySelector('.sec-empty-remove');
    t.ok(rm && d.querySelectorAll('.sec-empty-remove').length === 1, 'an empty section offers "Remove this empty section"');
    V.state.sections.push(w.VPB.model.newSection([12]));
    t.ok(V.exportBlocks().filter(b => b['o:layout'] === 'blockGroup').length === 1, 'empty sections aren\u2019t saved');
    rm.click();
    t.ok(V.state.sections.length === 2 && /Section deleted/.test(d.getElementById('toast').textContent), 'the button removes it (undoable)');
    // One Alignment control: the Site menu's own.
    const menuBlock = V.state.sections[0].cols.flatMap(c => c.blocks).find(b => b.layout === 'specialSiteMenu');
    H.ui(ed).select(menuBlock);
    t.ok(!/Text alignment/.test(d.getElementById('side').textContent) && /Alignment for this block is under Content/.test(d.getElementById('side').textContent), 'site menu shows only its own Alignment setting');
    w.VPB.templatesPanel.apply(w.VPB.templates.ofType('header')[0], true);
    let res = await H.saveBlocks(ed, V.exportBlocks());
    t.ok(res.body.ok, 'header saved');
    // ---- footer
    ed = await H.openEditor(footerId);
    t.ok(ed.cfg.region === 'footer', 'editor knows it is the footer');
    ed.w.VPB.templatesPanel.apply(ed.w.VPB.templates.ofType('footer').find(x => x.id === 'footer-three-columns'), true);
    res = await H.saveBlocks(ed, ed.V.exportBlocks());
    t.ok(res.body.ok, 'footer saved');

    // ---- public site
    const html = await (await fetch(`${H.BASE}/s/${H.SITE}`)).text();
    // An empty section saved by an older version doesn't leave a gap.
    const hp0 = await H.api('GET', 'site_pages/' + headerId);
    await H.api('PATCH', 'site_pages/' + headerId, { 'o:block': [{ 'o:layout': 'blockGroup', 'o:data': { span: 0 }, 'o:layout_data': { vpb_section: { cols: [{ w: 12 }] } } }].concat(hp0['o:block'].map(b => ({ 'o:layout': b['o:layout'], 'o:data': b['o:data'], 'o:layout_data': b['o:layout_data'] }))) });
    const gapHtml = await (await fetch(`${H.BASE}/s/${H.SITE}`)).text();
    const builtHead = new JSDOM(gapHtml).window.document.querySelector('.pb-header-built');
    t.ok(builtHead && builtHead.querySelectorAll('.vpb-section, [class*="vpb-section"]').length === hp0['o:block'].filter(b => b['o:layout'] === 'blockGroup').length, 'empty saved sections are skipped on the site');
    const doc = new JSDOM(html).window.document;
    const head = doc.querySelector('.pb-header-built'), foot = doc.querySelector('.pb-footer-built');
    t.ok(head && !doc.querySelector('.pb-header:not(.pb-header-built)'), 'built header replaces the theme\u2019s');
    t.ok(head && head.querySelector('.special-blocks-logo a') && head.querySelector('.pb-nav .pb-menu') && head.querySelector('form[role="search"]'), 'header has logo, menu, and search');
    t.ok(head && [...head.querySelectorAll('.pb-sub-toggle')].every(b => doc.getElementById(b.getAttribute('aria-controls'))), 'header menu\u2019s sub-menu buttons point at real lists');
    t.ok(foot && foot.querySelector('.pb-nav-list') && foot.querySelector('.special-blocks-copyright') && new RegExp('\u00A9 ' + new Date().getFullYear() + ' DHS').test(foot.textContent), 'footer has a site map and a filled-in copyright line');
    t.ok(!/Fatal error|Warning:|Notice:/.test(html), 'no PHP errors');
    // ---- the pages can't be opened on their own
    const hp = await H.api('GET', 'site_pages/' + headerId);
    t.ok((await fetch(`${H.BASE}/s/${H.SITE}/page/${hp['o:slug']}`)).status === 404, 'header page address answers "not found"');
    // ---- other themes keep their own header
    await H.api('PATCH', 'sites/' + site['o:id'], { 'o:theme': 'default' });
    const plain = await (await fetch(`${H.BASE}/s/${H.SITE}`)).text();
    t.ok(!/pb-header-built/.test(plain), 'other themes ignore the built header');
    // ---- the menu builder doesn't offer them as pages
    const mb = await (await fetch(`${H.BASE}/admin/visual-page-builder/menu/${H.SITE}/edit`, { headers: { Cookie: cookie } })).text();
    const cfg = JSON.parse(new JSDOM(mb).window.document.getElementById('vpb-menu-config').textContent);
    t.ok(!cfg.pages.some(p => p.id === headerId || p.id === footerId), 'menu builder leaves them out');
  } finally {
    await H.api('PATCH', 'sites/' + site['o:id'], { 'o:theme': originalTheme });
    // Remove them so other tests see the theme's own header and footer
    // (opening Header & footer makes them again).
    for (const id of [headerId, footerId]) await H.api('DELETE', 'site_pages/' + id);
  }
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
