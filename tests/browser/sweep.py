import json, re, sys, time
from playwright.sync_api import sync_playwright
BASE = sys.argv[1] if len(sys.argv) > 1 else 'http://127.0.0.1:8080'
EMAIL, PW = 'admin@example.com', 'Test-password-123'
JQ_PATH = sys.argv[2] if len(sys.argv) > 2 else '/home/claude/omeka-s/application/asset/vendor/jquery/jquery.min.js'
problems = []
def watch(page, where):
    page.on('pageerror', lambda e: problems.append(f'[{where}] JS error: {e}'))
    page.on('console', lambda m: m.type == 'error' and not re.search(r'tile\.openstreetmap|ERR_|Failed to load resource|youtube|net::', m.text) and problems.append(f'[{where}] console: {m.text[:200]}'))
with sync_playwright() as p:
    b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args=['--no-sandbox'])
    ctx = b.new_context(viewport={'width': 1366, 'height': 900})
    # This sandbox can't reach code.jquery.com (Omeka's own CDN copy); serve Omeka's bundled jQuery instead.
    JQ = open(JQ_PATH, 'rb').read()
    ctx.route('**/code.jquery.com/**', lambda r: r.fulfill(status=200, body=JQ, content_type='application/javascript'))
    page = ctx.new_page(); watch(page, 'admin')
    page.goto(BASE + '/login'); page.fill('#email', EMAIL); page.fill('#password', PW); page.click('#loginform [type=submit]'); page.wait_for_load_state()
    # A page with every Special Block, made in the editor.
    page.goto(BASE + '/admin/visual-builder/dhs'); page.wait_for_load_state()
    if 'Visual Builder' not in page.content(): problems.append('hub did not load')
    # create page via standard add form
    page.goto(BASE + '/admin/site/s/dhs/add-page')
    page.fill('input[name="o:title"]', 'Sweep ' + str(int(time.time())))
    page.click('button[type=submit], input[type=submit]'); page.wait_for_load_state()
    pid = re.search(r'/page/([\w-]+)', page.url)
    # find page id via API
    import urllib.request
    pages = json.load(urllib.request.urlopen(BASE + '/api/site_pages?sort_by=id&sort_order=desc&per_page=1&key_identity=testkey&key_credential=testcred'))
    page_id = pages[0]['o:id']; slug = pages[0]['o:slug']
    ed = ctx.new_page(); watch(ed, 'editor')
    ed.goto(f'{BASE}/admin/visual-page-builder/dhs/{page_id}'); ed.wait_for_function('window.VPB && VPB.store')
    names = ed.evaluate("VPB.cfg.layouts.filter(l => l.name.startsWith('special')).map(l => l.name)")
    ed.evaluate("""names => { names.forEach(n => { const b = VPB.model.newBlock(n); VPB.store.state.sections.push(VPB.model.sectionWith(b)); }); VPB.store.commit(); VPB.app.renderAll(); }""", names)
    ed.wait_for_timeout(3000)  # live previews
    # Click through: select each block, open sidebar
    n = ed.evaluate("VPB.store.state.sections.length")
    for i in range(n):
        ed.evaluate(f"VPB.app.select({{kind:'block', id: VPB.store.state.sections[{i}].cols[0].blocks[0].id}})")
    ed.wait_for_timeout(1500)
    ok = ed.evaluate("VPB.save.save()")
    ed.wait_for_timeout(1500)
    if not ok: problems.append('saving the all-blocks page failed')
    # Device views, preview, + bar, templates tab
    for dev in ['tablet', 'phone', 'desktop']:
        ed.click(f'[data-dev="{dev}"]'); ed.wait_for_timeout(300)
    ed.click('#previewBtn'); ed.wait_for_timeout(300); ed.click('#previewBtn')
    ed.locator('.ins-plus').first.click(); ed.wait_for_timeout(300); ed.keyboard.press('Escape')
    # Public page with every block
    pub = ctx.new_page(); watch(pub, 'public')
    pub.goto(f'{BASE}/s/dhs/page/{slug}'); pub.wait_for_load_state('networkidle'); pub.wait_for_timeout(1500)
    html = pub.content()
    for bad in ['Fatal error', 'Warning:', 'Notice:', 'Deprecated:']:
        if bad in html: problems.append(f'[public] PHP {bad} in page')
    # Story map + quiz + timeline buttons interact
    if pub.locator('.special-blocks-story-play').count():
        pub.locator('.special-blocks-story-next').first.click(); pub.wait_for_timeout(300)
    if pub.locator('.special-blocks-quiz-check').count():
        pub.locator('.special-blocks-quiz-check').first.click()
    # Mobile width
    m = ctx.new_page(); watch(m, 'public-phone'); m.set_viewport_size({'width': 390, 'height': 800})
    m.goto(f'{BASE}/s/dhs/page/{slug}'); m.wait_for_load_state('networkidle')
    sw = m.evaluate("document.documentElement.scrollWidth"); 
    if sw > 400: problems.append(f'[public-phone] page scrolls sideways ({sw}px wide)')
    # Other admin screens
    for path in ['/admin/visual-builder', '/admin/visual-page-builder/menu/dhs/edit', '/admin/visual-page-builder/region/dhs/header']:
        a = ctx.new_page(); watch(a, path); a.goto(BASE + path); a.wait_for_load_state('networkidle'); a.wait_for_timeout(800)
        if any(x in a.content() for x in ['Fatal error', 'Warning:']): problems.append(f'[{path}] PHP error text')
    print(json.dumps({'blocks': len(names), 'page': page_id, 'problems': problems}, indent=1))
    b.close()
