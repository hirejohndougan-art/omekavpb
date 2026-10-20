'use strict';
// Rearrange a page into an 8/4 section with styles, save, reopen, and check the
// stored data and the rebuilt layout. (Formerly e2e.js + rt.js.)
const H = require('../lib/harness');

(async () => {
  const t = H.suite('save and round trip');
  const fx = await H.fixtures();
  const pageId = await H.createPage('railroad-days', [
    { 'o:layout': 'pageTitle', 'o:data': {} },
    { 'o:layout': 'html', 'o:data': { html: '<p>Intro text.</p>' } },
    { 'o:layout': 'html', 'o:data': { html: '<h2>History</h2><p>More.</p>' } },
    { 'o:layout': 'media', 'o:data': {}, 'o:attachment': [{ 'o:item': { 'o:id': fx.items[0] }, 'o:media': null, 'o:caption': '<p>Cap</p>' }] },
    { 'o:layout': 'lineBreak', 'o:data': { break_type: 'opaque' } }
  ]);

  const ed = await H.openEditor(pageId);
  const { d, V } = ed, u = H.ui(ed);
  t.ok(!ed.errs.length, 'loads without errors: ' + ed.errs);
  t.ok(/standard editor/.test(d.getElementById('notice').textContent), 'first-open notice shown for a standard-editor page');
  t.eq(V.state.sections.map(s => s.cols.map(c => c.w + ':' + c.blocks.map(b => b.layout).join('+'))),
    [['12:pageTitle+html+html+media+lineBreak']], 'standard page imports as one full-width section');

  // Switch to Wide + narrow through the UI, then move the media block to column 2.
  u.press(d.querySelector('.sec'));
  d.querySelector('.presets button[aria-label="Wide + narrow"]').click();
  const s = V.state.sections[0];
  const mi = s.cols[0].blocks.findIndex(b => b.layout === 'media');
  s.cols[1].blocks.push(s.cols[0].blocks.splice(mi, 1)[0]);
  const txt = s.cols[0].blocks.find(b => b.layout === 'html' && /Intro/.test(b.data.html));
  Object.assign(txt, { bg: '#F1EEE4', pad: 24, align: 'center', cls: 'lead' });
  s.bg = '#E3ECE5'; s.cols[1].bg = '#F3EBD9';

  const out = V.exportBlocks();
  t.eq(out.map(b => [b['o:layout'], b['o:data'].span || null, b['o:layout_data'].grid_column_position || null, b['o:layout_data'].grid_column_span || null, b['o:layout_data'].vpb_col === undefined ? null : b['o:layout_data'].vpb_col]),
    [['blockGroup', 5, null, null, null], ['pageTitle', null, '1', '8', 0], ['media', null, '9', '4', 1], ['html', null, '1', '8', 0], ['html', null, '1', '8', 0], ['lineBreak', null, '1', '8', 0]],
    'export interleaves rows and sets grid positions');

  const res = await H.saveBlocks(ed, out);
  t.ok(res.status === 200 && res.body.ok, 'save accepted: ' + JSON.stringify(res.body.errors || ''));
  const bad = await H.saveBlocks(ed, out, 'wrong');
  t.ok(bad.status === 403 && !bad.body.ok, 'bad CSRF token refused');

  const saved = await H.api('GET', 'site_pages/' + pageId);
  t.ok(saved['o:layout'] === 'grid' && saved['o:layout_data'].grid_columns === 12 && saved['o:layout_data'].visual_page_builder.version === 1, 'page marked as a builder page');
  const media = saved['o:block'].find(b => b['o:layout'] === 'media');
  t.ok(media['o:attachment'][0]['o:item']['o:id'] === fx.items[0] && media['o:attachment'][0]['o:caption'] === '<p>Cap</p>', 'attachment and caption kept');
  const intro = saved['o:block'].find(b => /Intro/.test((b['o:data'] || {}).html || ''));
  t.ok(intro['o:layout_data'].background_color === '#F1EEE4' && intro['o:layout_data'].padding_top === '24' && intro['o:layout_data'].alignment_text === 'center' && intro['o:layout_data'].class === 'lead', 'block style stored in standard keys');

  // Reopen: identical layout, clean status, and re-export equals stored data.
  const ed2 = await H.openEditor(pageId);
  const V2 = ed2.V;
  t.eq(V2.state.sections.map(x => ({ bg: x.bg, cols: x.cols.map(c => c.w + (c.bg ? '(' + c.bg + ')' : '') + ':' + c.blocks.map(b => b.layout).join('+')) })),
    [{ bg: '#E3ECE5', cols: ['8:pageTitle+html+html+lineBreak', '4(#F3EBD9):media'] }], 'reopened layout matches');
  t.ok(!ed2.d.getElementById('notice').textContent, 'no first-open notice on a builder page');
  t.ok(/All changes saved|No changes/.test(ed2.d.getElementById('status').textContent), 'reopened editor is clean');
  const strip = bs => bs.map(b => ({ l: b['o:layout'], d: b['o:data'], ld: b['o:layout_data'], a: (b['o:attachment'] || []).map(a => [a['o:item'] && a['o:item']['o:id'], a['o:media'] && a['o:media']['o:id'], a['o:caption']]) }));
  t.eq(strip(V2.exportBlocks()), strip(saved['o:block']), 're-export equals stored data');
  t.ok(!ed2.errs.length, 'no runtime errors');

  // Public page renders true columns.
  const pub = await (await fetch(`${H.BASE}/s/${H.SITE}/page/${saved['o:slug']}`)).text();
  t.ok((pub.match(/class="vpb-column/g) || []).length === 2, 'public page renders two columns');
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
