# Handoff: Visual Page Builder 1.14.1 (theme unchanged, 1.0.4) — edit the table of contents from its list

John installed 1.14.0 and sent a screenshot: "no way to adjust the table of
contents items or indent them to create subsections". (Earlier he had asked
for Table links to be draggable sideways in the list to indent them.)

- `blocks/toc-blocks.js` "Found on this page" rows (`.toc-row`): grip
  (drag sideways, 24 px per level; or ArrowLeft/ArrowRight when focused),
  Outdent / Indent buttons, and a click-to-open panel (`.toc-entry`): Show in
  the table of contents (headings), Name in the table of contents, Level
  (select), Show it on the page. Only one entry open at a time (`openKey`).
- Level for a heading = its tag: `setLevel()` swaps h2/h3/h4 in the text
  block's HTML, keeping attributes (data-toc-*) and contents; Table link sets
  `data.level`. `setShown()` / `setName()` set data-toc-hide / data-toc-name.
- Canvas preview read title/show title/style/numbered straight from o:data,
  so a new block (no saved data yet) showed no title; now uses the schema
  defaults (`setting()`).
- A TOC carried over from 1.13's "only named spots" (levels `none`) on a page
  with headings now says so on the canvas instead of "Nothing to list yet".
- Tests: `module/anchors.test.js` +7 (indent, arrow key, rename, level select,
  Table link outdent, re-include). New `tests/browser/toc-indent.py`.
