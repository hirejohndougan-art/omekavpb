/* SPDX-License-Identifier: GPL-2.0-or-later */
/** Ready-made sections: one section each, added anywhere on a page. See catalog.js. */
(function (VPB) {
'use strict';
var T = VPB.templates.T, add = function (t) { VPB.templates.add('sections', t); };

add({ id: 'sec-text-image', name: 'Text beside a photo', description: 'A heading and a paragraph next to an item\u2019s photo.',
  sections: [{ cols: [7, 5], blocks: [[T.text('[Heading]', ['[A paragraph about the subject.]'])], [T.block('specialItemSpotlight', { mode: 'fixed', heading: '', image_position: 'top', show_description: false })]] }] });
add({ id: 'sec-image-text', name: 'Photo beside text', description: 'The same, with the photo on the left.',
  sections: [{ cols: [5, 7], blocks: [[T.block('specialItemSpotlight', { mode: 'fixed', heading: '', image_position: 'top', show_description: false })], [T.text('[Heading]', ['[A paragraph about the subject.]'])]] }] });
add({ id: 'sec-call-to-action', name: 'Call to action band', description: 'A colored band with a short message and buttons.',
  sections: [{ cols: [12], bg: '#0F5E54', blocks: [[T.text('[Get involved]', ['[One line inviting visitors to act.]']), T.block('specialButtons', { align: 'center', buttons: [{ text: 'Become a member', url: '#', style: 'primary' }, { text: 'Volunteer', url: '#', style: 'secondary' }] })]] }] });
add({ id: 'sec-three-features', name: 'Three features', description: 'Three columns, each with a heading, a line of text, and a link.',
  sections: [{ cols: [4, 4, 4], blocks: [[T.text('[First]', ['[A line about it.]'])], [T.text('[Second]', ['[A line about it.]'])], [T.text('[Third]', ['[A line about it.]'])]] }] });
add({ id: 'sec-quote-band', name: 'Quote band', description: 'A large quote across a warm background.',
  sections: [{ cols: [12], bg: '#F1EEE4', blocks: [[T.block('specialQuote', { text: '[A memorable line]', attribution: '[Who said it]', style: 'large' })]] }] });
add({ id: 'sec-numbers', name: 'By the numbers', description: 'Collection counts beside a short paragraph.',
  sections: [{ cols: [6, 6], blocks: [[T.text('[The collection]', ['[What it holds and where it comes from.]'])], [T.block('specialCollectionCounts', { style: 'large' })]] }] });
add({ id: 'sec-photo-strip', name: 'Photo strip', description: 'A row of photos from the collection.',
  sections: [{ cols: [12], blocks: [[T.block('specialRandomItems', { heading: '', count: 6, columns: 'auto', show_title: false })]] }] });
add({ id: 'sec-faq', name: 'Questions and answers', description: 'A heading and sections that open when clicked.',
  sections: [{ cols: [12], blocks: [[T.block('specialAccordion', { heading: '[Questions]', panels: [{ title: '[Question one]', body: '<p>[Answer]</p>' }, { title: '[Question two]', body: '<p>[Answer]</p>' }] })]] }] });
add({ id: 'sec-visit-strip', name: 'Visit strip', description: 'Open now and your visit info side by side.',
  sections: [{ cols: [5, 7], bg: '#E3ECE5', blocks: [[T.block('specialOpenNow', { source: 'site', show_week: true })], [T.block('specialVisitInfo', { source: 'site', heading: '', layout: 'stacked' })]] }] });
add({ id: 'sec-newsletter-band', name: 'Newsletter band', description: 'A newsletter sign-up across the page.',
  sections: [{ cols: [12], bg: '#F3EBD9', blocks: [[T.block('specialNewsletter', { heading: 'Stay in touch' })]] }] });
})(window.VPB);
