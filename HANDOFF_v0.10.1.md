# Handoff: v0.10.1 / Companion Theme 0.2.1 (big check)

John asked for "a big check". What was done:

1. **Whole suite with both error logs watched** (Omeka 4.2.1): 19 suites,
   357 checks pass. Only logged errors are the ones tests trigger on purpose
   (permission denied, not found). No PHP warnings or deprecations.
2. **Fresh install of Omeka 4.1.1** (first time on 4.1), installing the module
   and theme **from the actual download zip**, HTML purifier on, then all 19
   suites against it: all pass. Two tests assumed 4.2 (IIIF blocks only exist
   from 4.2) or purifier off; they now adapt. Only deprecations logged come
   from Omeka 4.1's own Laminas library on PHP 8.3.
3. **PHP 7.4 compatibility scan** (Omeka 4.1 allows 7.4): no PHP 8-only
   functions or syntax found. (Not run on an actual 7.4.)
4. **All 32 Special Blocks, empty and filled, with a built header and
   footer, in the Companion Theme:** page renders in ~0.3 s, 48 KB; no
   duplicate ids, every image has alt text, every form field a label,
   every aria-controls target exists, no javascript: links; editor loads
   all 64 with no errors.
   - Found: pages without a Page title block had no `<h1>` (screen-reader
     users navigate by headings). Fixed in the theme: a visually hidden h1
     with the page title is added when the content has none.
5. **Hostile and empty input** (earlier check, still covered by tests).
6. **Uninstall:** now removes only the calendar cache in
   `files/visual-page-builder/`; content and settings are kept so a
   reinstall restores everything (documented in `Module::uninstall`).

Known, not changed:
- Opening Header & footer creates the hidden page with a GET request; a
  forged link could only create an empty header/footer page (no harm).
- Omeka's site search may list the hidden header/footer pages.
- Still nothing tested in a real browser.

Test-env note: a second Omeka (4.1.1) can run beside the main one:
`/home/claude/omeka41`, DB `omeka41`, port 8081, `router41.php`; run tests
with `VPB_BASE=http://127.0.0.1:8081 VPB_JAR=/tmp/j41.txt OMEKA_PATH=/home/claude/omeka41`.
