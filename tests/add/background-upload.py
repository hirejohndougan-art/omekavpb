#!/usr/bin/env python3
# Background uploads on Add an item (Phase 7), in a real browser. Picks two files on the Files step,
# waits for the status line, then continues: the files are added once, not again on Continue.
# Needs the test install running, Playwright, and the sandbox admin test@example.com.
# Usage: python3 tests/add/background-upload.py  (BASE, ADMIN_EMAIL, ADMIN_PASSWORD env vars override)
import os, re, struct, subprocess, sys, zlib, tempfile
from playwright.sync_api import sync_playwright

BASE = os.environ.get('BASE', 'http://127.0.0.1:8080')
EMAIL = os.environ.get('ADMIN_EMAIL', 'test@example.com')
PASSWORD = os.environ.get('ADMIN_PASSWORD', 'testpass-local')
KEY, CRED = os.environ.get('VPB_API_KEY', 'testkey'), os.environ.get('VPB_API_CRED', 'testcred')
results = []

def check(ok, label):
    results.append(ok)
    print(('ok   ' if ok else 'FAIL ') + label)

def media_count(item):
    out = subprocess.run(['mysql', '-uroot', 'omeka_test', '-N', '-e', f'SELECT COUNT(*) FROM media WHERE item_id={item}'],
                         capture_output=True, text=True).stdout.strip()
    return int(out or 0)

def png(path):
    raw = b'\x00\xff\x00\x00'
    def chunk(tag, data):
        return struct.pack('>I', len(data)) + tag + data + struct.pack('>I', zlib.crc32(tag + data) & 0xffffffff)
    data = b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('>IIBBBBB', 1, 1, 8, 2, 0, 0, 0)) + chunk(b'IDAT', zlib.compress(raw)) + chunk(b'IEND', b'')
    with open(path, 'wb') as f:
        f.write(data)

with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=os.environ.get('CHROME_PATH', '/opt/pw-browsers/chromium-1194/chrome-linux/chrome'), args=['--no-sandbox'])
    page = browser.new_page(viewport={'width': 1280, 'height': 900})
    page.goto(BASE + '/admin/login')
    page.fill('#email', EMAIL)
    page.fill('#password', PASSWORD)
    page.click('input[name="submit"]')
    page.wait_for_load_state()

    page.goto(BASE + '/admin/vpb-add')
    page.click('button:has-text("Start")')
    page.wait_for_load_state()
    m = re.search(r'item=(\d+)', page.url)
    item = int(m.group(1)) if m else 0
    check(bool(item), 'a new draft item is started')
    if not item:
        sys.exit(1)
    page.goto(f'{BASE}/admin/vpb-add?item={item}&step=1')
    before = media_count(item)

    tmp = tempfile.mkdtemp()
    a, b = os.path.join(tmp, 'first.png'), os.path.join(tmp, 'second.png')
    png(a); png(b)
    page.set_input_files('input[type="file"]', [a, b])
    page.wait_for_function("document.getElementById('vpb-upload-status').textContent.indexOf('files added') !== -1", timeout=30000)
    check(media_count(item) == before + 2, 'two background uploads add two files')
    check('2 files added.' in page.inner_text('#vpb-upload-status'), 'the status line says 2 files added')
    check(page.eval_on_selector('input[type="file"]', 'el => el.files.length') == 0, 'the file input is cleared once the files are in')

    page.click('button:has-text("Add files and continue")')
    page.wait_for_load_state()
    check('step=2' in page.url, 'Continue moves on to the Describe step')
    check(media_count(item) == before + 2, 'Continue does not add the files a second time')

    subprocess.run(['curl', '-s', '-o', '/dev/null', '-X', 'DELETE', f'{BASE}/api/items/{item}?key_identity={KEY}&key_credential={CRED}'])
    browser.close()

print(f'{sum(results)} passed, {len(results) - sum(results)} failed')
sys.exit(0 if all(results) else 1)
