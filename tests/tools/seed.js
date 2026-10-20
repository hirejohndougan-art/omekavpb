'use strict';
/**
 * Seeds a fresh Omeka S test install with the fixtures the tests expect:
 * - site "dhs" with three items ("Railroad photo 0/1/2", two images each)
 * - one asset ("Depot banner") and a researcher user for permission tests
 * - for Special Blocks: a second site "legacy", item sets "Railroad" (on dhs)
 *   and "Legacy photos" (on legacy), and three dated items: "Legacy portrait"
 *   (legacy only), "Shared map" (both sites), "Undated letter" (dhs only).
 *   The portrait and map are dated today's month and day, for On this day.
 * - v0.7.0: subjects on those three items (Subjects list), and an oral
 *   history item with a short silent WAV recording and a transcript in
 *   bibo:content (Oral history, Document and transcription).
 * Safe to run more than once. Needs the API key from test-env (see CONTINUE_HERE §7).
 */
const { api, BASE } = require('../lib/harness');
const KEY = process.env.VPB_API_KEY || 'testkey', CRED = process.env.VPB_API_CRED || 'testcred';
const auth = `key_identity=${KEY}&key_credential=${CRED}`;

// A tiny valid PNG (4x3 pixels) in a given color, so no image library is needed.
function png(r, g, b) {
  const zlib = require('zlib');
  const crc = buf => { let c, t = []; for (let n = 0; n < 256; n++) { c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1; t[n] = c >>> 0; } let x = 0xffffffff; for (const v of buf) x = t[(x ^ v) & 0xff] ^ (x >>> 8); return (x ^ 0xffffffff) >>> 0; };
  const chunk = (type, data) => { const len = Buffer.alloc(4); len.writeUInt32BE(data.length); const td = Buffer.concat([Buffer.from(type), data]); const c = Buffer.alloc(4); c.writeUInt32BE(crc(td)); return Buffer.concat([len, td, c]); };
  const W = 4, H = 3, ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(W, 0); ihdr.writeUInt32BE(H, 4); ihdr[8] = 8; ihdr[9] = 2;
  const raw = Buffer.alloc((W * 3 + 1) * H);
  for (let y = 0; y < H; y++) for (let x = 0; x < W; x++) raw.set([r, g, b], y * (W * 3 + 1) + 1 + x * 3);
  return Buffer.concat([Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]), chunk('IHDR', ihdr), chunk('IDAT', zlib.deflateSync(raw)), chunk('IEND', Buffer.alloc(0))]);
}
// A tenth of a second of silence as a WAV file.
function wav() {
  const rate = 8000, n = 800, b = Buffer.alloc(44 + n);
  b.write('RIFF', 0); b.writeUInt32LE(36 + n, 4); b.write('WAVE', 8); b.write('fmt ', 12);
  b.writeUInt32LE(16, 16); b.writeUInt16LE(1, 20); b.writeUInt16LE(1, 22); b.writeUInt32LE(rate, 24);
  b.writeUInt32LE(rate, 28); b.writeUInt16LE(1, 32); b.writeUInt16LE(8, 34); b.write('data', 36); b.writeUInt32LE(n, 40);
  b.fill(128, 44);
  return b;
}
const COLORS = [[180, 60, 40], [40, 110, 160], [60, 140, 80]];
const lit = v => [{ type: 'literal', property_id: 1, '@value': v }];

(async () => {
  let site = (await api('GET', 'sites?slug=dhs'))[0];
  if (!site) site = await api('POST', 'sites', { 'o:title': 'DHS', 'o:slug': 'dhs', 'o:theme': 'default', 'o:is_public': true });
  console.log('site', site['o:id']);

  const items = await api('GET', 'items?per_page=100');
  for (let n = 0; n < 3; n++) {
    const title = 'Railroad photo ' + n;
    if (items.some(i => i['o:title'] === title)) continue;
    const fd = new FormData();
    fd.append('data', JSON.stringify({
      'dcterms:title': lit(title), 'o:is_public': true, 'o:site': [{ 'o:id': site['o:id'] }],
      'o:media': [0, 1].map(k => ({ 'o:ingester': 'upload', file_index: String(k), 'dcterms:title': lit('Scan ' + 'AB'[k] + n) }))
    }));
    fd.append('file[0]', new Blob([png(...COLORS[n])], { type: 'image/png' }), `img${n}a.png`);
    fd.append('file[1]', new Blob([png(...COLORS[(n + 1) % 3])], { type: 'image/png' }), `img${n}b.png`);
    const r = await (await fetch(`${BASE}/api/items?${auth}`, { method: 'POST', body: fd })).json();
    console.log('item', title, r['o:id'] || JSON.stringify(r.errors));
  }

  const assets = await api('GET', 'assets?per_page=100');
  if (!assets.some(a => a['o:name'] === 'Depot banner')) {
    const fd = new FormData();
    fd.append('data', JSON.stringify({ 'o:name': 'Depot banner' }));
    fd.append('file', new Blob([png(40, 110, 160)], { type: 'image/png' }), 'banner.png');
    const r = await (await fetch(`${BASE}/api/assets?${auth}`, { method: 'POST', body: fd })).json();
    console.log('asset', r['o:id'] || JSON.stringify(r.errors));
  }

  const users = await api('GET', 'users?email=researcher@example.com');
  if (!users.length) {
    const u = await api('POST', 'users', { 'o:email': 'researcher@example.com', 'o:name': 'Researcher', 'o:role': 'researcher', 'o:is_active': true });
    console.log('researcher user', u['o:id'] || JSON.stringify(u.errors), '(set its password: see tests/README.md)');
  }
  await seedSpecialBlocks(site);
  console.log('seed done');
})().catch(e => { console.error(e); process.exit(1); });

