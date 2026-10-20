# Handoff: v0.11.1 (the two "worth knowing" items, plus a time zone bug)

Special Blocks count: 40 (`SpecialBlocks::LAYOUTS`).

1. **Click-to-place markers** (Who's who): `asset/js/editor/blocks/photo-hotspots.js`
   adds a tool above the generated form via the new `VPB.blocks.extend(layout, fn)`
   hook (schema.js calls it first). Click the photo to add a marker; drag to
   move (window listeners, percent of the image box). Numbers still editable.
   Item lookups now include a `large` image so positions match the real shape.
2. **Open now from Visit info:** new `source` (default "The site's Visit
   info", optional other site). `parseDays()` reads "Tuesday to Saturday",
   "Tue-Sat", "Mon, Wed & Fri", "Weekends", "Weekdays", "Daily", ranges across
   the weekend; `hoursFromVisitInfo()` builds the week. Visit info gained
   "Closed dates" (`2026-11-26 | Thanksgiving`). Old blocks with their own
   hours keep using them if the site has none readable.
   **Live status:** `special-blocks/js/open-now.js` recomputes in the browser
   every minute in the society's time zone (Intl), so cached pages stay right.
3. **Bug found and fixed while testing:** Omeka only uses its time zone
   setting for formatting; PHP's default stayed UTC here, so "today"/"now"
   in announcements, events, on this day, item of the day, did you know, open
   now, copyright year, and calendar feeds followed the server, not Omeka.
   Blocks now get the setting from the factory (`setTimeZone`, `now()`,
   `today()` in AbstractSchemaBlock); CalendarFeed takes the zone too (cache
   key includes it). Event dates are built at noon in that zone so formatting
   can't shift them a day (now tested: "June 14, 2099" stays June 14).

Tests: 81 PHP unit checks (+8 for days/hours), v011 e2e 22 (Visit-info hours,
closed date with reason, browser recompute, Omeka time zone, markers click and
drag), render test +1 (event dates). Full suite passes.
