# Handoff: Visual Page Builder 1.3.0 (theme unchanged, 1.0.3)

John: change every Special Block's title, with an option to hide it; change
block background colors; and web-standard formatting for headings and
paragraphs (fonts, sizes, colors, bold, italic, underline, lists, quotes…).

- **Titles** (`AbstractSchemaBlock::fields()`, used everywhere instead of
  `schema()`): puts the block's `heading` (or a new empty one) first, in a
  "Title" group labelled "Title", followed by `show_heading` ("Show the
  title", default on). `renderData()` blanks the heading when hidden, and
  for blocks whose template never drew a heading, draws the heading partial
  above the block. Excluded (title is the content): Logo, Site title,
  Copyright line, Breadcrumbs, Announcement bar.
- **Block background colors**: already existed for every block (Style ›
  Background: 18 swatches + picker, and background images). Told John where.
- **Formatting bar** (`blocks/html.js`): Style select (p, h2, h3, h4,
  blockquote), Font select (10 web-safe stacks incl. "Default (theme
  font)" = inherit), Size (execCommand sizes 2–6), B, I, U, S, text color,
  highlight (input type=color), bulleted/numbered list, quote, align
  left/center/right, link (SAFE_URL), unlink, clear formatting. Uses
  `styleWithCSS` so output is inline styles; selects/colors save and restore
  the selection. Wraps onto rows above the text.
- Verified with Omeka's HTML purifier ON: font-family, color, font-size,
  background-color, text-align, u/s, ol, blockquote all survive.
- Tests: new `module/titles-and-text.test.js` (12); harness gained
  `setting`/`setSetting`. Full suite passes. execCommand behaviour itself is
  browser-side and not verified in a real browser.
