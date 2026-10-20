# Handoff: Visual Page Builder v0.8.0 (page templates)

John: "Let's add a templates feature with premade pages which includes
homepage, browse, story, about, contact etc. Give me 5 templates of each."
I read "etc." as one more kind: events and support. 6 kinds × 5 = 30.

## What was built
- `asset/js/editor/templates/catalog.js`: the format (sections → column
  widths → blocks as `[layout, data]`), helpers (`T.title()`, `T.text()`,
  `T.block()`…), and the registry. One file per kind adds five templates.
- `templates-panel.js`: the sidebar's third tab. Pick a kind; each template
  shows a small diagram (rows = sections, boxes = columns, block names inside),
  a description, **Use for this page** (asks before replacing a page with
  content) and **Add to the end**. One undo step either way. Blocks whose type
  isn't available are left out, with a note.
- Templates use bracketed placeholders for text, and lean on blocks that fill
  themselves from the collection (random/recent items, collection cards,
  galleries, decades, subjects, counts, visit info from site settings).
- Buttons in templates link to "#" until changed, so `Normalizer::safeUrl()`
  now accepts "#…" links.
- An empty slideshow (no photos chosen yet) shows a note in the editor
  preview instead of nothing.

| Kind | Templates |
|---|---|
| Home | Classic welcome, Collection first, Story led, Simple and clear, Community hub |
| Browse | Collections grid, Explore by topic, Explore by time, Featured and recent, For researchers |
| Story | Photo essay, Then and now, Oral history feature, Story with sidebar, Exhibit with chapters |
| About | Mission and history, Our people, Partners and supporters, Questions and answers, Year in review |
| Contact | Plan your visit, Contact and newsletter, Research requests, Volunteer with us, Simple contact |
| Events and support | Events calendar, Featured event, Events and newsletter, Fundraising campaign, Support and membership |

## Tests
`npm test`: 16 suites, 296 checks, all pass. New `module/templates.test.js`
(13): checks every block and setting of all 30 templates against the real
schemas, the tab, replace/undo/add, and saves and renders each template.
ESLint and php-cs-fixer clean. Upgrade 0.7.0 → 0.8.0 works.

## Not verified
How the templates look in a real browser and in DHS's theme.
