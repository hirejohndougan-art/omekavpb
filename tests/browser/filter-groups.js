// Browser check for the Collection, Type, Date and Description filter groups (Phase 4).
// Creates three items under a fresh search word (one in a new item set, one dated and
// not described, one undated), checks the groups and their counts on a live search page,
// presses one choice in each group, then deletes the items and the set.
// Run by hand: NODE_PATH=<scratchpad>/node_modules node tests/browser/filter-groups.js
// (needs Playwright and Chromium; not part of npm test).
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = process.env.VPB_BASE || 'http://127.0.0.1:8080';
const AUTH = 'key_identity=testkey&key_credential=testcred';
const letters = Math.random().toString(36).replace(/[^a-z]/g, '').slice(0, 6);
const WORD = 'vpbgroups' + letters;
let failures = 0;
const check = (name, ok) => { console.log(`${ok ? 'ok  ' : 'FAIL'} ${name}`); if (!ok) failures++; };

async function api(method, path, body) {
  const r = await fetch(`${BASE}/api/${path}?${AUTH}`, { method, headers: { 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : undefined });
  return r.json();
}
const lit = (property_id, v) => [{ property_id, type: 'literal', '@value': v }];

(async () => {
  const termId = async (term) => (await (await fetch(`${BASE}/api/properties?term=${term}&${AUTH}`)).json())[0]['o:id'];
  const title = await termId('dcterms:title'), date = await termId('dcterms:date'), desc = await termId('dcterms:description');
  const set = await api('POST', 'item_sets', { 'dcterms:title': lit(title, `${WORD} collection`), 'o:is_public': true });
  const cls = (await (await fetch(`${BASE}/api/resource_classes?term=dcterms:BibliographicResource&${AUTH}`)).json())[0]['o:id'];
  const make = (n, extra) => api('POST', 'items', Object.assign({ 'o:is_public': true, 'o:resource_class': { 'o:id': cls }, 'dcterms:title': lit(title, `${WORD} item ${n}`) }, extra));
  const a = await make(0, { 'o:item_set': [{ 'o:id': set['o:id'] }], 'dcterms:date': lit(date, '1923'), 'dcterms:description': lit(desc, 'A described photograph') });
  const b = await make(1, { 'dcterms:date': lit(date, '1987') });
  const c = await make(2, {});
  const ids = [a['o:id'], b['o:id'], c['o:id']];
  for (const id of ids) execSync(`mysql -uroot omeka_test -e "INSERT IGNORE INTO item_site (item_id, site_id) SELECT ${id}, id FROM site WHERE slug='dhs'"`);

  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium_headless_shell-1194/chrome-linux/headless_shell' });
  try {
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    await page.goto(`${BASE}/s/dhs/item?fulltext_search=${WORD}`, { waitUntil: 'domcontentloaded' });
    const legends = await page.locator('.vpb-facet legend').allTextContents();
    check('Collection group is offered', legends.includes('Collection'));
    check('Type group is offered', legends.includes('Type'));
    check('Date group is offered', legends.includes('Date'));
    check('Description group is offered', legends.includes('Description'));
    check('the Description choice counts one item', (await page.locator('.vpb-facet', { hasText: 'Description' }).locator('li', { hasText: 'Has a description' }).locator('.vpb-facet-count').textContent()).trim() === '1');
    check('the Collection choice names the new set', await page.locator('.vpb-facet', { hasText: 'Collection' }).locator('.vpb-facet-label', { hasText: `${WORD} collection` }).count() === 1);
    check('the Date group offers a decade bar', await page.locator('.vpb-facet', { hasText: 'Date' }).locator('.vpb-facet-bar').count() >= 1);

    // Type: pressing a class shows it under Filtered by (its label is looked up, not its title).
    await page.locator('.vpb-facet', { hasText: 'Type' }).locator('li a').first().click();
    await page.waitForLoadState('domcontentloaded');
    check('pressing a Type choice lists it as in use', await page.locator('.vpb-facets-active li').count() === 1);
    check('the Type filter shows its label, not blank', ((await page.locator('.vpb-facets-active li a').first().textContent()) || '').replace(/\s*×\s*$/, '').trim().length > 0);

    await page.goto(`${BASE}/s/dhs/item?fulltext_search=${WORD}`, { waitUntil: 'domcontentloaded' });
    await page.locator('.vpb-facet', { hasText: 'Description' }).locator('li', { hasText: 'Has a description' }).locator('a').click();
    await page.waitForLoadState('domcontentloaded');
    check('pressing Has a description leaves one item', (await page.locator('.vpb-results-count, .vpb-results-heading').first().textContent().catch(() => '')).includes('1 item'));
    check('the filter is listed as in use', await page.locator('.vpb-facets-active').count() === 1);
  } finally {
    await browser.close();
    for (const id of ids) await fetch(`${BASE}/api/items/${id}?${AUTH}`, { method: 'DELETE' });
    await fetch(`${BASE}/api/item_sets/${set['o:id']}?${AUTH}`, { method: 'DELETE' });
  }
  console.log(failures ? `filter-groups FAILED (${failures})` : 'filter-groups checks passed');
  process.exit(failures ? 1 : 0);
})().catch(e => { console.error('ERR', e.message); process.exit(1); });
