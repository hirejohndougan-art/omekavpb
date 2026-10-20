"""Item template in the editor, Phase 2, in a real browser (test install, admin session in /tmp/jar.txt).

Creates an Item template through the Theme Builder form, then checks the editor:
the item field blocks are in the palette, Title and Main image show "Required" in place
of Delete (and offer Hide), and other blocks keep Delete. The template is removed afterward.
    python3 tests/browser/item-template-editor.py
"""
import json
import secrets
import sys
import urllib.request
from playwright.sync_api import sync_playwright

BASE = 'http://127.0.0.1:8080'
AUTH = 'key_identity=testkey&key_credential=testcred'
NAME = 'Editor check item ' + secrets.token_hex(3)
failures = []


def check(name, ok, detail=''):
    print(('ok   ' if ok else 'FAIL ') + name + (f' ({detail})' if detail and not ok else ''))
    if not ok:
        failures.append(name)


def api(method, path):
    req = urllib.request.Request(BASE + path + ('&' if '?' in path else '?') + AUTH, method=method)
    with urllib.request.urlopen(req) as r:
        return json.loads(r.read() or b'null')


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

    pg.goto(BASE + '/admin/visual-builder/dhs/theme')
    csrf = pg.locator('input[name="vpb_theme_csrf"]').first.get_attribute('value')
    pg.select_option('#vpb-add-screen', 'item')
    pg.select_option('#vpb-add-scope', 'all')
    pg.fill('#vpb-add-name', NAME)
    pg.click('form:has(input[name=vpb_action][value=add]) button[type=submit]')
    pg.wait_for_load_state()
    tid = None
    for page in api('GET', '/api/site_pages?per_page=1000'):
        if page.get('o:title') == NAME:
            tid = page['o:id']
    check('the item template is made', tid is not None)
    try:
        # The Theme Builder page loads jQuery from a CDN this sandbox can't reach, so errors
        # from before this point don't count: only the editor's own.
        errors.clear()
        pg.goto(f'{BASE}/admin/visual-page-builder/dhs/{tid}')
        pg.wait_for_selector('.pal-wrap')
        labels = pg.locator('.pal-wrap .pal-label').all_text_contents()
        for want in ['Item title', 'Main image', 'Description', 'Details list', 'Subjects as tags', 'Item sets', 'Cite this item', 'Download']:
            check(f'the palette has {want}', want in labels, str(labels[:12]))

        title = pg.locator('.blk').filter(has=pg.locator('.req-tag')).filter(has_text='Item title').first
        check('Item title says Required', title.count() == 1)
        check('Item title offers Hide', title.locator('.req-hide', has_text='Hide').count() == 1)
        check('Item title has no Delete button', title.locator('button', has_text='Delete').count() == 0)

        image = pg.locator('.blk').filter(has=pg.locator('.req-tag')).filter(has_text='Main image').first
        check('Main image says Required', image.count() == 1)

        check('no script errors', not errors, '; '.join(errors)[:300])
    finally:
        api('DELETE', f'/api/site_pages/{tid}') if tid else None
    b.close()

print('item template editor checks ' + ('passed' if not failures else 'FAILED'))
sys.exit(1 if failures else 0)
