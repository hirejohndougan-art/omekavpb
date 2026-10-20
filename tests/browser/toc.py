# v1.14.0: Table of contents from headings, in a real browser: the heading
# panel follows the cursor, leave-out/rename, Found on this page, Table link
# bar (drag it), save, and the public page (no extra space for a Table link).
import json, time, urllib.request
from playwright.sync_api import sync_playwright
BASE = 'http://127.0.0.1:8080'
JQ = open('/home/claude/omeka-s/application/asset/vendor/jquery/jquery.min.js', 'rb').read()
def api(path, method='GET', data=None):
    req = urllib.request.Request(BASE + '/api/' + path + ('&' if '?' in path else '?') + 'key_identity=testkey&key_credential=testcred', method=method, data=json.dumps(data).encode() if data else None, headers={'Content-Type': 'application/json'})
    return json.load(urllib.request.urlopen(req))
site = api('sites?slug=dhs')[0]['o:id']
slug = 'toc-browser-' + str(int(time.time()))
page = api('site_pages', 'POST', {'o:site': {'o:id': site}, 'o:title': 'TOC browser', 'o:slug': slug, 'o:is_public': True, 'o:block': [
    {'o:layout': 'specialJumpLinks', 'o:data': {'heading': 'On this page', 'levels': 'h3', 'style': 'list', 'numbered': True}},
    {'o:layout': 'html', 'o:data': {'html': '<h2>The railroad arrives</h2><p>One.</p><h3>The first depot</h3><p>Two.</p>'}},
    {'o:layout': 'html', 'o:data': {'html': '<p>Above the spot.</p>'}},
    {'o:layout': 'specialTableLink', 'o:data': {'name': 'Where it happened', 'level': '1'}},
    {'o:layout': 'html', 'o:data': {'html': '<p>Below the spot.</p>'}},
    {'o:layout': 'html', 'o:data': {'html': '<h2>The Great Storm of September 8, 1900</h2><p>Three.</p>'}}]})
res, errs = {}, []
with sync_playwright() as p:
    b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args=['--no-sandbox'])
    ctx = b.new_context(viewport={'width': 1366, 'height': 900})
    ctx.route('**/code.jquery.com/**', lambda r: r.fulfill(status=200, body=JQ, content_type='application/javascript'))
    pg = ctx.new_page(); pg.on('pageerror', lambda e: errs.append(str(e)))
    pg.goto(BASE + '/login'); pg.fill('#email', 'admin@example.com'); pg.fill('#password', 'Test-password-123'); pg.click('#loginform [type=submit]'); pg.wait_for_load_state()
    pg.goto(f"{BASE}/admin/visual-page-builder/dhs/{page['o:id']}"); pg.wait_for_function('window.VPB && VPB.store'); pg.wait_for_timeout(800)
    toc = lambda: pg.locator('.blk').first.locator('.special-blocks-jump-list li').evaluate_all('ls => ls.map(l => l.firstChild.textContent + " " + l.querySelector("a").textContent)')
    res['canvas list'] = toc()
    res['table link bar'] = pg.locator('.b-tablelink').inner_text()
    # Click into the storm heading (a real click puts the cursor there).
    pg.locator('.b-html h2', has_text='Great Storm').click(); pg.wait_for_timeout(300)
    res['panel follows cursor'] = 'a section (Heading 2)' in pg.locator('#side .rt-toc').inner_text()
    pg.fill('#f-toc-name', 'The Great Storm'); pg.locator('#f-toc-name').press('Tab'); pg.wait_for_timeout(400)
    pg.locator('.b-html h3', has_text='first depot').click(); pg.wait_for_timeout(300)
    res['panel moved to h3'] = 'a subsection (Heading 3)' in pg.locator('#side .rt-toc').inner_text()
    pg.locator('#f-toc-show').uncheck(); pg.wait_for_timeout(400)
    res['canvas after'] = toc()
    res['badges'] = pg.evaluate("""() => [...document.querySelectorAll('.b-html [data-toc-hide], .b-html [data-toc-name]')].map(e => getComputedStyle(e, '::after').content)""")
    pg.locator('.blk').first.click(); pg.wait_for_timeout(300)
    res['found'] = pg.locator('.toc-found-item').all_inner_texts()
    pg.locator('#side').screenshot(path='/tmp/pw/toc-side.png')
    pg.screenshot(path='/tmp/pw/toc-editor.png')
    # Drag the Table link above the first text block with its handle.
    tl = pg.locator('.blk', has=pg.locator('.b-tablelink'))
    tl.locator('.b-tablelink').click(); pg.wait_for_timeout(300); tl.hover(); h = tl.locator('.tools .handle'); hb = h.bounding_box()
    print('partial', json.dumps(res))
    target = pg.locator('.blk', has=pg.locator('h2', has_text='railroad')).bounding_box()
    pg.mouse.move(hb['x'] + 5, hb['y'] + 5); pg.mouse.down(); pg.mouse.move(hb['x'] + 30, hb['y'] - 40, steps=5)
    pg.mouse.move(target['x'] + 60, target['y'] + 6, steps=12); pg.mouse.up(); pg.wait_for_timeout(500)
    res['after drag'] = toc()
    pg.keyboard.press('Control+z'); pg.wait_for_timeout(400)
    res['after undo'] = toc()
    pg.click('#saveBtn'); pg.wait_for_timeout(2500)
    res['saved'] = pg.locator('#status').inner_text()
    pub = ctx.new_page(); pub.on('pageerror', lambda e: errs.append('public: ' + str(e)))
    pub.goto(f"{BASE}/s/dhs/page/{slug}"); pub.wait_for_timeout(600)
    res['public list'] = pub.locator('.special-blocks-jump-links a').all_inner_texts()
    # The Table link adds no space: the gap between the paragraphs around it equals the normal gap.
    res['spacing'] = pub.evaluate("""() => {
      const ps = [...document.querySelectorAll('.block-html p')];
      const a = ps.find(p => /Above the spot/.test(p.textContent)).closest('.block').getBoundingClientRect();
      const b = ps.find(p => /Below the spot/.test(p.textContent)).closest('.block').getBoundingClientRect();
      const t = document.getElementById('where-it-happened').getBoundingClientRect();
      const blocks = [...document.querySelectorAll('.vpb-column > .block')].map(x => x.getBoundingClientRect());
      const gaps = blocks.slice(1).map((r, i) => Math.round(r.top - blocks[i].bottom));
      return { between: Math.round(b.top - a.bottom), gaps: gaps, spotHeight: t.height, spotAt: Math.round(t.top - a.bottom) };
    }""")
    pub.locator('.special-blocks-jump-links a', has_text='Where it happened').click(); pub.wait_for_timeout(900)
    res['jumped'] = pub.evaluate("() => location.hash + ' focus=' + (document.activeElement && document.activeElement.id)")
    pub.screenshot(path='/tmp/pw/toc-public.png', full_page=True)
    b.close()
print(json.dumps({'results': res, 'errors': errs}, indent=1))
