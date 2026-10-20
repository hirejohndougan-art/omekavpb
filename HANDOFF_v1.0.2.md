# Handoff: Visual Page Builder 1.0.2 + Companion Theme 1.0.1

John (screenshot of a header in the editor): the menu doesn't left-justify
when he chooses left; empty "Drop blocks here" sections can't be removed and
add space above and below the header.

**Alignment.** The sidebar showed two alignment controls for the Site menu:
the block's own Alignment (Content › Display) and the general "Text
alignment" (Layout). The general one only sets text-align, which can't move
a flex menu, so choosing Left there did nothing. Now blocks whose schema has
an `align` field hide the general control and say where theirs is. Also added
an explicit left rule, and in the theme the block's own alignment beats the
menu style's placement (e.g. "Logo left, menu right" had `margin-left: auto`).
Not verified in a real browser which control John used; both paths fixed.

**Empty sections.**
- Editor: a section with nothing in any column shows "Remove this empty
  section" in its placeholder (the section's own Delete only appears once the
  section itself is selected, which is hard when clicking selects the column).
- Saving: empty sections are no longer saved (`exportBlocks`).
- Public: empty groups already saved are skipped (`PageLayout`), so existing
  headers lose the gaps without re-saving.
- Templates: "Add to the end" on a page with only empty sections replaces
  them (the new header page starts with one empty section, which caused the gap
  above).

Tests: regions +6; the edge test now expects empty sections to be left out. The test seed re-dates the On this day items to "today" (site time zone) on each run, since the fixtures went stale overnight (test data, not a module bug). Full suite passes.
