import json, sys
from playwright.sync_api import sync_playwright
BASE = 'http://127.0.0.1:8080'
JQ = open('/home/claude/omeka-s/application/asset/vendor/jquery/jquery.min.js', 'rb').read()
AXE = open('/tmp/pw/node_modules/axe-core/axe.min.js').read()
pid, slug = sys.argv[1], sys.argv[2]
out = {}
with sync_playwright() as p:
    b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args=['--no-sandbox'])
    ctx = b.new_context(viewport={'width': 1366, 'height': 900})
    ctx.route('**/code.jquery.com/**', lambda r: r.fulfill(status=200, body=JQ, content_type='application/javascript'))
    pg = ctx.new_page()
    pg.goto(BASE + '/login'); pg.fill('#email', 'admin@example.com'); pg.fill('#password', 'Test-password-123'); pg.click('#loginform [type=submit]'); pg.wait_for_load_state()
    for name, url, scope in [('public', f'{BASE}/s/dhs/page/{slug}', '.special-blocks, .vpb-section, main'), ('editor', f'{BASE}/admin/visual-page-builder/dhs/{pid}', 'body'), ('hub', f'{BASE}/admin/visual-builder/dhs', '#content'), ('menu', f'{BASE}/admin/visual-page-builder/menu/dhs/edit', 'body'), ('header', f'{BASE}/admin/visual-page-builder/region/dhs/header', 'body'), ('home', f'{BASE}/s/dhs', 'body')]:
        pg.goto(url); pg.wait_for_load_state('networkidle'); pg.wait_for_timeout(2500)
        pg.add_script_tag(content=AXE)
        r = pg.evaluate("""async (scope) => { const res = await axe.run(document, { runOnly: ['wcag2a', 'wcag2aa'] }); return res.violations.map(v => ({ id: v.id, impact: v.impact, n: v.nodes.length, ex: v.nodes.slice(0, 2).map(n => n.target.join(' ') + ' :: ' + (n.failureSummary || '').split('\\n')[1]) })); }""", scope)
        out[name] = r
    b.close()
print(json.dumps(out, indent=1)[:6000])
