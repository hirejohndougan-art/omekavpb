# VPB Search speed checks (plan Phase 3)

Targets (plan section 2): search with three facets under 300 ms; suggestions under 100 ms;
full reindex as a background job.

1. Synthetic index (test install): `php tests/search/perf/generate.php 10000`, then
   `OMEKA_PATH=/home/claude/omeka-test/omeka php tests/search/perf/time.php`.
   Remove the rows afterward:
   ```
   DELETE FROM vpb_search_facet WHERE item_id>=9000001;
   DELETE FROM vpb_search_term WHERE item_id>=9000001;
   DELETE FROM vpb_search_doc WHERE item_id>=9000001;
   DELETE FROM vpb_search_facet_value WHERE label LIKE 'bench %';
   ```
2. The DHS collection: on a copy of the DHS site (SiteGround question 3 in the Phase 0 results),
   run `time.php` with `OMEKA_PATH` pointing at that install. Its queries use the synthetic
   words, so replace them with real words from the collection before reading the numbers.

Result on 10,000 synthetic items (test install, MariaDB 10.11, no site filter): search 5-105 ms,
three facets 5-43 ms, facet counts 31-37 ms, zero-result "did you mean" 43 ms.
