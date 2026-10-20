/* SPDX-License-Identifier: GPL-2.0-or-later */
/** Page title: shows the page's title; nothing to set. */
(function (VPB) {
'use strict';

var h = VPB.dom.h;

VPB.blocks.register('pageTitle', {
  preview: function () { return h('h1', { class: 'b-title', text: VPB.cfg.pageTitle }); },
  note: function () { return h('p', { class: 'note', text: 'This block has no content to edit here.' }); }
});
})(window.VPB);
