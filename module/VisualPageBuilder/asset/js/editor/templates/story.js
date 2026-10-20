/* SPDX-License-Identifier: GPL-2.0-or-later */
/** Story and exhibit templates. See catalog.js for the format. */
(function (VPB) {
'use strict';
var T = VPB.templates.T, add = function (t) { VPB.templates.add('story', t); };

add({
  id: 'story-photo-essay', name: 'Photo essay',
  description: 'A big opening photo, the story in short sections, a gallery, and a closing quote.',
  sections: [
    { cols: [12], blocks: [[T.block('specialHeroBanner', { title: '[Story title]', subtitle: '[A one-line summary]', height: 'large' })]] },
    { cols: [12], blocks: [[T.text('', ['[Open the story: set the scene, the place, and the time.]', '[Continue the story.]'])]] },
    { cols: [12], blocks: [[T.block('specialGallery', { mode: 'chosen', items: [], show_captions: true })]] },
    { cols: [12], blocks: [[T.block('specialQuote', { text: '[A memorable line from the people involved]', attribution: '[Who said it]', style: 'large' })]] },
    { cols: [12], blocks: [[T.text('', ['[Close the story: what changed, and what remains today.]'])]] }
  ]
});

add({
  id: 'story-then-now', name: 'Then and now',
  description: 'Compare places past and present with sliders, each with its own short story.',
  sections: [
    { cols: [12], blocks: [[T.title(), T.p('[Why these places matter and how they\u2019ve changed.]')]] },
    { cols: [8, 4], blocks: [[T.block('specialThenAndNow', { caption: '[Place one]' })], [T.text('[Place one]', ['[What stood here, and what stands now.]'])]] },
    { cols: [4, 8], blocks: [[T.text('[Place two]', ['[What stood here, and what stands now.]'])], [T.block('specialThenAndNow', { caption: '[Place two]' })]] },
    { cols: [12], blocks: [[T.p('[An invitation to share photos of other places.]')]] }
  ]
});

add({
  id: 'story-oral-history', name: 'Oral history feature',
  description: 'Introduce a speaker, play the interview with its transcript, and show related items.',
  sections: [
    { cols: [12], blocks: [[T.title(), T.p('[Who the speaker is and when the interview was recorded.]')]] },
    { cols: [12], blocks: [[T.block('specialOralHistory', {})]] },
    { cols: [6, 6], blocks: [
      [T.block('specialQuote', { text: '[A striking line from the interview]', attribution: '[Speaker]', style: 'boxed' })],
      [T.block('specialRandomItems', { heading: 'Related items', count: 4, columns: '2' })]
    ] }
  ]
});

add({
  id: 'story-two-column', name: 'Story with sidebar',
  description: 'The story in a wide column with photos, key facts, and a document alongside it.',
  sections: [
    { cols: [12], blocks: [[T.title()]] },
    { cols: [8, 4], blocks: [
      [T.text('', ['[Begin the story.]', '[Continue the story.]']), T.block('media', { layout: 'horizontal' }), T.text('', ['[Finish the story.]'])],
      [T.text('At a glance', ['[Date: …]', '[Place: …]', '[People: …]']), T.block('specialItemSpotlight', { mode: 'fixed', heading: 'Key object', image_position: 'top', show_description: false })]
    ] },
    { cols: [12], blocks: [[T.block('specialDocumentTranscription', {})]] }
  ]
});

add({
  id: 'story-chapters', name: 'Exhibit with chapters',
  description: 'A landing page for a multi-part exhibit: introduction, chapter links, and highlights.',
  sections: [
    { cols: [12], blocks: [[T.block('specialHeroBanner', { title: '[Exhibit title]', subtitle: '[What the exhibit is about]' })]] },
    { cols: [8, 4], blocks: [
      [T.text('About this exhibit', ['[Introduce the exhibit and its chapters.]'])],
      [T.block('tableOfContents', { depth: '1' })]
    ] },
    { cols: [12], blocks: [[T.block('specialTopicTiles', { heading: 'Chapters', columns: '3', tiles: [{ title: '[Chapter one]', url: '#' }, { title: '[Chapter two]', url: '#' }, { title: '[Chapter three]', url: '#' }] })]] },
    { cols: [12], blocks: [[T.block('specialCitation', { organization: '[Your organization]' })]] }
  ]
});
})(window.VPB);
