# Handoff: Visual Page Builder v0.6.0 (small gaps filled)

John picked "fill the small gaps" from the list of what's left.

| Gap | What was built | Where |
|---|---|---|
| Visit info typed per block | A **Visit info** group in each site's settings; the block can show "A site's visit info" (this site or another). Hours are lines of `days \| times`. Links cleaned; notes plain text. Existing blocks keep their typed details (default source = block). | `src/Form/VisitInfoSettings.php` (form elements + `read()`), `VisitInfo.php`, template uses `$info`; listeners in `Module.php` |
| Events typed by hand | Optional **calendar feed** (.ics) per Upcoming events block, merged with typed events. Fetched server-side via `Omeka\HttpClient` (8 s timeout, 2 MB cap, http/https only), cached an hour in `files/visual-page-builder/`, last copy used on failure. Parser handles folding, all-day (exclusive DTEND), UTC and TZID times, escapes, CANCELLED. RRULE not expanded (first date only). | `src/Stdlib/CalendarFeed.php`; `UpcomingEvents::setCalendarFeed()` set by `SchemaBlockFactory` |
| Table of contents preview showed a shape | Lists the page's real sub-pages from the site navigation (menu labels, to the chosen depth); says so when the page isn't in the menu. | controller `subPages()` → `cfg.subPages`; `blocks/table-of-contents.js` |
| Society Blocks conversion only checked by hand | Automated test: uninstall + reinstall through Omeka's admin converts `society*` blocks and keeps settings. | `tests/module/upgrade-rename.test.js` |

Also: the test helper `createPage()` now makes slugs from titles, so test
pages can have real titles.

## Tests
`npm test`: 14 suites, 249 checks, all pass. New: visit info and calendar
feeds (13, with a local feed server), table of contents (3), upgrade rename (6),
8 more PHP unit checks for the feed parser. ESLint and php-cs-fixer clean.
Upgrade 0.5.1 → 0.6.0 works.

## Not verified
A real Google Calendar feed (tested with a hand-written .ics in the same
format), real browser, DHS's theme and server, Omeka 4.1. Server must be able to
reach the calendar's address (outbound HTTPS).
