'use strict';
// v1.12.0: page history, duplicate, download/import layout, shared sections,
// footnotes, check page, print styles.
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');
const H = require('../lib/harness');

(async () => {
  const t = H.suite('v1.12.0: history, duplicate, layouts, shared sections, footnotes, check, print');
  const cookie = H.readCookie();
  const get = (p, c = cookie) => fetch(H.BASE + p, { headers: { Cookie: c, Accept: 'application/json' }, redirect: 'manual' });
  const pageId = await H.createPage('Twelve', [{ 'o:layout': 'html', 'o:data': { html: '<p>First version</p>' } }]);

  // ---- 1. Page history
  let ed = await H.openEditor(pageId);
  ed.V.state.sections[0].cols[0].blocks[0].data = { html: '<p>Second version</p>' };
  await H.saveBlocks(ed, ed.V.exportBlocks());
  ed.V.state.sections[0].cols[0].blocks[0].data = { html: '<p>Third version</p>' };
  await H.saveBlocks(ed, ed.V.exportBlocks());
  const hist = await (await get(ed.cfg.historyUrl)).json();
  t.ok(hist.ok && hist.versions.length >= 2 && hist.versions[0].id > hist.versions[1].id && /Twelve/.test(hist.versions[0].title), 'each save keeps the earlier version, newest first');
  const older = await (await get(ed.cfg.historyUrl + '?version=' + hist.versions[1].id)).json();
  t.ok(older.ok && /First version/.test(JSON.stringify(older.page['o:block'])), 'an earlier version can be fetched');
  ed = await H.openEditor(pageId);
  ed.w.VPB.pageTools.load(older.page, 'replace');
  t.ok(/First version/.test(ed.V.state.sections[0].cols[0].blocks[0].data.html) && /Unsaved/.test(H.ui(ed).status()), 'loading it puts it in the editor as unsaved changes');
  ed.d.dispatchEvent(new ed.w.KeyboardEvent('keydown', { key: 'z', ctrlKey: true, bubbles: true }));
  t.ok(/Third version/.test(ed.V.state.sections[0].cols[0].blocks[0].data.html), 'Undo takes it back');
  const other = await H.login('researcher@example.com', 'Test-password-123');
  t.ok((await get(ed.cfg.historyUrl, other)).status === 403, 'history is only for people who can edit the page');
  t.ok(ed.d.getElementById('pageMenuBtn') && /History/.test(ed.d.querySelector('.pt-menu').textContent) && /Check page/.test(ed.d.querySelector('.pt-menu').textContent), 'the Page menu (History, Download, Import, Check page)');

  // ---- 3. Import a layout file
  const file = JSON.stringify({ format: 'visual-page-builder-layout', version: 1, title: 'From elsewhere', page: { 'o:layout': 'grid', 'o:layout_data': {}, 'o:block': [{ 'o:layout': 'html', 'o:data': { html: '<p>Imported</p>' }, 'o:layout_data': {} }] } });
  t.ok(ed.w.VPB.pageTools.offer('{"nope":1}', 'x.json') === false && /isn.t a Visual Page Builder layout/.test(ed.d.getElementById('toast').textContent), 'a wrong file is refused');
  ed.w.VPB.pageTools.offer(file, 'layout.json');
  [...ed.d.querySelectorAll('.lv-overlay button')].find(b => b.textContent === 'Add to the end').click();
  const texts = ed.V.state.sections.flatMap(s => s.cols.flatMap(c => c.blocks)).map(b => b.data.html);
  t.ok(texts.length === 2 && /Imported/.test(texts[1]), 'importing can add a layout to the end (or replace the page)');

  // ---- 2. Duplicate a page (Visual Builder page form)
  const hub = await (await get('/admin/visual-builder/dhs?q=Twelve')).text();
  const csrf = (hub.match(/name="vpb_hub_csrf" value="([^"]+)"/) || [])[1];
  t.ok(csrf && /Duplicate/.test(hub) && /Shared sections/.test(hub), 'the Visual Builder page has Duplicate and Shared sections');
  const dup = await fetch(H.BASE + '/admin/visual-builder/dhs', { method: 'POST', headers: { Cookie: cookie, 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ vpb_hub_csrf: csrf, vpb_action: 'duplicate', page_id: pageId }), redirect: 'manual' });
  const dupId = +(dup.headers.get('location') || '').split('/').pop();
  const copy = dupId && await H.api('GET', 'site_pages/' + dupId);
  t.ok(copy && copy['o:title'] === 'Copy of Twelve' && copy['o:is_public'] === false && /Third version/.test(JSON.stringify(copy['o:block'])), 'Duplicate makes a private copy with the same content and opens it');

  // ---- 4. Shared sections
  const newShared = await fetch(H.BASE + '/admin/visual-builder/dhs', { method: 'POST', headers: { Cookie: cookie, 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ vpb_hub_csrf: csrf, vpb_action: 'new_shared', title: 'Empty shared' }), redirect: 'manual' });
  const emptySharedId = +(newShared.headers.get('location') || '').split('/').pop();
  const es = await H.api('GET', 'site_pages/' + emptySharedId);
  t.ok(/^vpb-shared-/.test(es['o:slug']) && es['o:is_public'] === true, '"New shared section" makes a hidden shared page and opens it');
  const sp = await H.openEditor(emptySharedId);
  t.ok(sp.d.querySelector('.shared-banner') && !sp.d.getElementById('sitePreviewBtn'), 'editing a shared section says so');
  // Make a section of the page shared.
  ed = await H.openEditor(pageId);
  ed.w.prompt = () => 'Visit us strip';
  ed.w.VPB.sharedSections.makeShared(ed.V.state.sections[0]);
  await H.sleep(1500);
  const sharedBlock = ed.V.state.sections[0].cols[0].blocks[0];
  t.ok(sharedBlock.layout === 'specialSharedSection' && sharedBlock.data.section > 0, '"Make this a shared section" swaps the section for a Shared section block');
  const sharedId = sharedBlock.data.section;
  H.ui(ed).select(sharedBlock);
  t.ok([...H.ui(ed).side().querySelectorAll('#f-shared-pick option')].some(o => o.textContent === 'Visit us strip' && o.selected) && !H.ui(ed).side().querySelector('[id$="-section"]'), 'the block offers the shared sections by name');
  const saved = await H.saveBlocks(ed, ed.V.exportBlocks());
  const slug = saved.body.page['o:slug'];
  let html = await (await fetch(`${H.BASE}/s/${H.SITE}/page/${slug}`)).text();
  t.ok(/Third version/.test(html) && /special-blocks-shared-section/.test(html), 'the page shows the shared section');
  const sharedSlug = (await H.api('GET', 'site_pages/' + sharedId))['o:slug'];
  t.ok((await fetch(`${H.BASE}/s/${H.SITE}/page/${sharedSlug}`)).status === 404, 'a shared section has no page of its own on the site');
  // Edit it once → the page shows the change.
  const se = await H.openEditor(sharedId);
  se.V.state.sections[0].cols[0].blocks[0].data = { html: '<p>Changed once, shown everywhere</p>' };
  await H.saveBlocks(se, se.V.exportBlocks());
  html = await (await fetch(`${H.BASE}/s/${H.SITE}/page/${slug}`)).text();
  t.ok(/Changed once, shown everywhere/.test(html), 'changing the shared section changes every page that shows it');
  // A shared section can't show itself (no endless loop).
  await H.api('PATCH', 'site_pages/' + sharedId, { 'o:block': [{ 'o:layout': 'specialSharedSection', 'o:data': { section: sharedId } }, { 'o:layout': 'html', 'o:data': { html: '<p>Inside</p>' } }] });
  const loopRes = await fetch(`${H.BASE}/s/${H.SITE}/page/${slug}`);
  t.ok(loopRes.status === 200 && /Inside/.test(await loopRes.text()), 'a shared section that includes itself is drawn once, not forever');

  // ---- 5. Footnotes
  const fnPage = await H.createPage('Notes test', [
    { 'o:layout': 'html', 'o:data': { html: '<p>Founded in 1890<sup class="vpb-footnote">County records, vol. 2.</sup> by settlers.</p>' } },
    { 'o:layout': 'html', 'o:data': { html: '<p>The depot<sup class="vpb-footnote">See <a href="https://example.org">the railroad history</a>.</sup> opened.</p>' } }
  ]);
  const fnSlug = (await H.api('GET', 'site_pages/' + fnPage))['o:slug'];
  const prevP = H.setting('use_htmlpurifier'); H.setSetting('use_htmlpurifier', '1');
  let fd;
  try {
    // Re-save through the editor so the purifier runs on the notes.
    const fe = await H.openEditor(fnPage);
    await H.saveBlocks(fe, fe.V.exportBlocks());
    fd = new JSDOM(await (await fetch(`${H.BASE}/s/${H.SITE}/page/${fnSlug}`)).text()).window.document;
  } finally { H.setSetting('use_htmlpurifier', prevP); }
  const refs = [...fd.querySelectorAll('sup.vpb-fn-ref a')].map(a => a.textContent + a.getAttribute('href'));
  t.eq(refs, ['1#vpb-fn-1', '2#vpb-fn-2'], 'footnotes are numbered in page order, across text blocks');
  const items = [...fd.querySelectorAll('.vpb-footnotes li')];
  t.ok(items.length === 2 && /County records/.test(items[0].textContent) && items[1].querySelector('a[href="https://example.org"]') && items[0].querySelector('a.vpb-fn-back[href="#vpb-fnref-1"]'), 'listed under Notes at the end (links kept), each with a link back');
  t.ok(!fd.querySelector('sup.vpb-footnote'), 'the note text isn\u2019t left in the sentence');
  await H.api('PATCH', 'site_pages/' + fnPage, { 'o:block': [{ 'o:layout': 'specialFootnotes', 'o:data': { heading: 'Sources' } }, { 'o:layout': 'html', 'o:data': { html: '<p>A<sup class="vpb-footnote">One.</sup></p>' } }] });
  const fd2 = new JSDOM(await (await fetch(`${H.BASE}/s/${H.SITE}/page/${fnSlug}`)).text()).window.document;
  t.ok(fd2.querySelectorAll('.vpb-footnotes').length === 1 && fd2.querySelector('.special-blocks-footnotes h2').textContent === 'Sources', 'with a Footnotes block, the notes go there instead');
  const fe2 = await H.openEditor(fnPage);
  H.ui(fe2).select(fe2.V.state.sections.flatMap(s => s.cols.flatMap(c => c.blocks)).find(b => b.layout === 'html'));
  t.ok(H.ui(fe2).side().querySelector('.rt-panel [aria-label="Add a footnote"]'), 'text formatting (sidebar) has a footnote button');

  // ---- 6. Check page
  const ce = await H.openEditor(pageId);
  const M = ce.w.VPB.model;
  const bad = M.newBlock('html', '<h4>Skipped</h4><p><img src="x.png"> <a href="">empty</a> <a href="#nowhere">gone</a> <a href="/x">click here</a></p>');
  const jl = M.newBlock('specialJumpLinks');
  ce.V.state.sections = [M.sectionWith(bad), M.newSection([12]), M.sectionWith(jl)];
  const issues = ce.w.VPB.pageCheck.run().map(i => i.level + ': ' + i.text);
  const has = re => issues.some(x => re.test(x));
  t.ok(has(/fix: A picture in a text block has no description/) && has(/fix: .*skips a level \(Heading 1 to Heading 4\)/) && has(/fix: A link \(.empty.\) doesn.t go anywhere/) && has(/fix: A link goes to #nowhere/) && has(/tip: A link just says .click here./) && has(/tip: Section 2 is empty/) && has(/fix: The Table of contents has nothing to list yet/), 'Check page finds the common problems: ' + issues.length);
  ce.w.VPB.pageCheck.open();
  t.ok(ce.d.querySelector('.pc-list') && [...ce.d.querySelectorAll('.pc-list button')].some(b => b.textContent === 'Show me'), 'listed with "Show me" buttons');
  ce.V.state.sections = [M.sectionWith(M.newBlock('html', '<h2>Fine</h2><p><a href="https://example.org">The society\u2019s history</a></p>'))];
  t.ok(!ce.w.VPB.pageCheck.run().length, 'a tidy page: no problems');

  // ---- 7. Print styles
  const css = fs.readFileSync(path.join(H.MODULE_DIR, 'asset/css/public.css'), 'utf8');
  const theme = fs.readFileSync(path.join(H.MODULE_DIR, '../../theme/visual-page-builder-companion/asset/css/style.css'), 'utf8');
  t.ok(/@media print[\s\S]*attr\(href\)/.test(css) && /@media print[\s\S]*\.pb-nav/.test(theme), 'print styles: link addresses printed; menus and search dropped');

  for (const id of [dupId, emptySharedId, sharedId, fnPage]) await H.api('DELETE', 'site_pages/' + id).catch(() => {});
  t.ok(!ed.errs.length && !ce.errs.length, 'no editor errors');
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
