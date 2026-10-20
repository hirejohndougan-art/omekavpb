/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * oEmbed: a video or other embed from a web address. Omeka fetches the embed
 * code on save when the address changed (url != url_old) or "refresh" is on.
 */
(function (VPB) {
'use strict';

var h = VPB.dom.h, F = VPB.fields, B = VPB.blocks, obj = VPB.util.obj, isOn = VPB.util.isOn;

VPB.blocks.register('oembed', {
  render: function (b) {
    var oe = obj(B.val(b, 'oembed', null));
    if (!B.val(b, 'url', '')) return null;
    var box = h('div', { class: 'pv-embed' });
    if (oe.thumbnail_url && /^https?:\/\//.test(oe.thumbnail_url)) box.appendChild(h('img', { src: oe.thumbnail_url, alt: '', loading: 'lazy' }));
    box.appendChild(h('span', { class: 'pv-play', 'aria-hidden': 'true' }));
    box.appendChild(h('span', { class: 'pv-embed-title', text: oe.title || B.val(b, 'url', '') }));
    return box;
  },
  card: function (b) {
    var d = obj(b.data);
    return [h('span', { text: d.url ? d.url : 'No address set yet' })];
  },
  editor: function (box, b) {
    box.appendChild(F.textField('Web address to embed', 'f-ou', B.val(b, 'url', ''), function (v) { B.set(b, 'url', v.trim()); }, { type: 'url', placeholder: 'https://www.youtube.com/watch?v=\u2026' }));
    var oe = obj(B.val(b, 'oembed', null));
    if (oe.title || oe.provider_name) box.appendChild(F.note('Currently embedded: ' + [oe.title, oe.provider_name ? '(' + oe.provider_name + ')' : ''].filter(Boolean).join(' ')));
    box.appendChild(F.note('YouTube, Vimeo, Flickr, SoundCloud, and other oEmbed services work. Omeka fetches the embed when you save; if the address isn\u2019t supported, saving shows an error and nothing is lost.'));
    box.appendChild(F.checkField('Fetch the embed again on save', 'f-or', isOn(B.val(b, 'refresh', false)), function (on) { B.set(b, 'refresh', on ? '1' : '0'); }));
  },
  // Mirror Omeka's own form: after a save, the current address is the "old"
  // one, so the next save doesn't fetch again unless it changes.
  afterSave: function (d) {
    if (d && typeof d === 'object' && !Array.isArray(d)) { d.url_old = d.url; d.refresh = '0'; }
    return d;
  }
});
})(window.VPB);
