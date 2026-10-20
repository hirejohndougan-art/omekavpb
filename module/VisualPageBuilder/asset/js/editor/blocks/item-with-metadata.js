/* SPDX-License-Identifier: GPL-2.0-or-later */
/** Item with metadata: an item's files with its full description. Items only. */
(function (VPB) {
'use strict';

var F = VPB.fields;

VPB.blocks.register('itemWithMetadata', {
  card: VPB.attachments.card,
  render: function (b) {
    var files = VPB.attachments.render(b, { titles: 'item_title', caption: false });
    if (!files) return null;
    return VPB.dom.h('div', { class: 'pv-iwm' }, [files, F.note('The item\u2019s full description shows here on the site.')]);
  },
  editor: function (box, b) {
    box.appendChild(VPB.attachments.editor(b, true));
    box.appendChild(F.note('Shows the item\u2019s files with its full description underneath.'));
  }
});
})(window.VPB);
