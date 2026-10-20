# Handoff: Visual Page Builder v0.11.0 (eight more Special Blocks)

John picked from my suggestions: Who's who in this photo, Item table,
Milestones, Did you know?, Open now, Embed from a trusted site, Share this
page, Breadcrumbs. 40 Special Blocks in all.

| Block | Notes |
|---|---|
| `specialPhotoHotspots` | Markers placed by numbers (across/down %) in the sidebar; `js/hotspots.js` bubble; numbered list always present (no-JS, screen readers; can be visually hidden). Click-to-place in the editor is NOT built (possible follow-up). |
| `specialItemTable` | Columns from a fixed set of Dublin Core fields; `js/item-table.js` sortable headings with `aria-sort`, numeric-aware. |
| `specialMilestones` | Typed list, order as entered, optional item/asset photo. |
| `specialDidYouKnow` | `pick()`: daily (crc32 of date + block) or random. |
| `specialOpenNow` | Per-day hours text parsed by `parseRange()` ("10-4" = 10 am–4 pm); closed dates; `status()` finds next opening within 14 days. Server-time, Omeka time zone (cached pages would show stale status). Hours live in the block, not Visit info. |
| `specialEmbed` | `toEmbedUrl()` allowlist: YouTube (→ youtube-nocookie), Vimeo, Google Maps embed, Google Forms (→ embedded=true), Google Calendar embed, archive.org. https only. Editor preview shows a labeled placeholder (no iframe). |
| `specialSharePage` | mailto/Facebook/X/LinkedIn links; Copy/Print via `js/share.js`; in a header/footer it shares the page being viewed. |
| `specialBreadcrumbs` | From `publicNav()->findActive()`; page not in the menu → Home › page; nothing on the home page; editor preview shows a note. |

Tests: new `special/v011-blocks.test.js` (16) and 31 more PHP unit checks
(hours parsing and open/closed at set times, embed allow/refuse cases, daily
facts). Full suite passes. Not verified in a real browser.
