import json, re, sys, urllib.request
from playwright.sync_api import sync_playwright
BASE = 'http://127.0.0.1:8080'
JQ = open('/home/claude/omeka-s/application/asset/vendor/jquery/jquery.min.js', 'rb').read()
def api(path, method='GET', data=None):
    req = urllib.request.Request(BASE + '/api/' + path + ('&' if '?' in path else '?') + 'key_identity=testkey&key_credential=testcred', method=method,
        data=json.dumps(data).encode() if data else None, headers={'Content-Type': 'application/json'})
    return json.load(urllib.request.urlopen(req))
site = api('sites?slug=dhs')[0]['o:id']
items = api('items?per_page=3')
page = api('site_pages', 'POST', {'o:site': {'o:id': site}, 'o:title': 'Interact test', 'o:slug': 'interact-test-' + str(items[0]['o:id']) + str(len(api('site_pages?per_page=1&sort_by=id&sort_order=desc'))) + str(__import__('time').time()).replace('.', ''), 'o:is_public': True, 'o:block': [
    {'o:layout': 'html', 'o:data': {'html': '<p>Hello brave world</p>'}},
    {'o:layout': 'specialMap', 'o:data': {'places': [{'name': 'A', 'lat': '29.46', 'lng': '-95.05'}]}},
    {'o:layout': 'specialStoryMap', 'o:data': {'stops': [{'title': 'One', 'date': '1900', 'lat': '29.46', 'lng': '-95.05'}, {'title': 'Two', 'date': '1910', 'lat': '29.47', 'lng': '-95.04'}]}},
    {'o:layout': 'specialTimeline', 'o:data': {'events': [{'title': 'E1', 'date': '1900'}, {'title': 'E2', 'date': '1901'}]}}]})
pid, slug = page['o:id'], page['o:slug']
res = {}
errs = []
with sync_playwright() as p:
    b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args=['--no-sandbox'])
    ctx = b.new_context(viewport={'width': 1366, 'height': 900})
    ctx.route('**/code.jquery.com/**', lambda r: r.fulfill(status=200, body=JQ, content_type='application/javascript'))
    pg = ctx.new_page()
    pg.on('pageerror', lambda e: errs.append(str(e)))
    pg.goto(BASE + '/login'); pg.fill('#email', 'admin@example.com'); pg.fill('#password', 'Test-password-123'); pg.click('#loginform [type=submit]'); pg.wait_for_load_state()
    pg.goto(f'{BASE}/admin/visual-page-builder/dhs/{pid}'); pg.wait_for_function('window.VPB && VPB.store'); pg.wait_for_timeout(1500)
    # 1. Formatting bar: select "brave", Bold, then font.
    txt = pg.locator('.b-html').first
    txt.click()
    pg.evaluate("""() => { const el = document.querySelector('.b-html'); const r = document.createRange(); const t = el.querySelector('p').firstChild; r.setStart(t, 6); r.setEnd(t, 11); const s = getSelection(); s.removeAllRanges(); s.addRange(r); }""")
    res['bar visible'] = pg.locator('.rt-bar').first.is_visible()
    pg.locator('.rt-bar [aria-label="Bold (Ctrl+B)"]').first.click()
    pg.locator('.rt-bar select[aria-label="Font"]').first.select_option(label='Georgia')
    pg.locator('.rt-bar select[aria-label="Size"]').first.select_option(label='Large')
    pg.on('dialog', lambda d: d.accept('https://example.org'))
    pg.locator('.rt-bar [aria-label="Link"]').first.click()
    pg.wait_for_timeout(300)
    html = pg.evaluate("VPB.store.state.sections[0].cols[0].blocks[0].data.html")
    res['bold'] = bool(re.search(r'font-weight:\s*bold|<b>|<strong>', html))
    res['font'] = 'Georgia' in html
    res['size'] = 'font-size' in html
    res['link'] = 'href="https://example.org"' in html
    res['html'] = html[:300]
    # 2. Map pin tool: select the map block, click the sidebar map.
    pg.evaluate("VPB.app.select({kind:'block', id: VPB.store.state.sections.flatMap(s=>s.cols.flatMap(c=>c.blocks)).find(b=>b.layout==='specialMap').id})")
    pg.wait_for_timeout(800)
    mp = pg.locator('.pm-map')
    res['map tool has tiles'] = pg.locator('.pm-map .vpb-map-tile').count() > 0
    box = mp.bounding_box()
    pg.mouse.click(box['x'] + box['width'] * 0.3, box['y'] + box['height'] * 0.4)
    pg.wait_for_timeout(500)
    res['new place form'] = pg.locator('.pm-new #pm-new-name').count() == 1
    if res['new place form']:
        pg.fill('#pm-new-name', 'Clicked spot'); pg.click('.pm-new button:has-text("Done")'); pg.wait_for_timeout(300)
    places = pg.evaluate("VPB.store.state.sections.flatMap(s=>s.cols.flatMap(c=>c.blocks)).find(b=>b.layout==='specialMap').data.places")
    res['place added'] = len(places) == 2 and places[1]['name'] == 'Clicked spot'
    # 3. List rows open one at a time (Places list).
    rows = pg.locator('.lrow')
    res['rows'] = rows.count()
    if rows.count() >= 2:
        rows.nth(0).locator('.lrow-head').click(); rows.nth(1).locator('.lrow-head').click()
        res['one open'] = pg.locator('.lrow.open').count() == 1
    # 4. Header band save question (no header built: may be absent).
    res['header band present'] = pg.locator('#regionTop .rv-header').count()
    # Save
    res['saved'] = pg.evaluate("VPB.save.save()")
    # 5. Public: story map and maps draw with Leaflet.
    pub = ctx.new_page(); pub.on('pageerror', lambda e: errs.append('public: ' + str(e)))
    pub.goto(f'{BASE}/s/dhs/page/{slug}'); pub.wait_for_load_state('networkidle'); pub.wait_for_timeout(1500)
    res['places map markers'] = pub.locator('.special-blocks-places-map .vpb-map-pin').count()
    res['story pins'] = pub.locator('.special-blocks-story-pin').count()
    pub.click('.special-blocks-story-next'); pub.wait_for_timeout(1600)
    res['story pane 2 shown'] = pub.evaluate("[...document.querySelectorAll('.special-blocks-story-pane')].findIndex(p => !p.hidden)") == 1
    res['story current pin'] = pub.locator('.special-blocks-story-pin.is-current').count() == 1
    res['public text bold'] = pub.locator('.page-content b, .page-content span[style*="bold"], b, strong').count() > 0
    tl = pub.locator('.special-blocks-timeline-horizontal .special-blocks-tl-nav')
    res['timeline nav shown'] = tl.count() == 1 and tl.is_visible()
    b.close()
print(json.dumps({'results': res, 'errors': errs}, indent=1))
