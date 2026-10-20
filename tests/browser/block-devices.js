// Browser check for hiding a block on one device (Phase 5). Loads the real public.css and
// checks which marked blocks show at phone, tablet and desktop widths. Needs Playwright and
// a Chromium it can launch. Usage:
//   PLAYWRIGHT_MODULE=/path/to/playwright CHROME_PATH=/path/to/headless_shell node tests/browser/block-devices.js
const fs = require('fs');
const path = require('path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const css = fs.readFileSync(path.resolve(__dirname, '../../module/VisualPageBuilder/asset/css/public.css'), 'utf8');
const html = '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>' + css + '</style></head>'
  + '<body><div class="vpb-page page-layout-grid">'
  + '<div class="block vpb-hide-desktop" id="d">D</div><div class="block vpb-hide-tablet" id="t">T</div>'
  + '<div class="block vpb-hide-phone" id="p">P</div><div class="block" id="all">ALL</div></div></body></html>';
// Expected: each marked block is hidden only on its own device.
const expected = {
  390: { d: true, t: true, p: false, all: true },
  768: { d: true, t: false, p: true, all: true },
  1280: { d: false, t: true, p: true, all: true },
};
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || undefined, args: ['--no-sandbox'] });
  let failed = 0;
  for (const width of Object.keys(expected)) {
    const page = await browser.newPage({ viewport: { width: Number(width), height: 800 } });
    await page.setContent(html);
    for (const id of Object.keys(expected[width])) {
      const shown = await page.locator('#' + id).isVisible();
      if (shown !== expected[width][id]) { failed++; console.log('FAIL', width + 'px', id, 'shown', shown); }
    }
    await page.close();
  }
  await browser.close();
  console.log(failed ? failed + ' block visibility checks failed' : 'block visibility checks passed');
  process.exit(failed ? 1 : 0);
})();
