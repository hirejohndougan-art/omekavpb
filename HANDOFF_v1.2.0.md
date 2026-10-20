# Handoff: Visual Page Builder 1.2.0 (theme unchanged, 1.0.3) — batch 2

Continuing "build everything except the Classic module".

- **Preview on site** (`theme-preview.js`, `BuilderController::themePreviewAction`,
  route action `theme-preview`): POSTs the unsaved blocks; the server
  creates/updates a private page `vpb-preview-<page id>` (same layout data)
  and returns its URL; the editor shows it in a full-screen frame with
  desktop/tablet/phone widths, "Open in a new tab", "Back to editing"
  (Escape). Hidden from lists via `SiteRegions::isHiddenSlug` (header,
  footer, previews); deleted when the page is saved. Not offered when
  editing a header/footer.
- **Typed text** (`blocks/html.js`): formatting bar shown while the text has
  focus (execCommand: bold, italic, h2, p, list, link with `SAFE_URL`,
  unlink); one global selection tracker remembers the cursor; sidebar
  "Insert an image…" (asset picker) inserts `<img alt>` at the cursor or at
  the end. `VPB.textTools` for tests.
- **Smaller screens**: `section.resp = {tablet: stack|two|same, phone:
  stack|two}` in `vpb_section.resp` (default stack, which is what Omeka did
  before); canvas Tablet/Phone views follow it (`respMode`); public classes
  `vpb-tablet-*` / `vpb-phone-*` with media queries (tablet 601–896px).
- **Ready-made sections**: kind `sections` (single: true) with 10 templates
  (`templates/sections.js`); card button "Add this section" inserts at the
  "+" spot, after the selected section, or at the end.
- Tests: new `module/batch2.test.js` (18); templates test now 50 templates.
  Full suite passes. Not verified in a real browser (execCommand behaviour,
  the media queries, the preview frame).
