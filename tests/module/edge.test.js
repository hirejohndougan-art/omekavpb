'use strict';
// Import of a non-builder grid page, unsafe HTML, embeds, unknown blocks, empty sections.
const H = require('../lib/harness');

(async () => {
  const t = H.suite('edge cases');
  const pageId = await H.createPage('edge', [
    { 'o:layout': 'html', 'o:data': { html: '<p>Left <img src=x onerror="window.PWNED=1"></p>' }, 'o:layout_data': { grid_column_position: '1', grid_column_span: '3' } },
    { 'o:layout': 'html', 'o:data': { html: '<p>Right</p>' }, 'o:layout_data': { grid_column_position: '4', grid_column_span: '3' } },
    { 'o:layout': 'html', 'o:data': { html: '<p>Video</p><iframe src="https://www.youtube.com/embed/x"></iframe>' }, 'o:layout_data': { grid_column_position: 'auto', grid_column_span: '6' } },
    { 'o:layout': 'mappingMap', 'o:data': { zoom: '5', bounds: '1,2,3,4' }, 'o:layout_data': { class: 'map-x' } }
  ], { 'o:layout': 'grid', 'o:layout_data': { grid_columns: 6 } });
  const stored = await H.api('GET', 'site_pages/' + pageId);
  const video = stored['o:block'][2]['o:data'].html;
  if (!/iframe/.test(video)) console.log('  note: HTML purifier is on, so the iframe was already stripped; embed-lock save check is weaker. See test-env notes.');

  const ed = await H.openEditor(pageId);
  const { w, d, V } = ed, u = H.ui(ed);
  t.eq(V.state.sections.map(s => s.cols.map(c => c.w + ':' + c.blocks.map(b => b.layout).join('+'))),
    [['6:html', '6:html'], ['12:html+mappingMap']], 'grid-6 side-by-side blocks import as a 6/6 section');
  t.ok(!w.PWNED, 'onerror payload in the preview did not run');
  t.ok(!d.querySelector('#secs iframe'), 'iframe not rendered in the preview');
  const locked = [...d.querySelectorAll('.b-html')].filter(e => !e.hasAttribute('contenteditable')).length;
  // With Omeka's HTML purifier on, unsafe parts were removed on save, so
  // fewer blocks need locking.
  const unsafe = stored['o:block'].filter(b => /onerror|<iframe/i.test((b['o:data'] || {}).html || '')).length;
  t.ok(locked === unsafe, 'blocks with unsafe HTML are locked: ' + locked + ' of ' + unsafe);
  t.ok(d.querySelector('.b-card.unknown'), 'unknown block shows an Unavailable card');
  u.select(u.blk('mappingMap'));
  t.ok(/isn.t active/.test(u.side().textContent), 'unknown block settings explain it is kept as is');

  [...d.querySelectorAll('.pal')].find(b => b.textContent.startsWith('3 columns')).click();
  const res = await H.saveBlocks(ed, V.exportBlocks());
  t.ok(res.status === 200 && res.body.ok, 'saves');
  const p = res.body.page;
  t.eq(p['o:block'][2]['o:data'] && p['o:block'].find(b => /Video/.test((b['o:data'] || {}).html || ''))['o:data'].html, video, 'locked embed block saved unchanged');
  const map = p['o:block'].find(b => b['o:layout'] === 'mappingMap');
  t.eq([map['o:data'], map['o:layout_data'].class], [{ zoom: '5', bounds: '1,2,3,4' }, 'map-x'], 'unknown block data and class kept');
  t.eq(p['o:block'].filter(b => b['o:layout'] === 'blockGroup').map(g => g['o:data'].span + ':' + g['o:layout_data'].vpb_section.cols.map(c => c.w).join('/')),
    ['2:6/6', '2:12'], 'sections stored; the empty one is left out (it would only add space)');
  const ed2 = await H.openEditor(pageId);
  t.ok(ed2.V.state.sections.length === 2, 'reloads without the empty section');
  t.ok(!ed.errs.length && !ed2.errs.length, 'no runtime errors');
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
