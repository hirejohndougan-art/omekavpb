/* SPDX-License-Identifier: GPL-2.0-or-later */
/** Media embed: items' files with captions, plus display options. */
(function (VPB) {
'use strict';

var h = VPB.dom.h, F = VPB.fields, B = VPB.blocks;

VPB.blocks.register('media', {
  card: VPB.attachments.card,
  render: function (b) {
    return VPB.attachments.render(b, { horizontal: B.val(b, 'layout', '') === 'horizontal', titles: B.val(b, 'show_title_option', 'item_title') });
  },
  editor: function (box, b) {
    box.appendChild(VPB.attachments.editor(b, false));
    box.appendChild(h('h4', { text: 'Display options' }));
    box.appendChild(F.segField('Arrangement', [['', 'Vertical'], ['horizontal', 'Horizontal']], B.val(b, 'layout', ''), function (v) { B.set(b, 'layout', v); }));
    box.appendChild(F.segField('Show files as', [['', 'Embedded'], ['thumbnail', 'Thumbnails']], B.val(b, 'media_display', ''), function (v) { B.set(b, 'media_display', v); }));
    box.appendChild(F.selectField('Thumbnail size', 'f-tt', VPB.thumbnailTypes.map(function (t) { return [t, t.charAt(0).toUpperCase() + t.slice(1)]; }), B.val(b, 'thumbnail_type', 'square'), function (v) { B.set(b, 'thumbnail_type', v); }));
    box.appendChild(F.selectField('Title under each file', 'f-st', [['item_title', 'Item title'], ['file_name', 'File title'], ['no_title', 'No title']], B.val(b, 'show_title_option', 'item_title'), function (v) { B.set(b, 'show_title_option', v); }));
  }
});
})(window.VPB);
