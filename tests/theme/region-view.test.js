'use strict';
// v1.4.0: the site's header and footer show around the page in the editor;
// clicking one goes to edit it, asking to save first if there are changes.
const H = require('../lib/harness');

(async () => {
  const t = H.suite('header and footer around the page');
  const cookie = H.readCookie();
  const open = async (r, from) => +(await fetch(`${H.BASE}/admin/visual-page-builder/region/${H.SITE}/${r}` + (from ? '?from=' + from : ''), { headers: { Cookie: cookie }, redirect: 'manual' })).headers.get('location').split('?')[0].split('/').pop();
  const headerId = await open('header'), footerId = await open('footer');
  try {
    for (const [id, r, tpl] of [[headerId, 'header', 'header-classic'], [footerId, 'footer', 'footer-simple']]) {
      const ed = await H.openEditor(id);
      ed.w.VPB.templatesPanel.apply(ed.w.VPB.templates.ofType(r).find(x => x.id === tpl), true);
      await H.saveBlocks(ed, ed.V.exportBlocks());
    }
    const pageId = await H.createPage('Around the page', [{ 'o:layout': 'html', 'o:data': { html: '<p>Body</p>' } }]);
    let ed = await H.openEditor(pageId);
    let { w, d, V } = ed;
    const went = [];
    w.VPB.regionsView.go = url => went.push(url);
    const hb = d.querySelector('#regionTop .rv-header'), fb = d.querySelector('#regionBottom .rv-footer');
    t.ok(hb && fb, 'header above the page, footer below');
    t.ok(hb.querySelector('.special-blocks-logo, .pb-nav') && /click to edit/.test(hb.textContent), 'the header shows its blocks, marked "click to edit"');
    t.ok(!hb.querySelector('.rv-content [tabindex]:not([tabindex="-1"])'), 'nothing inside the bands can be tabbed to');
    // No changes: goes straight there, remembering the page.
    hb.click();
    t.ok(went.length === 1 && went[0].endsWith(`/region/${H.SITE}/header?from=${pageId}`), 'no unsaved changes: goes to edit the header: ' + went[0]);
    // With changes: asks first.
    V.state.sections[0].cols[0].blocks[0].data = { html: '<p>Changed</p>' }; w.VPB.store.commit(); w.VPB.app.renderAll();
    d.querySelector('#regionBottom .rv-footer').click();
    const dlg = d.querySelector('.lv-overlay[role="dialog"]');
    t.ok(dlg && /Save your changes first/.test(dlg.textContent) && went.length === 1, 'unsaved changes: asks before leaving');
    t.eq([...dlg.querySelectorAll('button')].map(b => b.textContent), ['Save and continue', 'Continue without saving', 'Cancel'], 'three choices');
    [...dlg.querySelectorAll('button')].find(b => b.textContent === 'Cancel').click();
    t.ok(!d.querySelector('.lv-overlay') && went.length === 1, 'Cancel stays');
    d.querySelector('#regionBottom .rv-footer').click();
    [...d.querySelectorAll('.lv-overlay button')].find(b => b.textContent === 'Save and continue').click();
    await H.sleep(1500);
    const saved = await H.api('GET', 'site_pages/' + pageId);
    t.ok(went.length === 2 && /footer\?from=/.test(went[1]) && /Changed/.test(JSON.stringify(saved['o:block'])), 'Save and continue saves, then goes to the footer');
    // Top-bar Header button: same behavior, remembers the page.
    const topHeader = [...d.querySelectorAll('header.top a')].find(a => a.textContent === 'Header');
    t.ok(topHeader && topHeader.getAttribute('href').endsWith('?from=' + pageId), 'the top bar\u2019s Header button remembers the page');

    // Editing the header (opened from the page): page band and footer below.
    ed = await H.openEditor(headerId, { query: '?from=' + pageId });
    ({ w, d } = ed);
    w.VPB.regionsView.go = url => went.push(url);
    const pb = d.querySelector('#regionBottom .rv-page');
    t.ok(pb && /Around the page/.test(pb.textContent) && d.querySelector('#regionBottom .rv-footer') && !d.querySelector('#regionTop .rv-band'), 'in the header: the page band (named) and the footer below');
    pb.click();
    t.ok(went[went.length - 1] === `/admin/visual-page-builder/${H.SITE}/${pageId}`, 'clicking the page band goes back to the page');
    t.ok(!ed.errs.length, 'no editor errors ' + ed.errs);
  } finally {
    for (const id of [headerId, footerId]) await H.api('DELETE', 'site_pages/' + id);
  }
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
