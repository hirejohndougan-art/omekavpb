# VISUAL PAGE BUILDER (Omeka S module, with Special Blocks) — CONTINUE HERE
### Handoff packet for a fresh chat. Last updated at the end of the Phase 2 session, v2.1.0 (October 19, 2026). Release notes: `HANDOFF_v0.2.0.md` through `HANDOFF_v2.1.0.md`.

---

## 0. READ THIS FIRST (for the next Claude)

**The container resets between sessions.** Nothing in `/home/claude/` or `/tmp/`
survives. The only authoritative artifact is this kit:

> **`VPB-TRANSFER-KIT_v2.0.0.zip`**, which contains Visual Page Builder v2.0.0 and the Companion Theme v2.0.0 (source + install zip; Special Blocks included),
> all three design stages, the test scripts, and the test-environment scripts.

Start by unpacking it:
```bash
mkdir -p /home/claude/vpb && cd /home/claude/vpb
unzip -q /mnt/user-data/uploads/VPB-TRANSFER-KIT_v2.0.0.zip
mv VPB-TRANSFER-KIT_v2.0.0/* . && rmdir VPB-TRANSFER-KIT_v2.0.0
ls   # expect: CONTINUE_HERE.md  HANDOFF_*.md  module/  package.json  prototypes/  test-env/  tests/  tools/
npm install   # jsdom + eslint for the tests
```

