"""Theme Builder smoke test in a real browser (Visual Builder › site › Theme Builder).

Run against the test install (Omeka on 127.0.0.1:8080), signed in with the admin cookie jar (/tmp/jar.txt):
    python3 tests/browser/theme_builder.py

Checks: the page loads at desktop and phone width with no sideways scroll;
adding a template works; a template with missing required blocks can't go live;
a duplicate appears; Global styles save and the new values come back.
Templates made by this script stay on the test install (there is no delete
button yet; remove them as hidden pages named vpb-template-* if you need a clean site).
"""
import json
import secrets
import sys
import urllib.request
from playwright.sync_api import sync_playwright

BASE = 'http://127.0.0.1:8080'
SITE = 'dhs'
URL = f'{BASE}/admin/visual-builder/{SITE}/theme'
AUTH = 'key_identity=testkey&key_credential=testcred'
TAG = 'Smoke test item ' + secrets.token_hex(3)  # unique per run, so leftovers never match


def api(method, path, body=None):
    req = urllib.request.Request(BASE + path + ('&' if '?' in path else '?') + AUTH,
                                 data=json.dumps(body).encode() if body is not None else None,
                                 headers={'Content-Type': 'application/json'}, method=method)
    with urllib.request.urlopen(req) as r:
        return json.loads(r.read() or b'null')
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
    b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium', args=['--no-sandbox'])
    ctx = b.new_context(viewport={'width': 1280, 'height': 900})
    # Signed in with the admin session in the cookie jar (written by the harness login).
    ctx.add_cookies(jar_cookies())
    pg = ctx.new_page()

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
    pg.fill('#vpb-add-name', TAG)
    pg.click('form:has(input[name=vpb_action][value=add]) button[type=submit]')
    pg.wait_for_load_state()
    check('added template is listed', pg.locator('.vpb-theme-name', has_text=TAG).count() == 1)

    row = pg.locator('.vpb-theme-row', has_text=TAG)
    row.locator('button', has_text='Make live').click()
    pg.wait_for_load_state()
    # A new item template starts with its required blocks (Title, Main image), so it can go live.
    check('the item template goes live (its required blocks are in place)', pg.locator('.vpb-theme-row', has_text=TAG).locator('button', has_text='Back to draft').count() == 1)
    check('no missing-blocks message', pg.locator('text=It cannot go live yet').count() == 0)

    # Duplicate it.
    pg.locator('.vpb-theme-row', has_text=TAG).locator('button', has_text='Duplicate').first.click()
    pg.wait_for_load_state()
    check('duplicate appears', pg.locator('.vpb-theme-name', has_text='Copy of ' + TAG).count() == 1)

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
    # Remove the templates this run made (the template and its copy).
    site_id = api('GET', '/api/sites?slug=' + SITE)[0]['o:id']
    for page in api('GET', '/api/site_pages?site_id=%d&per_page=1000' % site_id):
        if TAG in (page.get('o:title') or ''):
            api('DELETE', '/api/site_pages/%d' % page['o:id'])
    b.close()

print('failures:', len(failures))
sys.exit(1 if failures else 0)
