# Tests

End-to-end tests for the Visual Page Builder and Special Blocks modules. They drive the real editor (its
own script files, loaded in the order `edit.phtml` lists them) inside jsdom,
against a live Omeka S install, and check what Omeka actually stored.

## Setup (once per container)

1. Build the Omeka test install: CONTINUE_HERE.md §7.
2. `npm install` (in the kit root).
3. Install the module (CONTINUE_HERE.md §7, step 7).
4. `npm run seed`: site "dhs", three items with two images each, one asset,
   a researcher user, and the Special Blocks fixtures (a second site "legacy",
   two item sets, three dated items). Then `test-env/set-researcher-password.sh`.
5. `bash /tmp/login.sh` (copy of `test-env/login.sh`) writes the admin session
   to `/tmp/jar.txt`.

## Running

```bash
npm test                 # every *.test.js, one at a time
npm test block-settings  # only files whose path contains this
npm run lint             # ESLint (editor + tests)
npm run lint:php         # Omeka's PHP style rules (see tools/README.md)
```

Each test creates its own page with a unique slug, so they can run repeatedly.

| File | What it covers |
|---|---|
| `module/save-roundtrip.test.js` | Import a standard page, rearrange into columns with styles, save, CSRF refusal, reopen gives the same layout and data, public columns |
| `module/block-settings.test.js` | Sidebar settings for every core block type through the UI, item/asset pickers, Save button, stored data, reopen, public page, oEmbed failure path |
| `module/edge.test.js` | Grid-6 import, script injection not run, embed blocks locked and saved unchanged, unknown module block kept, empty section |
| `module/permissions.test.js` | Logged out, researcher role, wrong site, missing page, POST-only save |
| `module/sidebar-tools.test.js` | v0.4.0: block search, ⓘ help, Layers outline (select, reorder), resizable sidebar (drag, release anywhere, keyboard, reset, remembered), live previews (real items, inert, cached, updates), preview endpoint guards |
| `module/menu-builder.test.js` | Menu builder: site list and admin link, add/indent/reorder/rename, style and preview, save, unsafe links and missing pages dropped, depth and token guards, researcher refused (restores the menu) |
| `theme/regions.test.js` | Header & footer: admin pages, page made once, editor region mode, header/footer templates only, save, theme shows them (logo, menu, search, site map, copyright), 404 on their own address, other themes ignore them, left out of page lists (deletes them afterward) |
| `theme/region-view.test.js` | Header/footer shown around the page in the editor, click to edit, the save question (save / don't / cancel), top-bar buttons remember the page, the page band leads back |
| `theme/theme.test.js` | Companion Theme: all five menu styles and their depth, sub-menu buttons, skip link, builder columns, no outside fonts, footer visit info (needs the special/ tests' saved settings), editor warning and page template dropped on save (restores the site's theme and menu) |
| `module/templates.test.js` | 30 page templates: six kinds × five, every block and setting valid against the schemas, Templates tab (replace asks, undo, add), each template saves and renders |
| `module/pages-list.test.js` | Modules › Page builder: admin link, sites, a site's pages with editor links, Find a page, header/footer/menu/add links, hidden pages left out, researcher refused |
| `module/insert-bars.test.js` | + bars above/between/below sections: click then pick a section or a block to add it there, Escape cancels, hidden in Preview |
| `module/bg-images.test.js` | Background images for sections, columns, blocks: picker, fit, position, darkening, saved in Omeka's own settings, reload, public page |
| `module/batch2.test.js` | Ready-made sections (added at the +), formatting bar and link rules, pictures in text, smaller-screen columns (editor views, saved classes), Preview on site (private copy, hidden, removed on save) |
| `module/titles-and-text.test.js` | Title + Show the title on every Special Block (except the 5 whose title is their content), titles drawn for blocks that had none, the formatting bar's controls and fonts, styles surviving Omeka's HTML cleaner |
| `module/anchors.test.js` | Table of contents from headings (H2/H3/H4 levels, reading order across columns, leave out/rename a heading through the panel, saved as `vpb_toc`, reload, nested numbered list, depth), Table link (canvas bar, id, no title), Link name retired, older pages (named spots only, h2h3, old link name), jump focus, Check page. Real-browser version: `tests/browser/toc.py` |
| `module/vpb-map.test.js` | The module's own map: Web Mercator math (matches the OSM tile formula), tiles, credit, pins (labeled buttons, placement), popups, keyboard, zoom buttons, fitting, flyTo, dashed lines, click lat/lng, drag, pinch, wheel only after click. Real-browser version: `tests/browser/maptest.py` |
| `module/v112.test.js` | Page history (kept per save, fetch, load as unsaved, undo, permissions), import (wrong file refused, add/replace), Duplicate, shared sections (new, make from a section, block list, public render, hidden address, edit once → everywhere, self-inclusion), footnotes (numbering, list at end, Footnotes block, purifier), Check page, print CSS |
| `module/palette-groups.test.js` | Every Special Block in exactly one palette group; group order on pages; Maps and Finding your way contents; the Table of contents rename; folding remembered; search opens groups; Works well with; header editing has no subgroups |
| `module/colors.test.js` | 18 background swatches and the custom color picker, white text on dark backgrounds (editor, saved class, public page), automatic class hidden from the CSS class field |
| `module/toc-preview.test.js` | Table of contents preview lists the page's real sub-pages from the site navigation, to the chosen depth (restores the navigation afterward) |
| `module/upgrade-rename.test.js` | Pages using the old Society Blocks layouts are converted on install (uninstalls and reinstalls the module through Omeka's admin) |
| `module/load-order.test.js` | No editor script reads a part of `VPB` that loads after it; every script file is loaded |
| `special/render.test.js` | All 14 Special Blocks: saved data cleaned and typed, public output, multi-site items and links, on-this-day matching, safe links |
| `special/standard-editor.test.js` | Special Blocks forms in Omeka's standard editor render and save (form submitted like Omeka's page script does) |
| `special/visual-editor.test.js` | Schema-built sidebar forms in the Visual Page Builder: show_if, site checklists, asset picker, list entries, save and reopen |
| `special/visit-info-and-events.test.js` | Visit info from a site's settings (saved through the real settings form); Upcoming events from a calendar feed served by a local Python server on port 8091 (merge, order, hidden past/cancelled, safe links, broken feed, cache) |
| `special/more-blocks.test.js` | The 13 blocks added in 0.7.0: public output (date windows, carousel and gallery markup, decades and subject counts, transcripts, safe links, https-only newsletter, filled citation) and editor palette/previews |
| `special/v011-blocks.test.js` | The 8 blocks added in 0.11.0 on the public site (markers, sortable table, timeline, daily fact, open now, trusted embed only, share links, breadcrumbs from the menu) and in the editor (embed placeholder, palette) |
| `special/map-timeline.test.js` | Map of places (pins, item links, tour, list, bundled Leaflet, editor pin tool) and Timeline (items + events, order, across/down) |
| `special/timeline-entries.test.js` | Timeline entries (linked item fills title/date, picture choice, details, no duplicates, card size, show pictures/details) and map place pictures; sidebar entries open one at a time |
| `special/story-map.test.js` | Story map: date order, panes with photos, privacy-mode videos loaded only when shown, untrusted videos refused, works without JS; the player (next, timeline, rewind, play/pause, auto-advance); editor tool with date |
| `special/quiz.test.js` | Archive quiz: questions filtered, radio groups, search links, hint, answers not plain in the page, checking right/wrong, explanation and item, score, Try again |
| `special/quiz.test.js` | Archive quiz: questions left out when incomplete, search links, hints, answers not plain in the page, checking, explanations, item link, locking, score, Try again |
| `special/oral-history-sync.test.js` | Synced oral history: stamps in several formats, lines without stamps, caption (.vtt) files, topic index, highlighting while playing, click to jump, topics, search with marks and counts |
| `special/multisite.test.js` | The `special_blocks_site_ids` API filter for items and item sets, no duplicates |
| `special/php-unit.test.js` | Runs `special/php/unit.php`: Normalizer, On this day date matching, Upcoming events filtering (needs `OMEKA_PATH` if Omeka isn't at /home/claude/omeka-s) |

`lib/harness.js` holds the shared helpers (open the editor, UI actions, API calls).

## Refactoring safely: golden master

`tests/tools/golden.js` records what the editor renders (canvas, palette, and
the settings panel for every section, column, and block) plus the exported
data, for chosen pages. Record before a refactor and compare after:

```bash
npm run golden:record -- /tmp/golden.json 17 18 20
# …change code…
npm run golden:compare -- /tmp/golden.json
```

## Not covered

jsdom has no layout engine: real-browser look, pointer dragging, and column
resizing by mouse are not tested. Check those by hand in a browser.