This project is **separate from Polybius** (John's Electron catalog app). It is an
add-on for **Omeka S**, the web platform behind DHS's public archive site
(`archives.dickinsonhistoricalsociety.org`). Don't mix it into the Polybius kit.

---

> **TO-DO LIST: `TODO.md`** (John asked for one, Oct 2026). Check it first.
>
> **RULE (John, Oct 2026): this module is independent of Polybius.** Build it
> as if Polybius doesn't exist. Things that belong in Polybius go in
> `POLYBIUS-NOTES.md`, not in the module.

## 1. WHO / HOW JOHN WORKS

**John Dougan**, Executive Director, Dickinson Historical Society (Dickinson, Texas).
He's an experienced Electron/React developer, but new to Omeka module (PHP/Laminas)
development.

- Terse. Expects Claude to infer and act. Says "continue" to mean "keep going as planned".
- **Values honesty over reassurance.** Say plainly what was and wasn't verified.
  Never claim a visual or interactive behavior works if it wasn't seen in a real browser.
- Prefers mockups to react to, then iterates one change at a time.
- On Windows 11 / PowerShell. Give exact commands one line at a time.
- Often on mobile. Keep replies scannable.

---

## 2. HOW WE GOT HERE (the conversation arc)

1. **Research on Omeka S gaps.** Top wants: faceted search (Faceted Browse / Advanced
   Search modules exist), in-document search, IIIF viewers, bulk editing,
   collections management (Polybius covers this), hierarchical browse (the Hierarchy
   module exists). Forum gaps with no module found: replacing a media file in place,
   one media on several items, a reconciliation endpoint for OpenRefine, watermarking,
   full edit history with revert, drag-order of items within an item set, Handle IDs,
   full Neatline on S, and **Curatescape walking tours (Omeka Classic only)**.
   Hermes (John's planned public interface) could fill the tours and faceted gaps.
2. **"Is there an Elementor for Omeka?"** No live visual editor exists. Core 4.1+ has
   a Grid layout and block groups. Community modules: Page Blocks (2022, pre-4.1,
   compatibility unverified) and Block Plus.
3. **Design stage 1: static mockup** (Claude Design canvas): a three-panel editor.
4. **Design stage 2: working prototype v1**: blocks on a 12-column grid, each with
   its own width.
5. **Design stage 3: working prototype v2.** John asked for:
   - **Sections that hold adjustable columns, with blocks dropped into the columns**
     (instead of per-block widths).
   - **Settings shown in the LEFT sidebar when something is selected**, with an
     "All elements" back button and a "Selected: X / Settings" bar. The right
     sidebar was removed.
   - **A collapsible left sidebar** (top-left toggle, Ctrl+\\), remembered between visits.
   - The "sun" settings icon was replaced with a sliders icon, and hidden on
     desktop unless the sidebar is collapsed.
6. **Module v0.1.0 built and tested against a real Omeka S 4.2.1** (section 5).
7. **v0.2.0:** John asked to edit block properties in the sidebar instead of going
   back to the standard editor. All core block types are now editable there. See
   `HANDOFF_v0.2.0.md`.
8. **v0.2.1:** code cleanup for human developers (no behavior change): the
   editor split into small files with a block-type registry, lookups moved into
   a PHP service, asserting test suite + lint + Omeka's PHP style rules. See
   `HANDOFF_v0.2.1.md`.
9. **v0.3.0 + Society Blocks 0.1.0:** John asked for custom blocks (random items
   first) that can be set to this site, several chosen sites, or all sites. A
   **separate module, SocietyBlocks**, adds 14 blocks (separate so removing the
   page builder never removes them from pages). The page builder gained
   schema-driven sidebar forms for any module's blocks. See `HANDOFF_v0.3.0.md`
   and `module/SocietyBlocks/README.md`.
10. **v0.4.0 + Society Blocks 0.2.0:** everything the Design-canvas mockup
   showed is now in the module: ⓘ help on every block, block search, the Layers
   tab, a resizable sidebar, and live previews (real items for Browse preview
   and Society Blocks). See `HANDOFF_v0.4.0.md`.
11. **v0.5.0:** John wanted the blocks **inside the page builder**, not a
   separate module (I had recommended separate and asked, but he never
   answered and I went ahead; he corrected it). They are now **Special Blocks**
   in VisualPageBuilder, listed after Sections and Text in the sidebar (0.5.1). Layout names changed
   society* → special*; the module's install/upgrade converts old pages. The
   SocietyBlocks module is retired. See `HANDOFF_v0.5.0.md`.
   **Lesson: when a structural choice is asked and not answered, confirm
   before building on it.**
12. **v0.6.0:** filled the small gaps: Visit info from a site's settings
   (entered once), Upcoming events from a calendar (.ics) feed, real sub-pages
   in the Table of contents preview, and an automated test for the Society
   Blocks conversion. See `HANDOFF_v0.6.0.md`.
13. **v0.7.0:** 13 more Special Blocks (announcement, slideshow, gallery,
   topic tiles, decades, subjects, oral history, document transcription,
   people, partners, fundraising, newsletter, citation). 27 in all. See
   `HANDOFF_v0.7.0.md`.
14. **v0.8.0:** page templates: 30 ready-made pages (home, browse, story,
   about, contact, events/support × 5) in a Templates tab. Undo also exists
   since v0.1.0 (John asked; it was already there). See `HANDOFF_v0.8.0.md`.
15. **v0.9.0 + Companion Theme 0.1.0:** theme page templates can't take
   over builder pages; a companion theme (`theme/visual-page-builder-companion/`); a menu
   builder with five menu styles. John chose all three after I asked. See
   `HANDOFF_v0.9.0.md`. Test install: link the theme with
   `ln -sfn /home/claude/vpb/theme/visual-page-builder-companion /home/claude/omeka-s/themes/visual-page-builder-companion`.
16. **v0.9.1:** John asked for one download with both plus instructions, and
   for the theme to be called **Companion Theme for Visual Page Builder**
   (folder `visual-page-builder-companion`). See `HANDOFF_v0.9.1.md`.
17. **v0.10.0 + Companion Theme 0.2.0:** headers and footers built in the
   visual editor (stored as reserved pages), five header/footer blocks, five
   header and five footer templates; the theme shows them. John chose "build
   all four" after I asked. See `HANDOFF_v0.10.0.md`.
18. **v0.10.1 (big check):** full suite with logs watched, fresh install on
   Omeka 4.1.1 from the download zip (all suites pass there too), a11y and
   markup scan of all 32 blocks, h1 fix in the theme, uninstall cleans the
   cache. See `HANDOFF_v0.10.1.md`.
19. **v0.11.0:** eight more Special Blocks John picked (40 in all). See
   `HANDOFF_v0.11.0.md`.
20. **v0.11.1:** click-to-place markers; Open now from Visit info and live in
   the browser; fixed "today/now" using the server clock instead of Omeka's
   time zone. See `HANDOFF_v0.11.1.md`.
21. **v0.12.0 + theme 0.3.0:** ten more menu styles (15 in all), four of
   them centered logo with the menu underneath. See `HANDOFF_v0.12.0.md`.
22. **v0.12.1:** licensed **GPL-2.0-or-later** (John chose GPL-2.0; "or later"
   keeps it compatible with Omeka S's GPL-3.0). LICENSE files, README
   sections, SPDX lines in every file. John is posting to the Omeka forum.
23. **v1.0.0:** John asked for everything to be version 1. Module and Companion
   Theme are both **1.0.0** (the first public release, for the Omeka forum).
   Earlier numbers (0.x) were internal development; the handoff files keep
   them as history. Next versions: 1.0.1 for fixes, 1.1.0 for new features.
24. **v1.0.1:** sidebar always starts open; header/footer and pages have
   separate palettes (`PLACEMENT`). See `HANDOFF_v1.0.1.md`.
25. **v1.0.2 (+ theme 1.0.1):** first real-browser feedback from John:
   menu alignment (two controls, the general one couldn't move it) and empty
   sections in headers (now removable, not saved, skipped publicly). See
   `HANDOFF_v1.0.2.md`.
26. **v1.0.3 (+ theme 1.0.2):** 18 background colors, a custom color picker,
   white text on dark backgrounds. See `HANDOFF_v1.0.3.md`.
27. **v1.0.4:** Modules › Page builder (sites → pages → editor). See
   `HANDOFF_v1.0.4.md`.
28. **v1.0.5:** header/footer canvas fits its content and centers items.
   See `HANDOFF_v1.0.5.md`.
29. **v1.0.6:** one "Visual Builder" admin entry; one page per site with
   header, footer, menu, theme, and pages. See `HANDOFF_v1.0.6.md`.
30. **v1.0.7:** "+" bars between sections (drop target; click to add there).
   See `HANDOFF_v1.0.7.md`.
31. **v1.0.8:** "← Visual Builder" button in the editor and menu builder.
   See `HANDOFF_v1.0.8.md`.
32. **v1.1.0:** John: "build everything except the Classic module", plus a
   Map (OpenStreetMap, pins linked to items) and a Timeline. Batch 1 done:
   background images, Map of places (with tour mode = walking tours),
   Timeline. Batch 2 NEXT: theme-accurate previews, links/images in inline
   text, per-device column widths, section templates. Items outside this
   module (faceted search needs Polybius + Faceted Browse; replace-media and
   reconciliation are separate modules) need John's go-ahead. See
   `HANDOFF_v1.1.0.md`.
33. **v1.2.0 (batch 2 done):** Preview on site, formatting bar + links +
   pictures in typed text, smaller-screen columns, 10 ready-made sections.
   Remaining from John's "everything" list are outside this module
   (faceted search: Polybius + Faceted Browse; replace media in place;
   reconciliation): ask John. See `HANDOFF_v1.2.0.md`.
34. **v1.3.0:** Title + "Show the title" on every Special Block; full
   formatting bar for headings/paragraphs. See `HANDOFF_v1.3.0.md`.
35. **v1.4.0:** header/footer shown around the page in the editor; click to
   edit, with a save-first question. See `HANDOFF_v1.4.0.md`.
36. **v1.4.1:** clicking the map opens "New place" (name + link an item).
   See `HANDOFF_v1.4.1.md`.
37. **v1.5.0:** timeline entries (item, picture, details), map place
   pictures, list entries open one at a time (all lists). Built from the
   approved Timeline mockup artboard. See `HANDOFF_v1.5.0.md`.
38. **v1.6.0:** Story map block (John's idea: map above, timeline below,
   play/pause/rewind, info pane with photo, text, video). Built from the
   approved Story map mockup artboard. See `HANDOFF_v1.6.0.md`.
39. **v1.7.0:** Archive quiz block. See `HANDOFF_v1.7.0.md`.
40. **v1.8.0:** synced oral history transcripts. See `HANDOFF_v1.8.0.md`.
41. **v1.8.1:** bug sweep with real-browser (headless Chromium) checks, now
   in `tests/browser/`; 3 bugs fixed. See `HANDOFF_v1.8.1.md`.
42. **v1.8.2:** thorough code check (PHPStan, PHP 7.4, security review, CSS,
   ids); 2 small fixes. Static checks in `tests/static/`. See `HANDOFF_v1.8.2.md`.
43. **v1.9.0:** link names (anchors) + Jump links block (named spots only by
   default). See `HANDOFF_v1.9.0.md`.
44. **v1.10.0:** Leaflet removed; the module's own map (`asset/map/vpb-map.js`),
   written from scratch at John's request. No third-party code bundled now.
   See `HANDOFF_v1.10.0.md` (also 1.10.1: tileUrl/credit options, live demo).
45. **v1.11.0:** map polish to Leaflet's level for our uses (smooth zoom with
   tiles kept while loading, momentum, popups kept in view, double-tap zoom
   without stray clicks). Live demo: https://claude.ai/artifact/W2mL5RrW97V4L3MpZYLdWC.
   See `HANDOFF_v1.11.0.md`.
46. **v1.12.0 (+ theme 1.0.4):** long-lived features: page history, duplicate,
   download/import layout, shared sections, footnotes, check page, print.
   See `HANDOFF_v1.12.0.md`. 1.12.1: text formatting moved from a bar over
   the text into the sidebar (`formatPanel()` in blocks/html.js), standard
   line icons for alignment (incl. Justify), lists, link, clear, footnote;
   buttons show pressed state (queryCommandState) as the cursor moves.
47. **v1.13.0:** Special Blocks in nine palette groups (`SpecialBlocks::GROUPS`),
   "Works well with" notes (`WORKS_WITH`), Jump links shown as "Table of
   contents (jump links)". See `HANDOFF_v1.13.0.md`.
48. **v1.14.0:** Table of contents from headings (John's idea: "like LibreOffice").
   H2/H3/H4 → section/subsection/sub-subsection; per-heading "Show in the
   table of contents" and "Name in the table of contents" (cursor in a
   heading → Text formatting panel); new **Table link** block for spots with
   no heading (no space on the site, dashed bar in the editor); "Found on
   this page" in the TOC's settings; Link name retired (kept only where set).
   Block renamed "Table of contents (this page)" (Omeka's own sub-pages block
   is also "Table of contents"). Real-browser check: `tests/browser/toc.py`.
   See `HANDOFF_v1.14.0.md`.
49. **v1.14.1:** John (screenshot of 1.14.0): "no way to adjust the table of
   contents items or indent them to create subsections". "Found on this page"
   entries now indent/outdent (arrows, drag the grip sideways, arrow keys):
   for headings that changes the tag H2/H3/H4; click an entry for show /
   name / level / "Show it on the page". Canvas preview now uses the schema
   defaults (title showed missing on new blocks). See `HANDOFF_v1.14.1.md`.
50. **v1.14.2:** Menu builder page couldn't scroll (editor.css locks body to
   the window; the 15 styles ran past it). Page scrolls now, Preview moved
   above the styles and stays in view (sticky), styles in a 2-column grid.
   See `HANDOFF_v1.14.2.md`.
51. **v1.15.0:** Menu styles shown as pictures: every style card on the Menu
   page draws the menu in that style (`drawMenu(style, links, mini)`), using
   the site's links once there are 3+ with a sub-page, sample links before.
   Larger preview of the chosen style below the cards. See `HANDOFF_v1.15.0.md`.
52. **v1.16.0 (+ theme 1.1.0):** Menu colors (8 schemes + 5 custom colors,
   `vpb_menu_colors`, `MenuSettings`, `--pb-nav-*`), 4 more vertical styles
   (19 total; `MenuController::VERTICAL`, theme body class `pb-menu-side`),
   Desktop/Tablet/Phone preview of the real site via a session draft
   (`draftAction`, `vpbMenu` helper), Site menu block follows the Menu page
   only. See `HANDOFF_v1.16.0.md`.
53. **v2.0.0 (+ theme 2.0.0), Phase 1 of the 2.x build plan (partial):**
   Theme Builder foundations. `src/Templates/` (Screens with required blocks,
   Resolver precedence, Required check, Store on hidden `vpb-template-` pages,
   GlobalStyles defaults matching theme.ini). Theme Builder screen
   (`ThemeController`, route `visual-page-builder-theme`): templates by screen,
   add, duplicate, make live (needs required blocks), move, Global styles.
   Theme 2.0: secondary/text/link colors, text size, corners as CSS variables.
   Not yet wired to public screens (Phase 2). Tests: `tests/php` (49),
   `tests/browser/theme_builder.py`. See `HANDOFF_v2.0.0.md` for the gap list.
39. **v1.7.0:** Archive quiz block (multiple choice; answers found by
   searching the archive). See `HANDOFF_v1.7.0.md`.

Published artifacts (John's claude.ai account):
- Static mockup (Design canvas): https://claude.ai/artifact/7vSWgcRcKCqhp1QH4knWeN
- Working prototype (v2 is the latest version at this link): https://claude.ai/artifact/B9yvJZahEmBRjLYdqZEuuA

---

54. **v2.1.0 (+ theme 2.1.0), Phase 2 of the 2.x build plan (partial):** item field blocks (Title, Main image, Description, Date, Details, One field, Subjects, Item sets, Cite, Download, Previous/next) that only appear in Item templates; Title and Main image can be hidden (not deleted) in a template; placeholder for items with no file; the public item page draws a live Item template (all items, or by class) through `vpbTemplate` and the Companion Theme's item view, falling back to Omeka's own page. Theme Builder rows have an Edit link. Tests: `tests/theme/item-template.test.js` (10 checks), `tests/theme/item-template-class.test.js` (5), PHP 58; `npm test` exit 0. Not done: Playwright test, check-page list warnings, template delete (API page delete 500s on the test install). See `HANDOFF_v2.1.0.md`.

## 3. KEY FINDINGS FROM OMEKA'S SOURCE (verified by reading 4.1.0 and 4.2.1)

- **Block groups always span the full width** in Grid layout (PageLayout.php forces
  `grid-position-1 grid-span-N`), and a group's children are placed row by row in the
  group's inherited grid. **Omeka has no native "column holding a stack of blocks."**
  That drove the hybrid storage design (section 4). John accepted the fallback.
- A group's children are simply the next `o:data.span` blocks. Groups can't nest.
- `o:layout_data` (page and block) is stored **as-is**, so custom keys survive.
- Saving **replaces the whole block list by position**. Attachments must be passed
  back in or they're lost; the module does pass them through.
- **There is no core "Heading" block.** (An earlier prototype wrongly listed one.)
  Headings are HTML blocks.
- The HTML block runs **HTMLPurifier on save** when the `use_htmlpurifier` setting is on
  (the default). That removes iframes and similar embeds no matter what editor is used.
- Core block layouts in 4.2.1: pageTitle, media, browsePreview, listOfSites,
  tableOfContents, lineBreak, itemWithMetadata, pageDateTime, blockGroup,
  iiifPresentation, iiifImage, asset, html, listOfPages, oembed.
- Standard block layout_data keys: `grid_column_position`, `grid_column_span`, `class`,
  `alignment_block`, `alignment_text`, `background_color` (hex only),
  `background_image_asset`, `background_image_position_x/y`, `background_image_size`,
  `max_width`, `min_height`, `padding_top/right/bottom/left` (CSS length strings;
  bare numbers mean px), `template_name`.
- Page layout_data: `grid_columns`, `grid_column_gap`, `grid_row_gap`, `template_name`.
- `page-grid.css` switches `.page-layout-grid` to `display:block` under 896px.
- **Compatibility:** PageLayout.php, AbstractLayout.php, and page-grid.css are
  byte-identical in 4.1.0 and 4.2.1. SitePageAdapter and PageLayoutFactory differ
  only cosmetically. Module constraint: `^4.1.0`. Current Omeka release is 4.2.1
  (June 2026). No public data exists on which versions sites run.

---

## 4. MODULE DESIGN (v0.1.0)

**Name / namespace:** `VisualPageBuilder`. The author in module.ini is "Dickinson
Historical Society". **License is not chosen yet** (Omeka modules are usually GPL-3.0).
Ask John.

### Storage format
- Page: `o:layout = "grid"`, layout_data `grid_columns: 12`, gaps default 16, plus
  the marker `visual_page_builder: {version: 1}`.
- **Section = `blockGroup` block.** `o:data.span` = the number of child blocks. Its
  background, padding, and class use the standard keys. Column widths live in
  `vpb_section: {cols: [{w, bg}]}`.
- **Child blocks** get `grid_column_position` (the column's start, as a string),
  `grid_column_span` (the column's width, as a string), and **`vpb_col`** (the column
  index). Children are **interleaved row by row** (row 0 of every column, then
  row 1, and so on), so the page still reads sensibly without the module.
- Editor-managed block keys: `background_color` (bg), `padding_*` (only when all
  four are equal integers), `alignment_text`, `class`. Every other key is preserved
  untouched.

### Rendering
- `VisualPageBuilder\View\Helper\PageLayout` **overrides Omeka's `pageLayout` view
  helper** (registered under the same name in module.config.php).
- Pages without the marker go to `parent::render()`, unchanged.
- Marked pages: each section group is wrapped in `.vpb-column` divs (grid
  position/span classes, optional background). Non-builder groups render the way
  core does.
- `asset/css/public.css` stacks blocks inside columns and stacks columns under 896px.
- **With the module removed:** core renders every block in its section with the
  correct position, width, and styling. Only the stacking within a column becomes
  row by row. This was verified.

### Admin
- Route `admin/visual-page-builder`:
  `/admin/visual-page-builder/:site-slug/:page-id[/:action]` (actions edit|save).
- **"Open visual editor"** button injected via the `view.edit.page_actions` event on
  `Omeka\Controller\SiteAdmin\Page`, shown only if the user may update the page.
- ACL: the controller is allowed for all roles. The controller then checks
  `$page->userIsAllowed('update')`, and the API re-checks on save.
- **Save:** a JSON POST with a Laminas `Csrf` token (`visual_page_builder_csrf`).
  The server whitelists block keys (`o:layout`, `o:data`, `o:layout_data`,
  `o:attachment`), then does a **full (non-partial) API update** carrying over
  title, slug, and is_public. It returns the saved page and a fresh token.
- The editor page is a terminal full-screen view:
  `view/visual-page-builder/admin/builder/edit.phtml`. Its config is embedded as
  JSON (`#vpb-config`).

### Editor (`asset/js/editor/`, split in v0.2.1; see the module README's code map)
- Ported from prototype v2. The model is sections → cols → blocks.
- `importPage()` handles builder pages exactly. Other pages are row-split:
  runs of full-width blocks merge into one 1-column section, side-by-side rows
  become multi-column sections, gaps become empty columns, and non-12 grids scale
  to 12.
- `exportBlocks()` produces the storage format above.
- HTML blocks are editable in place (contenteditable) after a **sanitizing
  preview**. Blocks whose HTML had to be sanitized (embeds, scripts, on*
  attributes) are **locked** so they can't be damaged.
- **v0.2.0:** core block types (media, itemWithMetadata, asset, browsePreview,
  listOfSites, pageDateTime, tableOfContents, lineBreak, oembed, iiifImage,
  iiifPresentation) are edited in the sidebar (`contentEditor()`), with item/asset
  search pickers backed by the `lookup` action. Cards show thumbnails/summaries.
  Blocks from other modules still show as cards pointing to the standard editor. Blocks from missing modules show a red "Unavailable" card and
  are kept as-is.
- Dirty tracking, unsaved-changes warnings, and Ctrl+S save.
  `window.VisualPageBuilder` exposes `{exportBlocks, importPage, state}` for tests.
- **Adding a block type:** one file in `asset/js/editor/blocks/` calling
  `VPB.blocks.register()`, listed in `edit.phtml` after `blocks/registry`.
  Load-order rule and namespace layout: header of `editor/core.js`.

---

## 5. WHAT WAS TESTED (against a live Omeka S 4.2.1 + MariaDB in the container)

All passed:
- Installs from Admin › Modules. The button appears. The editor page and config
  load (14 layouts).
- **Round trip:** rearranged into an 8/4 section with backgrounds, padding,
  alignment, and class, then saved. Stored data verified via the API, including an
  **item attachment and its caption**. Reopening the editor rebuilds the identical
  layout. Re-exporting without changes equals the stored data.
- Public page renders true columns. **Deactivating the module** still renders all
  content (row fallback). Reactivating restores the columns.
- Logged out → redirected to login. A researcher role is refused (403) on both edit
  and save. A bad CSRF token gets 403. A wrong site slug or missing page gets 404.
- The editor's preview doesn't execute an `onerror` payload.
- A 6-column grid page with side-by-side blocks imports correctly to 12 columns.
- An unknown `mappingMap` block keeps its data and class.
- An empty section saves and reloads.
- With the HTML purifier off, a text block containing an iframe is locked in the
  editor and saved back **unchanged**.

**NOT verified — say so if asked:**
- Real-browser look and feel, mouse/touch dragging, and column-bar resizing.
  The container has no browser, so jsdom was used.
- Running on Omeka **4.1** (only source-diffed).
- Theme accuracy of the editor preview (it only approximates the theme).
- DHS's actual server, PHP version, and theme.

---

## 6. OPEN ITEMS / NEXT STEPS

0. **DONE in v1.1.0 (was TO DO): Background images**
   in the visual editor for sections, columns, and blocks (pages, headers,
   footers). Pick from Assets or an item; fit (fill / whole picture);
   position (top / center / bottom); optional dark overlay that also turns
   text white. Omeka 4.1+ already stores `background_image_asset`,
   `background_image_position_x/_y`, `background_image_size` for blocks and
   groups (see `AbstractLayout.php`); the editor keeps those today but has no
   control for them, and columns would need our own (`vpb_section.cols`).
   Not started.

0-. **DECIDED NOT TO BUILD (John, 2026-10-05): zoomable image viewer block,
   IIIF/tiling support, or a custom image server.** Don't re-propose.

0a. **TO DO LATER (John, 2026-10-03): "Replace File", a separate Omeka S
   module** (NOT part of Visual Page Builder). A "Replace file" button per
   media in the item edit page's Media tab and on the media edit page; small
   dialog (current file preview, choose new file, Replace); keeps the same
   media record (ID, position, title, description, links, page blocks);
   regenerates thumbnails; notes when/what was replaced; warns if the file
   type changes; only for users who can edit the item. Open question: keep a
   backup copy of the old file (I suggested yes, undoable) — ask John.

0b. **ON HOLD (John: "hold that thought", 2026-10-03): an Omeka Classic
   version.** Prompted by texascitylibrary-archive.org (Moore Memorial Public
   Library, Texas City), which runs Omeka Classic (version unknown; check
   `?v=` in its page source). Would be a separate Classic plugin reusing the
   editor JS: save layouts into Simple Pages (HTML + shortcodes) and/or
   Exhibit Builder pages; header/footer/menu need a Classic companion theme.
   Two questions asked and NOT answered: edit Simple Pages, Exhibit pages, or
   both? For the Texas City site, the forum, or both? Ask again before
   building.

1. **John to try it** on a test page on a staging copy (back up the DB first) and
   report visual and drag problems with screenshots.
2. Check the DHS site's Omeka version (shown at the bottom of the admin dashboard).
   It must be 4.1 or later.
3. (Done: GPL-2.0-or-later.) Choose a license for the module and confirm the module names and author line.
4. Possible improvements, **not yet requested — ask before building**:
   - Theme-accurate previews (server-render blocks through the site theme).
   - Links and images in the inline text editor.
   - Per-device column widths.
   - Section templates.
   - (Done in v0.10.1: tested on a real 4.1.1 install.)
5. Related ideas raised earlier (not started): faceted search via Faceted Browse +
   Numeric Data Types, which needs Polybius's Omeka publisher to send dates as
   numeric timestamps (check the publisher code); walking tours in Hermes;
   replace-media-in-place and reconciliation as Polybius sync pain points.

---

## 7. REBUILDING THE TEST ENVIRONMENT (in the container)

```bash
# 1. PHP + MariaDB (the nodesource apt repo is blocked; move it aside first)
mkdir -p /tmp/aptoff && for f in /etc/apt/sources.list.d/*; do grep -l nodesource "$f" >/dev/null 2>&1 && mv "$f" /tmp/aptoff/; done
apt-get update -qq && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq php-cli php-mysql php-xml php-mbstring php-gd php-intl php-curl php-zip mariadb-server unzip

# 2. Start MariaDB directly (mysqld_safe/service don't work here)
mkdir -p /run/mysqld && chown mysql:mysql /run/mysqld
(setsid nohup /usr/sbin/mariadbd --user=mysql > /tmp/mysqld.log 2>&1 < /dev/null &); sleep 5
mysql -uroot -e "CREATE DATABASE omeka CHARACTER SET utf8mb4; CREATE USER 'omeka'@'127.0.0.1' IDENTIFIED BY 'omekapw'; CREATE USER 'omeka'@'localhost' IDENTIFIED BY 'omekapw'; GRANT ALL ON omeka.* TO 'omeka'@'127.0.0.1'; GRANT ALL ON omeka.* TO 'omeka'@'localhost';"

# 3. Omeka S release (GitHub release assets are reachable)
cd /home/claude && curl -sSL -o omeka.zip https://github.com/omeka/omeka-s/releases/download/v4.2.1/omeka-s-4.2.1.zip && unzip -q omeka.zip
printf 'user = "omeka"\npassword = "omekapw"\ndbname = "omeka"\nhost = "127.0.0.1"\n' > omeka-s/config/database.ini
cp /home/claude/vpb/test-env/router.php /home/claude/vpb/test-env/start-omeka.sh /home/claude/
chmod +x /home/claude/start-omeka.sh && /home/claude/start-omeka.sh   # dev mode, memory_limit=512M
```
4. **Install Omeka.** POST the /install form with curl (see the field names in
   §8). Two traps:
   - The email domain must be real-looking (`example.com`, not `example.test`).
   - Without `memory_limit=512M` the installer dies partway. If that happens, drop
     and recreate the DB before retrying.
5. **API key.** Insert a row into `api_key`: `id='testkey'`, `label='test'`
   (required), `credential_hash=password_hash('testcred')`, `owner_id=1`,
   `created=NOW()`. Then `test-env/api.sh METHOD path [json]` works.
6. `test-env/login.sh` logs in `admin@example.com` / `Test-password-123` and writes
   `/tmp/jar.txt`. Copy it to `/tmp` first, since it uses `/tmp` paths.
7. Link both modules: `ln -sfn /home/claude/vpb/module/VisualPageBuilder /home/claude/omeka-s/modules/VisualPageBuilder`
   Then install via POST `/admin/module/install?id=VisualPageBuilder` with the
   `csrf` value from `/admin/module`. (Uninstall uses a separate confirm form:
   GET `/admin/module/uninstall-confirm?id=…` for `confirmform_csrf`.)
   Module errors show only in `logs/application.log` after setting
   `'log' => true` in `config/local.config.php`.
   After moving or relinking a module folder, restart the PHP server (it caches paths).
8. **Seed and run the tests:** `npm run seed`, then
   `test-env/set-researcher-password.sh`, then `npm test`. See `tests/README.md`.
   (Old manual test data, kept for reference:)
   - Site `dhs` (id 1).
   - Page 2 `railroad-days`: pageTitle, two html blocks, a media block with item 1
     attached plus a caption, and a lineBreak.
   - Page 3 `edge`: the grid-6 edge cases.
   - Page 5 `embed`: an iframe block, created with `use_htmlpurifier` set to `"0"`.

**Pitfalls learned:**
- `pkill -f "php -S"` kills your own shell command. Kill by PID instead.
- `sh` is dash, so brace expansion (`mkdir -p a/{b,c}`) and `${var:0:10}` fail. Use `bash -c`.
- Omeka escapes URLs in HTML as `&#x2F;`, so grep for escaped paths.
- jsdom needs a `url:` option for localStorage.
- jsdom must be v27+ (older versions lack `PointerEvent`).
- Omeka's session cookie name is a hash, not PHPSESSID.

## 8. INSTALLER FORM FIELDS
`user[email]`, `user[email-confirm]`, `user[name]`, `user[password-confirm][password]`,
`user[password-confirm][password-confirm]`, `settings[installation_title]`,
`settings[time_zone]`, `settings[locale]`, `submit`. The login form uses
`email`, `password`, `loginform_csrf`, and `submit`.

---

## 9. KIT CONTENTS
| Path | What |
|---|---|
| `module/VisualPageBuilder/` | The module v2.0.0, including Special Blocks (code map in its README) |
| `theme/visual-page-builder-companion/`, `theme/visual-page-builder-companion.zip` | The companion theme (README inside); zip built like the module's |
| (old) `module/VisualPageBuilder.zip` | Install zip: files at the top, no wrapping folder, so Windows "Extract All" gives one `VisualPageBuilder` folder (John asked for this). `npm run package` rebuilds it |
| `tests/module/`, `tests/special/` | Asserting end-to-end suites for each module; `tests/lib/harness.js`; seed and golden-master tools (`tests/README.md`) |
| `package.json`, `eslint.config.js`, `.php-cs-fixer.dist.php` | `npm test`, `npm run lint`, `npm run lint:php` (Omeka's rules, both modules) |
| `tools/` | Where the php-cs-fixer phar goes (`tools/README.md`) |
| `test-env/` | router.php, start-omeka.sh, login.sh, api.sh, set-researcher-password.sh, package.sh |
| `prototypes/` | Design stages 1–3 (the live mockup is the Design canvas linked in §2) |
| `HANDOFF_v0.2.0.md` … `HANDOFF_v2.1.0.md` | Release notes (0.3.0/0.4.0 describe the old separate module) |
