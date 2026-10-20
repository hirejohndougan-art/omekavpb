/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * The Layers tab: an outline of the page, top to bottom. Click a row to select
 * it (its settings open); the arrows move sections and blocks.
 */
(function (VPB) {
'use strict';

var D = VPB.dom, h = D.h, svg = D.svg, I = VPB.icons;
var S = VPB.store, state = S.state;

function row(opts) {
  var r = h('div', { class: 'layer' + (opts.selected ? ' selected' : '') + (opts.muted ? ' muted' : ''), role: 'treeitem', 'aria-level': String(opts.level), 'aria-selected': String(!!opts.selected) });
  r.style.paddingLeft = (opts.level - 1) * 16 + 'px';
  var b = h('button', { type: 'button', class: 'layer-main' });
  b.innerHTML = svg(opts.icon, 16);
  b.appendChild(h('span', { class: 'layer-label', text: opts.label }));
  if (opts.meta) b.appendChild(h('span', { class: 'layer-meta', text: opts.meta }));
  b.addEventListener('click', opts.select);
  r.appendChild(b);
  if (opts.move) {
    r.appendChild(D.iconBtn('Move ' + opts.label + ' up', I.up, function () { opts.move(-1); }));
    r.appendChild(D.iconBtn('Move ' + opts.label + ' down', I.down, function () { opts.move(1); }));
  }
  return r;
}

function blockMeta(b) {
  if (b.att && b.att.length) return b.att.length + (b.att.length === 1 ? ' item' : ' items');
  return '';
}

/** Draw the outline into `host`. */
function render(host) {
  host.appendChild(h('p', { class: 'note', text: 'Everything on the page, top to bottom. Click a row to open its settings; the arrows reorder.' }));
  var tree = h('div', { class: 'layers', role: 'tree', 'aria-label': 'Page layers' });
  var sel = state.sel || {};
  if (!state.sections.length) tree.appendChild(h('p', { class: 'note', text: 'The page is empty.' }));
  state.sections.forEach(function (s, si) {
    tree.appendChild(row({ level: 1, icon: I.section, label: 'Section ' + (si + 1), meta: s.cols.map(function (c) { return c.w; }).join(' / '),
      selected: sel.kind === 'sec' && sel.id === s.id, select: function () { VPB.app.select({ kind: 'sec', id: s.id }); },
      move: function (d) { VPB.actions.moveSec(s.id, d); } }));
    var multi = s.cols.length > 1;
    s.cols.forEach(function (c, ci) {
      if (multi) {
        tree.appendChild(row({ level: 2, icon: I.column, label: 'Column ' + (ci + 1), meta: c.w + ' of 12',
          selected: sel.kind === 'col' && sel.id === c.id, select: function () { VPB.app.select({ kind: 'col', id: c.id }); } }));
      }
      if (!c.blocks.length) {
        tree.appendChild(row({ level: multi ? 3 : 2, icon: I.none, label: 'Empty', muted: true, select: function () { VPB.app.select({ kind: 'col', id: c.id }); } }));
      }
      c.blocks.forEach(function (b) {
        tree.appendChild(row({ level: multi ? 3 : 2, icon: b.layout === 'html' ? I.text : I.block, label: VPB.blocks.name(b), meta: blockMeta(b),
          selected: sel.kind === 'block' && sel.id === b.id, select: function () { VPB.app.select({ kind: 'block', id: b.id }); },
          move: function (d) { VPB.actions.moveBlock(b.id, d); } }));
      });
    });
  });
  host.appendChild(tree);
}

VPB.layers = { render: render };
})(window.VPB);
