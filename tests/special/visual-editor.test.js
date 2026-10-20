'use strict';
// Special Blocks in the Visual Page Builder: the sidebar form is built from
// each block's schema, edits save, and cards summarize the settings.
const H = require('../lib/harness');

(async () => {
  const t = H.suite('special blocks: visual editor');
  const fx = await H.specialFixtures();
  const core = await H.fixtures();
  const pageId = await H.createPage('special-vpb', [
    { 'o:layout': 'specialRandomItems', 'o:data': {} },
    { 'o:layout': 'specialThenAndNow', 'o:data': { before_item_id: core.items[0] } }
  ]);
  const ed = await H.openEditor(pageId);
  const { d } = ed, u = H.ui(ed), sleep = H.sleep;
  t.ok(!ed.errs.length, 'editor loads ' + ed.errs);
  const pal = [...d.querySelectorAll('.pal')].map(b => b.textContent);
  t.ok(['Random items', 'Hero banner', 'Upcoming events', 'Collection cards'].every(n => pal.some(p => p.includes(n))), 'Special Blocks appear in the palette');

  // Random items: scope, sites, count
  const rnd = u.blk('specialRandomItems');
  t.ok(/Show from: This site/.test(u.el(rnd).textContent), 'card summarizes the settings: ' + u.el(rnd).textContent.slice(0, 80));
  u.select(rnd);
  t.ok(u.side().querySelector('#sf-site_scope') && !u.side().querySelector('#sf-site_ids'), 'Sites hidden until "Chosen sites"');
  u.setVal('#sf-site_scope', 'selected');
  t.ok(u.side().querySelector('#sf-site_ids'), 'Sites appear after choosing "Chosen sites"');
  u.check(`sf-site_ids-${fx.dhs}`, true);
  u.check(`sf-site_ids-${fx.legacy}`, true);
  u.setVal('#sf-count', '8');
  u.check('sf-has_media', false);
  t.ok(/Chosen sites/.test(u.el(rnd).textContent) && /Legacy Center/.test(u.el(rnd).textContent), 'card updates: ' + u.el(rnd).textContent.slice(0, 120));

  // Then and now: pick the "after" photo from the asset picker
  const tn = u.blk('specialThenAndNow');
  u.select(tn);
  t.ok(/Railroad photo 0/.test(u.side().textContent), 'existing item shows its title');
  const afterBtn = [...u.side().querySelectorAll('button')].filter(b => b.textContent === 'Choose an image\u2026')[1];
  afterBtn.click(); await sleep(900);
  const row = u.side().querySelector('#pk-assets-sf-after_asset_id') && u.side().querySelector('.pk-row');
  t.ok(!!row, 'asset picker opens for the "after" photo');
  row.click(); await sleep(300);
  t.ok(tn.data.after_asset_id === core.asset && !u.side().querySelector('.picker'), 'asset chosen and picker closes');

  // Accordion added from the palette: add two sections
  [...d.querySelectorAll('.pal')].find(b => /Accordion/.test(b.textContent)).click(); await sleep(100);
  const acc = u.blk('specialAccordion');
  u.select(acc);
  u.button('Add an entry').click();
  u.setVal('#sf-panels-0-title', 'When are you open?');
  u.setVal('#sf-panels-0-body', '<p>Tuesday to Saturday.</p>');
  u.button('Add an entry').click();
  u.setVal('#sf-panels-1-title', 'Is there parking?');
  t.eq(acc.data.panels.map(p => p.title), ['When are you open?', 'Is there parking?'], 'accordion entries added');
  u.side().querySelectorAll('.att')[1].querySelector('[aria-label="Move up"]').click();
  t.eq(acc.data.panels.map(p => p.title), ['Is there parking?', 'When are you open?'], 'entries reorder');

  d.getElementById('saveBtn').click(); await sleep(2500);
  t.ok(/All changes saved/.test(u.status()), 'saved: ' + u.status() + ' ' + u.toast());
  const saved = await H.api('GET', 'site_pages/' + pageId);
  const get = l => saved['o:block'].find(b => b['o:layout'] === l)['o:data'];
  t.eq([get('specialRandomItems').site_scope, get('specialRandomItems').site_ids, get('specialRandomItems').count, get('specialRandomItems').has_media], ['selected', [fx.dhs, fx.legacy], 8, false], 'random items saved');
  t.eq([get('specialThenAndNow').before_item_id, get('specialThenAndNow').after_asset_id], [core.items[0], core.asset], 'then and now saved');
  t.eq(get('specialAccordion').panels, [{ title: 'Is there parking?', body: '' }, { title: 'When are you open?', body: '<p>Tuesday to Saturday.</p>' }], 'accordion saved in order');

  const ed2 = await H.openEditor(pageId);
  const strip = bs => bs.map(b => [b['o:layout'], b['o:data']]);
  t.eq(strip(ed2.V.exportBlocks()), strip(saved['o:block']), 'reopened editor re-exports the stored data');
  t.ok(!ed.errs.length && !ed2.errs.length, 'no runtime errors');
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
