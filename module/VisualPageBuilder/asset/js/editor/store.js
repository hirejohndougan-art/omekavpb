/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * Editor state, selection lookups, and undo/redo history.
 *
 * History works on JSON snapshots of `state.sections`. `commit()` records a
 * step if anything changed since the last one; the "saved" snapshot is what the
 * server has, and drives the Unsaved changes indicator.
 */
(function (VPB) {
'use strict';

var state = {
  sections: [],   // See model.js for the shapes.
  sel: null,      // { kind: 'sec' | 'col' | 'block', id } or null
  dev: 'desktop', // Preview width: desktop | tablet | phone
  preview: false, // Preview mode hides all editing marks.
  panel: 'add'    // Sidebar view: 'add' (palette) or 'settings'
};

var past = [], future = [], committed = '', savedToServer = '';
var MAX_HISTORY = 150;

function snap() { return JSON.stringify(state.sections); }
/** Record an undo step if anything changed, then refresh the status line. */
function commit() {
  var now = snap();
  if (now !== committed) { past.push(committed); if (past.length > MAX_HISTORY) past.shift(); future = []; committed = now; }
  VPB.app.refreshStatus();
}
function restore(s) { state.sections = JSON.parse(s); fixSel(); VPB.app.renderAll(); VPB.app.refreshStatus(); }
function undo() { commit(); if (!past.length) return; future.push(committed); committed = past.pop(); restore(committed); }
function redo() { if (!future.length) return; past.push(committed); committed = future.pop(); restore(committed); }
function isDirty() { return snap() !== savedToServer; }
function canUndo() { return !!past.length || snap() !== committed; }
function canRedo() { return !!future.length; }
/** The server now has this snapshot. */
function markSaved(s) { savedToServer = s; }
/** Treat the current state as both the latest undo step and what the server has. */
function rebase() { committed = snap(); savedToServer = committed; }

/* ---------- lookups ---------- */
function findSec(id) { for (var i = 0; i < state.sections.length; i++) if (state.sections[i].id === id) return { si: i, sec: state.sections[i] }; return null; }
function findCol(id) { for (var i = 0; i < state.sections.length; i++) { var s = state.sections[i]; for (var j = 0; j < s.cols.length; j++) if (s.cols[j].id === id) return { si: i, sec: s, ci: j, col: s.cols[j] }; } return null; }
function findBlock(id) { for (var i = 0; i < state.sections.length; i++) { var s = state.sections[i]; for (var j = 0; j < s.cols.length; j++) { var c = s.cols[j]; for (var k = 0; k < c.blocks.length; k++) if (c.blocks[k].id === id) return { si: i, sec: s, ci: j, col: c, bi: k, block: c.blocks[k] }; } } return null; }
/** Drop the selection if what it points at no longer exists (after undo, delete). */
function fixSel() {
  if (!state.sel) return;
  var ok = state.sel.kind === 'sec' ? findSec(state.sel.id) : state.sel.kind === 'col' ? findCol(state.sel.id) : findBlock(state.sel.id);
  if (!ok) state.sel = null;
}
/** The section containing the current selection, or null. */
function selSection() {
  if (!state.sel) return null;
  if (state.sel.kind === 'sec') return findSec(state.sel.id);
  var r = state.sel.kind === 'col' ? findCol(state.sel.id) : findBlock(state.sel.id);
  return r ? { si: r.si, sec: r.sec } : null;
}

VPB.store = {
  state: state,
  snap: snap, commit: commit, undo: undo, redo: redo, isDirty: isDirty, canUndo: canUndo, canRedo: canRedo, markSaved: markSaved, rebase: rebase,
  findSec: findSec, findCol: findCol, findBlock: findBlock, fixSel: fixSel, selSection: selSection
};
})(window.VPB);
