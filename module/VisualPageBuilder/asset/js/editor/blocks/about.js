/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * Plain-language help for Omeka's own blocks, shown by the palette's ⓘ
 * buttons and under a block's name in its settings. Other modules' blocks
 * send their own help text with their schema.
 */
(function (VPB) {
'use strict';

var ABOUT = {
  'html:heading': ['A title that introduces a part of the page. You type it right on the page.', 'Starting a new part of a page.'],
  'html:para': ['Regular text. You type it right on the page; Ctrl+B and Ctrl+I make words bold or italic.', 'Introductions, stories, and explanations.'],
  html: ['Text with formatting: paragraphs, lists, links. Heading and Paragraph are quicker for plain text.', 'Longer formatted text.'],
  pageTitle: ['Shows this page\u2019s title as a large heading. There is nothing to set.', 'The top of most pages.'],
  asset: ['Shows one or more images you uploaded to the site (not from the collection), each with an optional caption and link.', 'Logos, banners, and decorative photos.'],
  browsePreview: ['Shows a short list of items from a search, such as everything about the railroad, with a link to see them all.', 'Pointing visitors to a group of items.'],
  iiifImage: ['Shows a zoomable image hosted on another institution\u2019s IIIF image server.', 'Very large scans visitors can zoom into.'],
  iiifPresentation: ['Shows a page-turning viewer for a document hosted elsewhere, using its IIIF manifest address.', 'Books, letters, and scrapbooks from partner archives.'],
  itemWithMetadata: ['Shows one item\u2019s images together with its full description: title, date, creator, and the rest.', 'Featuring a single object in detail.'],
  lineBreak: ['Adds blank space between blocks, or a thin line to separate them.', 'Breathing room on busy pages.'],
  listOfPages: ['Shows links to pages on this site that you choose.', 'A \u201Cmore to explore\u201D list.'],
  listOfSites: ['Shows links to the other sites on this Omeka installation.', 'A front page that connects several sites.'],
  media: ['Shows images or files from items you pick, with captions.', 'A small gallery inside a story.'],
  oembed: ['Embeds a video or other media from sites like YouTube or Vimeo, using its web address.', 'Oral history videos and recorded talks.'],
  pageDateTime: ['Shows when this page was created or last updated.', 'Letting visitors know how current a page is.'],
  tableOfContents: ['Lists the pages that sit under this page in the site\u2019s menu.', 'Exhibit landing pages with several chapters.']
};

Object.keys(ABOUT).forEach(function (key) {
  VPB.blocks.describe(key, { description: ABOUT[key][0], goodFor: ABOUT[key][1] });
});
})(window.VPB);
