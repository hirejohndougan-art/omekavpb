/* SPDX-License-Identifier: GPL-2.0-or-later */
/** Header and footer templates (shown only when editing those). See catalog.js. */
(function (VPB) {
'use strict';
var T = VPB.templates.T;
var header = function (t) { VPB.templates.add('header', t); };
var footer = function (t) { VPB.templates.add('footer', t); };

header({
  id: 'header-classic', name: 'Logo and menu',
  description: 'Your logo on the left, the menu and a search box on the right.',
  sections: [{ cols: [3, 6, 3], blocks: [[T.block('specialLogo', {})], [T.block('specialSiteMenu', { align: 'right' })], [T.block('specialSearchBox', { size: 'normal', heading: '' })]] }]
});
header({
  id: 'header-centered', name: 'Centered',
  description: 'Your logo centered at the top with the menu centered under it.',
  sections: [{ cols: [12], blocks: [[T.block('specialLogo', { align: 'center', height: 'large' }), T.block('specialSiteMenu', { align: 'center' })]] }]
});
header({
  id: 'header-two-rows', name: 'Two rows with search',
  description: 'Name and tagline beside a big search box, with the menu in a row of its own below.',
  sections: [
    { cols: [7, 5], blocks: [[T.block('specialSiteTitle', { tagline: '[Your tagline]', size: 'large' })], [T.block('specialSearchBox', { size: 'normal' })]] },
    { cols: [12], bg: '#F1EEE4', blocks: [[T.block('specialSiteMenu', { align: 'left' })]] }
  ]
});
header({
  id: 'header-announcement', name: 'With an announcement bar',
  description: 'A notice bar across the top (it hides itself between your dates), then logo and menu.',
  sections: [
    { cols: [12], blocks: [[T.block('specialAnnouncement', { text: '[A current notice, such as holiday hours]', style: 'info' })]] },
    { cols: [4, 8], blocks: [[T.block('specialLogo', {})], [T.block('specialSiteMenu', { align: 'right' })]] }
  ]
});
header({
  id: 'header-minimal', name: 'Minimal with buttons',
  description: 'Just your name and two buttons, Visit and Donate. The menu can live in the footer.',
  sections: [{ cols: [8, 4], blocks: [[T.block('specialSiteTitle', { size: 'medium' })], [T.block('specialButtons', { align: 'right', buttons: [{ text: 'Visit', url: '#', style: 'secondary' }, { text: 'Donate', url: '#', style: 'primary' }] })]] }]
});

footer({
  id: 'footer-simple', name: 'Simple',
  description: 'Social links and a copyright line, centered.',
  sections: [{ cols: [12], blocks: [[T.block('specialSocialLinks', { align: 'center' }), T.block('specialCopyright', {})]] }]
});
footer({
  id: 'footer-three-columns', name: 'Three columns',
  description: 'Visit info, a list of your pages, and social links, with a copyright line below.',
  sections: [
    { cols: [4, 4, 4], blocks: [
      [T.block('specialVisitInfo', { source: 'site', heading: 'Visit', layout: 'stacked' })],
      [T.block('specialSiteMenu', { style: 'list', align: 'left', heading: 'Explore' })],
      [T.block('specialSocialLinks', { heading: 'Follow us' })]
    ] },
    { cols: [12], blocks: [[T.block('specialCopyright', {})]] }
  ]
});
footer({
  id: 'footer-newsletter', name: 'Newsletter and social',
  description: 'A newsletter sign-up beside your social links, then the copyright line.',
  sections: [
    { cols: [7, 5], blocks: [[T.block('specialNewsletter', { heading: 'Stay in touch' })], [T.block('specialSocialLinks', { heading: 'Follow us' })]] },
    { cols: [12], blocks: [[T.block('specialCopyright', {})]] }
  ]
});
footer({
  id: 'footer-partners', name: 'Partners strip',
  description: 'A row of partner and sponsor logos, then the copyright line.',
  sections: [
    { cols: [12], blocks: [[T.block('specialPartners', { heading: 'With thanks to', logo_height: 'small', grayscale: true, partners: [{ name: '[Partner]' }, { name: '[Partner]' }, { name: '[Partner]' }] })]] },
    { cols: [12], blocks: [[T.block('specialCopyright', {})]] }
  ]
});
footer({
  id: 'footer-big', name: 'Large with site map',
  description: 'Logo and a line about you, every page as a site map, visit info, and a short sign-up; copyright and social links below.',
  sections: [
    { cols: [3, 3, 3, 3], blocks: [
      [T.block('specialLogo', {}), T.p('[One line about the society]')],
      [T.block('specialSiteMenu', { style: 'list', align: 'left', heading: 'Site map' })],
      [T.block('specialVisitInfo', { source: 'site', heading: 'Visit', layout: 'stacked' })],
      [T.block('specialNewsletter', { heading: 'Newsletter', text: '', privacy: '' })]
    ] },
    { cols: [6, 6], blocks: [[T.block('specialCopyright', { align: 'left' })], [T.block('specialSocialLinks', { align: 'right' })]] }
  ]
});
})(window.VPB);
