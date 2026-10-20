# Handoff: Visual Page Builder 1.14.0 (theme unchanged, 1.0.4) — Table of contents from headings

John rejected the "Link name + jump links" design, refined it to draggable
"Table links", then asked whether headings themselves (font sizes/styles, as
in LibreOffice) should be the links. Mockup updated (Design canvas,
`Toc.dc.html`), he said yes, then "build it".

## What changed
- **Table of contents (this page)** (`specialJumpLinks`, layout name kept):
  lists the page's Heading 2 (section), 3 (subsection), 4 (sub-subsection)
  from text blocks, in reading order (section by section, column by column).
  Settings: Title, Show the title, Headings to include (`levels`: h2 / h3
  default / h4 / none = only Table links), Style (list/row/box), Number the
  entries (1, 1.1, 2…). Nested `ul`/`ol`; the number sits before the link
  (`span.special-blocks-jump-num`). Levels never skip (an H4 right after an
  H2 is one step in).
- **Per heading** (cursor in a heading → Text formatting panel → "Table of
  contents"): Show in the table of contents (default on), Name in the table
  of contents (optional). Stored in the text block's layout data
  `vpb_toc: [{i, hide, name}]` (i = index among the block's H2–H4). In the
  editor they live on the heading as `data-toc-hide` / `data-toc-name`
  (moved on/off by `model.tocApply` / `tocExtract`; restored after save in
  `save.js`). Canvas shows "not in contents" / "contents: …" badges.
- **Table link** (`specialTableLink`, new, Finding your way): name + level;
  renders an empty `span.vpb-table-link` with its id; takes no space (public.css
  cancels the column gap); dashed ⚓ bar in the editor; no title setting.
- **Found on this page** at the top of the TOC's settings: every heading and
  Table link (left-out ones struck through, with why); click to go there.
  The canvas preview is computed in the editor (`toc.js` mirrors
  `Anchors::plan()` / `JumpLinks::entries()`), and refreshes as you type.
- **Ids:** H2–H4 all get ids now (`Anchors::headingsIn`, matched by index,
  fixes a latent shift with empty headings); headings with their own id keep
  it and are listed. Standard-editor pages get ids too (`PageLayout::addAnchors`,
  only when the block wrappers line up exactly; otherwise untouched).
- **Link name retired:** the field only appears where a spot already has one
  ("Older link name"), so old links keep working; still listed in the TOC.
  Old TOC settings map via `JumpLinks::upgradeData()` (source named → none;
  h2h3 → h3), on the site and when opened in the editor.
- Check page: TOC with nothing to list; Table link with no name; `#` links
  checked against heading/Table link ids.
- Save: a network failure during an oEmbed fetch now returns a clear
  "couldn't reach the web" error instead of HTTP 500.
- Label "Table of contents (this page)" because Omeka's core sub-pages block
  is also called "Table of contents".

## Tests
`module/anchors.test.js` rewritten (38 checks). palette-groups (48 blocks),
titles-and-text, v112 updated. Full suite passes; browser sweep 48 blocks, no
problems; new `tests/browser/toc.py` (real cursor, drag, undo, save, public
spacing 0, jump focus) passes.
