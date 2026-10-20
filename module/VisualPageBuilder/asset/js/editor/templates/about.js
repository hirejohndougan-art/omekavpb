/* SPDX-License-Identifier: GPL-2.0-or-later */
/** About page templates. See catalog.js for the format. */
(function (VPB) {
'use strict';
var T = VPB.templates.T, add = function (t) { VPB.templates.add('about', t); };

add({
  id: 'about-mission', name: 'Mission and history',
  description: 'Your mission, a short history of the society, and what the collection holds today.',
  sections: [
    { cols: [12], blocks: [[T.title()]] },
    { cols: [8, 4], blocks: [
      [T.text('Our mission', ['[Your mission statement.]']), T.text('Our history', ['[When and why the society began, and milestones since.]'])],
      [T.block('specialCollectionCounts', { heading: 'The collection today', style: 'large' })]
    ] },
    { cols: [12], blocks: [[T.block('specialButtons', { align: 'center', buttons: [{ text: 'Become a member', url: '#', style: 'primary' }, { text: 'Volunteer', url: '#', style: 'secondary' }] })]] }
  ]
});

add({
  id: 'about-people', name: 'Our people',
  description: 'Introduce the board, staff, and volunteers with photos and short bios.',
  sections: [
    { cols: [12], blocks: [[T.title(), T.p('[A sentence about the people who keep the society going.]')]] },
    { cols: [12], blocks: [[T.block('specialPeople', { heading: 'Board', people: [{ name: '[Name]', role: 'President' }, { name: '[Name]', role: 'Vice president' }, { name: '[Name]', role: 'Treasurer' }, { name: '[Name]', role: 'Secretary' }] })]] },
    { cols: [12], blocks: [[T.block('specialPeople', { heading: 'Staff and volunteers', photo_shape: 'square', people: [{ name: '[Name]', role: '[Role]' }, { name: '[Name]', role: '[Role]' }] })]] }
  ]
});

add({
  id: 'about-partners', name: 'Partners and supporters',
  description: 'Thank your partners and sponsors, with a word from one of them.',
  sections: [
    { cols: [12], blocks: [[T.title(), T.p('[How partnerships help the society.]')]] },
    { cols: [12], blocks: [[T.block('specialPartners', { heading: 'Partners', logo_height: 'large', partners: [{ name: '[Partner]' }, { name: '[Partner]' }, { name: '[Partner]' }, { name: '[Partner]' }] })]] },
    { cols: [12], blocks: [[T.block('specialQuote', { text: '[A word from a partner]', attribution: '[Name, organization]', style: 'boxed' })]] },
    { cols: [12], blocks: [[T.block('specialButtons', { align: 'center', buttons: [{ text: 'Become a sponsor', url: '#', style: 'primary' }] })]] }
  ]
});

add({
  id: 'about-faq', name: 'Questions and answers',
  description: 'Common questions in sections that open when clicked, and a way to ask more.',
  sections: [
    { cols: [12], blocks: [[T.title()]] },
    { cols: [12], blocks: [[T.block('specialAccordion', { open_first: true, panels: [
      { title: 'When are you open?', body: '<p>[Your hours]</p>' },
      { title: 'Can I donate items to the collection?', body: '<p>[How donations work]</p>' },
      { title: 'Can I get copies of photographs?', body: '<p>[How to request copies]</p>' },
      { title: 'How can I volunteer?', body: '<p>[How to get involved]</p>' }
    ] })]] },
    { cols: [12], blocks: [[T.p('Still have a question? [How to reach you].')]] }
  ]
});

add({
  id: 'about-year', name: 'Year in review',
  description: 'An annual-report style page: numbers, a campaign, highlights, and thanks to partners.',
  sections: [
    { cols: [12], blocks: [[T.title(), T.p('[A summary of the year.]')]] },
    { cols: [6, 6], blocks: [
      [T.block('specialCollectionCounts', { show_with_media: true, style: 'large' })],
      [T.block('specialFundraising', { title: '[This year\u2019s campaign]', goal: 10000, raised: 0 })]
    ] },
    { cols: [12], blocks: [[T.text('Highlights', ['[Highlight one]', '[Highlight two]', '[Highlight three]'])]] },
    { cols: [12], blocks: [[T.block('specialPartners', { heading: 'Thank you', partners: [{ name: '[Supporter]' }, { name: '[Supporter]' }] })]] }
  ]
});
})(window.VPB);
