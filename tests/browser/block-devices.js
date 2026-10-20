// Browser check for block settings by device (Phase 5): hiding a block on one device, and its
// padding on tablet and phone. Loads the real public.css and checks the marked blocks at phone,
// tablet and desktop widths. Needs Playwright and
// a Chromium it can launch. Usage:
//   PLAYWRIGHT_MODULE=/path/to/playwright CHROME_PATH=/path/to/headless_shell node tests/browser/block-devices.js
const fs = require('fs');
const path = require('path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const css = fs.readFileSync(path.resolve(__dirname, '../../module/VisualPageBuilder/asset/css/public.css'), 'utf8');
const html = '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>' + css + '</style></head>'
  + '<body><div class="vpb-page page-layout-grid">'
  + '<div class="block vpb-hide-desktop" id="d">D</div><div class="block vpb-hide-tablet" id="t">T</div>'
  + '<div class="block vpb-hide-phone" id="p">P</div><div class="block" id="all">ALL</div>'
  + '<div class="block vpb-has-pad-tablet vpb-has-pad-phone" id="pad" style="--vpb-pad-tablet:40px;--vpb-pad-phone:6px">PAD</div>'
  + '</div></body></html>';
// Expected: each marked block is hidden only on its own device.
const expected = {
  390: { d: true, t: true, p: false, all: true, pad: true },
  768: { d: true, t: false, p: true, all: true, pad: true },
  1280: { d: false, t: true, p: true, all: true, pad: true },
};
// Padding (px) the padded block should have at each width: its own on tablet and phone, else none set.
const expectedPad = { 390: '6px', 768: '40px', 1280: '0px' };
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || undefined, args: ['--no-sandbox'] });
  let failed = 0;
  for (const width of Object.keys(expected)) {
    const page = await browser.newPage({ viewport: { width: Number(width), height: 800 } });
    await page.setContent(html);
    for (const id of Object.keys(expected[width])) {
      if (id === 'pad') continue;
      const shown = await page.locator('#' + id).isVisible();
      if (shown !== expected[width][id]) { failed++; console.log('FAIL', width + 'px', id, 'shown', shown); }
    }
    const padding = await page.locator('#pad').evaluate((el) => getComputedStyle(el).paddingTop);
    if (padding !== expectedPad[width]) { failed++; console.log('FAIL', width + 'px', 'padding', padding, 'expected', expectedPad[width]); }
    await page.close();
  }
  await browser.close();
  console.log(failed ? failed + ' block checks failed' : 'block checks passed');
  process.exit(failed ? 1 : 0);
})();
