import json, time, urllib.request, struct, zlib, threading
from playwright.sync_api import sync_playwright
BASE = 'http://127.0.0.1:8080'
JQ = open('/home/claude/omeka-s/application/asset/vendor/jquery/jquery.min.js', 'rb').read()
def png(r, g, b):
    raw = b''.join(b'\x00' + bytes([r, g, b]) * 256 for _ in range(256))
    def chunk(t, d): return struct.pack('>I', len(d)) + t + d + struct.pack('>I', zlib.crc32(t + d) & 0xffffffff)
    return b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('>IIBBBBB', 256, 256, 8, 2, 0, 0, 0)) + chunk(b'IDAT', zlib.compress(raw)) + chunk(b'IEND', b'')
TILE = png(220, 230, 210)
slow = {'on': False}
held = []
def tile_route(r):
    if slow['on']: held.append(r); return   # answered later, to see what shows meanwhile
    r.fulfill(status=200, body=TILE, content_type='image/png')
def api(path, method='GET', data=None):
    req = urllib.request.Request(BASE + '/api/' + path + ('&' if '?' in path else '?') + 'key_identity=testkey&key_credential=testcred', method=method, data=json.dumps(data).encode() if data else None, headers={'Content-Type': 'application/json'})
    return json.load(urllib.request.urlopen(req))
site = api('sites?slug=dhs')[0]['o:id']
page = api('site_pages', 'POST', {'o:site': {'o:id': site}, 'o:title': 'Map four', 'o:slug': 'map-four-' + str(int(time.time())), 'o:is_public': True, 'o:block': [
    {'o:layout': 'specialMap', 'o:data': {'places': [{'name': 'Center', 'lat': '29.4608', 'lng': '-95.0513', 'note': 'In the middle'}, {'name': 'Far corner with a long note', 'lat': '29.4750', 'lng': '-95.0300', 'note': 'This popup would be cut off at the top-right edge of the map unless the map moves to show it.'}]}}]})
