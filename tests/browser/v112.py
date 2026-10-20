import json, time, urllib.request
from playwright.sync_api import sync_playwright
BASE = 'http://127.0.0.1:8080'
JQ = open('/home/claude/omeka-s/application/asset/vendor/jquery/jquery.min.js', 'rb').read()
def api(path, method='GET', data=None):
    req = urllib.request.Request(BASE + '/api/' + path + ('&' if '?' in path else '?') + 'key_identity=testkey&key_credential=testcred', method=method, data=json.dumps(data).encode() if data else None, headers={'Content-Type': 'application/json'})
    return json.load(urllib.request.urlopen(req))
site = api('sites?slug=dhs')[0]['o:id']
page = api('site_pages', 'POST', {'o:site': {'o:id': site}, 'o:title': 'Print me', 'o:slug': 'print-me-' + str(int(time.time())), 'o:is_public': True, 'o:layout': 'grid', 'o:layout_data': {'grid_columns': 12, 'visual_page_builder': {'version': 1}}, 'o:block': [
    {'o:layout': 'html', 'o:data': {'html': '<h2>History</h2><p>Founded<sup class="vpb-footnote">County records.</sup>. See <a href="https://example.org/history">the history</a>.</p>'}},
    {'o:layout': 'specialSearchBox', 'o:data': {}}]})
res, errs = {}, []
with sync_playwright() as p:
    b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args=['--no-sandbox'])
    ctx = b.new_context(viewport={'width': 1300, 'height': 900}, accept_downloads=True)
    ctx.route('**/code.jquery.com/**', lambda r: r.fulfill(status=200, body=JQ, content_type='application/javascript'))
    pg = ctx.new_page(); pg.on('pageerror', lambda e: errs.append(str(e)))
    pg.goto(BASE + '/login'); pg.fill('#email', 'admin@example.com'); pg.fill('#password', 'Test-password-123'); pg.click('#loginform [type=submit]'); pg.wait_for_load_state()
    pg.goto(f"{BASE}/admin/visual-page-builder/dhs/{page['o:id']}"); pg.wait_for_function('window.VPB && VPB.store'); pg.wait_for_timeout(800)
    pg.click('#pageMenuBtn'); pg.wait_for_timeout(150)
    mb, menu = pg.locator('#pageMenuBtn').bounding_box(), pg.locator('.pt-menu').bounding_box()
    res['menu opens under the button'] = menu is not None and menu['y'] >= mb['y'] + mb['height'] - 2 and abs(menu['x'] - mb['x']) < 250
    res['menu keyboard'] = pg.evaluate("document.activeElement.textContent")
    with pg.expect_download() as dl:
        pg.click('.pt-menu button:has-text("Download layout")')
    d = dl.value; path = d.path(); data = json.load(open(path))
    res['download'] = d.suggested_filename.endswith('-layout.json') and data['format'] == 'visual-page-builder-layout' and len(data['page']['o:block']) >= 2
    pg.click('#pageMenuBtn'); pg.click('.pt-menu button:has-text("Check page")'); pg.wait_for_timeout(200)
    res['check page dialog'] = pg.locator('.lv-card h2:has-text("Check page")').count() == 1
    pg.keyboard.press('Escape')
    pg.click('#pageMenuBtn'); pg.click('.pt-menu button:has-text("History")'); pg.wait_for_timeout(600)
    res['history dialog'] = pg.locator('.lv-card h2:has-text("Page history")').count() == 1
    pg.keyboard.press('Escape')
    # Public page, printed.
    pub = ctx.new_page(); pub.on('pageerror', lambda e: errs.append('pub ' + str(e)))
    pub.goto(f"{BASE}/s/dhs/page/{page['o:slug']}"); pub.wait_for_load_state('networkidle')
    pub.emulate_media(media='print')
    res['print: search box hidden'] = pub.locator('.special-blocks-search-box').evaluate("e => getComputedStyle(e).display") == 'none'
    res['print: link address shown'] = 'example.org/history' in pub.evaluate("getComputedStyle(document.querySelector('.vpb-page a[href^=\"http\"]'), '::after').content")
    res['footnote numbered'] = pub.locator('sup.vpb-fn-ref a').inner_text() == '1'
    pub.pdf(path='/tmp/pw/print.pdf') if False else None
    b.close()
print(json.dumps({'results': res, 'errors': errs}, indent=1))
