import json, time, urllib.request
from playwright.sync_api import sync_playwright
BASE = 'http://127.0.0.1:8080'
JQ = open('/home/claude/omeka-s/application/asset/vendor/jquery/jquery.min.js', 'rb').read()
def api(path, method='GET', data=None):
    req = urllib.request.Request(BASE + '/api/' + path + ('&' if '?' in path else '?') + 'key_identity=testkey&key_credential=testcred', method=method, data=json.dumps(data).encode() if data else None, headers={'Content-Type': 'application/json'})
    return json.load(urllib.request.urlopen(req))
site = api('sites?slug=dhs')[0]['o:id']
page = api('site_pages', 'POST', {'o:site': {'o:id': site}, 'o:title': 'Side format', 'o:slug': 'side-format-' + str(int(time.time())), 'o:is_public': True, 'o:block': [{'o:layout': 'html', 'o:data': {'html': '<p>Hello brave world</p><p>Second line</p>'}}]})
res, errs = {}, []
with sync_playwright() as p:
    b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args=['--no-sandbox'])
    ctx = b.new_context(viewport={'width': 1366, 'height': 900})
    ctx.route('**/code.jquery.com/**', lambda r: r.fulfill(status=200, body=JQ, content_type='application/javascript'))
    pg = ctx.new_page(); pg.on('pageerror', lambda e: errs.append(str(e)))
    pg.goto(BASE + '/login'); pg.fill('#email', 'admin@example.com'); pg.fill('#password', 'Test-password-123'); pg.click('#loginform [type=submit]'); pg.wait_for_load_state()
    pg.goto(f"{BASE}/admin/visual-page-builder/dhs/{page['o:id']}"); pg.wait_for_function('window.VPB && VPB.store'); pg.wait_for_timeout(800)
    pg.locator('.b-html').first.click(); pg.wait_for_timeout(300)
    res['panel in sidebar'] = pg.locator('#side .rt-panel').count() == 1
    res['nothing floats over the text'] = pg.locator('.blk .rt-bar').count() == 0
    # select "brave" in the page, then click Bold in the sidebar
    pg.evaluate("""() => { const p = document.querySelector('.b-html p'); const r = document.createRange(); r.setStart(p.firstChild, 6); r.setEnd(p.firstChild, 11); const s = getSelection(); s.removeAllRanges(); s.addRange(r); }""")
    pg.wait_for_timeout(100)
    pg.locator('#side .rt-panel [aria-label="Bold (Ctrl+B)"]').click(); pg.wait_for_timeout(150)
    res['bold pressed shows'] = pg.locator('#side .rt-panel [aria-label="Bold (Ctrl+B)"]').get_attribute('aria-pressed') == 'true'
    pg.locator('#side .rt-panel select[aria-label="Font"]').select_option(label='Georgia'); pg.wait_for_timeout(150)
    pg.locator('#side .rt-panel [aria-label="Align center"]').click(); pg.wait_for_timeout(150)
    html = pg.evaluate("VPB.store.state.sections[0].cols[0].blocks[0].data.html")
    res['bold applied to the selection'] = 'brave' in html and ('font-weight: bold' in html or '<b>' in html) and 'Hello <' in html
    res['font applied'] = 'Georgia' in html
    res['centered'] = 'text-align: center' in html
    res['center pressed'] = pg.locator('#side .rt-panel [aria-label="Align center"]').get_attribute('aria-pressed') == 'true'
    res['html'] = html[:220]
    pg.locator('#side .rt-panel').screenshot(path='/tmp/pw/panel.png')
    b.close()
print(json.dumps({'results': res, 'errors': errs}, indent=1))
