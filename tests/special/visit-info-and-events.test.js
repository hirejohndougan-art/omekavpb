'use strict';
// v0.6.0: Visit info from a site's settings; Upcoming events from a calendar feed.
const { spawn } = require('child_process');
const path = require('path');
const { JSDOM } = require('jsdom');
const H = require('../lib/harness');

const FEED_PORT = 8091;

/** Save a site's Visit info through its real settings form. */
async function saveSiteVisitInfo(slug, values) {
  const url = `${H.BASE}/admin/site/s/${slug}`;
  const cookie = H.readCookie();
  const d = new JSDOM(await (await fetch(url, { headers: { Cookie: cookie } })).text()).window.document;
  const form = d.getElementById('site-form');
  Object.entries(values).forEach(([k, v]) => { form.querySelector(`[name="${k}"]`).value = v; });
  const body = new URLSearchParams();
  [...form.elements].forEach(el => {
    if (!el.name || el.disabled) return;
    if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return;
    if (el.tagName === 'SELECT' && el.multiple) { [...el.selectedOptions].forEach(o => body.append(el.name, o.value)); return; }
    body.append(el.name, el.value);
  });
  const res = await fetch(url, { method: 'POST', redirect: 'manual', headers: { Cookie: cookie, 'Content-Type': 'application/x-www-form-urlencoded' }, body });
  return { status: res.status, hasGroup: !!d.querySelector('[name="vpb_visit_hours"]') };
}

(async () => {
  const t = H.suite('special blocks: visit info and calendar feeds');
  const fx = await H.specialFixtures();

  // ---- Visit info from site settings
  const saved = await saveSiteVisitInfo('legacy', {
    vpb_visit_hours: 'Monday to Friday | 9 am to 3 pm\nSaturday | By appointment',
    vpb_visit_address: '100 Legacy Lane\nDickinson, TX',
    vpb_visit_email: 'legacy@example.org',
    vpb_visit_directions_url: 'javascript:alert(1)',
    vpb_visit_notes: 'Park <b>behind</b> the building.'
  });
  t.ok(saved.hasGroup, 'site settings have a Visit info group');
  t.ok(saved.status === 302, 'site settings saved: ' + saved.status);

  // ---- Calendar feed server (serves tests/fixtures/)
  const server = spawn('python3', ['-m', 'http.server', String(FEED_PORT), '--bind', '127.0.0.1'], { cwd: path.join(__dirname, '..', 'fixtures'), stdio: 'ignore' });
  await H.sleep(800);
  const feedUrl = `http://127.0.0.1:${FEED_PORT}/events.ics?run=${Date.now()}`; // unique, so the hour-long cache starts empty

  const pageId = await H.createPage('special-visit-events', [
    { 'o:layout': 'specialVisitInfo', 'o:data': { source: 'site', source_site: fx.legacy, heading: 'Visit the Legacy Center' } },
    { 'o:layout': 'specialVisitInfo', 'o:data': { heading: 'Typed', hours: [{ days: 'Sunday', times: 'Closed' }] } },
    { 'o:layout': 'specialUpcomingEvents', 'o:data': { feed_url: feedUrl, count: 10, events: [{ title: 'Typed talk', date: '2099-06-20' }] } },
    { 'o:layout': 'specialUpcomingEvents', 'o:data': { heading: 'Broken feed', feed_url: `http://127.0.0.1:${FEED_PORT + 7}/nothing.ics`, events: [{ title: 'Still shown', date: '2099-01-01' }] } }
  ]);
  const page = await H.api('GET', 'site_pages/' + pageId);
  const pub = () => fetch(`${H.BASE}/s/${H.SITE}/page/${page['o:slug']}`).then(r => r.text());
  let html = await pub();
  server.kill();

  const visit = html.slice(html.indexOf('Visit the Legacy Center'), html.indexOf('>Typed<'));
  t.ok(/Monday to Friday/.test(visit) && /9 am to 3 pm/.test(visit) && /By appointment/.test(visit), 'visit info shows the chosen site\u2019s hours');
  t.ok(/100 Legacy Lane<br \/>\s*Dickinson, TX/.test(visit) && /mailto:legacy@example.org/.test(visit), 'address and email from site settings');
  t.ok(!/javascript:/i.test(html) && !/Get directions/.test(visit), 'unsafe directions link dropped');
  t.ok(/Park &lt;b&gt;behind&lt;\/b&gt; the building\./.test(visit), 'notes from site settings are plain text');
  t.ok(/Sunday/.test(html.slice(html.indexOf('>Typed<'))), 'a block with its own details still uses them');

  const events = html.slice(html.indexOf('Upcoming events'), html.indexOf('Broken feed'));
  const order = ['Railroad Days festival', 'Typed talk', 'Evening lecture on the interurban', 'Board meeting'].map(x => events.indexOf(x));
  t.ok(order.every(i => i >= 0) && order.every((v, i) => !i || v > order[i - 1]), 'feed events merged with typed ones, in date order: ' + order);
  t.ok(!/Cancelled tour|Old event/.test(events), 'cancelled and past feed events hidden');
  t.ok(/href="https:\/\/example.org\/railroad-days"/.test(events), 'feed event links kept');
  t.ok(/Still shown/.test(html), 'an unreachable feed doesn\u2019t break the block');

  html = await pub();
  t.ok(/Railroad Days festival/.test(html), 'feed served from the cache when the calendar is unreachable');
  t.ok(!/Fatal error|Warning:|Notice:/.test(html), 'no PHP errors');
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
