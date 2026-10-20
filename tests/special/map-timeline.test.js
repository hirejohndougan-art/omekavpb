'use strict';
// v1.1.0: Map of places (OpenStreetMap) and Timeline.
const { JSDOM } = require('jsdom');
const H = require('../lib/harness');

(async () => {
  const t = H.suite('special blocks: map and timeline');
  const core = await H.fixtures();
  await H.specialFixtures(); // makes sure the test data exists
  const blocks = [
    ['specialMap', { heading: 'Historic Dickinson', tour: true, places: [
      { name: 'The depot', lat: '29.4608', lng: '-95.0513', note: 'Built 1896', item_id: core.items[0] },
      { name: '', lat: '29.4650', lng: '-95.0420', item_id: core.items[1] },
      { name: 'Bad pin', lat: '200', lng: 'x' }
    ] }],
    ['specialTimeline', { heading: 'Through the years', site_scope: 'all', events: [{ date: '1900-09-08', title: 'The Great Storm', text: 'Galveston hurricane.' }, { date: 'someday', title: 'No date' }] }],
    ['specialTimeline', { layout: 'vertical', use_items: false, order: 'desc', events: [{ date: '1925', title: 'Later' }, { date: '1896', title: 'Earlier' }] }]
  ].map(([l, d]) => ({ 'o:layout': l, 'o:data': d }));
  const pageId = await H.createPage('Map and timeline', blocks);
  const page = await H.api('GET', 'site_pages/' + pageId);
  const html = await (await fetch(`${H.BASE}/s/${H.SITE}/page/${page['o:slug']}`)).text();
  const d = new JSDOM(html).window.document;
  t.ok(!/Fatal error|Warning:|Notice:/.test(html), 'page renders without PHP errors');

  const map = d.querySelector('.special-blocks-map[data-places]');
  const pins = JSON.parse(map.getAttribute('data-places'));
  t.ok(pins.length === 2 && pins[0].lat === 29.4608 && pins[0].lng === -95.0513, 'two good pins; the bad one is left out');
  t.ok(pins[1].name === 'Railroad photo 1' || /Railroad/.test(pins[1].name), 'a pin with no name uses its item\u2019s title');
  t.ok(pins[0].url && /\/item\//.test(pins[0].url) && map.getAttribute('data-tour') === '1', 'pins link their items; tour on');
  t.ok(d.querySelector('ol.special-blocks-map-list') && /Built 1896/.test(d.querySelector('.special-blocks-map-list').textContent), 'numbered list of stops under the map (works without JavaScript)');
  const srcs = [...d.querySelectorAll('script[src]')].map(x => x.getAttribute('src'));
  t.ok(srcs.some(x => x.includes('/modules/VisualPageBuilder/asset/map/vpb-map.js')) && srcs.some(x => x.includes('places-map.js')) && !srcs.some(x => /leaflet/i.test(x)), 'the module\u2019s own map script loads (no outside map library)');

  const tls = d.querySelectorAll('.special-blocks-timeline');
  t.ok(tls.length === 2, 'two timelines');
  const across = [...tls[0].querySelectorAll('.special-blocks-tl-entry')].map(li => li.querySelector('.special-blocks-tl-title').textContent);
  const storm = across.indexOf('The Great Storm');
  t.ok(storm >= 0 && !across.includes('No date') && across.some(x => /Legacy portrait|Shared map/.test(x)), 'items and typed events together; undated left out: ' + across.join(' | '));
  const years = [...tls[0].querySelectorAll('.special-blocks-tl-entry')].map(li => +li.querySelector('.special-blocks-tl-year').textContent);
  t.ok(years.every((y, i) => !i || y >= years[i - 1]), 'oldest first: ' + years.join(','));
  t.ok(tls[0].querySelector('.special-blocks-tl-nav [aria-controls]') && srcs.some(x => x.includes('timeline.js')), 'across: Earlier / Later buttons');
  t.eq([...tls[1].querySelectorAll('.special-blocks-tl-title')].map(x => x.textContent), ['Later', 'Earlier'], 'down the page, newest first');

  // Editor: pin tool, previews.
  const ed = await H.openEditor(pageId);
  const u = H.ui(ed);
  u.select(u.blk('specialMap'));
  t.ok(u.side().querySelector('.pm-tool .pm-map'), 'the map block\u2019s settings have the place-pins map');
  ed.w.VPB.placesMapTool.addPlace(u.blk('specialMap'), 29.47, -95.06);
  t.ok(u.blk('specialMap').data.places.length === 4 && u.blk('specialMap').data.places[3].lat === '29.470000' && /Unsaved/.test(u.status()), 'clicking the map adds a place there');
  const form = u.side().querySelector('.pm-new');
  t.ok(form && form.querySelector('#pm-new-name') && [...form.querySelectorAll('button')].some(b => /Link an item/.test(b.textContent)), 'then asks for a name and offers to link an item');
  const nameBox = form.querySelector('#pm-new-name');
  nameBox.value = 'Old post office'; nameBox.dispatchEvent(new ed.w.Event('input')); nameBox.dispatchEvent(new ed.w.Event('change'));
  [...u.side().querySelectorAll('.pm-new button')].find(b => b.textContent === 'Done').click();
  t.ok(u.blk('specialMap').data.places[3].name === 'Old post office' && !u.side().querySelector('.pm-new'), 'Done keeps the name and closes the form');
  // Linking an item through the picker.
  ed.w.VPB.placesMapTool.addPlace(u.blk('specialMap'), 29.48, -95.07);
  [...u.side().querySelectorAll('.pm-new button')].find(b => /Link an item/.test(b.textContent)).click();
  const q = u.side().querySelector('.pm-new input[type="search"], .pm-new input.pk-q, .pm-new input[id^="pk-"]');
  if (q) { q.value = 'Railroad'; q.dispatchEvent(new ed.w.Event('input')); }
  await H.sleep(1500);
  const rowBtn = u.side().querySelector('.pm-new .pk-row');
  if (rowBtn) rowBtn.click();
  const p5 = u.blk('specialMap').data.places[4];
  t.ok(p5 && p5.item_id && /Railroad/.test(p5.name), 'linking an item to an unnamed place uses the item\u2019s title: ' + JSON.stringify(p5));
  const labels = [...ed.d.querySelectorAll('.pal-label')].map(x => x.textContent);
  t.ok(labels.includes('Map of places') && labels.includes('Timeline'), 'both in the palette');
  t.ok(!ed.errs.length, 'no editor errors ' + ed.errs);
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
