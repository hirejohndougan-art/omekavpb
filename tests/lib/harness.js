'use strict';
/**
 * Shared test harness for the Visual Page Builder editor.
 *
 * Loads the real editor page from a running Omeka S (so the server-built config
 * is used), then runs the editor's own script files from this repo inside jsdom,
 * in the order the page lists them. Network calls from the editor go to the live
 * server with the logged-in admin session.
 *
 * Environment:
 *   VPB_BASE    Omeka base URL (default http://127.0.0.1:8080)
 *   VPB_JAR     curl cookie jar from test-env/login.sh (default /tmp/jar.txt)
 *   VPB_API_KEY / VPB_API_CRED   API key (default testkey / testcred)
 *   VPB_SITE    site slug (default dhs)
 */
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const BASE = process.env.VPB_BASE || 'http://127.0.0.1:8080';
const JAR = process.env.VPB_JAR || '/tmp/jar.txt';
const SITE = process.env.VPB_SITE || 'dhs';
const MODULE_DIR = path.resolve(__dirname, '../../module/VisualPageBuilder');
const KEY = process.env.VPB_API_KEY || 'testkey';
const CRED = process.env.VPB_API_CRED || 'testcred';

function readCookie(jar = JAR) {
  return fs.readFileSync(jar, 'utf8').split('\n')
    .filter(l => l && (!l.startsWith('#') || l.startsWith('#HttpOnly_')))
    .map(l => l.split('\t')).filter(p => p.length >= 7)
    .map(p => p[5] + '=' + p[6]).join('; ');
}

const sleep = ms => new Promise(r => setTimeout(r, ms));

function api(method, p, body) {
  const url = BASE + '/api/' + p + (p.includes('?') ? '&' : '?') + `key_identity=${KEY}&key_credential=${CRED}`;
  const opts = { method, headers: {} };
  if (body !== undefined) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
  return fetch(url, opts).then(r => r.json());
}

function editorUrl(pageId) { return `${BASE}/admin/visual-page-builder/${SITE}/${pageId}`; }

/** The editor's script files, in page order, as local paths in this repo. */
function scriptFiles(html) {
  const out = [];
  const re = /<script src="([^"]+)"><\/script>/g;
  let m;
  while ((m = re.exec(html))) {
    const src = m[1].replace(/&#x2F;/g, '/').replace(/&amp;/g, '&').split('?')[0];
    const i = src.indexOf('/VisualPageBuilder/asset/');
    if (i < 0) continue;
    out.push(path.join(MODULE_DIR, src.slice(i + '/VisualPageBuilder/'.length)));
  }
  return out;
}

/**
 * Open the editor for a page. Returns { w, d, V, errs, cfg, files }.
 * `html` may be passed to reuse an already-fetched editor page.
 */
async function openEditor(pageId, { cookie = readCookie(), html, query = '' } = {}) {
  if (!html) html = await (await fetch(editorUrl(pageId) + query, { headers: { Cookie: cookie } })).text();
  const files = scriptFiles(html);
  if (!files.length) throw new Error('No editor scripts found in the editor page. Is the module installed and the page id right?');
  const dom = new JSDOM(html.replace(/<script src="[^"]*VisualPageBuilder[^"]*"><\/script>/g, ''), {
    url: editorUrl(pageId), runScripts: 'outside-only', pretendToBeVisual: true,
    beforeParse(w) { w.matchMedia = () => ({ matches: false }); }
  });
  const w = dom.window, d = w.document, errs = [];
  w.addEventListener('error', e => errs.push(e.message));
  w.fetch = (u, o = {}) => fetch(new URL(u, BASE).href, Object.assign({}, o, { headers: Object.assign({}, o.headers, { Cookie: cookie }) }))
    .then(async r => { const t = await r.text(); return { ok: r.ok, status: r.status, json: async () => JSON.parse(t) }; });
  w.confirm = () => true;
  for (const f of files) {
    try { w.eval(fs.readFileSync(f, 'utf8') + '\n//# sourceURL=' + f); } catch (e) { errs.push(path.basename(f) + ': ' + e.message); }
  }
  const cfg = JSON.parse(d.getElementById('vpb-config').textContent);
  return { w, d, V: w.VisualPageBuilder, errs, cfg, files, html, cookie };
}

/** Tiny assertion collector. */
function suite(name) {
  let pass = 0, fail = 0;
  return {
    ok(cond, msg) { if (cond) pass++; else { fail++; console.log(`  FAIL [${name}] ${msg}`); } },
    eq(a, b, msg) { const A = JSON.stringify(a), B = JSON.stringify(b); this.ok(A === B, `${msg}\n      got:      ${A}\n      expected: ${B}`); },
    done() { console.log(`${fail ? 'FAIL' : 'ok  '} ${name}: ${pass} passed, ${fail} failed`); process.exitCode = fail ? 1 : (process.exitCode || 0); return !fail; },
    get failed() { return fail; }
  };
}

/** Post blocks straight to the save endpoint (bypassing the UI). */
function saveBlocks(ed, blocks, csrf) {
  return fetch(BASE + ed.cfg.saveUrl, {
    method: 'POST', headers: { 'Content-Type': 'application/json', Cookie: ed.cookie },
    body: JSON.stringify({ csrf: csrf === undefined ? ed.cfg.csrf : csrf, blocks })
  }).then(async r => ({ status: r.status, body: await r.json() }));
}

