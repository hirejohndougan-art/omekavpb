# Handoff: Visual Page Builder 1.9.0 (theme unchanged, 1.0.3) — link names + Jump links

John: page anchors to link to and jump down the page; and a table of contents
that auto-populates with ONLY those named spots, nothing else.

- `src/Anchors.php`: `slug()`, `plan($page)` (named spots from layout data
  `vpb_anchor`/`vpb_anchor_label` on any block or section group, then h2/h3 in
  html blocks without their own id; ids never repeat: named first, headings
  get -2, -3…; cached per page id), `withId()`, `headingIds()`.
- `PageLayout`: `renderBlock()` adds the planned heading ids and the block's
  id (first tag, plus `data-vpb-anchor`); `openGroup()` adds a section's id.
- `JumpLinks` (`specialJumpLinks`, page only): source named (default) /
  named_headings / headings; levels h2 / h2h3; style list/row/box; numbered.
  Walks blocks in page order. Editor preview shows a note (needs the page).
  `js/jump-links.js`: smooth scroll (auto with reduced motion), pushState
  hash, focus target (tabindex -1). CSS: `[data-vpb-anchor]` scroll-margin
  (var `--vpb-anchor-offset`, default 6rem, for fixed headers).
- Editor: `model.slug()` (matches the server), section/block `anchor` +
  `anchorLabel` kept out of ld and saved back; `fields.anchorField` (Link
  name, tidied on change, clash warning, Copy link using `cfg.publicUrl`,
  "Name in Jump links"); `#name` tag on the canvas; the formatting bar's link
  prompt lists the page's spots. Columns don't get link names.
- Test `module/anchors.test.js` (15). Full suite passes.
