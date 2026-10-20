/* SPDX-License-Identifier: GPL-2.0-or-later */
/** Table of contents: this page's sub-pages. */
(function (VPB) {
'use strict';

var h = VPB.dom.h, F = VPB.fields, B = VPB.blocks, obj = VPB.util.obj;
var MAX_DEPTH = 6;

VPB.blocks.register('tableOfContents', {
  // The pages under this one in the site's navigation (cfg.subPages), to the
  // chosen depth, as the site will list them.
  render: function (b) {
    var depth = Math.max(1, Math.min(6, parseInt(B.val(b, 'depth', '1'), 10) || 1));
    var pages = Array.isArray(VPB.cfg.subPages) ? VPB.cfg.subPages : [];
    if (!pages.length) return F.note('No pages are under this one in the site\u2019s navigation yet. Add some under Navigation and they\u2019ll be listed here.');
    function list(nodes, level) {
      return h('ul', { class: 'pv-list pv-toc' }, nodes.map(function (n) {
        var li = h('li', {}, [h('span', { class: 'pv-link', text: n.title })]);
        if (level < depth && n.children && n.children.length) li.appendChild(list(n.children, level + 1));
        return li;
      }));
    }
    return list(pages, 1);
  },
  card: function (b) {
    var d = obj(b.data);
    return [h('span', { text: (parseInt(d.depth, 10) > 1 ? d.depth + ' levels' : '1 level') + ' of sub-pages' })];
  },
  editor: function (box, b) {
    var dep = parseInt(B.val(b, 'depth', '1'), 10); if (!(dep >= 1)) dep = 1;
    var opts = [];
    for (var n = 1; n <= MAX_DEPTH; n++) opts.push([String(n), String(n)]);
    box.appendChild(F.selectField('Levels of sub-pages to list', 'f-dep', opts, String(Math.min(dep, MAX_DEPTH)), function (v) { B.set(b, 'depth', v); }));
  }
});
})(window.VPB);
