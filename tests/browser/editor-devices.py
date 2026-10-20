"""Editor device preview, Phase 5, in a real browser (test install, admin session in /tmp/jar.txt).

Checks: Desktop, Tablet and Phone set the page width (960, 720, 375 px) and the pressed
button; a block hidden on Phone is dimmed only while Phone is previewed. Nothing is saved:
the hide toggle is switched back before the page is left.

    python3 tests/browser/editor-devices.py [page id]   (default: the Browse items template, 13)
"""
import json
import sys
from playwright.sync_api import sync_playwright

BASE = 'http://127.0.0.1:8080'
PAGE = sys.argv[1] if len(sys.argv) > 1 else '13'
URL = f'{BASE}/admin/visual-page-builder/dhs/{PAGE}'
failures = []


def check(name, ok, detail=''):
    print(('ok   ' if ok else 'FAIL ') + name + (f' ({detail})' if detail and not ok else ''))
    if not ok:
        failures.append(name)


def jar_cookies():
    out = []
    for line in open('/tmp/jar.txt'):
        if not line.strip() or (line.startswith('#') and not line.startswith('#HttpOnly_')):
            continue
        p = line.rstrip('\n').split('\t')
        if len(p) >= 7:
            out.append({'name': p[5], 'value': p[6], 'domain': '127.0.0.1', 'path': '/'})
    return out


with sync_playwright() as p:
    b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium_headless_shell-1194/chrome-linux/headless_shell', args=['--no-sandbox'])
    ctx = b.new_context(viewport={'width': 1280, 'height': 900})
    ctx.add_cookies(jar_cookies())
    pg = ctx.new_page()
    errors = []
    pg.on('pageerror', lambda e: errors.append(str(e)))
    pg.goto(URL)
    pg.wait_for_selector('.seg button[data-dev]')

    widths = {'desktop': 960, 'tablet': 720, 'phone': 375}
    for dev in ['tablet', 'phone', 'desktop']:
        pg.click(f'.seg button[data-dev="{dev}"]')
        width = pg.evaluate("parseFloat(document.getElementById('page').style.width)")
        pressed = pg.get_attribute(f'.seg button[data-dev="{dev}"]', 'aria-pressed')
        check(f'{dev}: the page is {widths[dev]} px wide', width == widths[dev], str(width))
        check(f'{dev}: its button is pressed', pressed == 'true', str(pressed))

    blocks = pg.locator('.blk')
    check('the template has blocks to preview', blocks.count() >= 1, str(blocks.count()))
    if blocks.count() >= 1:
        first = blocks.first
        first.click()
        # The Phone hide toggle sits in the block's tools.
        toggle = pg.locator('.blk .tools .dev-hide', has_text='Phone').first
        was = toggle.get_attribute('aria-pressed')
        pg.click(f'.seg button[data-dev="phone"]')
        before_hidden = pg.evaluate("document.querySelectorAll('.blk.is-hidden-dev').length")
        toggle.click()
        pg.wait_for_timeout(100)
        pg.click('.seg button[data-dev="phone"]')
        dimmed_on_phone = pg.evaluate("document.querySelectorAll('.blk.is-hidden-dev').length")
        check('a block hidden on Phone is dimmed while Phone is previewed', dimmed_on_phone > before_hidden, f'{before_hidden} -> {dimmed_on_phone}')
        pg.click('.seg button[data-dev="desktop"]')
        dimmed_on_desktop = pg.evaluate("document.querySelectorAll('.blk.is-hidden-dev').length")
        check('it is not dimmed on Desktop', dimmed_on_desktop == before_hidden, f'{dimmed_on_desktop} vs {before_hidden}')
        # Switch the toggle back, so nothing is changed.
        pg.locator('.blk .tools .dev-hide', has_text='Phone').first.click()
        check('the Phone toggle is back as it was', pg.locator('.blk .tools .dev-hide', has_text='Phone').first.get_attribute('aria-pressed') == was)

    check('no script errors', not errors, '; '.join(errors)[:300])
    b.close()

print('editor device checks ' + ('passed' if not failures else 'FAILED'))
sys.exit(1 if failures else 0)
