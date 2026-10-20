// Browser check for the A-Z row on the Filters block (Phase 4). Creates six items with subjects,
// presses M in headless Chromium, checks the list narrows and M is pressed, then deletes them.
// Run by hand: NODE_PATH=<scratchpad>/node_modules node tests/browser/letter-row.js (needs Playwright; not part of npm test).
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8080';
const AUTH = 'key_identity=testkey&key_credential=testcred';
const WORD = 'vpbletter' + Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);
const SUBJECTS = ['Ferry Crossing', 'Mill Works', 'Depot Yard', 'Harbor Pier', 'Quarry Road', 'Ferry Landing'];
async function api(method, path, body) {
  const r = await fetch(`${BASE}/api/${path}?${AUTH}`, { method, headers: { 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : undefined });
  return r.json();
}
(async () => {
  const props = await (await fetch(`${BASE}/api/properties?term=dcterms:subject&${AUTH}`)).json();
  const subj = props[0]['o:id'];
  const ids = [];
  for (const [n, s] of SUBJECTS.entries()) {
    const it = await api('POST', 'items', {
      'o:is_public': true,
      'dcterms:title': [{ property_id: 1, type: 'literal', '@value': `${WORD} item ${n}` }],
      'dcterms:subject': [{ property_id: subj, type: 'literal', '@value': `${s}` }],
    });
    ids.push(it['o:id']);
  }
  for (const id of ids) execSync(`mysql -uroot omeka_test -e "INSERT IGNORE INTO item_site (item_id, site_id) SELECT ${id}, id FROM site WHERE slug='dhs'"`);
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium_headless_shell-1194/chrome-linux/headless_shell' });
  try {
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    await page.goto(`${BASE}/s/dhs/item?fulltext_search=${WORD}`, { waitUntil: 'domcontentloaded' });
    const row = page.locator('.vpb-facet-letters').first();
    console.log('letter row present:', await row.count());
    console.log('letters:', (await row.locator('a').allTextContents()).join(' '));
    await row.locator('a', { hasText: /^M$/ }).click();
    await page.waitForLoadState('domcontentloaded');
    console.log('url:', decodeURIComponent(page.url()));
    console.log('subject values left:', (await page.locator('.vpb-facet-label').allTextContents()).filter(t => /Ferry|Mill|Depot|Harbor|Quarry/.test(t)));
    console.log('pressed:', await page.locator('.vpb-facet-letters a[aria-current="true"]').allTextContents());
  } finally {
    await browser.close();
    for (const id of ids) await fetch(`${BASE}/api/items/${id}?${AUTH}`, { method: 'DELETE' });
  }
})().catch(e => { console.error('ERR', e.message); process.exit(1); });
