# Handoff: Visual Page Builder 1.10.0 (theme unchanged, 1.0.3) — own map, no Leaflet

John (after a forum question about libraries): "Please write it from scratch"
(replace Leaflet). I'd recommended keeping Leaflet; John chose to replace it.

- `asset/map/vpb-map.js` + `vpb-map.css` (GPL, ~450 lines): `VpbMap.create(el,
  {wheelZoom: true | 'after-click', label})` → setView, fitBounds({padding,
  maxZoom}), flyTo(ll, z, {duration, animate}) (zooms out for long trips,
  eases; jumps with reduced motion), getZoom/getCenter, addPin(ll, {text,
  title, className, popup (escaped HTML), draggable, onClick, onDragEnd}) →
  {setLatLng, setClass, openPopup, el}, addLine(points, {dashed}), on('click'
  → [lat, lng]), toPoint/toLatLng. Web Mercator (`project`/`unproject`,
  checked against the OSM tile formula). Tiles from tile.openstreetmap.org,
  fractional zoom during pinch/fly by scaling the nearest level. Pointer
  Events for mouse/pen/touch: drag pan, two-finger pinch (settles on a whole
  level), click vs drag (4px); wheel (throttled; 'after-click' = only after
  focus/click, off on mouseleave/blur); double-click (+shift out); keyboard
  (arrows, + −, Escape); +/− buttons; OSM credit; ResizeObserver.
- Pins are buttons; the teardrop is an inner rotated span (rotating the
  positioned button itself skews its translate: found by the browser test).
  Pin colour via `--vpb-pin`.
- Callers switched: `special-blocks/js/places-map.js`, `story-map.js`
  (pins with `is-current`), editor `blocks/places-map.js` (click to add,
  drag to move). Templates and the editor page load `map/vpb-map.*`.
  `asset/vendor/leaflet` deleted; README "Third-party code: None".
- Tests: `module/vpb-map.test.js` (22, jsdom) and `tests/browser/maptest.py`
  (real Chromium with real touch events: tiles, pins in view, popup, Escape,
  mouse drag, wheel only after click, pinch, touch drag, arrows, Story map
  glide centres the stop, editor pin drag + click). Map tests updated.
  Full suite passes.
- Not testable here: real tiles (no internet; tests serve a blank tile) and
  real phones.

## 1.10.1
- `VpbMap.create` options `tileUrl` (template or function (z, x, y) → URL) and
  `credit` (HTML), so other tile pictures can be used. Used by the live demo
  page (https://claude.ai/artifact/W2mL5RrW97V4L3MpZYLdWC), which runs the real
  map code with drawn stand-in tiles (published pages can't load outside
  images). Mockup maps got the same +/− buttons.
