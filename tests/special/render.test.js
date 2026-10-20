'use strict';
// Special Blocks: every block saved through the API is cleaned against its
// schema, and renders on the public site with the right items and links.
const { JSDOM } = require('jsdom');
const H = require('../lib/harness');

(async () => {
  const t = H.suite('special blocks: data and public pages');
  const fx = await H.specialFixtures();
  const core = await H.fixtures();
  const blocks = [
    ['specialRandomItems', { site_scope: 'selected', site_ids: [String(fx.dhs), String(fx.legacy)], count: '20', heading: 'From both sites', bogus: 'x' }],
    ['specialRecentItems', { site_scope: 'selected', site_ids: [fx.legacy], count: 3 }],
    ['specialItemSpotlight', { mode: 'fixed', item_id: String(fx.portrait) }],
    ['specialOnThisDay', { site_scope: 'all', match: 'day', count: 10 }],
    ['specialCollectionCards', { site_scope: 'all', show_count: '1' }],
    ['specialCollectionCounts', { site_scope: 'selected', site_ids: [fx.legacy], show_with_media: '1' }],
    ['specialSearchBox', { target_site: fx.legacy, item_set_ids: [fx.legacyPhotos] }],
    ['specialHeroBanner', { asset_id: core.asset, title: 'Railroad Days', subtitle: 'Line one\nline two', button_text: 'Visit', button_url: 'javascript:alert(1)' }],
    ['specialThenAndNow', { before_item_id: core.items[0], after_asset_id: core.asset, caption: 'The depot' }],
    ['specialQuote', { text: 'It was a fine day.', attribution: 'A. Resident', item_id: fx.portrait }],
    ['specialAccordion', { panels: [{ title: 'When are you open?', body: '<p>Tuesday to Saturday.</p>' }, { title: '', body: '' }], open_first: '1', one_at_a_time: '1' }],
    ['specialButtons', { buttons: [{ text: 'Visit', page_id: '', url: '/s/dhs/page/welcome', style: 'primary' }, { text: 'Donate', url: 'https://example.org/donate', new_tab: '1', style: 'secondary' }, { text: '', url: '' }] }],
    ['specialVisitInfo', { hours: [{ days: 'Tuesday to Saturday', times: '10 am to 4 pm' }], address: '1 Main St\nDickinson, TX', email: 'info@example.org', phone: '(281) 555-0100' }],
    ['specialUpcomingEvents', { events: [{ title: 'Past talk', date: '2001-01-01' }, { title: 'Railroad Days', date: '2099-06-14', end_date: '2099-06-15', time: '10 am', location: 'The depot' }, { title: 'Bad date', date: '2099-13-40' }] }]
  ].map(([l, d]) => ({ 'o:layout': l, 'o:data': d }));
  const pageId = await H.createPage('special-all', blocks);
  const page = await H.api('GET', 'site_pages/' + pageId);
  const data = l => page['o:block'].find(b => b['o:layout'] === l)['o:data'];

  // ---- saved data is typed, defaulted, and cleaned
  const r = data('specialRandomItems');
  t.eq([r.site_ids, r.count, r.has_media, r.show_title, r.bogus], [[fx.dhs, fx.legacy], 20, true, true, undefined], 'random items: ids and numbers typed, defaults added, unknown keys dropped');
  t.ok(data('specialSearchBox').placeholder === 'Search photos, documents, and more', 'defaults filled in');
  t.ok(data('specialHeroBanner').button_url === '', 'javascript: link removed');
  t.eq(data('specialAccordion').panels.length, 1, 'empty accordion rows dropped');
  t.eq(data('specialButtons').buttons.map(b => [b.text, b.new_tab]), [['Visit', false], ['Donate', true]], 'empty button rows dropped, checkboxes typed');
  t.eq(data('specialUpcomingEvents').events.map(e => e.date), ['2001-01-01', '2099-06-14', ''], 'invalid dates cleared');

  // ---- public page
  const res = await fetch(`${H.BASE}/s/${H.SITE}/page/${page['o:slug']}`);
  const html = await res.text();
  t.ok(res.status === 200 && !/Fatal error|Warning:|Notice:/.test(html), 'public page renders without PHP errors');
  const section = cls => { const i = html.indexOf(cls); if (i < 0) return ''; const j = html.indexOf('class="special-blocks ', i + cls.length); return html.slice(i, j < 0 ? undefined : j); };
  ['random-items', 'recent-items', 'item-spotlight', 'on-this-day', 'collection-cards', 'collection-counts', 'search-box', 'hero-banner', 'then-and-now', 'quote', 'accordion', 'buttons', 'visit-info', 'upcoming-events']
    .forEach(c => t.ok(html.includes('special-blocks-' + c), 'renders ' + c));

  // Random items are random, and the test site keeps growing: look at up to
  // ten draws for one that has both sites' items.
  let random = section('special-blocks-random-items');
  for (let k = 0; k < 10 && !(/Legacy portrait/.test(random) && /Railroad photo/.test(random)); k++) {
    const again = await (await fetch(`${H.BASE}/s/${H.SITE}/page/${page['o:slug']}`)).text();
    const i = again.indexOf('special-blocks-random-items'), j = again.indexOf('class="special-blocks ', i + 10);
    random = again.slice(i, j < 0 ? undefined : j);
  }
  t.ok(/Legacy portrait/.test(random) && /Railroad photo/.test(random), 'random draws from both chosen sites');
  t.ok(/href="\/s\/legacy\/item\/\d+"[^>]*>[\s\S]*?Legacy portrait/.test(random), 'an item only on the other site links to that site');
  // No item twice (an item on both sites, like "Shared map", counts once). Titles can repeat
  // across test runs, so compare each card's item number.
  const randomCards = [...new JSDOM(random).window.document.querySelectorAll('li.special-blocks-card')];
  const ids = randomCards.map(c => ((c.querySelector('a[href*="/item/"]') || {}).href || '').replace(/.*\/item\//, ''));
  t.ok(randomCards.length > 0 && new Set(ids).size === ids.length, 'an item on both sites appears once (' + randomCards.length + ' cards, all different items)');
  const recent = section('special-blocks-recent-items');
  t.ok(/Legacy portrait/.test(recent) && !/Railroad photo/.test(recent), 'recent items: only the chosen site');
  t.ok(/Legacy portrait/.test(section('special-blocks-item-spotlight')), 'spotlight shows the chosen item');
  const otd = section('special-blocks-on-this-day');
  t.ok(/Legacy portrait/.test(otd) && /Shared map/.test(otd) && !/Undated letter/.test(otd) && !/Railroad photo/.test(otd), 'on this day: only items dated today');
  const cards = section('special-blocks-collection-cards');
  t.ok(/Railroad/.test(cards) && /Legacy photos/.test(cards), 'collection cards from all sites');
  t.ok(/2 items/.test(cards), 'collection cards show item counts');
  const counts = section('special-blocks-collection-counts');
  t.ok(/<dd>2<\/dd>/.test(counts) && /<dd>1<\/dd>/.test(counts), 'counts for the chosen site: 2 items, 1 item set');
  const search = section('special-blocks-search-box');
  t.ok(/action="\/s\/legacy\/item"/.test(search) && new RegExp('name="item_set_id\\[\\]" value="' + fx.legacyPhotos + '"').test(search), 'search box targets the other site and item set');
  t.ok(!/javascript:/i.test(html), 'no javascript: links anywhere');
  t.ok(/Line one<br \/>\s*line two/.test(section('special-blocks-hero-banner')), 'hero subtitle keeps line breaks, escaped');
  t.ok((section('special-blocks-then-and-now').match(/<img /g) || []).length === 2, 'then and now: both photos');
  t.ok(/href="\/s\/legacy\/item\/\d+"/.test(section('special-blocks-quote')), 'quote links to its source item');
  t.ok(/<details class="special-blocks-panel" name="special-accordion-\d+" open>/.test(section('special-blocks-accordion')), 'accordion: first open, grouped');
  const btns = section('special-blocks-buttons');
  t.ok(/href="\/s\/dhs\/page\/welcome"/.test(btns) && /target="_blank" rel="noopener noreferrer"/.test(btns), 'buttons: links and new tab');
  t.ok(/href="mailto:info@example.org"/.test(section('special-blocks-visit-info')) && /href="tel:2815550100"/.test(section('special-blocks-visit-info')), 'visit info: email and phone links');
  const ev = section('special-blocks-upcoming-events');
  t.ok(/Railroad Days/.test(ev) && !/Past talk/.test(ev) && !/Bad date/.test(ev), 'events: past and undated hidden');
  t.ok(/June 14, 2099/.test(ev) && /June 15, 2099/.test(ev) && /datetime="2099-06-14"/.test(ev), 'event dates shown on the right day (no time zone shift)');
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
