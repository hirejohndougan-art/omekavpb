'use strict';
// v0.2.0: edit every core block's settings in the sidebar through the real UI,
// save with the Save button, check what Omeka stored, then reopen.
const H = require('../lib/harness');

(async () => {
  const t = H.suite('block settings');
  const fx = await H.fixtures();
  const [item0, item1, item2] = fx.items;
  const pageId = await H.createPage('block-settings', [
    { 'o:layout': 'pageTitle', 'o:data': {} },
    { 'o:layout': 'html', 'o:data': { html: '<p>Intro text.</p>' } },
    { 'o:layout': 'media', 'o:data': { layout: '', media_display: '', thumbnail_type: 'square', show_title_option: 'item_title' }, 'o:attachment': [{ 'o:item': { 'o:id': item0 }, 'o:media': null, 'o:caption': '<p>First caption</p>' }] },
    { 'o:layout': 'browsePreview', 'o:data': { resource_type: 'items', query: 'fulltext_search=railroad', heading: 'Railroad items', limit: '6', components: ['resource-heading', 'thumbnail'], 'link-text': 'See all' } },
    { 'o:layout': 'itemWithMetadata', 'o:data': {}, 'o:attachment': [{ 'o:item': { 'o:id': 99999999 }, 'o:media': null, 'o:caption': '' }] },
    { 'o:layout': 'asset', 'o:data': [{ id: String(fx.asset), page: '', alt_link_title: '', caption: 'Banner caption' }] },
    { 'o:layout': 'pageDateTime', 'o:data': { display: 'created', date_format: 'long', time_format: 'none' } },
    { 'o:layout': 'tableOfContents', 'o:data': { depth: '2' } },
    { 'o:layout': 'listOfSites', 'o:data': { sort: 'alpha', limit: '', pagination: '0', summaries: '1', thumbnails: '1', exclude_current: '1' } },
    { 'o:layout': 'iiifImage', 'o:data': { image_url: '', title: '', show_title: '0' } },
    { 'o:layout': 'lineBreak', 'o:data': { break_type: 'opaque' } }
  ]);
  const firstPage = (await H.api('GET', 'site_pages?per_page=1&sort_by=id&sort_order=asc'))[0]['o:id'];

  const ed = await H.openEditor(pageId);
  const { w, d } = ed, u = H.ui(ed), sleep = H.sleep;
  t.ok(!ed.errs.length, 'loads without errors ' + ed.errs);

  // Media embed
  const media = u.blk('media');
  t.ok(u.el(media).querySelector('.pv-media .pv-img img') && /First caption/.test(u.el(media).textContent), 'media shows its image and caption as on the site');
  u.select(media);
  t.ok(u.side().querySelector('.content-ed') && u.side().querySelectorAll('.att').length === 1, 'sidebar shows the block settings with one item');
  const mopts = [...u.side().querySelectorAll('#f-am0 option')].map(o => o.value);
  t.eq(mopts, ['', String(fx.media[0][0]), String(fx.media[0][1])], 'file choice lists main file + both files');
  u.setVal('#f-am0', mopts[2]);
  u.setVal('#f-ac0', '<p>New caption</p>');
  u.seg('Arrangement', 'Horizontal'); u.select(media);
  u.setVal('#f-tt', 'medium'); u.setVal('#f-st', 'no_title');
  u.button('Add an item\u2026').click();
  t.ok(u.side().querySelector('#pk-items'), 'picker opens');
  u.setVal('#pk-items', 'photo 2', 'input'); await sleep(900);
  const hit = [...u.side().querySelectorAll('.pk-row')].find(r => /Railroad photo 2/.test(r.textContent));
  t.ok(!!hit, 'search finds the item');
  hit.click(); await sleep(700);
  t.ok(media.att.length === 2 && u.side().querySelector('#pk-items'), 'item added; picker stays open');
  u.button('Done adding').click();
  u.side().querySelectorAll('.att')[1].querySelector('[aria-label="Move up"]').click();
  t.ok(media.att[0]['o:item']['o:id'] === item2, 'reordered');

  // Item with metadata: replace a missing item
  const iwm = u.blk('itemWithMetadata');
  u.select(iwm);
  t.ok(/not found|No item/.test(u.side().textContent), 'missing item flagged');
  u.side().querySelector('.att [aria-label="Remove"]').click();
  u.button('Add an item\u2026').click(); u.setVal('#pk-items', String(item1), 'input'); await sleep(900);
  u.side().querySelector('.pk-row').click(); await sleep(300);
  t.ok(iwm.att.length === 1 && iwm.att[0]['o:item']['o:id'] === item1 && !u.side().querySelector('#f-ac0'), 'item with metadata: item set, no caption field');

  // Asset
  const asset = u.blk('asset');
  t.ok(u.el(asset).querySelector('.pv-assets img') && /Banner caption/.test(u.el(asset).textContent), 'asset shows its image and caption');
  u.select(asset);
  u.setVal('#f-xc0', 'Depot, 1911');
  u.setVal('#f-xp0', String(firstPage));
  t.ok(u.side().querySelector('#f-xl0'), 'link text field appears once a page is chosen');
  u.setVal('#f-xl0', 'Home');

  // Browse preview
  const bp = u.blk('browsePreview'); u.select(bp);
  u.setVal('#f-q', H.BASE + '/s/dhs/item?fulltext_search=depot#top');
  u.setVal('#f-lim', '3'); u.setVal('#f-hd', 'Depot items');
  u.check('f-c-resource-body', false);
  t.ok(bp.data.query === 'fulltext_search=depot', 'pasted URL reduced to its query: ' + bp.data.query);

  // The rest
  u.select(u.blk('listOfSites')); u.check('f-sth', false); u.setVal('#f-sl', '5'); u.check('f-sp', true);
  u.select(u.blk('pageDateTime')); u.setVal('#f-dd', 'modified'); u.setVal('#f-df', 'short');
  u.select(u.blk('tableOfContents')); u.setVal('#f-dep', '3');
  u.select(u.blk('lineBreak')); u.seg('Style', 'Blank space');
  // IIIF blocks arrived in Omeka 4.2; on 4.1 the block is unavailable.
  const hasIiif = ed.cfg.layouts.some(l => l.name === 'iiifImage');
  if (hasIiif) { u.select(u.blk('iiifImage')); u.setVal('#f-iu', 'https://iiif.example.org/img/1/info.json'); u.setVal('#f-it', 'Depot map'); u.check('f-is', true); }

  const depth = u.blk('tableOfContents').data.depth;
  d.getElementById('undo').click(); d.getElementById('redo').click();
  t.ok(u.blk('tableOfContents').data.depth === depth, 'undo/redo keeps settings');

  // Save with the Save button
  t.ok(/Unsaved/.test(u.status()), 'dirty before save');
  d.getElementById('saveBtn').click(); await sleep(2500);
  t.ok(/All changes saved/.test(u.status()), 'saved: ' + u.status() + ' / ' + u.toast());
  const page = await H.api('GET', 'site_pages/' + pageId);
  const get = L => page['o:block'].find(b => b['o:layout'] === L);
  const m = get('media');
  t.eq([m['o:data'].layout, m['o:data'].thumbnail_type, m['o:data'].show_title_option], ['horizontal', 'medium', 'no_title'], 'media options stored');
  t.eq(m['o:attachment'].map(a => [a['o:item']['o:id'], a['o:media'] && a['o:media']['o:id'], a['o:caption']]),
    [[item2, null, ''], [item0, +mopts[2], '<p>New caption</p>']], 'media items, file choice, and captions stored in order');
  t.ok(get('itemWithMetadata')['o:attachment'][0]['o:item']['o:id'] === item1, 'item with metadata stored');
  t.eq(get('asset')['o:data'], [{ id: String(fx.asset), page: String(firstPage), alt_link_title: 'Home', caption: 'Depot, 1911' }], 'asset stored');
  t.eq(get('browsePreview')['o:data'], { resource_type: 'items', query: 'fulltext_search=depot', heading: 'Depot items', limit: '3', components: ['resource-heading', 'thumbnail'], 'link-text': 'See all' }, 'browse preview stored');
  t.eq(get('listOfSites')['o:data'], { sort: 'alpha', limit: '5', pagination: '1', summaries: '1', thumbnails: '0', exclude_current: '1' }, 'list of sites stored');
  t.eq(get('pageDateTime')['o:data'], { display: 'modified', date_format: 'short', time_format: 'none' }, 'date stored');
  t.eq(get('tableOfContents')['o:data'], { depth: '3' }, 'table of contents stored');
  t.eq(get('lineBreak')['o:data'], { break_type: 'transparent' }, 'line break stored');
  if (hasIiif) t.eq(get('iiifImage')['o:data'], { image_url: 'https://iiif.example.org/img/1/info.json', title: 'Depot map', show_title: '1' }, 'IIIF stored');
  t.eq(get('html')['o:data'], { html: '<p>Intro text.</p>' }, 'untouched block unchanged');

  // oEmbed: Omeka can't reach the web here, so the save must fail cleanly and lose nothing.
  [...d.querySelectorAll('.pal')].find(b => /oEmbed/.test(b.textContent)).click(); await sleep(100);
  u.select(u.blk('oembed')); u.setVal('#f-ou', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');
  d.getElementById('saveBtn').click(); await sleep(6000);
  const outbound = /All changes saved/.test(u.status());
  if (outbound) console.log('  note: this server can reach the web, so the oEmbed fetch succeeded; failure path not exercised.');
  else t.ok(/Not saved/.test(u.status()) && /oEmbed/.test(u.toast()), 'oEmbed failure reported with Omeka\u2019s message: ' + u.toast());
  t.ok(u.blk('oembed') && u.blk('oembed').data.url, 'nothing lost after the save attempt');
  u.select(u.blk('oembed'));
  d.dispatchEvent(new w.KeyboardEvent('keydown', { key: 'Delete', bubbles: true }));
  d.getElementById('saveBtn').click(); await sleep(2500);
  t.ok(/All changes saved/.test(u.status()), 'clean save after removing the oEmbed block');
  t.ok(!ed.errs.length, 'no runtime errors ' + ed.errs);

  // Reopen
  const ed2 = await H.openEditor(pageId), u2 = H.ui(ed2);
  const stored = (await H.api('GET', 'site_pages/' + pageId))['o:block'].map(b => [b['o:layout'], b['o:data']]);
  t.eq(ed2.V.exportBlocks().map(b => [b['o:layout'], b['o:data']]), stored, 'reopened editor re-exports the stored data');
  t.ok(ed2.d.querySelectorAll('.pv-img img').length >= 3, 'previews show images');
  t.ok(/Up to 3 items matching fulltext_search=depot/.test(u2.el(u2.blk('browsePreview')).textContent), 'browse preview shows its summary card while loading');
  await sleep(1500);
  const bpEl = u2.el(u2.blk('browsePreview'));
  t.ok(bpEl.querySelector('.pv-browse') && /Depot items/.test(bpEl.textContent), 'browse preview then shows the real search results');
  t.ok(ed2.d.querySelector('.b-space') && !ed2.d.querySelector('.b-hr'), 'blank-space line break shown as space');
  u2.select(u2.blk('media'));
  t.ok(u2.side().querySelector('#f-am0').value === '' && u2.side().querySelector('#f-am1').value === String(mopts[2]), 'file choices restored');

  // Public page
  const pub = await (await fetch(`${H.BASE}/s/${H.SITE}/page/${page['o:slug']}`)).text();
  t.ok(/layout-horizontal/.test(pub) && /New caption/.test(pub), 'public page: horizontal media + caption');
  t.ok(/Depot, 1911/.test(pub) && /link-title">Home</.test(pub), 'public page: asset caption and link');
  t.ok(/Depot items/.test(pub) && (!hasIiif || /Depot map/.test(pub)), 'public page: browse heading' + (hasIiif ? ' and IIIF title' : ''));
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
