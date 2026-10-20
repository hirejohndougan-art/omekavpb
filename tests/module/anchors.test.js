'use strict';
// v1.14.0: the Table of contents builds itself from the page's headings
// (Heading 2 → section, 3 → subsection, 4 → sub-subsection), each heading can
// be left out or renamed, Table link blocks add spots with no heading, and
// pages made before (Link names, "only named spots") keep working.
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');
const H = require('../lib/harness');

(async () => {
  const t = H.suite('table of contents and table links');
  const pageId = await H.createPage('Toc', [{ 'o:layout': 'html', 'o:data': { html: '<p>Intro</p>' } }]);
  let ed = await H.openEditor(pageId);
  let { w, V } = ed;
  const u = H.ui(ed), M = w.VPB.model;
  const toc1 = M.newBlock('specialJumpLinks');
  const toc2 = M.newBlock('specialJumpLinks'); toc2.data = Object.assign({}, toc2.data, { heading: 'Everything', levels: 'h4', numbered: true, style: 'box' });
  const toc3 = M.newBlock('specialJumpLinks'); toc3.data = Object.assign({}, toc3.data, { heading: 'Sections', levels: 'h2', style: 'row' });
  t.eq(w.VPB.cfg.layouts.find(l => l.name === 'specialJumpLinks').schema.fields.find(f => f.key === 'levels').default, 'h3', 'a new Table of contents goes down to Heading 3');
  const text = M.newBlock('html', '<h2>The railroad arrives</h2><p>a</p><h3>The first depot</h3><p>b</p><h2>The Great Storm of September 8, 1900</h2><h4>A note on the sources</h4><p>c</p>');
  const link = M.newBlock('specialTableLink'); link.data = Object.assign({}, link.data, { name: 'Where it happened (map)', level: '2' });
  const after = M.newBlock('html', '<h2>Visit us</h2><p>Open Saturdays.</p>');
  // Two columns: reading order is column by column (Left A, Left B, Right),
  // though the page stores them row by row.
  const two = M.newSection([6, 6]);
  two.cols[0].blocks.push(M.newBlock('html', '<h2>Left A</h2>'), M.newBlock('html', '<h2>Left B</h2>'));
  two.cols[1].blocks.push(M.newBlock('html', '<h2>Right</h2>'));
  V.state.sections = [M.sectionWith(toc1), M.sectionWith(toc2), M.sectionWith(toc3), M.sectionWith(text), M.sectionWith(link), M.sectionWith(after), two];
  w.VPB.app.renderAll();

  // The Link name setting is retired: none on sections or blocks without one.
  u.select({ id: V.state.sections[3].id });
  t.ok(!u.side().querySelector('#f-sanchor'), 'sections no longer have a Link name setting');
  u.select(after);
  t.ok(!u.side().querySelector('#f-anchor'), 'nor do blocks');

  // Table link: a dashed bar on the canvas, named in its settings.
  const bar = u.el(link).querySelector('.b-tablelink');
  t.ok(bar && /Table link: Where it happened \(map\)/.test(bar.textContent) && /subsection/.test(bar.textContent), 'a Table link shows as a named bar on the canvas');
  t.ok(w.VPB.cfg.layouts.some(l => l.name === 'specialTableLink' && l.schema.fields.every(f => f.key !== 'show_heading')), 'Table link has no title setting');

  // Per-heading settings: put the cursor in a heading, then use the panel.
  function caretIn(headingText) {
    const div = u.el(text).querySelector('.b-html');
    const hd = [...div.querySelectorAll('h2,h3,h4')].find(x => x.textContent === headingText);
    const r = ed.d.createRange(); r.setStart(hd.firstChild, 2); r.collapse(true);
    const sel = w.getSelection(); sel.removeAllRanges(); sel.addRange(r);
    ed.d.dispatchEvent(new w.Event('selectionchange'));
  }
  u.select(text);
  t.ok(/Click into a heading/.test(u.side().querySelector('.rt-toc').textContent), 'the Table of contents panel says to click into a heading');
  caretIn('The Great Storm of September 8, 1900');
  t.ok(/a section \(Heading 2\)/.test(u.side().querySelector('.rt-toc').textContent) && u.side().querySelector('#f-toc-show').checked, 'in a heading: its level, and shown by default');
  u.setVal('#f-toc-name', 'The Great Storm', 'input'); u.setVal('#f-toc-name', 'The Great Storm');
  caretIn('The first depot');
  t.ok(/a subsection \(Heading 3\)/.test(u.side().querySelector('.rt-toc').textContent) && u.side().querySelector('#f-toc-name').value === '', 'the panel follows the cursor to another heading');
  u.check('f-toc-show', false);
  t.ok(/data-toc-name="The Great Storm"/.test(text.data.html) && /<h3 data-toc-hide="1">/.test(text.data.html), 'the settings sit on the headings while editing');

  // Canvas preview and "Found on this page".
  await new Promise(r => setTimeout(r, 300));
  const shown = b => [...u.el(b).querySelectorAll('.special-blocks-jump-list a')].map(a => { const n = a.previousSibling && a.previousSibling.previousSibling; return (n && n.className === 'special-blocks-jump-num' ? n.textContent + ' ' : '') + a.textContent; });
  t.eq(shown(toc1), ['The railroad arrives', 'The Great Storm', 'Where it happened (map)', 'Visit us', 'Left A', 'Left B', 'Right'], 'the canvas shows the real list (renamed, left out, Table link, column order)');
  t.eq(shown(toc2), ['1 The railroad arrives', '2 The Great Storm', '2.1 A note on the sources', '2.2 Where it happened (map)', '3 Visit us', '4 Left A', '5 Left B', '6 Right'], 'down to Heading 4, numbered (a Heading 4 under a Heading 2 is one step in)');
  u.select(toc1);
  const found = [...u.side().querySelectorAll('.toc-found-item')];
  const depot = found.find(x => /The first depot/.test(x.textContent)), note = found.find(x => /A note on the sources/.test(x.textContent));
  t.ok(found.length === 9 && depot.classList.contains('off') && /left out/.test(depot.textContent) && /deeper than chosen/.test(note.textContent), 'Found on this page lists every heading and Table link, with the left-out ones and why');
  t.ok(/8 listed|7 listed/.test(u.side().querySelector('.toc-found .lbl').textContent), 'with a count: ' + u.side().querySelector('.toc-found .lbl').textContent);
  found.find(x => /Where it happened/.test(x.textContent)).click();
  u.button('Show it on the page').click();
  t.eq(V.state.sel && V.state.sel.id, link.id, 'an entry opens its settings, and "Show it on the page" selects it');

  // Save: stored in the text block's layout data, not in its HTML.
  const res = await H.saveBlocks(ed, V.exportBlocks());
  t.ok(res.body.ok, 'saved');
  const saved = res.body.page['o:block'].find(b => b['o:layout'] === 'html' && /railroad/.test(b['o:data'].html));
  t.eq(saved['o:layout_data'].vpb_toc, [{ i: 1, hide: true }, { i: 2, name: 'The Great Storm' }], 'heading settings saved as vpb_toc (by heading index)');
  t.ok(!/data-toc/.test(saved['o:data'].html), 'and the saved HTML is plain');
  ed = await H.openEditor(pageId);
  const back = ed.V.state.sections[3].cols[0].blocks[0];
  t.ok(/data-toc-name="The Great Storm"/.test(back.data.html) && /data-toc-hide/.test(back.data.html), 'reopening puts them back on the headings');
  t.ok(!ed.V.exportBlocks().some(b => /data-toc/.test(JSON.stringify(b['o:data']))), 'and exporting takes them off again');

  // Public page.
  const html = await (await fetch(`${H.BASE}/s/${H.SITE}/page/${res.body.page['o:slug']}`)).text();
  const d = new JSDOM(html).window.document;
  const ids = [...d.querySelectorAll('h2[id], h3[id], h4[id]')].map(x => x.id);
  t.ok(['the-railroad-arrives', 'the-first-depot', 'the-great-storm-of-september-8-1900', 'a-note-on-the-sources', 'visit-us'].every(i => ids.includes(i)), 'Heading 2, 3, and 4 get ids: ' + ids.join(', '));
  const spot = d.getElementById('where-it-happened-map');
  t.ok(spot && spot.classList.contains('vpb-table-link') && !spot.textContent && spot.closest('.block-specialTableLink'), 'a Table link is an empty spot with its id');
  const lists = [...d.querySelectorAll('.special-blocks-jump-links')];
  const items = n => [...lists[n].querySelectorAll('a')].map(a => a.getAttribute('href') + ' ' + a.textContent);
  const nums = n => [...lists[n].querySelectorAll('.special-blocks-jump-num')].map(x => x.textContent);
  t.eq(items(0), ['#the-railroad-arrives The railroad arrives', '#the-great-storm-of-september-8-1900 The Great Storm', '#where-it-happened-map Where it happened (map)', '#visit-us Visit us', '#left-a Left A', '#left-b Left B', '#right Right'], 'the site lists the same, in reading order');
  t.eq(items(1).slice(0, 4), ['#the-railroad-arrives The railroad arrives', '#the-great-storm-of-september-8-1900 The Great Storm', '#a-note-on-the-sources A note on the sources', '#where-it-happened-map Where it happened (map)'], 'down to Heading 4');
  t.eq(nums(1), ['1', '2', '2.1', '2.2', '3', '4', '5', '6'], 'numbered 1, 2, 2.1, 2.2, \u2026 (the number sits before the link)');
  const nested = lists[1].querySelector('ol.special-blocks-jump-list > li > ol > li > a');
  t.ok(nested && /A note on the sources/.test(nested.textContent) && lists[1].classList.contains('special-blocks-jump-box'), 'subsections are nested lists');
  t.eq(items(2).map(x => x.split(' ').slice(1).join(' ')), ['The railroad arrives', 'The Great Storm', 'Visit us', 'Left A', 'Left B', 'Right'], 'Heading 2 only: no subsections or subsection Table links');
  t.ok(lists[0].getAttribute('aria-label') === 'On this page' && lists[0].querySelector('h2.special-blocks-heading'), 'labeled navigation with its title');

  // Clicking moves focus to the spot.
  const live = new JSDOM(html, { url: `${H.BASE}/s/${H.SITE}/page/x`, runScripts: 'outside-only', pretendToBeVisual: true });
  const lw = live.window;
  lw.matchMedia = () => ({ matches: true });
  lw.Element.prototype.scrollIntoView = function () { this.__scrolled = true; };
  lw.eval(fs.readFileSync(path.join(H.MODULE_DIR, 'asset/special-blocks/js/jump-links.js'), 'utf8'));
  lw.document.querySelector('.special-blocks-jump-links a[href="#where-it-happened-map"]').click();
  const target = lw.document.getElementById('where-it-happened-map');
  t.ok(target.__scrolled && lw.document.activeElement === target && lw.location.hash === '#where-it-happened-map', 'clicking scrolls there, moves focus, and updates the address');
  live.window.close();

  // Changing entries from the Table of contents' own list (v1.14.1).
  {
    const eu = H.ui(ed), tb = eu.blocks().find(x => x.layout === 'specialJumpLinks');
    eu.select(tb);
    const row = name => [...eu.side().querySelectorAll('.toc-row')].find(r => r.querySelector('.toc-name').textContent === name);
    row('Visit us').querySelector('[aria-label^="Indent"]').click();
    const visitBlock = eu.blocks().find(x => /Visit us/.test(x.data.html || ''));
    t.ok(/<h3>Visit us<\/h3>/.test(visitBlock.data.html), 'Indent in the list makes a heading one level smaller (Heading 2 \u2192 3)');
    row('Visit us').querySelector('.toc-grip').dispatchEvent(new ed.w.KeyboardEvent('keydown', { key: 'ArrowLeft', bubbles: true }));
    t.ok(/<h2>Visit us<\/h2>/.test(visitBlock.data.html), 'and the left arrow key on its grip moves it back');
    row('Visit us').querySelector('.toc-found-item').click();
    const nm = row('Visit us').querySelector('.toc-entry input[type=text]');
    nm.value = 'Visiting'; nm.dispatchEvent(new ed.w.Event('change'));
    t.ok(/data-toc-name="Visiting"/.test(visitBlock.data.html) && row('Visiting'), 'renamed from the list');
    const lv = row('Visiting').querySelector('.toc-entry select');
    lv.value = '3'; lv.dispatchEvent(new ed.w.Event('change'));
    t.ok(/<h4 data-toc-name="Visiting">Visit us<\/h4>/.test(visitBlock.data.html), 'its level set from the list (Sub-subsection = Heading 4), keeping its name');
    const map = row('Where it happened (map)');
    map.querySelector('[aria-label^="Outdent"]').click();
    t.eq(eu.blocks().find(x => x.layout === 'specialTableLink').data.level, '1', 'a Table link outdents too');
    const chk = row('The first depot').querySelector('.toc-found-item'); chk.click();
    const show = row('The first depot').querySelector('.toc-entry input[type=checkbox]');
    t.ok(show && !show.checked, 'a left-out heading shows unticked');
    show.checked = true; show.dispatchEvent(new ed.w.Event('change'));
    t.ok(!/data-toc-hide/.test(eu.blocks().find(x => /first depot/.test(x.data.html || '')).data.html), 'ticking it puts it back in');
  }

  // Check page.
  ed.V.state.sections.push(M.sectionWith(M.newBlock('specialTableLink')));
  const issues = ed.w.VPB.pageCheck.run().map(i => i.text);
  t.ok(issues.some(x => /Table link has no name/.test(x)), 'Check page: a Table link without a name');
  t.ok(!issues.some(x => /Table of contents has nothing/.test(x)), 'no complaint about a Table of contents that has entries');
  t.ok(!ed.errs.length, 'no editor errors');

  // Older pages: Link names and "only named spots" keep working.
  const oldId = await H.createPage('TocOld', [
    { 'o:layout': 'specialJumpLinks', 'o:data': { heading: 'Old list', source: 'named', levels: 'h2', style: 'list', numbered: false } },
    { 'o:layout': 'specialJumpLinks', 'o:data': { heading: 'Old both', source: 'named_headings', levels: 'h2h3', style: 'list', numbered: false } },
    { 'o:layout': 'html', 'o:data': { html: '<h2>Hours</h2><h3>Holidays</h3>' } },
    { 'o:layout': 'html', 'o:data': { html: '<p>Open Saturdays.</p>' }, 'o:layout_data': { vpb_anchor: 'visit-us', vpb_anchor_label: 'Visiting' } }
  ]);
  // Saving through the API cleans settings to today's, so put the old ones
  // back in the database the way version 1.13 stored them.
  const sql = q => require('child_process').execSync(`mysql -uroot ${process.env.VPB_DB || 'omeka'} -N -e "${q}"`).toString().trim();
  const jl = sql(`SELECT id FROM site_page_block WHERE page_id=${oldId} AND layout='specialJumpLinks' ORDER BY position`).split('\n');
  sql(`UPDATE site_page_block SET data='{\\"heading\\":\\"Old list\\",\\"source\\":\\"named\\",\\"levels\\":\\"h2\\",\\"style\\":\\"list\\",\\"numbered\\":false}' WHERE id=${jl[0]}`);
  sql(`UPDATE site_page_block SET data='{\\"heading\\":\\"Old both\\",\\"source\\":\\"named_headings\\",\\"levels\\":\\"h2h3\\",\\"style\\":\\"list\\",\\"numbered\\":false}' WHERE id=${jl[1]}`);
  t.ok(/"source":"named"/.test(sql(`SELECT data FROM site_page_block WHERE id=${jl[0]}`)), 'old settings in place');
  const page = await H.api('GET', `site_pages/${oldId}`);
  const od = new JSDOM(await (await fetch(`${H.BASE}/s/${H.SITE}/page/${page['o:slug']}`)).text()).window.document;
  const ol = [...od.querySelectorAll('.special-blocks-jump-links')].map(n => [...n.querySelectorAll('a')].map(a => a.getAttribute('href') + ' ' + a.textContent));
  t.eq(ol[0], ['#visit-us Visiting'], 'an old "only named spots" list still lists only them');
  t.eq(ol[1], ['#hours Hours', '#holidays Holidays', '#visit-us Visiting'], 'an old "named spots and headings" list keeps its headings and subheadings');
  t.ok(od.getElementById('visit-us') && /Open Saturdays/.test(od.getElementById('visit-us').textContent), 'the old link name still gives the block its id');
  const oed = await H.openEditor(oldId);
  const ou = H.ui(oed), ob = ou.blocks();
  t.ok(ob[0].data.levels === 'none' && !('source' in ob[0].data) && ob[1].data.levels === 'h3', 'old settings open as today’s (only Table links; down to Heading 3)');
  ou.select(ob[3]);
  const field = ou.side().querySelector('#f-anchor');
  t.ok(field && field.value === 'visit-us' && /Older link name/.test(ou.side().textContent), 'a block with an old link name still shows it, to change or clear');
  t.ok(!oed.errs.length, 'no editor errors (old page)');
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
