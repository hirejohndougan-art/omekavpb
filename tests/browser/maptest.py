import json, time, urllib.request, base64, struct, zlib
from playwright.sync_api import sync_playwright
BASE = 'http://127.0.0.1:8080'
JQ = open('/home/claude/omeka-s/application/asset/vendor/jquery/jquery.min.js', 'rb').read()
def png(r, g, b):
    raw = b''.join(b'\x00' + bytes([r, g, b]) * 256 for _ in range(256))
    def chunk(t, d): return struct.pack('>I', len(d)) + t + d + struct.pack('>I', zlib.crc32(t + d) & 0xffffffff)
    return b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('>IIBBBBB', 256, 256, 8, 2, 0, 0, 0)) + chunk(b'IDAT', zlib.compress(raw)) + chunk(b'IEND', b'')
TILE = png(220, 230, 210)
def api(path, method='GET', data=None):
    req = urllib.request.Request(BASE + '/api/' + path + ('&' if '?' in path else '?') + 'key_identity=testkey&key_credential=testcred', method=method, data=json.dumps(data).encode() if data else None, headers={'Content-Type': 'application/json'})
    return json.load(urllib.request.urlopen(req))
site = api('sites?slug=dhs')[0]['o:id']
page = api('site_pages', 'POST', {'o:site': {'o:id': site}, 'o:title': 'Map test', 'o:slug': 'map-test-' + str(int(time.time())), 'o:is_public': True, 'o:block': [
    {'o:layout': 'specialMap', 'o:data': {'tour': True, 'places': [{'name': 'Depot', 'lat': '29.4608', 'lng': '-95.0513', 'note': 'Built 1896'}, {'name': 'School', 'lat': '29.4700', 'lng': '-95.0400'}]}},
    {'o:layout': 'specialStoryMap', 'o:data': {'stops': [{'title': 'One', 'date': '1900', 'lat': '29.46', 'lng': '-95.05'}, {'title': 'Two', 'date': '1910', 'lat': '29.30', 'lng': '-94.80'}]}}]})
