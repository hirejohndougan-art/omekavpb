# Handoff: Visual Page Builder 1.6.0 (theme unchanged, 1.0.3) — Story map

John's idea: a chronological map: map above, timeline below; play, pause,
rewind; the map jumps to each stop as the timeline plays; an information pane
with a background photo and text, plus videos (YouTube etc.). Mocked up first
(artboard "Story map"); John approved ("I like story map").

- `StoryMap.php` (`specialStoryMap`): stops list: title, date, lat, lng,
  item_id (fills empty title/date), img (none/item/asset) + asset_id = pane
  background, text, video (none / url → `Embed::toEmbedUrl`, YouTube or Vimeo
  only / item → first video/* media file). Sorted by `Timeline::parseDate`;
  stops without coordinates, date, or title are skipped. Settings: seconds
  (4/6/10/15), loop, pane (right/left/below), height.
- Template `story-map.phtml`: map box, all panes pre-rendered (iframes use
  `data-src`), controls hidden until the script runs, timeline as buttons.
  Without JS: every pane shows in order, with "Watch the video" links.
- `js/story-map.js`: Leaflet map with numbered pins (current one larger and
  orange); `show(i)` hides other panes, unloads their videos, loads the
  current one's, marks the timeline (`aria-current="step"`), flies the map
  (or `setView` without animation for reduced motion). Play = setInterval;
  never autoplays; pauses on a stop with a video ("Paused for the video");
  loop option. `box.storyMap` exposes show/play/pause.
- Editor: `blocks/places-map.js` generalized (`LIST` per layout) so the same
  click-to-place map serves the Story map; a new stop's form asks name and
  date.
- Test `special/story-map.test.js` (21). Full suite passes. Not verified in
  a real browser (Leaflet flyTo, the video embeds).
