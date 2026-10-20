/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * Site menu block (headers and footers): its look comes from the Menu page
 * (style and colors, with desktop, tablet, and phone previews), so its
 * settings start with a link there. Loaded after blocks/schema.
 */
(function (VPB) {
'use strict';

var h = VPB.dom.h;

VPB.blocks.extend('specialSiteMenu', function (box) {
  var wrap = h('div', { class: 'field site-menu-link' });
  wrap.appendChild(h('p', { class: 'note', text: 'The menu’s links, style, and colors are set on the Menu page, with a preview at desktop, tablet, and phone widths. Every header follows it.' }));
  wrap.appendChild(h('a', { class: 'btn small', href: VPB.cfg.menuUrl, text: 'Edit the menu, its style, and colors' }));
  box.appendChild(wrap);
});
})(window.VPB);
