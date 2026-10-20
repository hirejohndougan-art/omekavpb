# Visual Page Builder — Handoff v0.2.0
> **Note (v0.2.1):** the tests named below were replaced by `tests/module/*.test.js`; see `tests/README.md`.


Supplements CONTINUE_HERE.md. John asked: *"when I select blocks in the visual
editor, it would be nice to be able to edit their properties instead of going
back to the standard editor."*

## What changed
**Sidebar editing for every core block type** (keys verified against Omeka 4.2.1's
BlockLayout classes; values written as strings, matching what the standard form sends):

| Block | Editable in sidebar |
|---|---|
| Media embed | Items (search, add, reorder, remove), which file per item, caption; arrangement (vertical/horizontal), embedded vs thumbnails, thumbnail size, title option |
| Item with metadata | Items (search, add, reorder, remove); no file/caption, like core's `itemOnly` form |
| Asset | Images (search by name, add, reorder, remove), caption, link to a site page, link text. `o:data` written as a list of `{id,page,alt_link_title,caption}` |
| Browse preview | Resource type, search query (a pasted URL is cut to its query string), limit, heading, link text, components |
| List of sites | Sort, limit, pagination, summaries, thumbnails, exclude current |
| Page date/time | Which dates, date format, time format |
| Table of contents | Depth 1–6 |
| Line break | Blank space / visible line (canvas now shows the difference) |
| oEmbed | URL, "fetch again on save"; shows the current embed's title |
| IIIF image / presentation | URL, title, show title |

Blocks from other modules still show a card and a pointer to the standard editor.
Their data is untouched, as before.

**Canvas cards** now show attachment thumbnails, asset images, or a one-line
summary (e.g. "“Depot items” · Up to 3 items matching fulltext_search=depot").

**Server (`BuilderController`)**
- New `lookupAction` (route action `lookup`, same page-update permission check):
  `?type=items&q=` (fulltext; a bare number searches by id), `?type=assets&q=`
  (name substring, newest 500 scanned), and `&ids=` for both.
- `editAction` config adds `lookupUrl`, `thumbnailTypes` (from Omeka config),
  `sitePages` (for asset links), and `refs` (titles/thumbnails/media lists for
  everything already attached, so cards render without extra requests).

**Save**
- Focused inputs are blurred before saving, so a half-typed field is included
  (Ctrl+S from inside a field works).
- After a successful save, each block's `o:data` is replaced with what Omeka
  stored (purified HTML, fetched oEmbed), unless the user edited during the save.
  oEmbed blocks then get `url_old = url` so later saves don't re-fetch.

## Tested (live Omeka S 4.2.1 + MariaDB in the container)
- `tests/module/content.js <pageId> edit` — 34 checks driving the real sidebar UI:
  file choice, captions, options, item search + add (picker stays open), reorder,
  missing-item flagging, item-with-metadata replace, asset caption/link, pasted
  browse URL, checkboxes, undo/redo, save via the Save button, API-verified
  stored data for every block, untouched blocks unchanged. oEmbed: Omeka can't
  reach YouTube from the container, so the save failed with Omeka's message
  shown in the toast and nothing lost; removing the block then saved cleanly.
- `content.js <pageId> verify` — 6 checks: reopened editor re-exports exactly the
  stored data; cards show thumbnails and summaries; file choice restored.
- Public page renders the new settings (horizontal media, asset link "Home" to
  the chosen page with caption, browse heading, IIIF title).
- v0.1.0 regressions re-run: e2e (save + bad CSRF 403), rt (reopen identical),
  edge (grid-6 import, XSS not executed, locked iframe block saved unchanged,
  unknown `mappingMap` block data + class kept, empty section).
- Upgrade path: Modules › Upgrade from 0.1.0 to 0.2.0 works (no DB changes).

## NOT verified
- Real browser look and feel of the new sidebar forms, picker, and thumbnails
  (jsdom only; no browser in the container).
- A successful oEmbed fetch (no outbound web from the container).
- A successful IIIF render with a real IIIF server.
- Omeka 4.1 (source-diffed only), DHS's server and theme.

## Test data (make-content-page.sh)
Items 1, 4, 7 ("Railroad photo 0/1/2", two uploaded files each; resource ids are
shared, so media are 2–3, 5–6, 8–9), asset 1 ("Depot banner"). The script's
page attaches a nonexistent item (99999) to Item-with-metadata on purpose.
