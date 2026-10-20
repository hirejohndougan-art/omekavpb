import json, sys, time, urllib.request
from playwright.sync_api import sync_playwright
BASE = 'http://127.0.0.1:8080'
JQ = open('/home/claude/omeka-s/application/asset/vendor/jquery/jquery.min.js', 'rb').read()
def api(path, method='GET', data=None):
    req = urllib.request.Request(BASE + '/api/' + path + ('&' if '?' in path else '?') + 'key_identity=testkey&key_credential=testcred', method=method,
        data=json.dumps(data).encode() if data else None, headers={'Content-Type': 'application/json'})
    return json.load(urllib.request.urlopen(req))
site = api('sites?slug=dhs')[0]['o:id']
oh = api('items?fulltext_search=Synced%20interview&per_page=1')[0]['o:id']
page = api('site_pages', 'POST', {'o:site': {'o:id': site}, 'o:title': 'Interact two', 'o:slug': 'interact-two-' + str(int(time.time())), 'o:is_public': True, 'o:block': [
    {'o:layout': 'html', 'o:data': {'html': '<p>Top</p>'}},
    {'o:layout': 'specialOralHistory', 'o:data': {'item_id': oh}},
    {'o:layout': 'specialArchiveQuiz', 'o:data': {'questions': [{'question': 'Q?', 'choices': 'A\nB', 'correct': 2, 'search': 'depot'}]}}]})
pid, slug = page['o:id'], page['o:slug']
res, errs = {}, []
with sync_playwright() as p:
    b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args=['--no-sandbox', '--autoplay-policy=no-user-gesture-required'])
    ctx = b.new_context(viewport={'width': 1366, 'height': 900})
    ctx.route('**/code.jquery.com/**', lambda r: r.fulfill(status=200, body=JQ, content_type='application/javascript'))
    pg = ctx.new_page(); pg.on('pageerror', lambda e: errs.append(str(e)))
    pg.goto(BASE + '/login'); pg.fill('#email', 'admin@example.com'); pg.fill('#password', 'Test-password-123'); pg.click('#loginform [type=submit]'); pg.wait_for_load_state()
    pg.goto(f'{BASE}/admin/visual-page-builder/dhs/{pid}'); pg.wait_for_function('window.VPB && VPB.store'); pg.wait_for_timeout(1000)
    n0 = pg.evaluate("VPB.store.state.sections.length")
    # Drag a section layout from the palette onto the + bar between sections 1 and 2.
    tile = pg.locator('.pal-label', has_text='2 columns').first
    res['tile found'] = tile.count() == 1
    bar = pg.locator('#secs > .ins').nth(0)
    tb, bb = tile.bounding_box(), bar.bounding_box()
    pg.mouse.move(tb['x'] + 10, tb['y'] + 5); pg.mouse.down()
    for k in range(1, 15):
        pg.mouse.move(tb['x'] + (bb['x'] + 40 - tb['x']) * k / 14, tb['y'] + (bb['y'] + bb['height'] / 2 - tb['y']) * k / 14); pg.wait_for_timeout(20)
    res['drag before drop'] = pg.evaluate("JSON.stringify(VPB.drag.current && VPB.drag.current())")
    res['bars now'] = pg.evaluate("[...document.querySelectorAll('#secs > *')].map(e => e.className + ':' + Math.round(e.getBoundingClientRect().top) + '-' + Math.round(e.getBoundingClientRect().bottom))")
    res['mouse y'] = tb['y'] + (bb['y'] + bb['height'] / 2 - tb['y'])
    pg.mouse.up(); pg.wait_for_timeout(400)
    secs = pg.evaluate("VPB.store.state.sections.map(s => s.cols.length)")
    res['dragged section landed at the bar'] = len(secs) == n0 + 1 and secs[0] == 2
    res['debug'] = {'n0': n0, 'secs': secs, 'tile': tb, 'bar': bb}
    pg.keyboard.press('Control+z'); pg.wait_for_timeout(200)
    res['undo'] = pg.evaluate("VPB.store.state.sections.length") == n0
    pg.keyboard.press('Control+y'); pg.wait_for_timeout(200)
    res['redo'] = pg.evaluate("VPB.store.state.sections.length") == n0 + 1
    # Public: oral history with a real audio element; quiz.
    pub = ctx.new_page(); pub.on('pageerror', lambda e: errs.append('public: ' + str(e)))
    pub.goto(f'{BASE}/s/dhs/page/{slug}'); pub.wait_for_load_state('networkidle'); pub.wait_for_timeout(800)
    res['oh synced'] = pub.locator('.special-blocks-oh-synced audio').count() == 1
    pub.locator('.special-blocks-oh-cue').nth(1).locator('button').click(); pub.wait_for_timeout(500)
    res['oh seek sets time'] = pub.evaluate("document.querySelector('.special-blocks-oh-synced audio').currentTime") >= 0
    res['oh current line'] = pub.locator('.special-blocks-oh-cue.is-current').count() == 1
    pub.fill('.special-blocks-oh-search input', 'storm'); pub.wait_for_timeout(200)
    res['oh search'] = pub.locator('.special-blocks-oh-cue mark').count()
    pub.check('.special-blocks-quiz input[value="2"]'); pub.click('.special-blocks-quiz-check')
    res['quiz right'] = 'Right!' in pub.locator('.special-blocks-quiz-verdict').inner_text()
    b.close()
print(json.dumps({'results': res, 'errors': errs}, indent=1))
