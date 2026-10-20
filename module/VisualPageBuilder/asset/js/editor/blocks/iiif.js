/* SPDX-License-Identifier: GPL-2.0-or-later */
/** IIIF image and IIIF presentation (manifest) blocks. */
(function (VPB) {
'use strict';

var h = VPB.dom.h, F = VPB.fields, B = VPB.blocks, obj = VPB.util.obj, isOn = VPB.util.isOn;

function define(layout, key, label, empty) {
  VPB.blocks.register(layout, {
    render: function (b) {
      var d = obj(b.data);
      if (!d[key]) return null;
      return h('div', { class: 'pv-embed pv-iiif' }, [
        isOn(d.show_title) && d.title ? h('h3', { class: 'pv-h3', text: d.title }) : null,
        h('span', { class: 'pv-embed-title', text: (layout === 'iiifImage' ? 'Zoomable image: ' : 'Document viewer: ') + d[key] })
      ]);
    },
    card: function (b) {
      var d = obj(b.data);
      return [h('span', { text: d[key] ? d[key] : empty })];
    },
    editor: function (box, b) {
      box.appendChild(F.textField(label, 'f-iu', B.val(b, key, ''), function (v) { B.set(b, key, v.trim()); }, { type: 'url', placeholder: 'https://\u2026' }));
      box.appendChild(F.textField('Title', 'f-it', B.val(b, 'title', ''), function (v) { B.set(b, 'title', v); }, { placeholder: 'Read aloud to screen reader users' }));
      box.appendChild(F.checkField('Show the title as a heading', 'f-is', isOn(B.val(b, 'show_title', false)), function (on) { B.set(b, 'show_title', on ? '1' : '0'); }));
    }
  });
}

define('iiifImage', 'image_url', 'IIIF image address', 'No image address set yet');
define('iiifPresentation', 'manifest_url', 'IIIF manifest address', 'No manifest address set yet');
})(window.VPB);
