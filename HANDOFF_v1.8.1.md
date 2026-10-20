# Handoff: Visual Page Builder 1.8.1 (theme unchanged, 1.0.3) — bug sweep

John: "Run through everything and check for bugs."

## How it was checked
- Full automated suite on Omeka 4.2.1 and 4.1.1: all pass.
- NEW: real-browser checks with headless Chromium (Python Playwright), kept
  in `tests/browser/` (see its README): every one of the 44 Special Blocks
  added in the editor (selected, saved, device views, Preview, + bar) and on
  the public page; Visual Builder page, menu builder, header editor;
  formatting bar (bold/font/size/link really write inline styles), map pin
  tool (real Leaflet click → New place form), list rows, drag from the
  palette onto a + bar, undo/redo, Story map (pins, Next, current pin),
  synced oral history with a real audio element, quiz; on both 4.2 and 4.1.
  No JavaScript errors from the module (only sandbox network noise).
- Accessibility scan (axe-core, WCAG A/AA): public all-blocks page, home,
  editor, Visual Builder, menu builder, header editor; default theme and
  Companion Theme.
- Sideways-scroll check at 390 / 768 / 1366 px, both themes.

## Bugs found and fixed
1. **Phones: page scrolled sideways** with a built header holding a large
   search box (Companion Theme). Cause: stacked sections inherited the
   theme's `align-items: center`, so a column could be wider than the
   screen; and header/footer grids used plain `1fr` columns (Omeka's own
   page CSS isn't loaded in theme headers). public.css: stacked sections
   stretch, columns `min-width: 0`, grids `minmax(0, 1fr)`, one column with
   no gaps under 600px.
2. **Preview on site left copies behind** in Omeka's page list (same title
   as the real page) when you closed the preview without saving, or deleted
   the page. Now: "Back to editing" deletes the copy (`discard`), the copy is
   titled `Preview copy of "…" (not published)`, and deleting a page deletes
   its copy (Module: api.delete.pre/post on SitePageAdapter).
3. **Accessibility:** the sidebar's Blocks/Layers/Templates tabs (and the
   preview window's width buttons) were given `aria-pressed` meant for the
   Desktop/Tablet/Phone buttons (`app.js` now targets `[data-dev]` only);
   menu builder tree's top list now `role="none"`; Visual Builder page-title
   links underlined (contrast).

Tests: batch2 +4 (copy naming, removed on close, removed on delete).
