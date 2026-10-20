# Real-browser checks (headless Chromium via Python Playwright)

Not part of `npm test` (they need Chromium and Python Playwright). Added in
the v1.8.1 bug sweep. Run against the test Omeka at 127.0.0.1:8080:

    python3 tests/browser/sweep.py http://127.0.0.1:8080        # every Special Block in the editor + public page, device views, hub, menu, header; JS errors
    python3 tests/browser/interact.py                            # formatting bar, map pin tool, list rows, story map, timeline
    python3 tests/browser/interact2.py                           # drag onto a + bar, undo/redo, synced oral history (needs the "Synced interview" item), quiz
    python3 tests/browser/axe2.py <page id> <page slug>          # accessibility (needs: npm install --no-save axe-core in the folder you run from; edit AXE path)
    python3 tests/browser/toc.py                                 # Table of contents: heading panel follows the cursor, Table link drag, no space on the site, jump
    python3 tests/browser/toc-indent.py                          # Table of contents list: indent and outdent (arrows, drag, keys), rename, leave out, save
    python3 tests/browser/phone.py <page slug>                   # sideways scrolling at 390 / 768 / 1366 px

Chromium path: /opt/pw-browsers/chromium-1194/chrome-linux/chrome. The
sandbox can't reach code.jquery.com, so the scripts serve Omeka's bundled
jQuery for that address. Errors from api.omeka.org (version check) and map
tiles are network noise here.
