# Handoff: Visual Page Builder 1.12.0 + Companion Theme 1.0.4 — long-lived features

John asked for features that stay stable a long time (no outside services);
I proposed seven; "Let's build 1 through 7".

1. **Page history** — `src/PageHistory.php`, table `vpb_page_revision`
   (id, page_id, created UTC, user_name, title, data JSON). `saveAction` keeps
   the page as it was before updating (never blocks a save); newest 50.
   Table created on install, upgrade (<1.12.0), AND lazily (`ensure()`; a
   skipped upgrade happened during development). Dropped on uninstall;
   rows removed when a page is deleted (Module api.delete.pre). Route action
   `history` (GET list / `?version=`). Editor: Page menu › History.
2. **Duplicate** — Visual Builder page form (POST, CSRF `vpb_hub_csrf`):
   private copy "Copy of …", slug `…-copy[-n]`, blocks + attachments via
   `PagesController::copyBlocks()`; opens the copy.
3. **Download / import layout** — `page-tools.js`: JSON format
   `visual-page-builder-layout` v1; import replaces or appends (Undo works).
4. **Shared sections** — hidden public pages `vpb-shared-…`
   (`SiteRegions::isSharedSlug`, 404 at own address via `hideRegionPages`,
   left out of lists). Block `specialSharedSection` (field `section`, a page
   id, `editor_hidden` — new schema flag: the editor's own tool shows a
   dropdown instead, `blocks/shared-section.js`) renders the page with
   `pageLayout()`, guarding self-inclusion. Created from the Visual Builder
   page (New shared section) or from a section (`make-shared` action:
   "Make this a shared section…" in section settings). Editing one shows a
   banner; no header/footer bands; no Preview on site.
5. **Footnotes** — formatting bar button inserts `<sup class="vpb-footnote">
   note</sup>`; `src/Footnotes.php` numbers them in page order in
   `PageLayout::renderBlock()` and lists them (block `specialFootnotes`, or
   appended at the end of the page). Survives Omeka's HTML purifier.
6. **Check page** — `page-check.js`, editor-only: images without alt,
   h1 in text / skipped heading levels, empty or "#missing" links, links
   with no words, vague link text (tip), empty sections (tip), duplicate
   link names, block-specific gaps (Jump links with nothing named, Shared
   section unset, map places without position, story stops without date,
   quiz questions with <2 choices), Footnotes block without notes (tip).
7. **Print** — print rules in public.css and the theme. BUG FOUND: the
   module's and theme's stylesheets were added with Laminas' default media
   "screen", so NO print rule ever applied (including an older one in
   special-blocks.css). Now added with media "all". Verified by printing to
   PDF in Chrome (`tests/browser/printpdf.py`).

Also: Page menu (button + dropdown, keyboard: arrows, Escape) in the top bar.
Tests: `module/v112.test.js` (29), `tests/browser/v112.py` (menu position and
keyboard, real download, dialogs, print). Full suite passes.

## Found while testing
- Install failed with "Class VisualPageBuilder\PageHistory not found": the
  module's classes aren't autoloaded during install/upgrade/uninstall, so
  Module.php now `require_once`s src/PageHistory.php there. (The suite's
  uninstall/reinstall test caught it.)
- Real upgrade on the Omeka 4.1.1 site from 1.8.0 → 1.12.0: table created,
  v112 tests pass there too.
