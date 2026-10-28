'use strict';
// v2.12.0: Story timeline: slides over a zoomable time bar, like Knight Lab's TimelineJS.
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');
const H = require('../lib/harness');

(async () => {
  const t = H.suite('special blocks: story timeline');
  const core = await H.fixtures();
  const data = {
    heading: 'Along the rails',
    intro_headline: 'Dickinson along the rails', intro_text: '<p>From the first depot <script>alert(1)</script>to the last train.</p>',
    intro_media: 'asset', intro_asset_id: core.asset,
    events: [
      { title: 'Last passenger train', date: 'circa 1936', media: 'none', text: '<p>Cars win.</p>', group: 'Railroad', bg: 'dark' },
      { title: 'The Great Storm', date: '1900-09-08', media: 'url', media_url: 'https://www.youtube.com/watch?v=abc123', caption: 'Film', credit: 'Rosenberg Library', text: '<p>The hurricane.</p>' },
      { title: 'Strawberry boom', date: '1905', end_date: '1915', media: 'asset', asset_id: core.asset, group: 'Farming' },
      { title: '', date: '1925', item_id: core.items[0], media: 'item', group: 'Railroad' },
      { title: '<b>Depot</b> opens', date: 'June 1879', media: 'none', display_date: 'Summer 1879' },
      { title: 'No date at all', date: '' },
      { title: 'Bad video', date: '1950', media: 'url', media_url: 'https://evil.example/video' }
    ],
    eras: [{ title: 'The berry years', start: '1895', end: '1925' }, { title: 'No start', start: '', end: '1930' }]
  };
  const pageId = await H.createPage('Story timeline', [{ 'o:layout': 'specialStoryTimeline', 'o:data': data }]);
  const page = await H.api('GET', 'site_pages/' + pageId);
  const url = `${H.BASE}/s/${H.SITE}/page/${page['o:slug']}`;
  const html = await (await fetch(url)).text();
  const d = new JSDOM(html).window.document;
  t.ok(!/Fatal error|Warning:|Notice:/.test(html), 'renders without PHP errors');
  const box = d.querySelector('section.special-blocks-stl');
  t.ok(box, 'the block is on the page');
  const titles = [...box.querySelectorAll('.stl-slide:not(.stl-intro) .stl-headline')].map(x => x.textContent);
  t.eq(titles, ['<b>Depot</b> opens', 'The Great Storm', 'Strawberry boom', 'Railroad photo 0', 'Last passenger train', 'Bad video'], 'events in date order; one with no date is left out; a linked item gives its title');
  t.ok(!box.querySelector('.stl-headline b') && !/<script>alert/.test(html), 'typed titles are escaped; story HTML is cleaned');
  const intro = box.querySelector('.stl-slide.stl-intro');
  t.ok(intro && intro.dataset.index === '0' && /Dickinson along the rails/.test(intro.textContent) && intro.querySelector('.stl-media img'), 'a title slide comes first, with its picture');
  const dates = [...box.querySelectorAll('.stl-slide:not(.stl-intro) .stl-date')].map(x => x.firstChild.textContent.trim());
  t.eq(dates, ['Summer 1879', 'September 8, 1900', '1905 – 1915', '1925', 'c. 1936', '1950'], 'dates are written out; "Date as shown" wins; an end date makes a span');
  const storm = box.querySelectorAll('.stl-slide')[2];
  const iframe = storm.querySelector('.stl-embed iframe');
  t.ok(iframe && iframe.getAttribute('data-src') === 'https://www.youtube-nocookie.com/embed/abc123' && !iframe.getAttribute('src'), 'YouTube in privacy mode, loaded only when its slide shows');
  t.ok(storm.querySelector('.stl-credit').textContent === 'Rosenberg Library' && storm.querySelector('.stl-caption').textContent === 'Film', 'caption and credit under the media');
  t.ok(!box.querySelectorAll('.stl-slide')[6].querySelector('iframe'), 'videos from other sites are refused');
  t.ok(box.querySelectorAll('.stl-slide')[5].classList.contains('stl-on-dark'), 'a dark background gets light text');
  const photo = box.querySelectorAll('.stl-slide')[4];
  t.ok(photo.querySelector('.stl-item-link a') && photo.querySelector('.stl-media img'), 'a linked item shows its picture and a link to it');
  t.ok(box.querySelector('.stl-nav[hidden]') && box.querySelector('.stl-next[hidden]'), 'time bar and arrows stay hidden until the script runs');
  const markers = [...box.querySelectorAll('.stl-marker')];
  t.ok(markers.length === 6 && markers.every((m, i) => m.dataset.slide === String(i + 1)), 'a marker for every event (none for the title slide)');
  const lefts = markers.map(m => parseFloat(m.style.left));
  t.ok(lefts.every((l, i) => !i || l >= lefts[i - 1]) && lefts[0] >= 0 && lefts[5] <= 100, 'markers sit in date order along the bar');
  t.ok(box.querySelector('.stl-spanbar[data-for="3"]') && markers[2].classList.contains('has-span'), 'the span shows as a bar');
  const bands = [...box.querySelectorAll('.stl-band')].map(b => (b.querySelector('.stl-band-label') || { textContent: '' }).textContent);
  t.eq(bands, ['', 'Farming', 'Railroad'], 'rows: unnamed first, then named rows in date order of their first event');
  t.eq([...box.querySelectorAll('.stl-era-label')].map(x => x.textContent), ['The berry years'], 'eras shade the bar; one with no start is left out');
  const labels = [...box.querySelectorAll('.stl-tick.is-labeled')].map(x => x.textContent);
  t.ok(labels.length >= 3 && labels.every(l => /^\d{4}$/.test(l)), 'year labels on the axis: ' + labels.join(' '));
  t.ok(/story-timeline\.js/.test(html.replace(/&#x2F;/g, '/')), 'its script loads');

  // Run the player.
  const live = new JSDOM(html, { url, runScripts: 'outside-only', pretendToBeVisual: true });
  const w = live.window;
  w.matchMedia = () => ({ matches: true }); // reduced motion: no animations
  w.eval(fs.readFileSync(path.join(H.MODULE_DIR, 'asset/special-blocks/js/story-timeline.js'), 'utf8'));
  await H.sleep(100);
  const lb = w.document.querySelector('section.special-blocks-stl');
  const shown = () => [...lb.querySelectorAll('.stl-slide')].filter(s => !s.hidden).map(s => s.dataset.index);
  t.ok(lb.classList.contains('is-enhanced') && !lb.querySelector('.stl-nav').hidden, 'the script takes over and shows the time bar');
  t.eq(shown(), ['0'], 'one slide at a time, starting with the title');
  t.ok(lb.querySelector('.stl-prev').hidden && /The Great Storm|Depot/.test(lb.querySelector('.stl-next').textContent), 'no Previous on the first slide; Next names the next event');
  lb.querySelector('.stl-begin').click();
  t.eq(shown(), ['1'], 'Begin opens the first event');
  t.ok(w.location.hash === '#' + lb.querySelectorAll('.stl-slide')[1].id, 'the address names the event, to share it');
  lb.querySelector('.stl-next').click();
  t.eq(shown(), ['2'], 'Next moves on');
  t.ok(lb.querySelectorAll('.stl-slide')[2].querySelector('iframe').getAttribute('src'), 'the shown slide’s video loads');
  t.ok(lb.querySelectorAll('.stl-marker')[1].getAttribute('aria-current') === 'step', 'its marker is marked current');
  lb.querySelectorAll('.stl-marker')[4].click();
  t.eq(shown(), ['5'], 'clicking a marker jumps to its event');
  t.ok(!lb.querySelectorAll('.stl-slide')[2].querySelector('iframe').getAttribute('src'), 'leaving a slide stops its video');
  lb.querySelector('.stl-prev').click();
  t.eq(shown(), ['4'], 'Previous goes back');
  const key = k => lb.querySelector('.stl-marker').dispatchEvent(new w.KeyboardEvent('keydown', { key: k, bubbles: true }));
  key('End');
  t.ok(shown()[0] === '6' && lb.querySelector('.stl-next').hidden, 'End goes to the last event; no Next there');
  key('Home');
  t.eq(shown(), ['0'], 'Home goes back to the title');
  key('ArrowRight');
  t.eq(shown(), ['1'], 'the right arrow key moves on');
  lb.querySelector('.stl-home').click();
  t.eq(shown(), ['0'], 'the house button returns to the title');
  const zin = lb.querySelector('.stl-zoom-in'), zout = lb.querySelector('.stl-zoom-out');
  t.ok(zout.disabled, 'can’t zoom out past the whole timeline');
  zin.click();
  t.ok(lb.querySelector('.stl-track').style.width === '200%' && !zout.disabled && lb.storyTimeline.zoom() === 2, 'zoom in widens the time bar');
  zout.click();
  t.ok(lb.querySelector('.stl-track').style.width === '100%', 'zoom out narrows it again');
  live.window.close();

  // Opening a shared link starts at that event.
  const anchor = box.querySelectorAll('.stl-slide')[3].id;
  const linked = new JSDOM(html, { url: url + '#' + anchor, runScripts: 'outside-only', pretendToBeVisual: true });
  linked.window.matchMedia = () => ({ matches: true });
  linked.window.eval(fs.readFileSync(path.join(H.MODULE_DIR, 'asset/special-blocks/js/story-timeline.js'), 'utf8'));
  await H.sleep(50);
  t.eq([...linked.window.document.querySelectorAll('.stl-slide')].filter(s => !s.hidden).map(s => s.id), [anchor], 'a link to an event opens at it');
  linked.window.close();

  // Archive items added automatically, in rows by item set.
  const auto = await H.createPage('Story timeline items', [{ 'o:layout': 'specialStoryTimeline', 'o:data': {
    events: [{ title: 'Typed', date: '1890' }], use_items: true, site_scope: 'all', item_rows: 'item_set', start_at: 'last', links: false
  } }]);
  const autoHtml = await (await fetch(`${H.BASE}/s/${H.SITE}/page/${(await H.api('GET', 'site_pages/' + auto))['o:slug']}`)).text();
  const ad = new JSDOM(autoHtml).window.document;
  const autoSlides = ad.querySelectorAll('.special-blocks-stl .stl-slide');
  t.ok(!/Fatal error|Warning:|Notice:/.test(autoHtml) && autoSlides.length >= 1, 'items from the archive render (' + autoSlides.length + ' slides)');
  const ab = ad.querySelector('section.special-blocks-stl');
  t.ok(ab.dataset.start === String(autoSlides.length - 1) && ab.dataset.links === '0', '"Opens at the most recent event" and "no links" reach the script');

  // Editor: palette and preview.
  const ed = await H.openEditor(pageId);
  const u = H.ui(ed);
  t.ok([...ed.d.querySelectorAll('.pal-label')].some(x => x.textContent === 'Story timeline'), 'in the palette');
  u.select(u.blk('specialStoryTimeline'));
  t.ok(/Events/.test(u.side().textContent) && /Eras/.test(u.side().textContent) && /Title slide/.test(u.side().textContent), 'its settings have Title slide, Events, and Eras');
  const pv = await (await fetch(H.BASE + ed.cfg.previewUrl, {
    method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', Cookie: ed.cookie },
    body: JSON.stringify({ csrf: ed.cfg.csrf, layout: 'specialStoryTimeline', data })
  })).json();
  const pd = new JSDOM(pv.html || '').window.document;
  const pbox = pd.querySelector('section.special-blocks-stl.is-preview');
  t.ok(pv.ok && pbox, 'the editor preview draws it');
  t.ok(pbox && pd.querySelectorAll('.stl-slide:not([hidden])').length === 1 && !pd.querySelector('.stl-nav[hidden]') && !pd.querySelector('.stl-next[hidden]'), 'the preview shows the first slide, the arrows, and the time bar, as visitors first see them');
  t.ok(!/story-timeline\.js/.test(pv.html || ''), 'the preview needs no script');
  const empty = await (await fetch(H.BASE + ed.cfg.previewUrl, {
    method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', Cookie: ed.cookie },
    body: JSON.stringify({ csrf: ed.cfg.csrf, layout: 'specialStoryTimeline', data: {} })
  })).json();
  t.ok(/add events in the sidebar/.test(empty.html || ''), 'an empty one says what to do');
  t.ok(!ed.errs.length, 'no editor errors ' + ed.errs);
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
