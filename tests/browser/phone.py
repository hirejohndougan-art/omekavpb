import sys
from playwright.sync_api import sync_playwright
JQ = open('/home/claude/omeka-s/application/asset/vendor/jquery/jquery.min.js', 'rb').read()
errs = []
with sync_playwright() as p:
    b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args=['--no-sandbox'])
    for w in (390, 768, 1366):
        ctx = b.new_context(viewport={'width': w, 'height': 800}); ctx.route('**/code.jquery.com/**', lambda r: r.fulfill(status=200, body=JQ, content_type='application/javascript'))
        pg = ctx.new_page(); pg.on('pageerror', lambda e: errs.append(str(e)))
        for path in ['/s/dhs', '/s/dhs/page/' + sys.argv[1]]:
            pg.goto('http://127.0.0.1:8080' + path); pg.wait_for_load_state('networkidle')
            sw = pg.evaluate('document.documentElement.scrollWidth')
            if sw > w + 2: errs.append(f'{w}px {path}: page {sw}px wide (scrolls sideways)')
            wide = pg.evaluate("[...document.querySelectorAll('body *')].filter(e => e.getBoundingClientRect().right > innerWidth + 2 && getComputedStyle(e).position !== 'fixed' && !e.closest('[style*=overflow], .special-blocks-tl-track, .vpb-map, .special-blocks-story-timeline')).slice(0,3).map(e => e.tagName + '.' + e.className)")
            if wide and sw > w + 2: errs.append(f'  widest: {wide}')
        ctx.close()
    b.close()
print(errs or 'no problems')
