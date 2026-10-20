'use strict';
// v1.13.0: Special Blocks in smaller palette groups, "Works well with", and
// Jump links shown as "Table of contents (this page)" (v1.14.0; before: "(jump links)"), next to Table link.
const fs = require('fs');
const path = require('path');
const H = require('../lib/harness');

(async () => {
  const t = H.suite('palette groups');
  // Every Special Block is in exactly one group.
  const src = fs.readFileSync(path.join(H.MODULE_DIR, 'src/SpecialBlocks.php'), 'utf8');
  const layouts = [...src.slice(src.indexOf('const LAYOUTS'), src.indexOf('];', src.indexOf('const LAYOUTS'))).matchAll(/'(special\w+)'\s*=>/g)].map(m => m[1]);
  const groupsSrc = src.slice(src.indexOf('const GROUPS'), src.indexOf('];', src.indexOf('const GROUPS')));
  const grouped = [...groupsSrc.matchAll(/'(special\w+)'/g)].map(m => m[1]);
  t.ok(layouts.length === 48 && grouped.length === 48 && new Set(grouped).size === 48 && layouts.every(l => grouped.includes(l)), 'all 48 Special Blocks are in exactly one group');

  const pageId = await H.createPage('Palette groups', [{ 'o:layout': 'html', 'o:data': { html: '<p>x</p>' } }]);
  const ed = await H.openEditor(pageId);
  const { d, w } = ed;
  const heads = () => [...d.querySelectorAll('.pal-sub')].map(b => b.textContent.replace(/\d+$/, '').replace(/^[\u25BE\u25B8]/, '').trim());
  t.eq(heads(), ['From your collection', 'Telling history', 'Maps', 'Recordings and documents', 'Visiting and events', 'Getting involved', 'Finding your way', 'Reuse', 'Browse pages', 'Media'], 'on a page: ten groups, in order (header and footer blocks aren\u2019t offered here)');
  const labelsUnder = name => { const head = [...d.querySelectorAll('.pal-sub')].find(b => b.textContent.includes(name)); const grid = d.getElementById(head.getAttribute('aria-controls')); return grid ? [...grid.querySelectorAll('.pal-label')].map(x => x.textContent) : []; };
  t.eq(labelsUnder('Maps'), ['Map of places', 'Story map'], 'Maps: Map of places and Story map together');
  const fyw = labelsUnder('Finding your way');
  t.ok(fyw.includes('Table of contents (this page)') && fyw.includes('Table link') && fyw.includes('Footnotes'), 'Jump links is now "Table of contents (this page)", in Finding your way with Table link and Footnotes');
  // Folding remembers.
  const mapsHead = () => [...d.querySelectorAll('.pal-sub')].find(b => b.textContent.includes('Maps'));
  mapsHead().click();
  t.ok(mapsHead().getAttribute('aria-expanded') === 'false' && !labelsUnder('Maps').length && /Maps/.test(w.localStorage.getItem('vpb.palFolded')), 'a group folds closed, and that\u2019s remembered');
  // Searching shows matches in every group, open.
  const s = d.getElementById('palSearch'); s.value = 'map'; s.dispatchEvent(new w.Event('input'));
  t.ok(mapsHead() && mapsHead().getAttribute('aria-expanded') === 'true' && labelsUnder('Maps').length === 2, 'while searching, groups with matches open');
  s.value = ''; s.dispatchEvent(new w.Event('input'));
  mapsHead().click();
  // Works well with.
  const tile = [...d.querySelectorAll('.pal-wrap')].find(x => x.querySelector('.pal-label').textContent === 'Table of contents (this page)');
  tile.querySelector('.pal-info').click();
  t.ok(/Works well with: Your headings \(Heading 2, 3, and 4 in text blocks\), Table link/.test(d.querySelector('.pal-about').textContent), 'the \u24D8 info says what it works well with');
  // Header editing: header and footer blocks, no smaller groups.
  const cookie = H.readCookie();
  const hid = +(await fetch(`${H.BASE}/admin/visual-page-builder/region/${H.SITE}/header`, { headers: { Cookie: cookie }, redirect: 'manual' })).headers.get('location').split('/').pop();
  const he = await H.openEditor(hid);
  t.ok(!he.d.querySelector('.pal-sub') && [...he.d.querySelectorAll('.pal-label')].some(x => x.textContent === 'Site menu'), 'in a header: its own blocks, no smaller groups');
  await H.api('DELETE', 'site_pages/' + hid);
  t.ok(!ed.errs.length && !he.errs.length, 'no editor errors');
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
