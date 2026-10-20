import json, time, urllib.request
from playwright.sync_api import sync_playwright
BASE = 'http://127.0.0.1:8080'
JQ = open('/home/claude/omeka-s/application/asset/vendor/jquery/jquery.min.js', 'rb').read()
def api(path, method='GET', data=None):
    req = urllib.request.Request(BASE + '/api/' + path + ('&' if '?' in path else '?') + 'key_identity=testkey&key_credential=testcred', method=method, data=json.dumps(data).encode() if data else None, headers={'Content-Type': 'application/json'})
    return json.load(urllib.request.urlopen(req))
site = api('sites?slug=dhs')[0]['o:id']
oh = api('items?fulltext_search=Synced%20interview&per_page=1')[0]['o:id']
two = lambda b: [b, b]
blocks = []
for b in [
    {'o:layout': 'specialMap', 'o:data': {'places': [{'name': 'A', 'lat': '29.46', 'lng': '-95.05'}]}},
    {'o:layout': 'specialStoryMap', 'o:data': {'stops': [{'title': 'One', 'date': '1900', 'lat': '29.46', 'lng': '-95.05'}]}},
    {'o:layout': 'specialTimeline', 'o:data': {'events': [{'title': 'E1', 'date': '1900'}]}},
    {'o:layout': 'specialArchiveQuiz', 'o:data': {'questions': [{'question': 'Q?', 'choices': 'A\nB', 'correct': 1}]}},
    {'o:layout': 'specialOralHistory', 'o:data': {'item_id': oh}},
    {'o:layout': 'specialAccordion', 'o:data': {'panels': [{'title': 'T', 'body': '<p>x</p>'}]}},
    {'o:layout': 'specialSlideshow', 'o:data': {}}, {'o:layout': 'specialNewsletter', 'o:data': {}}, {'o:layout': 'specialSearchBox', 'o:data': {}},
    {'o:layout': 'specialPhotoHotspots', 'o:data': {}}, {'o:layout': 'specialGallery', 'o:data': {'site_scope': 'all'}}, {'o:layout': 'specialOpenNow', 'o:data': {}}]:
    blocks += two(b)
page = api('site_pages', 'POST', {'o:site': {'o:id': site}, 'o:title': 'Dup ids', 'o:slug': 'dup-ids-' + str(int(time.time())), 'o:is_public': True, 'o:block': blocks})
with sync_playwright() as p:
    b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args=['--no-sandbox'])
    ctx = b.new_context(); ctx.route('**/code.jquery.com/**', lambda r: r.fulfill(status=200, body=JQ, content_type='application/javascript'))
    pg = ctx.new_page(); errs = []; pg.on('pageerror', lambda e: errs.append(str(e)))
    pg.goto(f"{BASE}/s/dhs/page/{page['o:slug']}"); pg.wait_for_load_state('networkidle')
    d = pg.evaluate("() => { const c = {}; document.querySelectorAll('[id]').forEach(e => c[e.id] = (c[e.id] || 0) + 1); return Object.entries(c).filter(([k, n]) => n > 1); }")
    lab = pg.evaluate("() => [...document.querySelectorAll('label[for]')].filter(l => !document.getElementById(l.htmlFor)).map(l => l.htmlFor)")
    radios = pg.evaluate("() => { const n = {}; document.querySelectorAll('input[type=radio]').forEach(r => n[r.name] = (n[r.name]||0) + 1); return n; }")
    print(json.dumps({'duplicate ids': d, 'labels pointing nowhere': lab, 'radio groups': radios, 'errors': errs}))
    pg.goto(BASE + '/login'); pg.fill('#email', 'admin@example.com'); pg.fill('#password', 'Test-password-123'); pg.click('#loginform [type=submit]'); pg.wait_for_load_state()
    pg.goto(f"{BASE}/admin/visual-page-builder/dhs/{page['o:id']}"); pg.wait_for_load_state('networkidle'); pg.wait_for_timeout(3000)
    d2 = pg.evaluate("() => { const c = {}; document.querySelectorAll('[id]').forEach(e => c[e.id] = (c[e.id] || 0) + 1); return Object.entries(c).filter(([k, n]) => n > 1); }")
    print(json.dumps({'editor duplicate ids': d2, 'errors': errs}))
    b.close()
