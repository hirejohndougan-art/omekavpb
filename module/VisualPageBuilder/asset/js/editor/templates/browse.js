/* SPDX-License-Identifier: GPL-2.0-or-later */
/** Browse and explore templates. See catalog.js for the format. */
(function (VPB) {
'use strict';
var T = VPB.templates.T, add = function (t) { VPB.templates.add('browse', t); };

add({
  id: 'browse-collections', name: 'Collections grid',
  description: 'A search box over a grid of every collection, with how many items each holds.',
  sections: [
    { cols: [12], blocks: [[T.title(), T.p('[A sentence on what the collections hold and how to use them.]'), T.block('specialSearchBox', {})]] },
    { cols: [12], blocks: [[T.block('specialCollectionCards', { heading: '', count: 24, show_description: true })]] }
  ]
});

add({
  id: 'browse-topics', name: 'Explore by topic',
  description: 'Picture tiles for your main themes, then every subject with how many items it has.',
  sections: [
    { cols: [12], blocks: [[T.title(), T.p('[Invite visitors to start with a theme.]')]] },
    { cols: [12], blocks: [[T.block('specialTopicTiles', { heading: '', columns: '3', tiles: [{ title: '[Theme one]', url: '#' }, { title: '[Theme two]', url: '#' }, { title: '[Theme three]', url: '#' }, { title: '[Theme four]', url: '#' }, { title: '[Theme five]', url: '#' }, { title: '[Theme six]', url: '#' }] })]] },
    { cols: [12], blocks: [[T.block('specialSubjectsList', { heading: 'All subjects', count: 40, style: 'pills' })]] }
  ]
});

add({
  id: 'browse-time', name: 'Explore by time',
  description: 'Decades to click through, items from this day in history, and the newest additions.',
  sections: [
    { cols: [12], blocks: [[T.title(), T.p('[A sentence inviting visitors to travel through time.]')]] },
    { cols: [12], blocks: [[T.block('specialBrowseByDecade', { heading: 'Choose a decade' })]] },
    { cols: [6, 6], blocks: [
      [T.block('specialOnThisDay', { count: 4, empty_text: 'Nothing dated today. Try a decade above.' })],
      [T.block('specialRecentItems', { count: 4 })]
    ] }
  ]
});

add({
  id: 'browse-featured', name: 'Featured and recent',
  description: 'An item of the day up top, a photo gallery from the whole collection, and what\u2019s new.',
  sections: [
    { cols: [12], blocks: [[T.block('specialItemSpotlight', { heading: 'Item of the day' })]] },
    { cols: [12], blocks: [[T.block('specialGallery', { heading: 'Photographs', count: 12 })]] },
    { cols: [12], blocks: [[T.block('specialRecentItems', { count: 6, link_text: 'Browse all items' })]] }
  ]
});

add({
  id: 'browse-research', name: 'For researchers',
  description: 'Search, subjects, decades, collection numbers, and how to cite and request copies.',
  sections: [
    { cols: [12], blocks: [[T.title(), T.p('[How the collection is organized and how to get help with research.]'), T.block('specialSearchBox', { show_label: true })]] },
    { cols: [8, 4], blocks: [
      [T.block('specialSubjectsList', { heading: 'Subjects', style: 'list', count: 50, sort: 'alpha' })],
      [T.block('specialBrowseByDecade', { heading: 'Decades' }), T.block('specialCollectionCounts', { style: 'inline' })]
    ] },
    { cols: [12], blocks: [[T.block('specialCitation', { organization: '[Your organization]' })]] }
  ]
});
})(window.VPB);