async function seedSpecialBlocks(dhs) {
  let legacy = (await api('GET', 'sites?slug=legacy'))[0];
  if (!legacy) legacy = await api('POST', 'sites', { 'o:title': 'Legacy Center', 'o:slug': 'legacy', 'o:theme': 'default', 'o:is_public': true });
  const props = {};
  for (const term of ['dcterms:title', 'dcterms:date', 'dcterms:description']) props[term] = (await api('GET', 'properties?term=' + term))[0]['o:id'];
  const val = (term, v) => [{ type: 'literal', property_id: props[term], '@value': v }];
  const sets = await api('GET', 'item_sets?per_page=100');
  const set = async (title) => sets.find(s => s['o:title'] === title) || api('POST', 'item_sets', { 'dcterms:title': val('dcterms:title', title), 'o:is_public': true });
  const rail = await set('Railroad');
  const lp = await set('Legacy photos');
  // Item sets are attached to sites from the site's side.
  await api('PATCH', 'sites/' + dhs['o:id'], { 'o:site_item_set': [{ 'o:item_set': { 'o:id': rail['o:id'] } }] });
  await api('PATCH', 'sites/' + legacy['o:id'], { 'o:site_item_set': [{ 'o:item_set': { 'o:id': lp['o:id'] } }] });

  // Today's month and day in the test site's time zone (Omeka's setting).
  const md = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Chicago', month: '2-digit', day: '2-digit' }).format(new Date()).replace('/', '-');
  const items = await api('GET', 'items?per_page=100');
  const specs = [
    ['Legacy portrait', [legacy], [lp], '1911-' + md],
    ['Shared map', [legacy, dhs], [lp, rail], '1950-' + md],
    ['Undated letter', [dhs], [rail], 'circa 1920s']
  ];
  for (const [title, sites, inSets, date] of specs) {
    const existing = items.find(i => i['o:title'] === title);
    if (existing) {
      // Re-date it to today on each run, so On this day still has matches
      // on a later day. Omeka replaces all values when any are sent, so
      // send the others back too.
      const current = (existing['dcterms:date'] || [])[0];
      if (current && current['@value'] !== date) {
        const values = {};
        Object.keys(existing).filter(k => /^[a-z]+:[A-Za-z]+$/.test(k) && Array.isArray(existing[k])).forEach(k => { values[k] = existing[k]; });
        values['dcterms:date'] = val('dcterms:date', date);
        await api('PATCH', 'items/' + existing['o:id'], values);
      }
      continue;
    }
    const fd = new FormData();
    fd.append('data', JSON.stringify({
      'dcterms:title': val('dcterms:title', title), 'dcterms:date': val('dcterms:date', date),
      'dcterms:description': val('dcterms:description', 'About ' + title + '.'), 'o:is_public': true,
      'o:site': sites.map(s => ({ 'o:id': s['o:id'] })), 'o:item_set': inSets.map(s => ({ 'o:id': s['o:id'] })),
      'o:media': [{ 'o:ingester': 'upload', file_index: '0' }]
    }));
    fd.append('file[0]', new Blob([png(120, 90, 60)], { type: 'image/png' }), 'scan.png');
    const r = await (await fetch(`${BASE}/api/items?${auth}`, { method: 'POST', body: fd })).json();
    console.log('item', title, r['o:id'] || JSON.stringify(r.errors));
  }
  // Subjects (replaced each run, so the counts stay predictable).
  props['dcterms:subject'] = (await api('GET', 'properties?term=dcterms:subject'))[0]['o:id'];
  props['bibo:content'] = (await api('GET', 'properties?term=bibo:content'))[0]['o:id'];
  const fresh = await api('GET', 'items?per_page=100');
  const subjects = { 'Legacy portrait': ['Schools'], 'Shared map': ['Railroad', 'Maps'], 'Undated letter': ['Railroad'] };
  for (const [title, subs] of Object.entries(subjects)) {
    const it = fresh.find(i => i['o:title'] === title);
    if (!it) continue;
    // Omeka replaces all of an item's values when any are sent, so send the
    // existing ones back along with the subjects.
    const full = await api('GET', 'items/' + it['o:id']);
    const values = {};
    Object.keys(full).filter(k => /^[a-z]+:[A-Za-z]+$/.test(k) && Array.isArray(full[k]) && k !== 'dcterms:subject').forEach(k => { values[k] = full[k]; });
    values['dcterms:subject'] = subs.map(v => val('dcterms:subject', v)[0]);
    await api('PATCH', 'items/' + it['o:id'], values);
  }
  if (!fresh.some(i => i['o:title'] === 'Oral history interview')) {
    const fd = new FormData();
    fd.append('data', JSON.stringify({
      'dcterms:title': val('dcterms:title', 'Oral history interview'), 'dcterms:date': val('dcterms:date', '1978-05-02'),
      'bibo:content': val('bibo:content', 'Interviewer: When did you first ride the train?\n\nResident: In 1925, to Galveston.'),
      'o:is_public': true, 'o:site': [{ 'o:id': dhs['o:id'] }],
      'o:media': [{ 'o:ingester': 'upload', file_index: '0' }]
    }));
    fd.append('file[0]', new Blob([wav()], { type: 'audio/wav' }), 'interview.wav');
    const r = await (await fetch(`${BASE}/api/items?${auth}`, { method: 'POST', body: fd })).json();
    console.log('item Oral history interview', r['o:id'] || JSON.stringify(r.errors));
  }
  for (const it of items.filter(i => /^Railroad photo/.test(i['o:title']) && !i['o:item_set'].length)) {
    await api('PATCH', 'items/' + it['o:id'], { 'o:item_set': [{ 'o:id': rail['o:id'] }] });
  }
}