res, errs = {}, []
with sync_playwright() as p:
    b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args=['--no-sandbox'])
    ctx = b.new_context(viewport={'width': 1280, 'height': 900}, has_touch=True)
    ctx.route('**/code.jquery.com/**', lambda r: r.fulfill(status=200, body=JQ, content_type='application/javascript'))
    ctx.route('**/tile.openstreetmap.org/**', lambda r: r.fulfill(status=200, body=TILE, content_type='image/png'))
    pg = ctx.new_page(); pg.on('pageerror', lambda e: errs.append(str(e)))
    pg.goto(f"{BASE}/s/dhs/page/{page['o:slug']}"); pg.wait_for_load_state('networkidle'); pg.wait_for_timeout(500)
    m = pg.locator('.special-blocks-map').first
    pg.evaluate("document.querySelector('.special-blocks-map').scrollIntoView({block: 'center'})"); pg.wait_for_timeout(200)
    box = m.bounding_box()
    res['tiles shown'] = pg.evaluate("[...document.querySelectorAll('.special-blocks-map .vpb-map-tile')].filter(i => i.complete && i.naturalWidth === 256).length")
    pins = pg.locator('.special-blocks-map .vpb-map-pin')
    res['pins'] = pins.count()
    inside = pg.evaluate("""() => { const m = document.querySelector('.special-blocks-map').getBoundingClientRect(); return [...document.querySelectorAll('.special-blocks-map .vpb-map-pin')].every(p => { const r = p.getBoundingClientRect(); return r.left >= m.left && r.right <= m.right && r.top >= m.top && r.bottom <= m.bottom; }); }""")
    res['pins inside the map'] = inside
    res['tour line'] = pg.locator('.special-blocks-map polyline.dashed').count() == 1
    pins.first.click(); pg.wait_for_timeout(200)
    res['popup'] = pg.locator('.special-blocks-map .vpb-map-popup:not([hidden])').inner_text()
    pg.keyboard.press('Escape'); pg.wait_for_timeout(100)
    res['popup closed by Escape'] = pg.locator('.special-blocks-map .vpb-map-popup[hidden]').count() == 1
    def center(): return pg.evaluate("(() => { const p = document.querySelector('.special-blocks-map .vpb-map-pin'); return p.getBoundingClientRect().left; })()")
    x0 = center()
    pg.mouse.move(box['x'] + 200, box['y'] + 200); pg.mouse.down(); pg.mouse.move(box['x'] + 260, box['y'] + 220, steps=8); pg.mouse.up()
    res['mouse drag pans'] = center() - x0 >= 58   # at least the drag (plus any drift)
    pg.wait_for_timeout(900)  # let any drift settle
    z = lambda: pg.evaluate("document.querySelector('.special-blocks-map .vpb-map-zoom button').disabled")
    # wheel: before clicking → page scrolls, map doesn't zoom
    pg.mouse.move(5, 5)  # leave the map: the wheel goes back to scrolling the page
    sy = pg.evaluate('scrollY'); w0 = pg.evaluate("document.querySelectorAll('.special-blocks-map .vpb-map-tile')[0].src.split('/')[3]")
    pg.mouse.move(box['x'] + 300, box['y'] + 150); pg.mouse.wheel(0, -200); pg.wait_for_timeout(300)
    w1 = pg.evaluate("document.querySelectorAll('.special-blocks-map .vpb-map-tile')[0].src.split('/')[3]")
    res['wheel ignored before click'] = w0 == w1
    pg.mouse.click(box['x'] + 300, box['y'] + 150); pg.mouse.wheel(0, -200); pg.wait_for_timeout(300)
    w2 = pg.evaluate("document.querySelectorAll('.special-blocks-map .vpb-map-tile')[0].src.split('/')[3]")
    res['wheel zooms after click'] = int(w2) == int(w1) + 1
    # pinch with real touch events (CDP)
    cdp = ctx.new_cdp_session(pg)
    cx, cy = box['x'] + 300, box['y'] + 180
    zbefore = int(pg.evaluate("document.querySelectorAll('.special-blocks-map .vpb-map-tile')[0].src.split('/')[3]"))
    cdp.send('Input.dispatchTouchEvent', {'type': 'touchStart', 'touchPoints': [{'x': cx - 40, 'y': cy, 'id': 1}, {'x': cx + 40, 'y': cy, 'id': 2}]})
    for k in range(1, 9):
        cdp.send('Input.dispatchTouchEvent', {'type': 'touchMove', 'touchPoints': [{'x': cx - 40 - k * 15, 'y': cy, 'id': 1}, {'x': cx + 40 + k * 15, 'y': cy, 'id': 2}]})
    cdp.send('Input.dispatchTouchEvent', {'type': 'touchEnd', 'touchPoints': []})
    pg.wait_for_timeout(300)
    zafter = int(pg.evaluate("document.querySelectorAll('.special-blocks-map .vpb-map-tile')[0].src.split('/')[3]"))
    res['pinch zooms in'] = f'{zbefore} -> {zafter}'
    # one-finger touch pan
    p0 = center()
    cdp.send('Input.dispatchTouchEvent', {'type': 'touchStart', 'touchPoints': [{'x': cx, 'y': cy, 'id': 3}]})
    for k in range(1, 6): cdp.send('Input.dispatchTouchEvent', {'type': 'touchMove', 'touchPoints': [{'x': cx + k * 10, 'y': cy, 'id': 3}]})
    cdp.send('Input.dispatchTouchEvent', {'type': 'touchEnd', 'touchPoints': []}); pg.wait_for_timeout(200)
    res['touch drag pans'] = round(center() - p0)
    pg.wait_for_timeout(900)
    # keyboard
    m.focus(); k0 = center(); pg.keyboard.press('ArrowLeft'); pg.wait_for_timeout(100)
    res['arrow keys pan'] = round(center() - k0)
    # Story map: Next glides and centers stop 2
    pg.evaluate("document.querySelector('.special-blocks-story-map').scrollIntoView({block: 'center'})"); pg.wait_for_timeout(200)
    pg.click('.special-blocks-story-next'); pg.wait_for_timeout(1700)
    res['story: current pin centered'] = pg.evaluate("""() => { const m = document.querySelector('.special-blocks-story-mapbox').getBoundingClientRect(); const p = document.querySelector('.special-blocks-story-pin.is-current').getBoundingClientRect(); return Math.abs((p.left + p.right) / 2 - (m.left + m.right) / 2) < 20; }""")
    sb = pg.locator('.special-blocks-story-mapbox').bounding_box()
    pg.screenshot(path='/tmp/pw/map.png', full_page=False, clip={'x': sb['x'], 'y': sb['y'], 'width': sb['width'], 'height': sb['height']})
    # Editor pin tool
    pg.goto(BASE + '/login'); pg.fill('#email', 'admin@example.com'); pg.fill('#password', 'Test-password-123'); pg.click('#loginform [type=submit]'); pg.wait_for_load_state()
    pg.goto(f"{BASE}/admin/visual-page-builder/dhs/{page['o:id']}"); pg.wait_for_function('window.VPB && VPB.store'); pg.wait_for_timeout(800)
    pg.evaluate("VPB.app.select({kind:'block', id: VPB.store.state.sections.flatMap(s=>s.cols.flatMap(c=>c.blocks)).find(b=>b.layout==='specialMap').id})"); pg.wait_for_timeout(600)
    pm = pg.locator('.pm-map'); pb = pm.bounding_box()
    pin = pg.locator('.pm-map .vpb-map-pin').first; pinb = pin.bounding_box()
    before = pg.evaluate("VPB.store.state.sections.flatMap(s=>s.cols.flatMap(c=>c.blocks)).find(b=>b.layout==='specialMap').data.places[0]")
    pg.mouse.move(pinb['x'] + 13, pinb['y'] + 13); pg.mouse.down(); pg.mouse.move(pinb['x'] + 53, pinb['y'] + 33, steps=6); pg.mouse.up(); pg.wait_for_timeout(500)
    after = pg.evaluate("VPB.store.state.sections.flatMap(s=>s.cols.flatMap(c=>c.blocks)).find(b=>b.layout==='specialMap').data.places[0]")
    res['editor: dragging a pin moves the place'] = (before['lat'], before['lng']) != (after['lat'], after['lng']) and float(after['lng']) > float(before['lng'])
    pm = pg.locator('.pm-map'); pb = pm.bounding_box()
    pg.mouse.click(pb['x'] + pb['width'] * 0.2, pb['y'] + pb['height'] * 0.8); pg.wait_for_timeout(500)
    res['editor: click adds a place'] = pg.locator('.pm-new #pm-new-name').count() == 1
    b.close()
print(json.dumps({'results': res, 'errors': errs}, indent=1))
