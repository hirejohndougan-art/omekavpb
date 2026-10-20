'use strict';
// v1.0.3: more background colors, a custom color picker, and white text on
// dark backgrounds, in pages, headers, and footers (one editor for all).
const H = require('../lib/harness');

(async () => {
  const t = H.suite('background colors');
  const pageId = await H.createPage('Colors', [{ 'o:layout': 'html', 'o:data': { html: '<p>Hello</p>' } }]);
  const ed = await H.openEditor(pageId);
  const { w, d, V } = ed, u = H.ui(ed);
  const block = V.state.sections[0].cols[0].blocks[0];
  u.select(block);
  const side = () => d.getElementById('side');
  t.ok(side().querySelectorAll('.swatches .sw').length === 18, '18 colors to choose from (plus none): ' + side().querySelectorAll('.swatches .sw').length);
  t.ok(side().querySelector('#f-bg-pick') && side().querySelector('input[placeholder="#RRGGBB"]'), 'custom color picker and code box');
  // A dark swatch.
  [...side().querySelectorAll('.swatches .sw')].find(b => b.getAttribute('aria-label') === 'Navy').click();
  t.ok(block.bg === '#1F3A5F' && /color:\s*#fff/i.test(u.el(block).querySelector('.inner').getAttribute('style')), 'dark swatch: white text in the editor');
  t.ok(/turns white/.test(side().textContent), 'the sidebar says why the text turns white');
  // The custom picker.
  const pick = side().querySelector('#f-bg-pick');
  pick.value = '#ffcc00'; pick.dispatchEvent(new w.Event('input')); pick.dispatchEvent(new w.Event('change'));
  t.ok(block.bg === '#FFCC00' && !/color:\s*#fff/i.test(u.el(block).querySelector('.inner').getAttribute('style') || ''), 'custom color picked; light, so text stays dark');
  t.ok(side().querySelector('input[placeholder="#RRGGBB"]').value === '#FFCC00', 'code box follows the picker');
  // Dark custom color on a column; save; public page.
  block.bg = '#2B2B2B'; block.cls = 'my-class';
  V.state.sections[0].cols[0].bg = '#8A3324';
  const res = await H.saveBlocks(ed, V.exportBlocks());
  const saved = res.body.page['o:block'].find(b => b['o:layout'] === 'html');
  t.ok(saved['o:layout_data'].background_color === '#2B2B2B' && /\bmy-class\b/.test(saved['o:layout_data'].class) && /\bvpb-on-dark\b/.test(saved['o:layout_data'].class), 'saved with its color and a dark-background class');
  const ed2 = await H.openEditor(pageId);
  t.eq(ed2.V.state.sections[0].cols[0].blocks[0].cls, 'my-class', 'the automatic class doesn\u2019t show up in your CSS class field');
  const html = await (await fetch(`${H.BASE}/s/${H.SITE}/page/${res.body.page['o:slug']}`)).text();
  t.ok(/vpb-on-dark/.test(html) && /class="[^"]*vpb-col[^"]*vpb-on-dark|vpb-on-dark[^"]*"[^>]*background-color: #8A3324/.test(html), 'public page: dark block and dark column get white text');
  t.ok(!ed.errs.length && !ed2.errs.length, 'no editor errors');
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
