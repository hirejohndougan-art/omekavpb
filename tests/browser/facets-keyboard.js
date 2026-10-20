// Keyboard check for the desktop facets (Phase 10). Tabs through the browse page at desktop width
// and reports each facet link that takes focus, and whether it shows a visible focus outline.
// Usage: PLAYWRIGHT_MODULE=/path/to/playwright node tests/browser/facets-keyboard.js
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
(async () => {
  const base = process.env.BASE || 'http://127.0.0.1:8080';
  const site = process.env.SITE || 'dhs';
  const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  await page.goto(base + '/s/' + site + '/item');
  const facetsOn = await page.locator('.vpb-facets a').count();
  console.log('facet links on page:', facetsOn);
  let missing = 0, seen = 0;
  for (let i = 0; i < 200 && seen < facetsOn; i++) {
    await page.keyboard.press('Tab');
    const r = await page.evaluate(() => {
      const a = document.activeElement;
      if (!a || !a.closest('.vpb-facets')) return null;
      const cs = getComputedStyle(a);
      return { text: (a.textContent || '').trim().slice(0, 30), outline: cs.outlineStyle + ' ' + cs.outlineWidth, focusVisible: a.matches(':focus-visible') };
    });
    if (!r) continue;
    seen++;
    const ok = r.focusVisible && r.outline.indexOf('none') !== 0;
    if (!ok) missing++;
    console.log(ok ? 'ok ' : 'NO ', r.text, '|', r.outline);
  }
  console.log('facet links reached by Tab:', seen, 'of', facetsOn, '; without visible focus:', missing, '(want 0)');
  await browser.close();
})().catch(e => { console.error(e.message); process.exit(1); });
