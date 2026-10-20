'use strict';
// Phase 4: the Filters block's settings in the editor. Its fields and groups come from
// the block's schema (BrowseFacets::schema), and the sidebar shows them when the block is selected.
const H = require('../lib/harness');

(async () => {
  const t = H.suite('Filters block settings');
  const pageId = await H.createPage('Filters settings', [{ 'o:layout': 'facets', 'o:data': {} }]);
  const ed = await H.openEditor(pageId);
  const u = H.ui(ed);
  const cfg = ed.w.VPB.cfg.layouts.find(l => l.name === 'facets');
  t.ok(!!cfg, 'the Filters block is a known layout (facets)');
  const keys = (cfg.schema.fields || []).map(f => f.key);
  const want = ['show_collection', 'show_type', 'show_subject', 'show_creator', 'show_place', 'show_date', 'show_images', 'show_description', 'text_order', 'text_shown'];
  t.eq(keys.filter(k => want.includes(k)), want, 'the settings: eight Fields checkboxes, then text order and text shown');
  const groups = [...new Set((cfg.schema.fields || []).map(f => f.group))].filter(g => g !== 'Title');
  t.eq(groups, ['Fields', 'Display'], 'two groups: Fields, then Display');
  const orderField = cfg.schema.fields.find(f => f.key === 'text_order');
  t.ok(orderField && JSON.stringify(orderField).includes('az'), 'text order offers count or A to Z');

  u.select(u.blk('facets'));
  const side = u.side();
  const text = side.textContent;
  t.ok(['Collection', 'Type', 'Subject', 'Creator', 'Place', 'Date, with the years', 'Images', 'Description'].every(l => text.includes(l)), 'the sidebar lists the eight Fields');
  t.ok(text.includes('Subjects, creators and places in') && side.querySelector('select') && text.includes('Values listed before'), 'the sidebar has the text order and how many values show');
  t.ok(side.querySelectorAll('input[type=checkbox]').length >= 8, 'each Field is a checkbox');
  t.ok(!ed.errs.length, 'no errors ' + ed.errs);
  await H.api('DELETE', 'site_pages/' + pageId);
  t.done();
})().catch(e => { console.error(e); process.exit(1); });
