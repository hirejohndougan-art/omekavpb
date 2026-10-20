'use strict';
// v1.6.0: Story map: map + timeline + information pane, played in date order.
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');
const H = require('../lib/harness');

(async () => {
  const t = H.suite('special blocks: story map');
  const core = await H.fixtures();
  const pageId = await H.createPage('Story map', [{ 'o:layout': 'specialStoryMap', 'o:data': { heading: 'Dickinson through the years', seconds: '4', stops: [
    { title: 'Main Street paved', date: '1925', lat: '29.46', lng: '-95.05', img: 'asset', asset_id: core.asset, text: 'Paving brings cars downtown.' },
    { title: '', date: '', lat: '29.47', lng: '-95.04', item_id: core.items[0], img: 'item', text: 'The depot photo.', video: 'none' },
    { title: 'The Great Storm', date: '1900-09-08', lat: '29.45', lng: '-95.06', img: 'none', text: 'The hurricane.', video: 'url', video_url: 'https://www.youtube.com/watch?v=abc123' },
    { title: 'No date', date: '', lat: '29.44', lng: '-95.07' },
    { title: 'Bad video', date: '1950', lat: '29.43', lng: '-95.08', video: 'url', video_url: 'https://evil.example/video' }
  ] } }]);
  // The depot item has no Date, so give it one through the stop instead:
  const page = await H.api('GET', 'site_pages/' + pageId);
  const html = await (await fetch(`${H.BASE}/s/${H.SITE}/page/${page['o:slug']}`)).text();
  const d = new JSDOM(html).window.document;
  t.ok(!/Fatal error|Warning:|Notice:/.test(html), 'renders without PHP errors');
  const box = d.querySelector('section.special-blocks-story-map');
  const titles = [...box.querySelectorAll('.special-blocks-story-timeline .special-blocks-story-label')].map(x => x.textContent);
  t.eq(titles, ['The Great Storm', 'Main Street paved', 'Bad video'], 'stops in date order; stops without a date (or whose item has none) are left out: ' + titles.join(' | '));
  const panes = box.querySelectorAll('.special-blocks-story-pane');
  t.ok(panes.length === 3 && panes[1].classList.contains('has-photo') && /background-image/.test(panes[1].getAttribute('style')), 'each stop has a pane; a chosen photo is its background');
  const iframe = panes[0].querySelector('iframe');
  t.ok(iframe && iframe.getAttribute('data-src') === 'https://www.youtube-nocookie.com/embed/abc123' && !iframe.getAttribute('src'), 'YouTube video in privacy mode, loaded only when its stop is shown');
  t.ok(panes[0].querySelector('.special-blocks-story-video-link'), 'a "Watch the video" link for browsers without JavaScript');
  t.ok(!panes[2].querySelector('iframe, video'), 'videos from untrusted sites are refused');
  t.ok(box.querySelector('.special-blocks-story-controls[hidden]'), 'controls stay hidden until the script runs (nothing broken without it)');
  t.ok(/map\/vpb-map\.js/.test(decodeURIComponent(html).replace(/&#x2F;/g, '/')) && /story-map\.js/.test(decodeURIComponent(html).replace(/&#x2F;/g, '/')), 'the module\u2019s own map and the story script load');

  // Run the player (without the map script: the story still works).
  const live = new JSDOM(html, { runScripts: 'outside-only', pretendToBeVisual: true });
  const w = live.window;
  w.matchMedia = () => ({ matches: false });
  const lb = w.document.querySelector('section.special-blocks-story-map');
  lb.setAttribute('data-seconds', '2');
  w.eval(fs.readFileSync(path.join(H.MODULE_DIR, 'asset/special-blocks/js/story-map.js'), 'utf8'));
  await H.sleep(200);
  const shown = () => [...lb.querySelectorAll('.special-blocks-story-pane')].findIndex(p => !p.hidden);
  t.ok(!lb.querySelector('.special-blocks-story-controls').hidden && shown() === 0, 'controls appear; the first stop shows');
  t.ok(lb.querySelectorAll('.special-blocks-story-pane')[0].querySelector('iframe').getAttribute('src'), 'the shown stop\u2019s video loads');
  lb.querySelector('.special-blocks-story-next').click();
  t.ok(shown() === 1 && lb.querySelectorAll('.special-blocks-story-timeline button')[1].getAttribute('aria-current') === 'step', 'Next moves on and marks the timeline');
  t.ok(!lb.querySelectorAll('.special-blocks-story-pane')[0].querySelector('iframe').getAttribute('src'), 'leaving a stop stops its video');
  lb.querySelectorAll('.special-blocks-story-timeline button')[2].click();
  t.ok(shown() === 2, 'clicking a timeline stop jumps there');
  lb.querySelector('.special-blocks-story-rewind').click();
  t.ok(shown() === 0, 'Back to the start');
  lb.querySelector('.special-blocks-story-play').click();
  t.ok(lb.querySelector('.special-blocks-story-play').getAttribute('aria-pressed') === 'true', 'Play starts it');
  await H.sleep(2300);
  t.ok(shown() === 1, 'it moves to the next stop by itself while playing');
  lb.querySelector('.special-blocks-story-play').click();
  t.ok(lb.querySelector('.special-blocks-story-play').getAttribute('aria-pressed') === 'false', 'Pause stops it');
  live.window.close();

  // Editor: palette, click-to-place with a date.
  const ed = await H.openEditor(pageId);
  const u = H.ui(ed);
  t.ok([...ed.d.querySelectorAll('.pal-label')].some(x => x.textContent === 'Story map'), 'in the palette');
  u.select(u.blk('specialStoryMap'));
  t.ok(u.side().querySelector('.pm-tool .pm-map'), 'the settings have the click-to-place map');
  ed.w.VPB.placesMapTool.addPlace(u.blk('specialStoryMap'), 29.5, -95.1);
  const form = u.side().querySelector('.pm-new');
  t.ok(form && /New stop/.test(form.textContent) && form.querySelector('#pm-new-date'), 'a new stop asks for its name and date');
  t.ok(!ed.errs.length, 'no editor errors ' + ed.errs);
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
