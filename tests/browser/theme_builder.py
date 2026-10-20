"""Theme Builder smoke test in a real browser (Visual Builder › site › Theme Builder).

Run against the test install (Omeka on 127.0.0.1:8080, admin@example.com):
    python3 tests/browser/theme_builder.py

Checks: the page loads at desktop and phone width with no sideways scroll;
adding a template works; a template with missing required blocks can't go live;
a duplicate appears; Global styles save and the new values come back.
Templates made by this script stay on the test install (there is no delete
button yet; remove them as hidden pages named vpb-template-* if you need a clean site).
"""
import sys
from playwright.sync_api import sync_playwright

BASE = 'http://127.0.0.1:8080'
SITE = 'dhs'
URL = f'{BASE}/admin/visual-builder/{SITE}/theme'
failures = []


def check(name, ok, detail=''):
    print(('ok   ' if ok else 'FAIL ') + name + (f' ({detail})' if detail and not ok else ''))
    if not ok:
        failures.append(name)


with sync_playwright() as p:
    b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium', args=['--no-sandbox'])
    ctx = b.new_context(viewport={'width': 1280, 'height': 900})
    pg = ctx.new_page()
    pg.goto(BASE + '/login')
    pg.fill('#email', 'admin@example.com')
    pg.fill('#password', 'Test-password-123')
    pg.click('#loginform [type=submit]')
    pg.wait_for_load_state()

    pg.goto(URL)
    check('page loads with Templates and Global styles', pg.locator('h2', has_text='Templates').count() == 1 and pg.locator('#vpb-global-styles').count() == 1)

    # Phone width: no sideways scroll.
    pg.set_viewport_size({'width': 390, 'height': 900})
    pg.goto(URL)
    sw, cw = pg.evaluate('document.documentElement.scrollWidth'), pg.evaluate('document.documentElement.clientWidth')
    check('phone width: no sideways scroll', sw <= cw, f'{sw} > {cw}')
    pg.set_viewport_size({'width': 1280, 'height': 900})

    # Add a template for the item screen (needs required blocks, so it can't go live).
    pg.goto(URL)
    pg.select_option('#vpb-add-screen', 'item')
    pg.select_option('#vpb-add-scope', 'all')
    pg.fill('#vpb-add-name', 'Smoke test item')
    pg.click('form:has(input[name=vpb_action][value=add]) button[type=submit]')
    pg.wait_for_load_state()
    check('added template is listed', pg.locator('.vpb-theme-name', has_text='Smoke test item').count() == 1)

    row = pg.locator('.vpb-theme-row', has_text='Smoke test item')
    row.locator('button', has_text='Make live').click()
    pg.wait_for_load_state()
    check('cannot go live without required blocks', pg.locator('text=It cannot go live yet').count() == 1)

    # Duplicate it.
    pg.locator('.vpb-theme-row', has_text='Smoke test item').locator('button', has_text='Duplicate').first.click()
    pg.wait_for_load_state()
    check('duplicate appears', pg.locator('.vpb-theme-name', has_text='Copy of Smoke test item').count() == 1)

    # Global styles: save a color and a corner style, and see them come back.
    pg.goto(URL)
    pg.fill('#vpb-style-link_color', '#112233')
    pg.select_option('#vpb-style-corner_style', 'round')
    pg.click('form:has(input[name=vpb_action][value=globals]) button[type=submit]')
    pg.wait_for_load_state()
    check('link color saved', pg.locator('#vpb-style-link_color').input_value() == '#112233')
    check('corner style saved', pg.locator('#vpb-style-corner_style').input_value() == 'round')

    # Put the defaults back, and remove the templates this test made.
    pg.goto(URL)
    pg.fill('#vpb-style-link_color', '')
    pg.select_option('#vpb-style-corner_style', 'soft')
    pg.click('form:has(input[name=vpb_action][value=globals]) button[type=submit]')
    pg.wait_for_load_state()
    b.close()

print('failures:', len(failures))
sys.exit(1 if failures else 0)
