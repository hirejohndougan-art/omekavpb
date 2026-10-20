# Handoff: Visual Page Builder 1.11.0 (theme unchanged, 1.0.3) — map polish

John asked if the own map is on par with Leaflet. My answer: on par for our
blocks, smoother in Leaflet in four ways. John: "Please do."

`asset/map/vpb-map.js`:
1. **Smooth zoom + no blank flashes**: `zoomAround()` animates fractional
   zoom over 250 ms (ease-out) around the pointer; repeated zooms continue
   from the target (`zoomTarget`); reduced motion → instant. `render()` keeps
   loaded tiles from other levels (≤3 apart, in view, z-index 1) until every
   tile of the current level has loaded (`done` on load/error →
   `queueRender()`), then drops them. Pinch end settles with `zoomAround(0)`.
2. **Momentum**: pan samples (last 6); on release within 80 ms of the last
   move and speed ≥0.3 px/ms, drift with decay 0.93/frame, speed capped at
   1.5 px/ms (about Leaflet's feel). Off with reduced motion.
3. **Popups stay in view**: after opening, the map pans (250 ms) so the
   popup is 10 px inside the map.
4. **Double-tap / double-click zoom** via `tap()`: two taps within 300 ms and
   30 px zoom in (shift: out). Single clicks are reported 260 ms later, so a
   double click/tap never adds two pins in the editor. The old dblclick
   handler is gone (dblclick only prevents text selection).
- Also `animatePan()`; `setView/panBy(keepAnim)` so animations can pan.

Tests: `module/vpb-map.test.js` 24 (delayed click, double click → zoom and
no click); `tests/browser/maptest-smooth.py` (real Chromium: mid-zoom scale,
old tiles kept while new held back, removed once loaded, momentum, popup
moved into view, double tap zoom, editor double-click adds no place, single
click still does); `maptest.py` adjusted for drift. Fixed a flaky preview
wait in `special/more-blocks.test.js`. Live demo republished (same URL).
