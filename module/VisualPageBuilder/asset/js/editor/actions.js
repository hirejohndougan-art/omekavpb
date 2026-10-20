/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * Edits to the page: moving, duplicating, and deleting sections and blocks,
 * adding blocks from the palette, and changing column widths. Each action
 * records an undo step and re-renders.
 */
(function (VPB) {
'use strict';

var U = VPB.util, nid = U.nid, clone = U.clone, clamp = U.clamp;
var toast = VPB.dom.toast, scrollToId = VPB.dom.scrollToId;
var M = VPB.model, S = VPB.store, state = S.state, CFG = VPB.cfg;
var MIN_W = VPB.MIN_W, MAX_COLS = VPB.MAX_COLS;
var findSec = S.findSec, findCol = S.findCol, findBlock = S.findBlock, selSection = S.selSection;
function commit() { S.commit(); }
function renderAll() { VPB.app.renderAll(); }
function mkCol(w, blocks, bg) { return M.mkCol(w, blocks, bg); }

function moveBlock(id, dir) {
  var r = findBlock(id); if (!r) return;
  var j = r.bi + dir;
  if (j < 0 || j >= r.col.blocks.length) { toast(dir < 0 ? 'Already at the top of its column. Drag it to move it to another column.' : 'Already at the bottom of its column. Drag it to move it to another column.'); return; }
  r.col.blocks.splice(r.bi, 1); r.col.blocks.splice(j, 0, r.block);
  commit(); renderAll();
}
function dupBlock(id) {
  var r = findBlock(id); var c = clone(r.block); c.id = nid('b');
  r.col.blocks.splice(r.bi + 1, 0, c);
  state.sel = { kind: 'block', id: c.id }; commit(); renderAll();
}
function isRequired(b) { return !!(CFG.required && CFG.required.indexOf(b.layout) >= 0); }
/** Hide or show a required block on the public page (it stays in the template). */
function toggleHidden(id) {
  var r = findBlock(id); if (!r) return;
  if (!isRequired(r.block)) return;
  if (r.block.ld && r.block.ld.vpb_hidden) delete r.block.ld.vpb_hidden; else r.block.ld = Object.assign({}, r.block.ld, { vpb_hidden: '1' });
  commit(); renderAll(); toast(r.block.ld.vpb_hidden ? 'Hidden on the page. It stays in the template.' : 'Shown on the page.');
}
/** Hide or show any block on one device (desktop, tablet or phone) on the public page. */
function toggleDevice(id, dev) {
  var r = findBlock(id); if (!r) return;
  var ld = Object.assign({}, r.block.ld);
  var list = (ld.vpb_hide || '').split(',').filter(Boolean);
  var at = list.indexOf(dev);
  if (at >= 0) list.splice(at, 1); else list.push(dev);
  if (list.length) ld.vpb_hide = list.join(','); else delete ld.vpb_hide;
  r.block.ld = ld;
  commit(); renderAll();
  var off = list.indexOf(dev) < 0;
  toast(off ? 'Shown on ' + dev + '.' : 'Hidden on ' + dev + '. It stays in the template.');
}
function delBlock(id) {
  var r = findBlock(id); if (!r) return;
  if (isRequired(r.block)) { toast('This block is required by the template, so it stays.'); return; }
  r.col.blocks.splice(r.bi, 1);
  state.sel = { kind: 'col', id: r.col.id };
  commit(); renderAll(); toast('Block deleted. Undo brings it back.');
}
function moveSec(id, dir) {
  var r = findSec(id), j = r.si + dir;
  if (j < 0 || j >= state.sections.length) return;
  state.sections.splice(r.si, 1); state.sections.splice(j, 0, r.sec);
  commit(); renderAll();
}
function dupSec(id) {
  var r = findSec(id), c = clone(r.sec);
  c.id = nid('s');
  c.cols.forEach(function (col) { col.id = nid('c'); col.blocks.forEach(function (b) { b.id = nid('b'); }); });
  state.sections.splice(r.si + 1, 0, c);
  state.sel = { kind: 'sec', id: c.id }; commit(); renderAll();
}
function delSec(id) {
  var r = findSec(id); if (!r) return;
  var n = 0; r.sec.cols.forEach(function (c) { n += c.blocks.length; });
  state.sections.splice(r.si, 1); state.sel = null;
  commit(); renderAll();
  toast(n ? 'Section and its ' + n + (n === 1 ? ' block' : ' blocks') + ' deleted. Undo brings them back.' : 'Section deleted. Undo brings it back.');
}
/** Move the border between columns ci and ci+1 by d twelfths. Returns false if it can't move. */
function shiftBorder(s, ci, d) {
  var a = s.cols[ci], b = s.cols[ci + 1]; if (!a || !b) return false;
  var total = a.w + b.w, lo = Math.min(MIN_W, a.w), hi = total - Math.min(MIN_W, b.w);
  var na = clamp(a.w + d, lo, hi);
  if (na === a.w) return false;
  a.w = na; b.w = total - na; return true;
}
function adjustColWidth(colId, d) {
  var r = findCol(colId); if (!r) return;
  if (r.sec.cols.length === 1) { toast('A single column always fills the section.'); return; }
  var ok = r.ci < r.sec.cols.length - 1 ? shiftBorder(r.sec, r.ci, d) : shiftBorder(r.sec, r.ci - 1, -d);
  if (!ok) toast('Columns can\u2019t be narrower than 2 of 12.');
  commit(); renderAll();
}
/** Apply a column layout (e.g. [8, 4]); blocks from removed columns join the last kept one. */
function setLayout(s, ws) {
  if (ws.length < s.cols.length) {
    var keep = s.cols.slice(0, ws.length), extra = s.cols.slice(ws.length), last = keep[keep.length - 1];
    var moved = 0;
    extra.forEach(function (c) { moved += c.blocks.length; last.blocks = last.blocks.concat(c.blocks); });
    s.cols = keep;
    if (moved) toast('Blocks from removed columns moved into column ' + ws.length + '.');
  } else {
    while (s.cols.length < ws.length) s.cols.push(mkCol(0));
  }
  s.cols.forEach(function (c, i) { c.w = ws[i]; });
}
function addColumn(s) {
  if (s.cols.length >= MAX_COLS) { toast('A section can have up to ' + MAX_COLS + ' columns.'); return; }
  var wi = 0; s.cols.forEach(function (c, i) { if (c.w > s.cols[wi].w) wi = i; });
  var c = s.cols[wi];
  if (c.w < MIN_W * 2) { toast('No column is wide enough to split. Narrow some columns first.'); return; }
  var a = Math.ceil(c.w / 2), nc = mkCol(c.w - a); c.w = a;
  s.cols.splice(wi + 1, 0, nc);
  state.sel = { kind: 'col', id: nc.id }; commit(); renderAll();
}
function removeColumn(colId) {
  var r = findCol(colId); if (!r) return;
  if (r.sec.cols.length === 1) { toast('A section needs at least one column. Delete the section instead.'); return; }
  var nb = r.ci > 0 ? r.sec.cols[r.ci - 1] : r.sec.cols[r.ci + 1];
  nb.w += r.col.w;
  nb.blocks = r.ci > 0 ? nb.blocks.concat(r.col.blocks) : r.col.blocks.concat(nb.blocks);
  r.sec.cols.splice(r.ci, 1);
  state.sel = { kind: 'sec', id: r.sec.id };
  commit(); renderAll();
  toast(r.col.blocks.length ? 'Column removed. Its blocks moved to the column beside it.' : 'Column removed.');
}
/** Add a section after the selected one (or at the end), from a palette tap. */
function tapAddSection(p) {
  var s = M.newSection(p.w.slice()), at = state.sections.length, cur = selSection();
  if (cur) at = cur.si + 1;
  if (typeof state.insertAt === 'number') { at = Math.min(state.insertAt, state.sections.length); state.insertAt = null; }
  state.sections.splice(at, 0, s);
  state.sel = { kind: 'sec', id: s.id };
  commit(); renderAll(); scrollToId(s.id);
}
/** Add a block after the selection (or in a new section at the end), from a palette tap. */
function tapAddBlock(b) {
  var sel = state.sel, r;
  // A spot chosen with a "+" bar: the block starts a new section there.
  if (typeof state.insertAt === 'number') {
    state.sections.splice(Math.min(state.insertAt, state.sections.length), 0, M.sectionWith(b));
    state.insertAt = null;
    state.sel = { kind: 'block', id: b.id };
    commit(); renderAll(); scrollToId(b.id);
    return;
  }
  if (sel && sel.kind === 'block' && (r = findBlock(sel.id))) r.col.blocks.splice(r.bi + 1, 0, b);
  else if (sel && sel.kind === 'col' && (r = findCol(sel.id))) r.col.blocks.push(b);
  else if (sel && sel.kind === 'sec' && (r = findSec(sel.id))) r.sec.cols[0].blocks.push(b);
  else state.sections.push(M.sectionWith(b));
  state.sel = { kind: 'block', id: b.id };
  commit(); renderAll(); scrollToId(b.id);
}

VPB.actions = {
  moveBlock: moveBlock, dupBlock: dupBlock, delBlock: delBlock,
  moveSec: moveSec, dupSec: dupSec, delSec: delSec,
  shiftBorder: shiftBorder, adjustColWidth: adjustColWidth, setLayout: setLayout, addColumn: addColumn, removeColumn: removeColumn,
  addSection: tapAddSection, addBlock: tapAddBlock, isRequired: isRequired, toggleHidden: toggleHidden,
  toggleDevice: toggleDevice
};
})(window.VPB);