res, errs = {}, []
with sync_playwright() as p:
    b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args=['--no-sandbox'])
    ctx = b.new_context(viewport={'width': 1280, 'height': 900}, has_touch=True)
    ctx.route('**/code.jquery.com/**', lambda r: r.fulfill(status=200, body=JQ, content_type='application/javascript'))
    ctx.route('**/tile.openstreetmap.org/**', tile_route)
    pg = ctx.new_page(); pg.on('pageerror', lambda e: errs.append(str(e)))
    pg.goto(f"{BASE}/s/dhs/page/{page['o:slug']}"); pg.wait_for_load_state('networkidle')
    pg.evaluate("document.querySelector('.special-blocks-map').scrollIntoView({block: 'center'})"); pg.wait_for_timeout(300)
    box = pg.locator('.special-blocks-map').bounding_box()
    zoomlevel = lambda: pg.evaluate("Math.max(...[...document.querySelectorAll('.special-blocks-map .vpb-map-tile')].filter(i => i.style.zIndex === '2').map(i => +i.src.split('/')[3]))")
    # 1. Smooth zoom: mid-animation tiles are scaled (not 256px); ends whole.
    z0 = zoomlevel()
    slow['on'] = True
    pg.click('.special-blocks-map .vpb-map-zoom button[aria-label="Zoom in"]'); pg.wait_for_timeout(120)
    ws = pg.evaluate("[...document.querySelectorAll('.special-blocks-map .vpb-map-tile')].map(i => parseFloat(i.style.width))")
    res['zoom animates (tiles mid-scale)'] = any(abs(x - 256.5) > 5 and abs(x - 512.5) > 5 for x in ws)
    # 2. Old level stays visible while the new one loads (tiles are slow now).
    pg.wait_for_timeout(250)
    levels = pg.evaluate("[...new Set([...document.querySelectorAll('.special-blocks-map .vpb-map-tile')].map(i => i.src.split('/')[3]))]")
    res['old tiles kept while new load'] = len(levels) >= 2
    slow['on'] = False
    for r in held: r.fulfill(status=200, body=TILE, content_type='image/png')
    pg.wait_for_timeout(800)
    levels2 = pg.evaluate("[...new Set([...document.querySelectorAll('.special-blocks-map .vpb-map-tile')].map(i => i.src.split('/')[3]))]")
    res['old tiles removed once loaded'] = levels2 == [str(z0 + 1)]
    slow['on'] = False
    # 3. Momentum: quick drag keeps moving after release.
    pinx = lambda: pg.evaluate("document.querySelector('.special-blocks-map .vpb-map-pin').getBoundingClientRect().left")
    a = pinx()
    pg.mouse.move(box['x'] + 300, box['y'] + 200); pg.mouse.down()
    for k in range(1, 6): pg.mouse.move(box['x'] + 300 + k * 30, box['y'] + 200); pg.wait_for_timeout(10)
    pg.mouse.up(); at_release = pinx(); pg.wait_for_timeout(600); after = pinx()
    res['momentum (keeps drifting after release)'] = round(after - at_release)
    # 4. Popup near the edge: the map moves so it's fully visible.
    pg.evaluate("document.querySelector('.special-blocks-map').focus()")
    far = pg.locator('.special-blocks-map .vpb-map-pin').nth(1)
    # put the far pin near the top edge first
    fb = far.bounding_box()
    pg.mouse.move(box['x'] + 600, box['y'] + 200); pg.mouse.down(); pg.mouse.move(box['x'] + 600 + (box['x'] + box['width'] - 40 - fb['x']) , box['y'] + 200 - (fb['y'] - box['y'] - 20), steps=10); pg.wait_for_timeout(150); pg.mouse.up(); pg.wait_for_timeout(700)
    far.click(); pg.wait_for_timeout(500)
    inside = pg.evaluate("""() => { const m = document.querySelector('.special-blocks-map').getBoundingClientRect(), p = document.querySelector('.special-blocks-map .vpb-map-popup').getBoundingClientRect(); return p.top >= m.top && p.right <= m.right && p.left >= m.left && p.bottom <= m.bottom; }""")
    res['popup moved into view'] = inside
    pg.keyboard.press('Escape')
    # 5. Double tap (touch) zooms in.
    cdp = ctx.new_cdp_session(pg)
    z1 = zoomlevel(); cx, cy = box['x'] + 500, box['y'] + 250
    for _ in range(2):
        cdp.send('Input.dispatchTouchEvent', {'type': 'touchStart', 'touchPoints': [{'x': cx, 'y': cy, 'id': 7}]})
        cdp.send('Input.dispatchTouchEvent', {'type': 'touchEnd', 'touchPoints': []}); pg.wait_for_timeout(80)
    pg.wait_for_timeout(500)
    res['double tap zooms in'] = f'{z1} -> {zoomlevel()}'
    # 6. Editor: double click zooms and adds no place.
    pg.goto(BASE + '/login'); pg.fill('#email', 'admin@example.com'); pg.fill('#password', 'Test-password-123'); pg.click('#loginform [type=submit]'); pg.wait_for_load_state()
    pg.goto(f"{BASE}/admin/visual-page-builder/dhs/{page['o:id']}"); pg.wait_for_function('window.VPB && VPB.store'); pg.wait_for_timeout(600)
    pg.evaluate("VPB.app.select({kind:'block', id: VPB.store.state.sections.flatMap(s=>s.cols.flatMap(c=>c.blocks)).find(b=>b.layout==='specialMap').id})"); pg.wait_for_timeout(600)
    pb = pg.locator('.pm-map').bounding_box()
    n0 = pg.evaluate("VPB.store.state.sections.flatMap(s=>s.cols.flatMap(c=>c.blocks)).find(b=>b.layout==='specialMap').data.places.length")
    pg.mouse.dblclick(pb['x'] + 60, pb['y'] + pb['height'] - 40); pg.wait_for_timeout(600)
    n1 = pg.evaluate("VPB.store.state.sections.flatMap(s=>s.cols.flatMap(c=>c.blocks)).find(b=>b.layout==='specialMap').data.places.length")
    res['editor double-click adds no place'] = n0 == n1
    pg.mouse.click(pb['x'] + 60, pb['y'] + pb['height'] - 40); pg.wait_for_timeout(600)
    res['editor single click still adds one'] = pg.locator('.pm-new').count() == 1
    b.close()
print(json.dumps({'results': res, 'errors': errs}, indent=1))
