'use strict';
// v0.4.0: block search, ⓘ help, Layers outline, resizable sidebar, live previews.
const H = require('../lib/harness');

(async () => {
  const t = H.suite('sidebar tools and live previews');
  const fx = await H.specialFixtures();
  const pageId = await H.createPage('sidebar-tools', [
    { 'o:layout': 'html', 'o:data': { html: '<h2>Railroad Days</h2>' } },
    { 'o:layout': 'specialRandomItems', 'o:data': { site_scope: 'selected', site_ids: [fx.legacy], count: 5 } },
    { 'o:layout': 'browsePreview', 'o:data': { query: 'fulltext_search=railroad', limit: '2', heading: 'Railroad items' } }
  ]);
  const ed = await H.openEditor(pageId);
  const { w, d, V } = ed, u = H.ui(ed), sleep = H.sleep;
  const pal = () => d.getElementById('palette');
  t.ok(!ed.errs.length, 'loads ' + ed.errs);

  // ---- palette: groups, search
  const titles = () => [...pal().querySelectorAll('.group-title')].map(x => x.textContent);
  t.eq(titles(), ['Sections', 'Text', 'Special Blocks', 'Omeka blocks'], 'palette groups: Sections, Text, then Special Blocks');
  const search = d.getElementById('palSearch');
  search.value = 'random'; search.dispatchEvent(new w.Event('input'));
  t.eq([...pal().querySelectorAll('.pal-label')].map(x => x.textContent), ['Random items'], 'search finds Random items only');
  t.ok(d.activeElement !== null && d.getElementById('palSearch') === search, 'search box kept while typing');
  search.value = 'zzzz'; search.dispatchEvent(new w.Event('input'));
  t.ok(/No block matches/.test(pal().textContent), 'no-match message');
  search.value = 'column'; search.dispatchEvent(new w.Event('input'));
  t.ok(pal().querySelectorAll('.secpal').length === 6, 'searching "column" finds the section layouts');
  search.value = ''; search.dispatchEvent(new w.Event('input'));

  // ---- ⓘ help
  const infoBtn = [...pal().querySelectorAll('.pal-info')].find(b => /Media embed/.test(b.getAttribute('aria-label')));
  t.ok(infoBtn && pal().querySelectorAll('.pal-info').length >= 30, 'every block tile has an info button: ' + pal().querySelectorAll('.pal-info').length);
  t.ok(!pal().querySelector('.secpal ~ .pal-info'), 'section layouts have no info button');
  infoBtn.click();
  const about = pal().querySelector('.pal-about');
  t.ok(about && /images or files from items/.test(about.textContent) && /Good for/.test(about.textContent), 'help panel explains the block in plain words');
  t.ok(pal().querySelector('.pal-info[aria-expanded="true"]'), 'info button shows it is open');
  [...pal().querySelectorAll('.pal-info')].find(b => /Random items/.test(b.getAttribute('aria-label'))).click();
  t.ok(/different handful of items/.test(pal().querySelector('.pal-about').textContent) && pal().querySelectorAll('.pal-about').length === 1, 'other modules\u2019 help text, one panel at a time');
  u.button('Add this block', pal()).click();
  const added = V.state.sections.flatMap(s => s.cols.flatMap(c => c.blocks)).filter(b => b.layout === 'specialRandomItems');
  t.ok(added.length === 2 && V.state.sel && V.state.sel.id === added[1].id, '"Add this block" adds and selects it');
  u.button('Settings', d.getElementById('selBar')).click();
  t.ok(/different handful of items/.test((u.side().querySelector('.p-about') || {}).textContent || ''), 'settings show what the block does');
  d.getElementById('undo').click();

  // ---- Layers
  u.select(u.blk('html'));
  d.querySelector('.back-btn').click();
  [...pal().querySelectorAll('[role="tab"]')].find(b => b.textContent === 'Layers').click();
  const rows = () => [...pal().querySelectorAll('.layer')].map(r => r.querySelector('.layer-label').textContent);
  t.eq(rows(), ['Section 1', 'Heading', 'Random items', 'Browse preview'], 'layers list the page');
  t.ok(pal().querySelector('.layer.selected .layer-label').textContent === 'Heading', 'layers show the selection');
  const browseRow = [...pal().querySelectorAll('.layer')].find(r => /Browse preview/.test(r.textContent));
  browseRow.querySelector('[aria-label="Move Browse preview up"]').click();
  t.eq(rows(), ['Section 1', 'Heading', 'Browse preview', 'Random items'], 'arrows reorder blocks');
  [...pal().querySelectorAll('.layer-main')].find(b => /Random items/.test(b.textContent)).click();
  t.ok(V.state.sel && V.state.sel.kind === 'block' && !d.getElementById('propsView').hidden, 'clicking a row selects it and opens its settings');

  // ---- resizable sidebar
  const handle = d.getElementById('sideResize');
  const sideW = () => d.documentElement.style.getPropertyValue('--side-w');
  t.ok(handle && handle.getAttribute('role') === 'separator' && handle.tabIndex === 0, 'resize handle is a focusable separator');
  t.eq(sideW(), '300px', 'default width');
  const pe = (type, x, extra) => new w.PointerEvent(type, Object.assign({ bubbles: true, clientX: x, button: 0, buttons: type === 'pointerup' ? 0 : 1, pointerType: 'mouse' }, extra));
  handle.dispatchEvent(pe('pointerdown', 300));
  w.dispatchEvent(pe('pointermove', 420));
  t.eq(sideW(), '420px', 'dragging right widens');
  w.dispatchEvent(pe('pointermove', 330));
  t.eq(sideW(), '330px', 'dragging back left narrows');
  w.dispatchEvent(pe('pointerup', 330));
  w.dispatchEvent(pe('pointermove', 500));
  t.eq(sideW(), '330px', 'letting go anywhere ends the drag');
  t.eq(w.localStorage.getItem('visual-page-builder-sidebar-width'), '330', 'width remembered');
  handle.dispatchEvent(pe('pointerdown', 330));
  w.dispatchEvent(pe('pointermove', 400));
  w.dispatchEvent(pe('pointermove', 600, { buttons: 0 }));
  t.eq(sideW(), '400px', 'a release outside the window (no pointerup) still ends the drag');
  w.dispatchEvent(pe('pointermove', 100, { buttons: 1 }));
  t.eq(sideW(), '400px', '…and later moves do nothing');
  handle.dispatchEvent(pe('pointerdown', 400));
  w.dispatchEvent(pe('pointermove', 50));
  w.dispatchEvent(pe('pointerup', 50));
  t.eq(sideW(), '280px', 'never narrower than 280 px');
  handle.dispatchEvent(new w.KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));
  t.eq(sideW(), '296px', 'arrow keys resize');
  handle.dispatchEvent(new w.MouseEvent('dblclick', { bubbles: true }));
  t.eq(sideW(), '300px', 'double-click resets');

  // ---- live previews
  await sleep(1800);
  const live = u.el(u.blk('specialRandomItems')).querySelector('.b-live');
  t.ok(live && /Legacy portrait/.test(live.textContent) && /Shared map/.test(live.textContent) && !/Railroad photo/.test(live.textContent), 'random items preview shows real items from the chosen site');
  t.ok(live && live.hasAttribute('inert') && !live.querySelector('script'), 'preview is inert and has no scripts');
  const text1 = live.textContent;
  V.state.dev = 'tablet'; ed.d.querySelector('[data-dev="tablet"]').click();
  t.ok(u.el(u.blk('specialRandomItems')).querySelector('.b-live').textContent === text1, 'redrawing doesn\u2019t reshuffle random items');
  const bp = u.el(u.blk('browsePreview'));
  t.ok(bp.querySelector('.pv-browse') && /Railroad items/.test(bp.textContent) && bp.querySelectorAll('.pv-card').length === 2, 'browse preview shows the real search results');
  u.select(u.blk('specialRandomItems'));
  u.setVal('#sf-count', '1');
  await sleep(1800);
  t.ok(u.el(u.blk('specialRandomItems')).querySelectorAll('.special-blocks-card').length === 1, 'changing a setting updates the preview');

  // ---- preview endpoint guards
  const post = (body, cookie = ed.cookie) => fetch(H.BASE + ed.cfg.previewUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', Cookie: cookie }, body: JSON.stringify(body) }).then(r => r.status);
  t.ok(await post({ csrf: 'bad', layout: 'browsePreview', data: {} }) === 403, 'preview: bad token refused');
  t.ok(await post({ csrf: ed.cfg.csrf, layout: 'noSuchLayout', data: {} }) === 400, 'preview: unknown layout refused');
  t.ok(await post({ csrf: ed.cfg.csrf, layout: 'html', data: {} }) === 400, 'preview: layouts without a server preview refused');
  t.ok((await fetch(H.BASE + ed.cfg.previewUrl, { headers: { Cookie: ed.cookie } })).status === 405, 'preview: GET refused');
  t.ok(!ed.errs.length, 'no runtime errors ' + ed.errs);
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
