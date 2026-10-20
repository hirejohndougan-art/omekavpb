/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * Page templates: ready-made pages (home, browse, story…) to start from.
 *
 * A template is plain data: sections, each with column widths and the blocks
 * in each column. The files next to this one add five templates per page type
 * with VPB.templates.add(); templates-panel.js shows them in the sidebar's
 * Templates tab and applies them.
 *
 *   VPB.templates.add('home', {
 *     id: 'home-welcome', name: 'Classic welcome',
 *     description: 'One line on what the page is like.',
 *     sections: [
 *       { cols: [8, 4], bg: '#F1EEE4', blocks: [[T.h2('Welcome'), T.p('…')], [T.block('specialVisitInfo', {…})]] }
 *     ]
 *   });
 *
 * Text is written as placeholders in [square brackets] for the society to
 * replace. Item blocks (random items, collection cards…) fill themselves from
 * the collection, so a new page shows real material straight away. A block
 * whose type isn't available is left out when the template is applied.
 */
(function (VPB) {
'use strict';

var TYPES = [
  { id: 'home', label: 'Home pages' },
  { id: 'browse', label: 'Browse and explore' },
  { id: 'story', label: 'Stories and exhibits' },
  { id: 'about', label: 'About' },
  { id: 'contact', label: 'Contact and visit' },
  { id: 'events', label: 'Events and support' },
  // Single sections to add anywhere on a page (templates/sections.js).
  { id: 'sections', label: 'Ready-made sections', single: true },
  // Shown only when editing the site's header or footer (see SiteRegions.php).
  { id: 'header', label: 'Headers', region: 'header' },
  { id: 'footer', label: 'Footers', region: 'footer' }
];
var byType = {};
TYPES.forEach(function (t) { byType[t.id] = []; });

function add(type, template) {
  if (!byType[type]) throw new Error('Unknown template type: ' + type);
  template.type = type;
  byType[type].push(template);
}

/** Helpers that keep template files short. Each returns [layout, data]. */
var T = {
  block: function (layout, data) { return [layout, data || {}]; },
  title: function () { return ['pageTitle', {}]; },
  h2: function (text) { return ['html', { html: '<h2>' + text + '</h2>' }]; },
  p: function (text) { return ['html', { html: '<p>' + text + '</p>' }]; },
  text: function (heading, paragraphs) {
    return ['html', { html: (heading ? '<h2>' + heading + '</h2>' : '') + paragraphs.map(function (x) { return '<p>' + x + '</p>'; }).join('') }];
  },
  space: function () { return ['lineBreak', { break_type: 'transparent' }]; },
  line: function () { return ['lineBreak', { break_type: 'opaque' }]; }
};

VPB.templates = {
  TYPES: TYPES,
  add: add,
  T: T,
  ofType: function (type) { return byType[type] || []; },
  all: function () { var out = []; TYPES.forEach(function (t) { out = out.concat(byType[t.id]); }); return out; }
};
})(window.VPB);
