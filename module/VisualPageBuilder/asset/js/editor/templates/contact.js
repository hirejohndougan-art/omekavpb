/* SPDX-License-Identifier: GPL-2.0-or-later */
/** Contact and visit templates. See catalog.js for the format. */
(function (VPB) {
'use strict';
var T = VPB.templates.T, add = function (t) { VPB.templates.add('contact', t); };

add({
  id: 'contact-visit', name: 'Plan your visit',
  description: 'Hours, address, and admission from your site settings, with buttons for directions and email.',
  sections: [
    { cols: [12], blocks: [[T.title(), T.p('[A warm invitation to visit.]')]] },
    { cols: [12], blocks: [[T.block('specialVisitInfo', { source: 'site', heading: '' })]] },
    { cols: [12], blocks: [[T.block('specialButtons', { buttons: [{ text: 'Get directions', url: '#', style: 'primary' }, { text: 'Email us', url: '#', style: 'secondary' }] })]] }
  ]
});

add({
  id: 'contact-newsletter', name: 'Contact and newsletter',
  description: 'How to reach you beside a newsletter sign-up.',
  sections: [
    { cols: [12], blocks: [[T.title()]] },
    { cols: [6, 6], blocks: [
      [T.block('specialVisitInfo', { source: 'site', heading: 'Get in touch', layout: 'stacked' })],
      [T.block('specialNewsletter', { heading: 'Our newsletter' })]
    ] }
  ]
});

add({
  id: 'contact-research', name: 'Research requests',
  description: 'For researchers: how to ask for help, request copies, and cite the collection.',
  sections: [
    { cols: [12], blocks: [[T.title(), T.p('[What research help you offer and how long requests take.]')]] },
    { cols: [12], blocks: [[T.block('specialAccordion', { panels: [
      { title: 'How do I request research help?', body: '<p>[How to send a request]</p>' },
      { title: 'Is there a fee?', body: '<p>[Fees, if any]</p>' },
      { title: 'Can I visit to do research?', body: '<p>[Appointments]</p>' }
    ] })]] },
    { cols: [12], blocks: [[T.block('specialCitation', { organization: '[Your organization]', contact_text: 'Send a research request', contact_url: '#' })]] }
  ]
});

add({
  id: 'contact-volunteer', name: 'Volunteer with us',
  description: 'Ways to help, a word from a volunteer, and a sign-up button.',
  sections: [
    { cols: [12], blocks: [[T.block('specialHeroBanner', { title: 'Volunteer with us', subtitle: '[A line about why volunteers matter]', height: 'small' })]] },
    { cols: [8, 4], blocks: [
      [T.block('specialAccordion', { heading: 'Ways to help', panels: [{ title: '[Archives and cataloging]', body: '<p>[What this involves]</p>' }, { title: '[Events]', body: '<p>[What this involves]</p>' }, { title: '[Tours and greeting visitors]', body: '<p>[What this involves]</p>' }] })],
      [T.block('specialQuote', { text: '[Why a volunteer enjoys helping]', attribution: '[Volunteer name]', style: 'boxed' }), T.block('specialButtons', { buttons: [{ text: 'Sign up to volunteer', url: '#', style: 'primary' }] })]
    ] }
  ]
});

add({
  id: 'contact-simple', name: 'Simple contact',
  description: 'Just the essentials: your details and a short note.',
  sections: [
    { cols: [12], blocks: [[T.title(), T.block('specialVisitInfo', { source: 'site', heading: '' }), T.p('[Anything else visitors should know.]')]] }
  ]
});
})(window.VPB);
