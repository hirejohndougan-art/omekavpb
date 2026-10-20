"""Phase 10 check: accessibility (axe, WCAG 2.0/2.1 A and AA) and the phone width (no sideways
scrolling at 390 px) on the friendly admin screens. Run against the test Omeka:

    python3 tests/browser/vpb-admin-a11y.py [item id]

Needs Python Playwright, Chromium, and axe-core in /tmp/pw/node_modules (npm install --no-save axe-core).
Prints one line per screen; exits 1 if any screen has serious or critical violations or sideways scrolling.
"""
import sys
from playwright.sync_api import sync_playwright

BASE = 'http://127.0.0.1:8080'
JAR = '/tmp/jar.txt'
AXE = open('/tmp/pw/node_modules/axe-core/axe.min.js').read()
ITEM = sys.argv[1] if len(sys.argv) > 1 else None
SCREENS = [
    ('home', '/admin/vpb-home'),
    ('items', '/admin/vpb-items'),
    ('add', '/admin/vpb-add'),
    ('files', '/admin/vpb-files'),
    ('item-sets', '/admin/vpb-item-sets'),
    ('import', '/admin/vpb-import'),
    ('settings', '/admin/vpb-settings'),
    ('people', '/admin/vpb-people'),
    ('questions', '/admin/vpb-questions'),
    ('glossary', '/admin/vpb-glossary'),
    ('search-settings', '/admin/vpb-search-settings'),
]
if ITEM:
    SCREENS.append(('one item', f'/admin/vpb-item/{ITEM}'))

BAD = {'serious', 'critical'}
failed = False
with sync_playwright() as p:
    b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args=['--no-sandbox'])
    ctx = b.new_context(viewport={'width': 390, 'height': 844})
    # Signed in as the test administrator: reuse the cookie from the API test session (see tests/php).
    cookies = []
    for line in open(JAR):
        if line.startswith('#') and not line.startswith('#HttpOnly_') or not line.strip():
            continue
        f = line.rstrip('\n').split('\t')
        if len(f) >= 7:
            cookies.append({'name': f[5], 'value': f[6], 'domain': '127.0.0.1', 'path': '/'})
    ctx.add_cookies(cookies)
    pg = ctx.new_page()
    for name, path in SCREENS:
        pg.goto(BASE + path)
        pg.wait_for_load_state('networkidle')
        pg.add_script_tag(content=AXE)
        violations = pg.evaluate("""async () => {
            const res = await axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] });
            return res.violations.map(v => ({ id: v.id, impact: v.impact, n: v.nodes.length,
                where: v.nodes.slice(0, 2).map(n => n.target.join(' ')) }));
        }""")
        scroll = pg.evaluate('() => document.documentElement.scrollWidth - document.documentElement.clientWidth')
        serious = [v for v in violations if v['impact'] in BAD]
        ok = not serious and scroll <= 0
        failed = failed or not ok
        print(f"{'ok  ' if ok else 'FAIL'} {name}: {len(violations)} rule(s) flagged, {len(serious)} serious or critical, sideways scroll {max(scroll, 0)} px")
        for v in violations:
            print(f"       {v['impact'] or '-'} {v['id']} x{v['n']} {', '.join(v['where'])}")
    b.close()
sys.exit(1 if failed else 0)
