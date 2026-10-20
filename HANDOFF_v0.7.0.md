# Handoff: Visual Page Builder v0.7.0 (13 more Special Blocks)

John asked what other blocks would help, then "build them": all 13 proposed.

| Block (layout) | Notes |
|---|---|
| Announcement bar (`specialAnnouncement`) | Shown only between start/end dates (Omeka time zone, end inclusive); editor preview always shows it with a "not showing today" note. Close button remembered per notice text (`js/announcement.js`). |
| Featured slideshow (`specialSlideshow`) | Accessible carousel: Previous/Next, dots, Pause; pauses on hover/focus; no auto-advance with reduced motion; slides stack without JS (`js/slideshow.js`). |
| Gallery (`specialGallery`) | Chosen items or the usual source; `<dialog>` viewer with arrows/keyboard; plain item links without JS (`js/gallery.js`). |
| Explore by topic (`specialTopicTiles`) | Tiles: title, item-or-asset image, text, page or URL. |
| Browse by decade (`specialBrowseByDecade`) | API counts per decade with property `sw` ("191"); links to this site's browse. |
| Subjects list (`specialSubjectsList`) | `Stdlib\ValueCounts` SQL (public items and values only, site/item-set filters); pills/list/cloud. |
| Oral history / Document and transcription | Shared `AbstractTranscriptBlock`; transcript from a property (default `bibo:content`); media drawn by Omeka's renderer. |
| People, Partners and sponsors, Fundraising progress | Lists of typed entries; emails validated; progress bar with ARIA. |
| Newsletter sign-up (`specialNewsletter`) | Posts straight to the service; action must be https; field names validated. |
| How to cite and reuse (`specialCitation`) | Placeholders filled per page; Copy button (`js/citation.js`). Uses the new `AbstractSchemaBlock::$renderingBlock` to know its page. |

Shared additions: `Stdlib\ImageSource` (item-or-asset image fields + URL),
factory injects `ValueCounts` via `setValueCounts()`.

## Bugs caught by the new tests
- `BrowseByDecade::decades()` reused a variable it had just changed, so a
  reversed range came out wrong. Fixed.
- The test seed's PATCH replaced all of an item's values (Omeka replaces every
  value when any are sent), wiping titles. The seed now sends existing values
  back. Test data only; the module wasn't affected.

## Tests
`npm test`: 15 suites, 283 checks, all pass. New suite `special/more-blocks`
(28) plus 6 PHP unit checks. ESLint and php-cs-fixer clean. Upgrade 0.6.0 →
0.7.0 works.

## Not verified
Real browser (slideshow motion, gallery dialog, copy button), real Mailchimp
sign-up, how these look in DHS's theme, Omeka 4.1.
