/* SPDX-License-Identifier: GPL-2.0-or-later */
/** Page date/time: when the page was created and/or last modified. */
(function (VPB) {
'use strict';

var h = VPB.dom.h, F = VPB.fields, B = VPB.blocks, obj = VPB.util.obj;
var FORMATS = [['none', 'None'], ['short', 'Short'], ['medium', 'Medium'], ['long', 'Long'], ['full', 'Full']];

/** A date from the page JSON ({'@value': '…'}), in the editor's language. */
function fmt(v, dateFmt, timeFmt) {
  var raw = v && v['@value'] ? v['@value'] : v;
  var dt = raw ? new Date(raw) : new Date();
  var opts = {};
  if (dateFmt !== 'none') opts.dateStyle = dateFmt;
  if (timeFmt !== 'none') opts.timeStyle = timeFmt;
  if (!opts.dateStyle && !opts.timeStyle) return '';
  try { return dt.toLocaleString(undefined, opts); } catch (e) { return dt.toDateString(); }
}

VPB.blocks.register('pageDateTime', {
  render: function (b) {
    var page = VPB.cfg.page, show = B.val(b, 'display', 'created_modified');
    var df = B.val(b, 'date_format', 'medium'), tf = B.val(b, 'time_format', 'none');
    var parts = [];
    if (show !== 'modified') parts.push('Created: ' + fmt(page['o:created'], df, tf));
    if (show !== 'created') parts.push('Last modified: ' + fmt(page['o:modified'], df, tf));
    return h('p', { class: 'pv-dates' }, [parts.join(' \u00B7 ')]);
  },
  card: function (b) {
    var d = obj(b.data);
    return [h('span', { text: ({ created_modified: 'Created and modified dates', created: 'Created date', modified: 'Modified date' }[d.display] || 'Created and modified dates') })];
  },
  editor: function (box, b) {
    box.appendChild(F.selectField('Which dates', 'f-dd', [['created_modified', 'Created and modified'], ['created', 'Created'], ['modified', 'Modified']], B.val(b, 'display', 'created_modified'), function (v) { B.set(b, 'display', v); }));
    box.appendChild(F.selectField('Date format', 'f-df', FORMATS, B.val(b, 'date_format', 'medium'), function (v) { B.set(b, 'date_format', v); }));
    box.appendChild(F.selectField('Time format', 'f-tf', FORMATS, B.val(b, 'time_format', 'none'), function (v) { B.set(b, 'time_format', v); }));
  }
});
})(window.VPB);
