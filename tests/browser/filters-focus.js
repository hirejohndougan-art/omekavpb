// Focus check for the phone Filters panel (Phase 10). While the full-screen panel is open,
// Tab must stay inside it, and Escape must return focus to the Filters button.
// Usage: PLAYWRIGHT_MODULE=/path/to/playwright node tests/browser/filters-focus.js
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
(async () => {
  const base = process.env.BASE || 'http://127.0.0.1:8080';
  const site = process.env.SITE || 'dhs';
  const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const page = await browser.newPage({ viewport: { width: 390, height: 800 } });
  await page.goto(base + '/s/' + site + '/item');
  const toggle = page.locator('.vpb-filters-toggle');
  if (!(await toggle.count())) { console.log('no Filters toggle on this page; nothing to check'); await browser.close(); return; }
  await toggle.click();
  const inPanel = () => page.evaluate(() => {
    const p = document.querySelector('.vpb-facets.is-open');
    return !!(p && p.contains(document.activeElement));
  });
  let escaped = 0;
  for (let i = 0; i < 40; i++) {
    await page.keyboard.press('Tab');
    if (!(await inPanel())) escaped++;
  }
  for (let i = 0; i < 40; i++) {
    await page.keyboard.press('Shift+Tab');
    if (!(await inPanel())) escaped++;
  }
  console.log('Tab and Shift+Tab presses that left the open panel:', escaped, '(want 0)');
  await page.keyboard.press('Escape');
  const back = await page.evaluate(() => document.activeElement && document.activeElement.classList.contains('vpb-filters-toggle'));
  console.log('focus back on Filters button after Escape:', back);
  const inert = await page.evaluate(() => document.querySelectorAll('[inert]').length);
  console.log('inert elements left after close (want 0):', inert);
  await browser.close();
})().catch(e => { console.error(e.message); process.exit(1); });