/** Tiny UI helpers bound to an editor instance. */
function ui(ed) {
  const { w, d, V } = ed;
  const side = () => d.getElementById('propsView');
  const blocks = () => V.state.sections.flatMap(s => s.cols.flatMap(c => c.blocks));
  return {
    side, blocks,
    blk: layout => blocks().find(b => b.layout === layout),
    el: b => d.querySelector('[data-id="' + b.id + '"]'),
    press(target) { target.dispatchEvent(new w.PointerEvent('pointerdown', { bubbles: true })); },
    select(b) { this.press(this.el(b)); },
    setVal(sel, v, ev = 'change') { const e = side().querySelector(sel); if (!e) throw new Error('missing ' + sel); e.value = v; e.dispatchEvent(new w.Event(ev, { bubbles: true })); },
    seg(label, text) { const g = [...side().querySelectorAll('.segw')].find(x => x.getAttribute('aria-label') === label); [...g.querySelectorAll('button')].find(b => b.textContent === text).click(); },
    check(id, on) { const c = side().querySelector('#' + id); if (c.checked !== on) { c.checked = on; c.dispatchEvent(new w.Event('change', { bubbles: true })); } },
    button(text, root = side()) { return [...root.querySelectorAll('button')].find(b => b.textContent === text); },
    status: () => d.getElementById('status').textContent,
    toast: () => d.getElementById('toast').textContent
  };
}

/**
 * Read or change an Omeka setting directly in the test database (the API
 * can't change global settings). Values are Omeka's JSON-encoded strings.
 */
function setting(id) {
  const out = require('child_process').execSync(`mysql -uroot ${process.env.VPB_DB || 'omeka'} -N -e "SELECT value FROM setting WHERE id='${id.replace(/[^a-z_]/g, '')}'"`).toString().trim();
  return out;
}
function setSetting(id, value) {
  const v = typeof value === 'string' && value.startsWith('"') ? value : JSON.stringify(String(value));
  require('child_process').execSync(`mysql -uroot ${process.env.VPB_DB || 'omeka'} -e "INSERT INTO setting (id, value) VALUES ('${id.replace(/[^a-z_]/g, '')}', '${v.replace(/'/g, '')}') ON DUPLICATE KEY UPDATE value=VALUES(value)"`);
}

module.exports = {
  setting, setSetting, BASE, SITE, MODULE_DIR, readCookie, sleep, api, editorUrl, openEditor, suite, saveBlocks, ui };

/** Create a page in the test site with a unique slug. Returns its id. */
async function createPage(slugBase, blocks, extra = {}) {
  const site = (await api('GET', 'sites?slug=' + SITE))[0];
  if (!site) throw new Error(`Site "${SITE}" not found. Run tools/seed.js first.`);
  // The first argument is the page title; the slug is made from it.
  const slug = slugBase.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') + '-' + Date.now().toString(36) + Math.floor(Math.random() * 1e4);
  const page = await api('POST', 'site_pages', Object.assign({
    'o:site': { 'o:id': site['o:id'] }, 'o:title': slugBase, 'o:slug': slug, 'o:is_public': true, 'o:block': blocks
  }, extra));
  if (!page['o:id']) throw new Error('Could not create page: ' + JSON.stringify(page.errors || page));
  return page['o:id'];
}

/** Ids of the seeded fixture resources (see tools/seed.js). */
async function fixtures() {
  const items = await api('GET', 'items?sort_by=id&sort_order=asc&per_page=50');
  const byTitle = t => items.find(i => i['o:title'] === t);
  const assets = await api('GET', 'assets?per_page=50');
  const need = ['Railroad photo 0', 'Railroad photo 1', 'Railroad photo 2'].map(byTitle);
  const asset = assets.find(a => a['o:name'] === 'Depot banner');
  if (need.some(x => !x) || !asset) throw new Error('Fixtures missing. Run: node tests/tools/seed.js');
  return {
    items: need.map(i => i['o:id']),
    media: need.map(i => i['o:media'].map(m => m['o:id'])),
    asset: asset['o:id']
  };
}

/** Log in as another user; returns a Cookie header value. */
async function login(email, password) {
  let jar = {};
  const take = r => (r.headers.getSetCookie ? r.headers.getSetCookie() : []).forEach(c => { const [kv] = c.split(';'); const i = kv.indexOf('='); jar[kv.slice(0, i)] = kv.slice(i + 1); });
  const hdr = () => Object.entries(jar).map(([k, v]) => k + '=' + v).join('; ');
  let r = await fetch(BASE + '/login', { redirect: 'manual' }); take(r);
  const csrf = ((await r.text()).match(/name="loginform_csrf" value="([^"]*)"/) || [])[1];
  r = await fetch(BASE + '/login', {
    method: 'POST', redirect: 'manual', headers: { 'Content-Type': 'application/x-www-form-urlencoded', Cookie: hdr() },
    body: new URLSearchParams({ email, password, loginform_csrf: csrf, submit: 'Log in' })
  });
  take(r);
  return hdr();
}

/** Ids of the Special Blocks fixtures (see tools/seed.js). */
async function specialFixtures() {
  const site = async (slug) => (await api('GET', 'sites?slug=' + slug))[0];
  const items = await api('GET', 'items?per_page=100');
  const sets = await api('GET', 'item_sets?per_page=100');
  const itemId = (t) => (items.find(i => i['o:title'] === t) || {})['o:id'];
  const setId = (t) => (sets.find(i => i['o:title'] === t) || {})['o:id'];
  const out = {
    dhs: (await site('dhs'))['o:id'], legacy: ((await site('legacy')) || {})['o:id'],
    railroad: setId('Railroad'), legacyPhotos: setId('Legacy photos'),
    portrait: itemId('Legacy portrait'), map: itemId('Shared map'), letter: itemId('Undated letter')
  };
  if (Object.values(out).some(v => !v)) throw new Error('Special Blocks fixtures missing. Run: npm run seed');
  return out;
}

module.exports.createPage = createPage;
module.exports.specialFixtures = specialFixtures;
module.exports.fixtures = fixtures;
module.exports.login = login;
