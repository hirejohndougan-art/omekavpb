'use strict';
// v1.5.0: timeline entries with a linked item, a picture, and details; map
// places with a picture choice; list entries open to edit one at a time.
const { JSDOM } = require('jsdom');
const H = require('../lib/harness');

(async () => {
  const t = H.suite('timeline entries and map pictures');
  const core = await H.fixtures();
  const legacyPortrait = (await H.api('GET', 'items?fulltext_search=Legacy%20portrait'))[0]['o:id'];
  const pageId = await H.createPage('Timeline entries', [
    { 'o:layout': 'specialTimeline', 'o:data': { heading: 'Through the years', use_items: true, site_scope: 'all', size: 'large', events: [
      { title: '', date: '', item_id: legacyPortrait, img: 'item', text: 'A school portrait.' },
      { title: 'The Great Storm', date: '1900-09-08', img: 'asset', asset_id: core.asset, text: 'Galveston hurricane.' },
      { title: 'No picture here', date: '1925', img: 'none', text: '' }
    ] } },
    { 'o:layout': 'specialTimeline', 'o:data': { use_items: false, show_details: false, show_images: false, events: [{ title: 'Quiet', date: '1930', text: 'Hidden details' }] } },
    { 'o:layout': 'specialMap', 'o:data': { places: [{ name: 'With image', lat: '29.46', lng: '-95.05', img: 'asset', asset_id: core.asset }, { name: 'Item picture', lat: '29.47', lng: '-95.04', item_id: core.items[0], img: 'item' }, { name: 'None', lat: '29.48', lng: '-95.03', item_id: core.items[1], img: 'none' }] } }
  ]);
  const page = await H.api('GET', 'site_pages/' + pageId);
  const html = await (await fetch(`${H.BASE}/s/${H.SITE}/page/${page['o:slug']}`)).text();
  const d = new JSDOM(html).window.document;
  t.ok(!/Fatal error|Warning:|Notice:/.test(html), 'no PHP errors');
  const tl = d.querySelectorAll('.special-blocks-timeline');
  const cards = [...tl[0].querySelectorAll('.special-blocks-tl-entry')];
  const byTitle = n => cards.find(c => c.querySelector('.special-blocks-tl-title').textContent === n);
  const portrait = byTitle('Legacy portrait');
  t.ok(portrait && portrait.querySelector('a.special-blocks-tl-title') && portrait.querySelector('img') && /A school portrait/.test(portrait.textContent), 'an entry linked to an item takes its title and date, links to it, shows its picture and the details');
  t.ok(cards.filter(c => /Legacy portrait/.test(c.textContent)).length === 1, 'the linked item isn\u2019t added twice by "add dated items automatically"');
  const storm = byTitle('The Great Storm');
  t.ok(storm && storm.querySelector('img') && /Galveston hurricane/.test(storm.textContent), 'an entry can use a chosen image');
  t.ok(byTitle('No picture here') && !byTitle('No picture here').querySelector('img'), '"No picture" shows no picture');
  t.ok(tl[0].classList.contains('special-blocks-tl-large'), 'card size class');
  t.ok(!/Hidden details/.test(tl[1].textContent) && /Quiet/.test(tl[1].textContent), '"Show details" off hides details');
  const pins = JSON.parse(d.querySelector('.special-blocks-map[data-places]').getAttribute('data-places'));
  t.ok(pins[0].thumb && /asset/.test(pins[0].thumb) && pins[1].thumb && pins[2].thumb === null, 'map places: chosen image, the item\u2019s picture, or none');

  // Sidebar: entries open one at a time; the image field shows only for "Choose an image".
  const ed = await H.openEditor(pageId);
  const u = H.ui(ed);
  u.select(u.blk('specialTimeline'));
  const rows = () => [...u.side().querySelectorAll('.lrow')];
  t.ok(rows().length === 3 && rows().every(r => !r.classList.contains('open')), 'entries start closed, each with a one-line summary');
  rows()[1].querySelector('.lrow-head').click();
  t.ok(rows()[1].classList.contains('open') && rows().filter(r => r.classList.contains('open')).length === 1, 'clicking an entry opens it (one at a time)');
  const open = rows()[1];
  t.ok(/Image/.test(open.textContent) && /Details/.test(open.textContent) && /Link an item/.test(open.textContent), 'an open entry has title, date, item, picture, image, and details');
  rows()[2].querySelector('.lrow-head').click();
  t.ok(!/Choose an image\u2026/.test(rows()[2].textContent.replace(/Choose an image(?!\u2026)/g, '')), 'the image field hides when the picture choice isn\u2019t "Choose an image"');
  [...u.side().querySelectorAll('button')].find(b => b.textContent === 'Add an entry').click();
  t.ok(rows().length === 4 && rows()[3].classList.contains('open'), 'a new entry opens by itself');
  t.ok(!ed.errs.length, 'no editor errors ' + ed.errs);
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
