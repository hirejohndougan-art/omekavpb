'use strict';
// v1.1.0: background images for sections, columns, and blocks.
const H = require('../lib/harness');

(async () => {
  const t = H.suite('background images');
  const fx = await H.fixtures();
  const pageId = await H.createPage('Background images', [{ 'o:layout': 'html', 'o:data': { html: '<p>Over the photo</p>' } }]);
  let ed = await H.openEditor(pageId);
  let { w, d, V } = ed;
  const u = H.ui(ed);
  const sec = V.state.sections[0], col = sec.cols[0], blk = col.blocks[0];
  // Choose through the sidebar: block first.
  u.select(blk);
  const side = () => d.getElementById('side');
  t.ok(/Background image/.test(side().textContent) && [...side().querySelectorAll('button')].some(b => b.textContent === 'Choose an image\u2026'), 'block settings offer a background image');
  [...side().querySelectorAll('button')].find(b => b.textContent === 'Choose an image\u2026').click();
  const q = d.getElementById('pk-assets-bgimg');
  t.ok(!!q, 'asset picker opens');
  q.value = ''; q.dispatchEvent(new w.Event('input'));
  await H.sleep(1200);
  const row = side().querySelector('.pk-row, .pk-res button, .pk-list button');
  row.click();
  t.ok(blk.img && blk.img.asset === fx.asset && blk.img.size === 'cover' && blk.img.pos === 'center', 'picked: fill the area, centered');
  t.ok(/background-image:/.test(u.el(blk).querySelector('.inner').getAttribute('style')), 'shown in the editor');
  // Options.
  [...side().querySelectorAll('.segw button')].find(b => b.textContent === 'Whole picture').click();
  [...side().querySelectorAll('.segw button')].find(b => b.textContent === 'Top').click();
  const ov = side().querySelector('#f-img-ov'); ov.checked = true; ov.dispatchEvent(new w.Event('change'));
  t.ok(blk.img.size === 'contain' && blk.img.pos === 'top' && blk.img.overlay, 'fit, position, and darken change');
  t.ok(/color:#fff/.test(u.el(blk).querySelector('.inner').getAttribute('style')), 'darkened: text turns white in the editor');
  // Section and column, set directly.
  sec.img = { asset: fx.asset, size: 'cover', pos: 'bottom', overlay: false };
  col.img = { asset: fx.asset, size: 'cover', pos: 'center', overlay: true };
  const res = await H.saveBlocks(ed, V.exportBlocks());
  const blocks = res.body.page['o:block'];
  const g = blocks.find(b => b['o:layout'] === 'blockGroup'), hb = blocks.find(b => b['o:layout'] === 'html');
  t.ok(g['o:layout_data'].background_image_asset == fx.asset && g['o:layout_data'].background_image_position_y === 'bottom', 'section saved in Omeka\u2019s own background image settings');
  t.ok(hb['o:layout_data'].background_image_size === 'contain' && /vpb-overlay/.test(hb['o:layout_data'].class) && /vpb-on-dark/.test(hb['o:layout_data'].class), 'block saved with fit, position, and darkening');
  t.ok(g['o:layout_data'].vpb_section.cols[0].img.asset == fx.asset && g['o:layout_data'].vpb_section.cols[0].img.overlay === true, 'column saved');
  // Reload: settings come back; the automatic classes stay hidden.
  ed = await H.openEditor(pageId);
  const r = ed.V.state.sections[0];
  t.ok(r.img && r.img.pos === 'bottom' && r.cols[0].img && r.cols[0].img.overlay && r.cols[0].blocks[0].img.size === 'contain', 'all three reload');
  t.ok(r.cols[0].blocks[0].cls === '' && ed.cfg.refs.assets[fx.asset], 'CSS class field stays clean; the image is known to the editor');
  // Public page.
  const html = await (await fetch(`${H.BASE}/s/${H.SITE}/page/${res.body.page['o:slug']}`)).text();
  t.ok((html.match(/background-image: url\(/g) || []).length >= 3, 'public page shows all three images');
  t.ok(/block-layout-background-image-size-contain/.test(html) && /vpb-column[^"]*vpb-overlay/.test(html), 'fit and darkening on the site');
  t.ok(!ed.errs.length, 'no editor errors');
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
