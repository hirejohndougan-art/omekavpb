# Handoff: Visual Page Builder 1.1.0 (theme unchanged, 1.0.3)

John: "start building everything except the Classic module", plus "a map
using OpenStreetMap where we can pin places and link items" and "a timeline".
Done in batches; this is batch 1.

## Background images
- Sections, columns, blocks: `target.img = {asset, size: cover|contain, pos:
  top|center|bottom, overlay}` (`model.cleanImg`). Sections and blocks save to
  Omeka's own `background_image_asset/_size/_position_y/_position_x`;
  columns to `vpb_section.cols[i].img` (PageLayout renders the style).
  Darkening = classes `vpb-overlay` + `vpb-on-dark` (stripped on load).
- Sidebar: `fields.bgImageField` under Background (asset picker, Fit,
  Position, "Darken…"). Canvas: `VPB.bgImageCss`. Server refs include
  background assets so thumbnails show on load. public.css: overlay and
  Omeka's size/position classes (Omeka loads its copy only on page views,
  not in theme headers/footers).
- Assets only (Omeka's setting takes an asset); items aren't offered.

## Map of places (`specialMap`)
- Places: name, latitude, longitude (text, validated by `PlacesMap::coord`),
  note, linked item. Tour mode: numbered pins, dashed line in order,
  numbered list (this covers "walking tours" from the idea list).
- Leaflet 1.9.4 bundled in `asset/vendor/leaflet` (BSD-2, LICENSE kept);
  OSM tiles with attribution; scroll-zoom only after clicking the map.
- Editor: `blocks/places-map.js` adds a map above the settings; click adds a
  place, drag moves one (`VPB.placesMapTool.addPlace`). Leaflet is loaded on
  the editor page. Preview shows a placeholder box + list (no live map in
  previews).

## Timeline (`specialTimeline`)
- Items with a date (property `ex`), sorted; dates read loosely
  (`Timeline::parseDate`: first 4-digit year, optional -MM-DD); typed events
  merged in; oldest/newest first; across (scrolls; Earlier/Later buttons in
  `js/timeline.js`) or down the page.

## Tests
New `module/bg-images` (14), `special/map-timeline` (15), PHP unit +5.
Full suite passes. Not verified in a real browser (Leaflet especially).

## Next (batch 2)
Theme-accurate previews; links and images in inline text; per-device column
widths; section templates. Ask John about the out-of-module items.
