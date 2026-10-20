// Browser check for the phone Filters panel (Phase 4). Needs Playwright and a Chromium it can
// launch. Usage (on the test install, with the site live):
//   PLAYWRIGHT_MODULE=/path/to/playwright CHROME_PATH=/path/to/headless_shell node tests/browser/phone-filters.js
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || undefined, args: ['--no-sandbox'] });
  const page = await browser.newPage({ viewport: { width: 390, height: 800 } });
  await page.goto('' + (process.env.BASE || 'http://127.0.0.1:8080') + '/s/' + (process.env.SITE || 'dhs') + '/item');
  const toggle = page.locator('.vpb-filters-toggle');
  const panel = page.locator('.vpb-facets');
  console.log('toggle visible on phone:', await toggle.isVisible());
  console.log('panel visible before:', await panel.isVisible());
  await toggle.click();
  console.log('panel visible after open:', await panel.isVisible(), 'expanded:', await toggle.getAttribute('aria-expanded'));
  await page.locator('.vpb-facets-done').click();
  console.log('panel visible after done:', await panel.isVisible(), 'expanded:', await toggle.getAttribute('aria-expanded'));
  await toggle.click();
  await page.keyboard.press('Escape');
  console.log('panel visible after Escape:', await panel.isVisible());
  const desk = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  await desk.goto('' + (process.env.BASE || 'http://127.0.0.1:8080') + '/s/' + (process.env.SITE || 'dhs') + '/item');
  console.log('desktop: toggle visible', await desk.locator('.vpb-filters-toggle').isVisible(), 'panel visible', await desk.locator('.vpb-facets').isVisible());
  await browser.close();
})().catch(e => { console.error(e.message); process.exit(1); });
