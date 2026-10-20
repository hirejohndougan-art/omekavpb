/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * Editor startup and the app shell: re-rendering, selection, the status line,
 * sidebar collapse and mobile sheets, and keyboard and toolbar wiring.
 * Loaded last; everything else is defined by the time this runs.
 */
(function (VPB) {
'use strict';

var S = VPB.store, state = S.state, A = VPB.actions, CFG = VPB.cfg;

/** Re-render everything from state. */
function renderAll() {
  document.querySelectorAll('.seg button[data-dev]').forEach(function (btn) { btn.setAttribute('aria-pressed', String(btn.getAttribute('data-dev') === state.dev)); });
  document.body.classList.toggle('preview', state.preview);
  var pb = document.getElementById('previewBtn');
  pb.setAttribute('aria-pressed', String(state.preview));
  pb.textContent = state.preview ? 'Back to editing' : 'Preview';
  VPB.canvas.render(); VPB.sidebar.renderSide(); refreshStatus();
  if (VPB.regionsView) VPB.regionsView.render();
}

/** Select a section, column, or block ({ kind, id }), or nothing (null). */
function select(sel) {
  VPB.picker.closeUnless(sel ? sel.id : null);
  state.sel = sel;
  state.panel = sel ? 'settings' : 'add';
  document.querySelectorAll('[data-kind]').forEach(function (el) {
    el.classList.toggle('selected', !!sel && el.getAttribute('data-kind') === sel.kind && el.getAttribute('data-id') === sel.id && !state.preview);
  });
  VPB.sidebar.renderSide();
}

/** Update the status line and the undo/redo buttons. */
function refreshStatus(text, cls) {
  var dirty = S.isDirty();
  var s = document.getElementById('status');
  s.className = 'status' + (cls ? ' ' + cls : (dirty ? ' dirty' : ''));
  s.textContent = text || (dirty ? 'Unsaved changes' : 'All changes saved');
  document.getElementById('undo').disabled = !S.canUndo();
  document.getElementById('redo').disabled = !S.canRedo();
}

/* ---------- sidebar collapse and mobile sheets ---------- */
function isMobile() { return window.matchMedia('(max-width: 900px)').matches; }
function setCollapsed(c) {
  document.body.classList.toggle('side-collapsed', c);
  var t = document.getElementById('sideToggle');
  t.setAttribute('aria-expanded', String(!c));
  t.setAttribute('aria-label', c ? 'Show sidebar' : 'Hide sidebar');
  t.setAttribute('title', (c ? 'Show sidebar' : 'Hide sidebar') + ' (Ctrl+\\)');
}
function openSettings() {
  if (isMobile()) { openSheet('settings'); return; }
  setCollapsed(false); state.panel = 'settings'; VPB.sidebar.renderSide();
}
// On phones the sidebar is a bottom sheet that opens on the Add or Settings view.
var sheetOpen = false;
function openSheet(which) {
  sheetOpen = !!which;
  if (which) state.panel = which;
  document.getElementById('side').classList.toggle('open', sheetOpen);
  VPB.sidebar.renderSide();
}
function closeSheets() { openSheet(null); }

/**
 * "+" between sections: remember the spot (state.insertAt, a section index)
 * and open the Blocks panel; the next section or block picked there is added
 * at that spot (actions.tapAddSection / tapAddBlock).
 */
function addAt(index) {
  state.insertAt = index;
  state.sel = null;
  if (isMobile()) openSheet('add');
  else { setCollapsed(false); state.panel = 'add'; VPB.sidebar.renderSide(); }
  VPB.canvas.render();
  VPB.dom.toast('Now pick a section or block in the sidebar; it goes where the + is. Press Escape to cancel.');
}

VPB.app = {
  renderAll: renderAll, select: select, refreshStatus: refreshStatus, addAt: addAt,
  isMobile: isMobile, openSettings: openSettings, closeSheets: closeSheets,
  isSheetOpen: function () { return sheetOpen; }
};

/* ---------- wiring ---------- */
var save = VPB.save.save;
document.querySelectorAll('.seg button').forEach(function (btn) {
  btn.addEventListener('click', function () { state.dev = btn.getAttribute('data-dev'); renderAll(); });
});
document.getElementById('sideToggle').addEventListener('click', function () { setCollapsed(!document.body.classList.contains('side-collapsed')); });
document.getElementById('undo').addEventListener('click', S.undo);
document.getElementById('redo').addEventListener('click', S.redo);
document.getElementById('saveBtn').addEventListener('click', save);
document.getElementById('previewBtn').addEventListener('click', function () { S.commit(); state.preview = !state.preview; closeSheets(); renderAll(); });
document.getElementById('openBlocks').addEventListener('click', function () { openSheet(sheetOpen && state.panel === 'add' ? null : 'add'); });
document.getElementById('openProps').addEventListener('click', function () { openSheet(sheetOpen && state.panel === 'settings' ? null : 'settings'); });
document.querySelector('[data-close="side"]').addEventListener('click', closeSheets);
document.getElementById('standardLink').addEventListener('click', function (e) {
  if (S.isDirty() && !window.confirm('You have unsaved changes in the visual editor. Leave without saving?')) e.preventDefault();
});
window.addEventListener('beforeunload', function (e) { if (S.isDirty()) { e.preventDefault(); e.returnValue = ''; } });
// Clicking on the page selects what was clicked (or clears the selection).
document.getElementById('canvasWrap').addEventListener('pointerdown', function (e) {
  if (state.preview || VPB.drag.current() || VPB.resize.current()) return;
  if (e.target.closest('.tools') || e.target.closest('.resize')) return;
  var t = e.target.closest('[data-kind]');
  var next = t ? { kind: t.getAttribute('data-kind'), id: t.getAttribute('data-id') } : null, cur = state.sel;
  if (!next && !cur) return;
  if (next && cur && next.kind === cur.kind && next.id === cur.id) {
    if (state.panel !== 'settings') { state.panel = 'settings'; VPB.sidebar.renderSide(); }
    return;
  }
  select(next);
});
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape' && state.insertAt !== null && state.insertAt !== undefined) { state.insertAt = null; VPB.canvas.render(); return; }
  var mod = e.ctrlKey || e.metaKey;
  if (mod && e.key.toLowerCase() === 's') { e.preventDefault(); save(); return; }
  var a = document.activeElement, tag = (a && a.tagName) || '';
  if (/INPUT|TEXTAREA|SELECT/.test(tag) || (a && a.isContentEditable)) return;
  if (mod && e.key === '\\' && !isMobile()) { e.preventDefault(); setCollapsed(!document.body.classList.contains('side-collapsed')); return; }
  if (mod && e.key.toLowerCase() === 'z') { e.preventDefault(); if (e.shiftKey) S.redo(); else S.undo(); }
  else if (mod && e.key.toLowerCase() === 'y') { e.preventDefault(); S.redo(); }
  else if ((e.key === 'Delete' || e.key === 'Backspace') && state.sel && !state.preview) {
    if (state.sel.kind === 'block') { e.preventDefault(); A.delBlock(state.sel.id); }
    else if (state.sel.kind === 'sec') { e.preventDefault(); A.delSec(state.sel.id); }
  } else if (e.key === 'Escape') { if (VPB.drag.current()) VPB.drag.cancel(); else if (state.sel) select(null); closeSheets(); }
});

/* ---------- start ---------- */
state.sections = VPB.model.importPage(CFG.page);
S.rebase();
// The sidebar always starts open (pages, headers, and footers alike); hiding
// it with the button or Ctrl+\ lasts until the editor is closed.
setCollapsed(false);
// Headers and footers are short: the canvas fits them instead of keeping a
// page-sized minimum height, and lines items up like the theme does.
if (CFG.region) document.body.classList.add('editing-region', 'editing-' + CFG.region);
VPB.sideResize.init();
if (isMobile()) state.dev = 'phone';
VPB.palette.render();
VPB.sidebar.renderNotice();
renderAll();

// Test hook: lets automated tests inspect the block list without saving.
window.VisualPageBuilder = { exportBlocks: VPB.model.exportBlocks, importPage: VPB.model.importPage, state: state };
})(window.VPB);
