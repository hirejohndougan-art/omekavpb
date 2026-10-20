"""Theme Builder, Phase 1 checks in a real browser: the Global styles live preview,
and deleting a template from the page (with its confirm step).

Run against the test install (Omeka on 127.0.0.1:8080), signed in with the admin
session in the cookie jar (/tmp/jar.txt, written by the harness login):
    python3 tests/browser/theme_phase1.py

It adds a template named "Delete check" and deletes it again; nothing else changes.
Global styles are put back to the values they had before the run.
"""
import json
import sys
import urllib.request
from playwright.sync_api import sync_playwright

BASE = 'http://127.0.0.1:8080'
URL = f'{BASE}/admin/visual-builder/dhs/theme'
JAR = '/tmp/jar.txt'
failures = []


def check(name, ok, detail=''):
    print(('ok   ' if ok else 'FAIL ') + name + (f' ({detail})' if detail and not ok else ''))
    if not ok:
        failures.append(name)


AUTH = 'key_identity=testkey&key_credential=testcred'


def api(method, path, body=None):
    req = urllib.request.Request(BASE + path + ('&' if '?' in path else '?') + AUTH,
                                 data=json.dumps(body).encode() if body is not None else None,
                                 headers={'Content-Type': 'application/json'}, method=method)
    with urllib.request.urlopen(req) as r:
        return json.loads(r.read() or b'null')


def jar_cookies():
    out = []
    for line in open(JAR):
        if not line.strip() or (line.startswith('#') and not line.startswith('#HttpOnly_')):
            continue
        p = line.rstrip('\n').split('\t')
        if len(p) >= 7:
            host = p[0].replace('#HttpOnly_', '')
            out.append({'name': p[5], 'value': p[6], 'domain': host, 'path': p[2]})
    return out


with sync_playwright() as p:
    b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium', args=['--no-sandbox'])
    ctx = b.new_context(viewport={'width': 1280, 'height': 900})
    ctx.add_cookies(jar_cookies())
    pg = ctx.new_page()
    dialogs = []
    pg.on('dialog', lambda d: (dialogs.append(d.message), d.accept()))

    pg.goto(URL)
    check('page loads for the signed-in admin', pg.locator('#vpb-global-styles').count() == 1, pg.url)
    check('the preview is shown beside the form', pg.locator('#vpb-style-preview').count() == 1)

    # Live preview: changing the primary color changes the preview's custom property at once.
    before = pg.evaluate("getComputedStyle(document.getElementById('vpb-style-preview')).getPropertyValue('--pb-accent').trim()")
    pg.evaluate("""() => { const el = document.getElementById('vpb-style-accent_color');
        el.value = '#8a2f1d'; el.dispatchEvent(new Event('input', { bubbles: true })); }""")
    after = pg.evaluate("document.getElementById('vpb-style-preview').style.getPropertyValue('--pb-accent')")
    check('changing the primary color updates the preview', after == '#8a2f1d', f'{before} -> {after}')

    # The preview's button takes the new primary color.
    btn_bg = pg.evaluate("getComputedStyle(document.querySelector('.vpb-style-preview-btn')).backgroundColor")
    check('the preview button uses the primary color', btn_bg == 'rgb(138, 47, 29)', btn_bg)

    # The link color is a text box: a bad value falls back to the primary color in the preview.
    pg.evaluate("""() => { const el = document.getElementById('vpb-style-link_color');
        el.value = 'nope'; el.dispatchEvent(new Event('input', { bubbles: true })); }""")
    link = pg.evaluate("document.getElementById('vpb-style-preview').style.getPropertyValue('--pb-link')")
    check('an invalid link color falls back to the primary color', link == '#8a2f1d', link)

    # Put the form back to what it was (reload discards unsaved changes).
    pg.goto(URL)

    # Phone width: the preview stacks under the form, no sideways scroll.
    pg.set_viewport_size({'width': 390, 'height': 900})
    pg.goto(URL)
    sw, cw = pg.evaluate('document.documentElement.scrollWidth'), pg.evaluate('document.documentElement.clientWidth')
    check('phone width: no sideways scroll', sw <= cw, f'{sw} > {cw}')
    pg.set_viewport_size({'width': 1280, 'height': 900})

    # The header shows in the hub as the current design, with an Edit link.
    # A temporary header page is made for the check and removed afterward.
    site_id = api('GET', '/api/sites?slug=dhs')[0]['o:id']
    header = api('POST', '/api/site_pages', {'o:site': {'o:id': site_id}, 'o:title': 'Site header (check)', 'o:slug': 'vpb-site-header', 'o:is_public': True})
    try:
        pg.goto(URL)
        cur = pg.locator('.vpb-theme-row', has_text='Current design')
        check('the header shows as the current design', cur.count() == 1)
        check('the current design has an Edit link', cur.locator('a', has_text='Edit').count() == 1)
        check('the header is not offered Delete or Duplicate', cur.locator('button', has_text='Delete').count() == 0 and cur.locator('button', has_text='Duplicate').count() == 0)
    finally:
        api('DELETE', '/api/site_pages/%d' % header['o:id'])

    # Delete a template from the page: add one, delete it, check it is gone.
    pg.goto(URL)
    pg.select_option('#vpb-add-screen', 'not-found')
    pg.select_option('#vpb-add-scope', 'all')
    pg.fill('#vpb-add-name', 'Delete check')
    pg.click('form:has(input[name=vpb_action][value=add]) button[type=submit]')
    pg.wait_for_load_state()
    row = pg.locator('.vpb-theme-row', has_text='Delete check')
    check('the test template is listed', row.count() == 1)
    dialogs.clear()
    row.locator('button', has_text='Delete').click()
    pg.wait_for_load_state()
    check('Delete asks first', len(dialogs) == 1 and 'Delete check' in dialogs[0], str(dialogs))
    check('the template is gone after Delete', pg.locator('.vpb-theme-row', has_text='Delete check').count() == 0)
    check('a success message is shown', pg.locator('text=Deleted "Delete check".').count() == 1 or pg.locator('text=Deleted').count() >= 1)

    b.close()

print('theme phase 1 browser checks ' + ('passed' if not failures else 'FAILED'))
sys.exit(1 if failures else 0)
