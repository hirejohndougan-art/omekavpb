# v1.14.1: change table of contents entries from its own settings: indent
# and outdent (arrows, keyboard, or drag the grip sideways), rename, leave out.
import json, time, urllib.request
from playwright.sync_api import sync_playwright
BASE = 'http://127.0.0.1:8080'
JQ = open('/home/claude/omeka-s/application/asset/vendor/jquery/jquery.min.js', 'rb').read()
def api(path, method='GET', data=None):
    req = urllib.request.Request(BASE + '/api/' + path + ('&' if '?' in path else '?') + 'key_identity=testkey&key_credential=testcred', method=method, data=json.dumps(data).encode() if data else None, headers={'Content-Type': 'application/json'})
    return json.load(urllib.request.urlopen(req))
site = api('sites?slug=dhs')[0]['o:id']
slug = 'toc-indent-' + str(int(time.time()))
page = api('site_pages', 'POST', {'o:site': {'o:id': site}, 'o:title': 'TOC indent', 'o:slug': slug, 'o:is_public': True, 'o:block': [
    {'o:layout': 'specialJumpLinks', 'o:data': {'heading': 'On this page', 'levels': 'h4', 'numbered': True}},
    {'o:layout': 'html', 'o:data': {'html': '<h2>Example 1</h2><p>a</p><h2>example 2</h2><p>b</p><h2>New heading</h2><p>c</p>'}},
    {'o:layout': 'specialTableLink', 'o:data': {'name': 'The map', 'level': '1'}}]})
res, errs = {}, []
with sync_playwright() as p:
    b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args=['--no-sandbox'])
    ctx = b.new_context(viewport={'width': 1366, 'height': 900})
    ctx.route('**/code.jquery.com/**', lambda r: r.fulfill(status=200, body=JQ, content_type='application/javascript'))
    pg = ctx.new_page(); pg.on('pageerror', lambda e: errs.append(str(e)))
    pg.goto(BASE + '/login'); pg.fill('#email', 'admin@example.com'); pg.fill('#password', 'Test-password-123'); pg.click('#loginform [type=submit]'); pg.wait_for_load_state()
    pg.goto(f"{BASE}/admin/visual-page-builder/dhs/{page['o:id']}"); pg.wait_for_function('window.VPB && VPB.store'); pg.wait_for_timeout(800)
    toc = lambda: pg.locator('.blk').first.locator('.special-blocks-jump-list li').evaluate_all('ls => ls.map(l => l.firstChild.textContent + " " + l.querySelector("a").textContent)')
    html = lambda: pg.evaluate("VPB.store.state.sections[0].cols[0].blocks[1].data.html")
    pg.locator('.blk').first.click(); pg.wait_for_timeout(300)
    res['title shown'] = pg.locator('.blk').first.locator('h2.special-blocks-heading').count() == 1
    row = lambda name: pg.locator('.toc-row', has=pg.locator('.toc-name', has_text=name))
    row('example 2').locator('[aria-label^="Indent"]').click(); pg.wait_for_timeout(300)
    res['indent button -> H3'] = '<h3>example 2</h3>' in html()
    # Drag the grip of "New heading" two steps right -> Heading 4
    g = row('New heading').locator('.toc-grip'); bb = g.bounding_box()
    pg.mouse.move(bb['x'] + 10, bb['y'] + 10); pg.mouse.down(); pg.mouse.move(bb['x'] + 40, bb['y'] + 10, steps=4); pg.mouse.move(bb['x'] + 62, bb['y'] + 10, steps=4); pg.mouse.up(); pg.wait_for_timeout(300)
    res['drag sideways -> H4'] = '<h4>New heading</h4>' in html()
    # Keyboard: outdent it back one step
    row('New heading').locator('.toc-grip').focus(); pg.keyboard.press('ArrowLeft'); pg.wait_for_timeout(300)
    res['arrow key -> H3'] = '<h3>New heading</h3>' in html()
    # Table link indent
    row('The map').locator('[aria-label^="Indent"]').click(); pg.wait_for_timeout(300)
    res['table link level'] = pg.evaluate("VPB.store.state.sections[0].cols[0].blocks[2].data.level")
    # Open "Example 1": rename it, then leave out "example 2"
    row('Example 1').locator('.toc-found-item').click(); pg.wait_for_timeout(200)
    nm = row('Example 1').locator('.toc-entry input[type=text]'); nm.fill('Intro'); nm.press('Enter'); pg.wait_for_timeout(300)
    row('example 2').locator('.toc-found-item').click(); pg.wait_for_timeout(200)
    row('example 2').locator('.toc-entry input[type=checkbox]').uncheck(); pg.wait_for_timeout(300)
    res['canvas list'] = toc()
    res['found rows'] = pg.locator('.toc-row').evaluate_all('rs => rs.map(r => getComputedStyle(r).paddingLeft + " " + r.querySelector(".toc-found-item").innerText.replace(/\\n/g, " | "))')
    pg.locator('#side').screenshot(path='/tmp/pw/toc-indent-side.png')
    pg.click('#saveBtn'); pg.wait_for_timeout(2500)
    res['saved'] = pg.locator('#status').inner_text()
    pub = ctx.new_page(); pub.goto(f"{BASE}/s/dhs/page/{slug}"); pub.wait_for_timeout(500)
    res['public'] = pub.locator('.special-blocks-jump-list li').evaluate_all('ls => ls.map(l => l.firstChild.textContent + " " + l.querySelector("a").textContent)')
    b.close()
print(json.dumps({'results': res, 'errors': errs}, indent=1))
