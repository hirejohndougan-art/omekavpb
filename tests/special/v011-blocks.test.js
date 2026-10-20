'use strict';
// v0.11.0: Who's who, Item table, Milestones, Did you know?, Open now, Embed,
// Share this page, Breadcrumbs, on the public site and in the editor.
const { JSDOM } = require('jsdom');
const H = require('../lib/harness');

(async () => {
  const t = H.suite('special blocks: v0.11.0 blocks');
  const core = await H.fixtures();
  const fx = await H.specialFixtures();
  const site = (await H.api('GET', 'sites?slug=' + H.SITE))[0];
  const originalNav = site['o:navigation'];
  const allDays = { hours_mon: '0:00-23:59', hours_tue: '0:00-23:59', hours_wed: '0:00-23:59', hours_thu: '0:00-23:59', hours_fri: '0:00-23:59', hours_sat: '0:00-23:59', hours_sun: '0:00-23:59' };
  const blocks = [
    ['specialPhotoHotspots', { item_id: core.items[0], alt: 'Class photo', markers: [{ label: 'Mary Jones', note: 'Teacher', x: 20, y: 30 }, { label: 'Tom Smith', x: 70, y: 60 }, { label: '' }] }],
    ['specialItemTable', { site_scope: 'all', item_set_ids: [fx.railroad], col_dcterms_date: true, col_dcterms_subject: true, col_dcterms_identifier: false, heading: 'Railroad items' }],
    ['specialMilestones', { milestones: [{ when: '1896', title: 'The depot opens', text: 'Trains to Galveston.' }, { when: '1900', title: 'The storm', item_id: core.items[1] }, { when: '', title: '' }] }],
    ['specialDidYouKnow', { facts: [{ text: 'The depot had a telegraph.', link_text: 'See it', link_url: '/s/dhs/item' }] }],
    ['specialOpenNow', Object.assign({ show_week: true }, allDays)],
    ['specialOpenNow', { hours_mon: '', closures: [] }],
    ['specialEmbed', { url: 'https://www.youtube.com/watch?v=abc123', title: 'A talk on the depot' }],
    ['specialEmbed', { url: 'https://evil.example/x', title: 'Bad' }],
    ['specialSharePage', { show_x: true, show_print: true }],
    ['specialBreadcrumbs', {}]
  ].map(([l, d]) => ({ 'o:layout': l, 'o:data': d }));
  const pageId = await H.createPage('Newest blocks', blocks);
  const parentId = await H.createPage('Exhibits hub', [{ 'o:layout': 'pageTitle', 'o:data': {} }]);
  try {
    await H.api('PATCH', 'sites/' + site['o:id'], { 'o:navigation': [{ type: 'page', data: { id: parentId, label: 'Exhibits' }, links: [{ type: 'page', data: { id: pageId, label: '' }, links: [] }] }] });
    const page = await H.api('GET', 'site_pages/' + pageId);
    const res = await fetch(`${H.BASE}/s/${H.SITE}/page/${page['o:slug']}`);
    const html = await res.text();
    const d = new JSDOM(html).window.document;
    t.ok(res.status === 200 && !/Fatal error|Warning:|Notice:/.test(html), 'page renders without PHP errors');
    const q = s => d.querySelector(s);

    const who = q('.special-blocks-photo-hotspots');
    t.ok(who && who.querySelectorAll('.special-blocks-hotspot').length === 2 && /left: 20%; top: 30%/.test(who.innerHTML), 'markers placed by percentage; empty marker skipped');
    t.ok(who && /1: Mary Jones/.test(who.querySelector('.special-blocks-hotspot').getAttribute('aria-label')) && /Teacher/.test(who.querySelector('.special-blocks-hotspot-list').textContent), 'markers named for screen readers; numbered list with notes');

    const table = q('.special-blocks-item-table table');
    t.eq(table ? [...table.querySelectorAll('thead th')].map(th => th.textContent) : [], ['Title', 'Date', 'Subject'], 'table columns as chosen');
    t.ok(table && table.querySelectorAll('tbody tr').length >= 4 && /Railroad/.test(table.textContent) && table.querySelector('tbody a[href]'), 'item rows with links');

    const ms = q('.special-blocks-milestones');
    t.ok(ms && ms.querySelectorAll('.special-blocks-milestone').length === 2 && /The depot opens/.test(ms.textContent) && ms.querySelector('img'), 'milestones in order, empty one skipped, with photo');
    t.ok(/The depot had a telegraph\./.test(html) && q('.special-blocks-did-you-know a[href="/s/dhs/item"]'), 'did you know shows the fact and link');

    const open = d.querySelectorAll('.special-blocks-open-now');
    t.ok(open.length === 1 && /Open now/.test(open[0].textContent) && open[0].querySelectorAll('.special-blocks-hours div').length === 7, 'open now (24-hour test hours) with the week listed; block without hours hidden');

    const frames = d.querySelectorAll('.special-blocks-embed iframe');
    t.ok(frames.length === 1 && frames[0].getAttribute('src') === 'https://www.youtube-nocookie.com/embed/abc123' && frames[0].getAttribute('title') === 'A talk on the depot', 'trusted embed shown with its title; untrusted refused');

    const share = q('.special-blocks-share-page');
    t.ok(share && share.querySelector('a[href^="mailto:?subject="]') && share.querySelector('a[href^="https://www.facebook.com/sharer/"]') && share.querySelector('a[href^="https://x.com/intent/post"]') && share.querySelector('.special-blocks-share-copy') && share.querySelector('.special-blocks-share-print'), 'share links and copy/print buttons');
    t.ok(share && new RegExp(encodeURIComponent('/s/dhs/page/' + page['o:slug'])).test(share.innerHTML), 'share links point at this page');

    const crumbs = q('.special-blocks-breadcrumbs');
    t.eq(crumbs ? [...crumbs.querySelectorAll('li')].map(li => li.textContent.replace(/\s*›\s*/g, '').trim()) : [], ['Home', 'Exhibits', 'Newest blocks'], 'breadcrumbs follow the menu');
    t.ok(crumbs && crumbs.querySelector('[aria-current="page"]') && crumbs.getAttribute('aria-label') === 'Breadcrumb', 'breadcrumbs are labeled and mark the current page');

    // ---- 0.11.1: Open now from the site's Visit info, kept current in the browser
    const legacy = (await H.api('GET', 'sites?slug=legacy'))[0];
    const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Chicago' }).format(new Date());
    const settingsUrl = `${H.BASE}/admin/site/s/legacy`;
    const sd = new JSDOM(await (await fetch(settingsUrl, { headers: { Cookie: H.readCookie() } })).text()).window.document;
    const form = sd.getElementById('site-form');
    form.querySelector('[name="vpb_visit_hours"]').value = 'Daily | 0:00-23:59';
    form.querySelector('[name="vpb_visit_closures"]').value = today + ' | Test holiday';
    const body = new URLSearchParams();
    [...form.elements].forEach(el => { if (!el.name || el.disabled || ((el.type === 'checkbox' || el.type === 'radio') && !el.checked)) return; if (el.tagName === 'SELECT' && el.multiple) { [...el.selectedOptions].forEach(o => body.append(el.name, o.value)); return; } body.append(el.name, el.value); });
    await fetch(settingsUrl, { method: 'POST', redirect: 'manual', headers: { Cookie: H.readCookie(), 'Content-Type': 'application/x-www-form-urlencoded' }, body });
    const onId = await H.createPage('Open now from site', [{ 'o:layout': 'specialOpenNow', 'o:data': { source: 'site', source_site: legacy['o:id'], show_week: true } }]);
    const onPage = await H.api('GET', 'site_pages/' + onId);
    const onHtml = await (await fetch(`${H.BASE}/s/${H.SITE}/page/${onPage['o:slug']}`)).text();
    t.ok(/Closed now/.test(onHtml) && /Test holiday/.test(onHtml) && /opens tomorrow at 12 am/.test(onHtml), 'hours and closed dates from Visit info: ' + (onHtml.match(/special-blocks-open-status[^>]*>[\s\S]{0,300}/) || [''])[0].replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').slice(0, 120));
    t.ok(/America\\\/Chicago|America\/Chicago/.test(onHtml.match(/data-hours="[^"]*"/)[0].replace(/&quot;/g, '"')), 'uses Omeka\u2019s time zone setting, not the server\u2019s');
    const live = new JSDOM(onHtml, { runScripts: 'outside-only' });
    const box = live.window.document.querySelector('.special-blocks-open-now');
    box.querySelector('.special-blocks-open-status').textContent = 'stale';
    live.window.eval(require('fs').readFileSync(require('path').join(H.MODULE_DIR, 'asset/special-blocks/js/open-now.js'), 'utf8'));
    await H.sleep(300); // The script waits for the page to finish loading.
    t.ok(/Closed now/.test(box.textContent) && /Test holiday/.test(box.textContent) && /opens tomorrow at 12 am/.test(box.textContent), 'the browser recomputes the status: ' + box.textContent.replace(/\s+/g, ' ').trim().slice(0, 80));
    live.window.close();

    // ---- in the editor
    const ed = await H.openEditor(pageId);
    await H.sleep(3500);
    const labels = [...ed.d.querySelectorAll('.pal-label')].map(x => x.textContent);
    t.ok(!['Logo', 'Site title', 'Site menu', 'Copyright line'].some(l => labels.includes(l)) && labels.includes('Breadcrumbs') && labels.includes('Gallery'), 'page palette leaves out header-only elements');
    t.ok(!ed.d.body.classList.contains('side-collapsed'), 'the sidebar starts open on a page');
    t.ok(!ed.d.body.classList.contains('editing-region'), 'pages keep the normal canvas');
    t.ok(["Who's who in this photo", 'Item table', 'Milestones', 'Did you know?', 'Open now', 'Embed from a trusted site', 'Share this page', 'Breadcrumbs'].every(l => labels.includes(l)), 'all eight in the palette');
    const embedPreview = [...ed.d.querySelectorAll('.blk')].map(b => b.textContent).find(x => /YouTube: A talk on the depot/.test(x));
    t.ok(!!embedPreview && !ed.d.querySelector('#secs iframe'), 'embed shows a placeholder in the editor, no iframe');
    // Click-to-place markers.
    const hu = H.ui(ed);
    hu.select(hu.blk('specialPhotoHotspots'));
    const stageImg = hu.side().querySelector('.hs-stage img');
    // The saved block has three marker rows (one without a name, so the public page skips it).
    t.ok(stageImg && hu.side().querySelectorAll('.hs-dot').length === 3, 'marker tool shows the photo with its markers');
    stageImg.dispatchEvent(new ed.w.MouseEvent('click', { bubbles: true, clientX: 0, clientY: 0 }));
    const placed = hu.blk('specialPhotoHotspots').data.markers;
    t.ok(placed.length === 4 && placed[3].label === 'Marker 4' && typeof placed[3].x === 'number', 'clicking the photo adds a marker there');
    const dot = hu.side().querySelector('.hs-dot');
    dot.dispatchEvent(new ed.w.PointerEvent('pointerdown', { bubbles: true, clientX: 0, clientY: 0 }));
    ed.w.dispatchEvent(new ed.w.PointerEvent('pointermove', { bubbles: true, clientX: 0, clientY: 0 }));
    ed.w.dispatchEvent(new ed.w.PointerEvent('pointerup', { bubbles: true }));
    t.ok(hu.blk('specialPhotoHotspots').data.markers[0].x === 50 && /Unsaved/.test(hu.status()), 'dragging a marker moves it (an undoable change)');
    t.ok(!ed.errs.length, 'no editor errors ' + ed.errs);
  } finally {
    await H.api('PATCH', 'sites/' + site['o:id'], { 'o:navigation': originalNav });
  }
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
