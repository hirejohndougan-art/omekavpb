/* SPDX-License-Identifier: GPL-2.0-or-later */
/** Line break: blank space or a visible line, shown as such on the canvas. */
(function (VPB) {
'use strict';

var h = VPB.dom.h, F = VPB.fields, B = VPB.blocks;

VPB.blocks.register('lineBreak', {
  preview: function (b) {
    return B.val(b, 'break_type', 'transparent') === 'opaque' ? h('hr', { class: 'b-hr' }) : h('div', { class: 'b-space', 'aria-label': 'Blank space' });
  },
  editor: function (box, b) {
    box.appendChild(F.segField('Style', [['transparent', 'Blank space'], ['opaque', 'Visible line']], B.val(b, 'break_type', 'transparent'), function (v) { B.set(b, 'break_type', v); }));
  }
});
})(window.VPB);
