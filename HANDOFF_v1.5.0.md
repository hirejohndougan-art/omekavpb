# Handoff: Visual Page Builder 1.5.0 (theme unchanged, 1.0.3)

John approved the Timeline mockup artboard ("Timeline block and its
settings") and asked that map pins get the same: items, chosen images, details.

- **Timeline** (`Timeline.php`): `events` are now "Entries": title, date,
  `item_id` (link; fills an empty title/date from the item), `img`
  (none / item / asset) + `asset_id` (shown only for "Choose an image"),
  `text` = Details. Entries come first; items linked by an entry aren't
  added again by "Also add dated items automatically" (`use_items`, default
  still on). New display settings: `size` (small/medium/large), `show_images`,
  `show_details`. Old saved events keep working (img defaults to item; no
  item → no picture).
- **Map of places**: each place gets the same `img` / `asset_id` choice; the
  popup and list use it.
- **All list settings** (`schema.js` `listControl`): rows are a one-line
  summary (number, first field, second field, a picture from an item/asset
  field) and open to edit one at a time; a new row opens by itself; fields
  with `show_if` inside rows follow that row's values.
- Test `special/timeline-entries.test.js` (14). Full suite passes.
- The mockup's Timeline artboard is now the reference design (no longer
  "proposal").
