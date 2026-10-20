# Handoff: Visual Page Builder 1.0.7 (theme unchanged, 1.0.3)

John: "put a bar with a plus sign in a box midway that always shows where
you can drag sections or blocks."

- canvas.js `insBar(index)`: a dashed line with a centered boxed "+" before,
  between, and after sections (and under the empty-page message), whenever
  editing (hidden in Preview). While dragging, the drop placeholder takes the
  bar's place where the item would land; dropping on a bar works through the
  existing `secIndexAt` (bars aren't `.sec`, so indexes are unchanged).
- Click "+": `VPB.app.addAt(index)` sets `state.insertAt`, opens the Blocks
  panel (sheet on phones), highlights the bar, toasts what to do. The next
  palette tap goes there: a section preset at that index, or a block in a new
  section there (`tapAddSection` / `tapAddBlock`). Escape cancels.
- Section gap reduced (22px → 6px) since the bars now separate sections.
- Test `module/insert-bars.test.js` (9). Full suite passes.
