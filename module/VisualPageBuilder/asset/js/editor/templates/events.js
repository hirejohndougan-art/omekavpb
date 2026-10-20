/* SPDX-License-Identifier: GPL-2.0-or-later */
/** Events and support templates. See catalog.js for the format. */
(function (VPB) {
'use strict';
var T = VPB.templates.T, add = function (t) { VPB.templates.add('events', t); };

add({
  id: 'events-calendar', name: 'Events calendar',
  description: 'All upcoming events in one list. Connect a calendar feed in the block\u2019s settings.',
  sections: [
    { cols: [12], blocks: [[T.title(), T.p('[What kinds of events you hold.]'), T.block('specialUpcomingEvents', { heading: '', count: 20 })]] }
  ]
});

add({
  id: 'events-featured', name: 'Featured event',
  description: 'Promote one big event with a photo, details, and a button, then list what\u2019s next.',
  sections: [
    { cols: [12], blocks: [[T.block('specialHeroBanner', { title: '[Event name]', subtitle: '[Date, time, and place]', button_text: 'Get tickets', button_url: '#', height: 'large' })]] },
    { cols: [8, 4], blocks: [
      [T.text('About the event', ['[What happens, who it\u2019s for, and what to bring.]'])],
      [T.block('specialUpcomingEvents', { heading: 'Also coming up', count: 3 })]
    ] }
  ]
});

add({
  id: 'events-newsletter', name: 'Events and newsletter',
  description: 'Upcoming events beside a sign-up so visitors hear about the next ones.',
  sections: [
    { cols: [12], blocks: [[T.title()]] },
    { cols: [8, 4], blocks: [
      [T.block('specialUpcomingEvents', { heading: '', count: 10 })],
      [T.block('specialNewsletter', { heading: 'Never miss an event' })]
    ] }
  ]
});

add({
  id: 'events-campaign', name: 'Fundraising campaign',
  description: 'A campaign page: the goal and progress, why it matters, and a way to give.',
  sections: [
    { cols: [12], blocks: [[T.block('specialHeroBanner', { title: '[Campaign name]', subtitle: '[What the money will do]', button_text: 'Donate', button_url: '#' })]] },
    { cols: [6, 6], blocks: [
      [T.block('specialFundraising', { title: '', goal: 10000, raised: 0, button_url: '#' })],
      [T.text('Why it matters', ['[The story behind the campaign.]'])]
    ] },
    { cols: [12], blocks: [[T.block('specialThenAndNow', { caption: '[Before and after, or then and now]' })]] }
  ]
});

add({
  id: 'events-support', name: 'Support and membership',
  description: 'Ways to support the society: membership, donations, and sponsors.',
  sections: [
    { cols: [12], blocks: [[T.title(), T.p('[Why support matters to the society.]')]] },
    { cols: [4, 4, 4], blocks: [
      [T.text('Become a member', ['[Membership benefits]']), T.block('specialButtons', { buttons: [{ text: 'Join', url: '#', style: 'primary' }] })],
      [T.text('Donate', ['[How gifts are used]']), T.block('specialButtons', { buttons: [{ text: 'Give', url: '#', style: 'primary' }] })],
      [T.text('Sponsor', ['[Sponsorship opportunities]']), T.block('specialButtons', { buttons: [{ text: 'Learn more', url: '#', style: 'secondary' }] })]
    ] },
    { cols: [12], blocks: [[T.block('specialPartners', { heading: 'Our sponsors', partners: [{ name: '[Sponsor]' }, { name: '[Sponsor]' }, { name: '[Sponsor]' }] })]] }
  ]
});
})(window.VPB);
