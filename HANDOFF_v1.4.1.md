# Handoff: Visual Page Builder 1.4.1 (theme unchanged, 1.0.3)

John: when clicking the map to add a point, you should be able to name the
spot and attach an item. (Also asked: timeline module or block? Answer: a
block, Special Block "Timeline", since 1.1.0.)

- `blocks/places-map.js`: a click adds an unnamed place and opens a "New
  place" form right under the sidebar map: Name (focused; Enter = Done),
  "Link an item (optional)…" (item picker; an unnamed place takes the item's
  title), Done (unnamed → "Place N"), Remove this pin. Selecting another
  block drops the form. `VPB.placesMapTool.naming()` for tests.
- Mockup updated the same way (name box, item list, Done, Remove).
- Test: map-timeline +3 (form appears, Done keeps the name, linking an item
  names an unnamed place). Full suite passes.
