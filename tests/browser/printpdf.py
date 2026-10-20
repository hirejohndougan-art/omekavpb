import json, urllib.request
from playwright.sync_api import sync_playwright
pages = json.load(urllib.request.urlopen('http://127.0.0.1:8080/api/site_pages?sort_by=id&sort_order=desc&per_page=5&key_identity=testkey&key_credential=testcred'))
slug = [p for p in pages if p['o:title'] == 'Print me'][0]['o:slug']
with sync_playwright() as p:
    b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args=['--no-sandbox'])
    pg = b.new_page(); pg.route('**/code.jquery.com/**', lambda r: r.abort())
    pg.goto('http://127.0.0.1:8080/s/dhs/page/' + slug); pg.wait_for_load_state('networkidle')
    pg.pdf(path='/tmp/pw/print.pdf')
    b.close()
