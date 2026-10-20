/* SPDX-License-Identifier: GPL-2.0-or-later */
/** List of sites: other sites on this Omeka install. */
(function (VPB) {
'use strict';

var h = VPB.dom.h, F = VPB.fields, B = VPB.blocks, obj = VPB.util.obj, isOn = VPB.util.isOn;

// Checkboxes store "1"/"0", as Omeka's form does.
function flag(b, key, label, id, dflt) {
  return F.checkField(label, id, isOn(B.val(b, key, dflt)), function (on) { B.set(b, key, on ? '1' : '0'); });
}

VPB.blocks.register('listOfSites', {
  render: function (b) {
    var d = obj(b.data), sites = (VPB.cfg.sites || []).slice();
    var current = VPB.cfg.siteId;
    if (isOn(B.val(b, 'exclude_current', true))) sites = sites.filter(function (s) { return s.id !== current; });
    if (d.sort === 'alpha' || !d.sort) sites.sort(function (a, c) { return a.title.localeCompare(c.title); });
    if (d.sort === 'newest') sites.reverse();
    if (parseInt(d.limit, 10) > 0) sites = sites.slice(0, parseInt(d.limit, 10));
    if (!sites.length) return h('p', { class: 'note', text: 'No other sites to list.' });
    return h('ul', { class: 'pv-list' }, sites.map(function (s) { return h('li', {}, [h('span', { class: 'pv-link', text: s.title })]); }));
  },
  card: function (b) {
    var d = obj(b.data);
    return [h('span', { text: ({ alpha: 'Alphabetical', oldest: 'Oldest first', newest: 'Newest first' }[d.sort] || 'Alphabetical') + (d.limit ? ' \u00B7 up to ' + d.limit : '') })];
  },
  editor: function (box, b) {
    box.appendChild(F.selectField('Order', 'f-so', [['alpha', 'Alphabetical'], ['oldest', 'Oldest first'], ['newest', 'Newest first']], B.val(b, 'sort', 'alpha'), function (v) { B.set(b, 'sort', v); }));
    box.appendChild(F.numberField('Most sites to show', 'f-sl', B.val(b, 'limit', ''), function (v) { B.set(b, 'limit', v); }, { placeholder: 'No limit' }));
    box.appendChild(flag(b, 'pagination', 'Split into pages (needs a limit)', 'f-sp', false));
    box.appendChild(flag(b, 'summaries', 'Show summaries', 'f-ss', true));
    box.appendChild(flag(b, 'thumbnails', 'Show thumbnails', 'f-sth', true));
    box.appendChild(flag(b, 'exclude_current', 'Leave out this site', 'f-sx', true));
  }
});
})(window.VPB);
