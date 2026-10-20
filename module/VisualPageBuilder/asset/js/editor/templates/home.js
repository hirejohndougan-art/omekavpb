/* SPDX-License-Identifier: GPL-2.0-or-later */
/** Home page templates. See catalog.js for the format. */
(function (VPB) {
'use strict';
var T = VPB.templates.T, add = function (t) { VPB.templates.add('home', t); };

add({
  id: 'home-classic', name: 'Classic welcome',
  description: 'A big photo with your name, a welcome, three quick links, and a taste of the collection.',
  sections: [
    { cols: [12], blocks: [[T.block('specialHeroBanner', { title: '[Your society\u2019s name]', subtitle: '[One line about who you are and what you keep]', button_text: 'Explore the collection', button_url: '#', height: 'large' })]] },
    { cols: [8, 4], blocks: [
      [T.text('Welcome', ['[A short welcome: what visitors will find here, and why your town\u2019s history matters.]'])],
      [T.block('specialVisitInfo', { source: 'site', heading: 'Visit us', layout: 'stacked' })]
    ] },
    { cols: [12], blocks: [[T.block('specialRandomItems', { heading: 'From the collection', count: 6, link_text: 'Browse everything' })]] }
  ]
});

add({
  id: 'home-collection-first', name: 'Collection first',
  description: 'Leads with a big search box, then your collections and the newest additions.',
  sections: [
    { cols: [12], bg: '#F1EEE4', pad: 32, blocks: [[T.title(), T.p('[One sentence inviting visitors to search the collection.]'), T.block('specialSearchBox', { size: 'large' })]] },
    { cols: [12], blocks: [[T.block('specialCollectionCards', { heading: 'Our collections', count: 8 })]] },
    { cols: [8, 4], blocks: [
      [T.block('specialRecentItems', { heading: 'Recently added', count: 4 })],
      [T.block('specialCollectionCounts', { heading: 'By the numbers', style: 'large' })]
    ] }
  ]
});

add({
  id: 'home-story-led', name: 'Story led',
  description: 'A slideshow of highlights, picture tiles into your topics, an item of the day, and a newsletter sign-up.',
  sections: [
    { cols: [12], blocks: [[T.block('specialSlideshow', { slides: [{ title: '[Highlight one]', text: '[A line about it]', button_text: 'Read the story', url: '#' }, { title: '[Highlight two]', text: '[A line about it]', button_text: 'See more', url: '#' }] })]] },
    { cols: [12], blocks: [[T.block('specialTopicTiles', { heading: 'Explore by topic', tiles: [{ title: '[Topic one]', url: '#' }, { title: '[Topic two]', url: '#' }, { title: '[Topic three]', url: '#' }, { title: '[Topic four]', url: '#' }] })]] },
    { cols: [6, 6], blocks: [
      [T.block('specialItemSpotlight', { heading: 'Item of the day', image_position: 'top' })],
      [T.block('specialNewsletter', { heading: 'Stay in touch' })]
    ] }
  ]
});

add({
  id: 'home-simple', name: 'Simple and clear',
  description: 'Title, a short welcome, three big buttons, and a row of photos. Easy to keep up to date.',
  sections: [
    { cols: [12], blocks: [[T.title(), T.p('[Two or three sentences about the society and what visitors can do here.]'), T.block('specialButtons', { size: 'large', buttons: [{ text: 'Visit', url: '#', style: 'primary' }, { text: 'Explore the collection', url: '#', style: 'secondary' }, { text: 'Support us', url: '#', style: 'secondary' }] })]] },
    { cols: [12], blocks: [[T.block('specialRandomItems', { count: 4, columns: '4', show_title: false })]] }
  ]
});

add({
  id: 'home-community', name: 'Community hub',
  description: 'For a busy society: a notice bar, a welcome photo, upcoming events, a fundraising goal, and partners.',
  sections: [
    { cols: [12], blocks: [[T.block('specialAnnouncement', { text: '[A current notice, such as holiday hours]', style: 'info' })]] },
    { cols: [12], blocks: [[T.block('specialHeroBanner', { title: '[Welcome headline]', subtitle: '[A line about what\u2019s happening this season]', button_text: 'See events', button_url: '#' })]] },
    { cols: [6, 6], blocks: [
      [T.block('specialUpcomingEvents', { heading: 'Coming up', count: 4 })],
      [T.block('specialFundraising', { title: '[Current campaign]', goal: 10000, raised: 0, text: '[Why this campaign matters]', button_url: '#' })]
    ] },
    { cols: [12], blocks: [[T.block('specialPartners', { heading: 'With thanks to', partners: [{ name: '[Partner one]' }, { name: '[Partner two]' }, { name: '[Partner three]' }], grayscale: true })]] }
  ]
});
})(window.VPB);
