"""Text size per device on a live page (Phase 5), in a real browser (test install).

Makes two pages with the same paragraph: one whose block sets a phone text size of 80%, and a
control with none. At phone width the paragraph should be 80% of the control's size. Desktop
should match the control. Both pages are removed afterward.
    python3 tests/browser/live-text-size.py
"""
import json
import re
import secrets
import sys
import urllib.request
from playwright.sync_api import sync_playwright

BASE = 'http://127.0.0.1:8080'
AUTH = 'key_identity=testkey&key_credential=testcred'
failures = []


def check(name, ok, detail=''):
    print(('ok   ' if ok else 'FAIL ') + name + (f' ({detail})' if detail and not ok else ''))
    if not ok:
        failures.append(name)


def api(method, path, body=None):
    req = urllib.request.Request(BASE + path + ('&' if '?' in path else '?') + AUTH,
                                 data=json.dumps(body).encode() if body is not None else None,
                                 headers={'Content-Type': 'application/json'}, method=method)
    with urllib.request.urlopen(req) as r:
        return json.loads(r.read() or b'null')


def make_page(title, layout_data):
    """A page with one paragraph. The block's layout data is saved through the editor's
    own save endpoint (the API doesn't keep a block's VPB layout data)."""
    site = api('GET', '/api/sites?slug=dhs')[0]
    page = api('POST', '/api/site_pages', {
        'o:site': {'o:id': site['o:id']}, 'o:title': title, 'o:slug': 'textsize-' + secrets.token_hex(3),
        'o:is_public': True, 'o:layout': 'grid', 'o:block': [],
    })
    cookie = '; '.join(f"{c['name']}={c['value']}" for c in jar_cookies())
    editor = urllib.request.urlopen(urllib.request.Request(f"{BASE}/admin/visual-page-builder/dhs/{page['o:id']}", headers={'Cookie': cookie})).read().decode()
    config = json.loads(re.search(r'<script type="application/json" id="vpb-config">(.*?)</script>', editor, re.S).group(1))
    body = {'csrf': config['csrf'], 'blocks': [
        {'o:layout': 'blockGroup', 'o:data': {'span': 1}, 'o:layout_data': {'vpb_section': {'cols': [{'w': 12, 'bg': None, 'img': None}], 'resp': {'tablet': 'stack', 'phone': 'stack'}}}, 'o:attachment': []},
        {'o:layout': 'html', 'o:data': {'html': '<p>Text size probe</p>'}, 'o:layout_data': dict({'grid_column_position': '1', 'grid_column_span': '12', 'vpb_col': 0}, **layout_data), 'o:attachment': []},
    ]}
    req = urllib.request.Request(BASE + config['saveUrl'], data=json.dumps(body).encode(), headers={'Content-Type': 'application/json', 'Cookie': cookie}, method='POST')
    urllib.request.urlopen(req).read()
    return page


def jar_cookies():
    out = []
    for line in open('/tmp/jar.txt'):
        if not line.strip() or (line.startswith('#') and not line.startswith('#HttpOnly_')):
            continue
        p = line.rstrip('\n').split('\t')
        if len(p) >= 7:
            out.append({'name': p[5], 'value': p[6], 'domain': '127.0.0.1', 'path': '/'})
    return out


sized = make_page('Text size check sized', {'vpb_text_phone': 80})
control = make_page('Text size check control', {})
try:
    with sync_playwright() as p:
        b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium_headless_shell-1194/chrome-linux/headless_shell', args=['--no-sandbox'])

        def size(page_obj, width):
            ctx = b.new_context(viewport={'width': width, 'height': 900})
            pg = ctx.new_page()
            pg.goto(f"{BASE}/s/dhs/page/{page_obj['o:slug']}")
            px = pg.evaluate("parseFloat(getComputedStyle(document.querySelector('.block-html p')).fontSize)")
            ctx.close()
            return px

        phone_sized, phone_control = size(sized, 390), size(control, 390)
        desk_sized, desk_control = size(sized, 1280), size(control, 1280)
        ratio = phone_sized / phone_control if phone_control else 0
        check('on a phone the sized paragraph is 80% of the control', abs(ratio - 0.8) < 0.02, f'{phone_sized}px vs {phone_control}px = {ratio:.3f}')
        check('on desktop it matches the control', abs(desk_sized - desk_control) < 0.5, f'{desk_sized}px vs {desk_control}px')
        b.close()
finally:
    api('DELETE', f"/api/site_pages/{sized['o:id']}")
    api('DELETE', f"/api/site_pages/{control['o:id']}")

print('live text size checks ' + ('passed' if not failures else 'FAILED'))
sys.exit(1 if failures else 0)
