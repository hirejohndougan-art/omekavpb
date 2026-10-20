/* SPDX-License-Identifier: GPL-2.0-or-later */
/** Resizing columns by dragging the bar between two columns. */
(function (VPB) {
'use strict';

var U = VPB.util, clamp = U.clamp;
var S = VPB.store, findSec = S.findSec;
var MIN_W = VPB.MIN_W;
function commit() { S.commit(); }
function renderAll() { VPB.app.renderAll(); }
function renderCanvas() { VPB.canvas.render(); }
function renderSide() { VPB.sidebar.renderSide(); }
function refreshStatus() { VPB.app.refreshStatus(); }
function select(sel) { VPB.app.select(sel); }

var resizing = null;

function startResize(e, secId, ci, handle) {
  if (S.state.preview) return;
  if (e.button !== undefined && e.button !== 0) return;
  e.preventDefault(); e.stopPropagation();
  var inner = handle.closest('.sec-inner'), cs = getComputedStyle(inner);
  var width = inner.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
  var gap = parseFloat(cs.columnGap) || 16;
  var s = findSec(secId).sec;
  resizing = { secId: secId, ci: ci, x0: e.clientX, a0: s.cols[ci].w, b0: s.cols[ci + 1].w, unit: (width + gap) / 12 };
  select({ kind: 'sec', id: secId });
  document.body.classList.add('resizing');
  renderCanvas();
  window.addEventListener('pointermove', onResizeMove);
  window.addEventListener('pointerup', onResizeEnd);
  window.addEventListener('pointercancel', onResizeEnd);
}
function onResizeMove(e) {
  if (!resizing) return;
  e.preventDefault();
  var s = findSec(resizing.secId).sec, a = s.cols[resizing.ci], b = s.cols[resizing.ci + 1];
  var total = resizing.a0 + resizing.b0;
  var na = clamp(resizing.a0 + Math.round((e.clientX - resizing.x0) / resizing.unit), MIN_W, total - MIN_W);
  if (na !== a.w) { a.w = na; b.w = total - na; renderCanvas(); renderSide(); refreshStatus(); }
}
function onResizeEnd() {
  window.removeEventListener('pointermove', onResizeMove);
  window.removeEventListener('pointerup', onResizeEnd);
  window.removeEventListener('pointercancel', onResizeEnd);
  resizing = null;
  document.body.classList.remove('resizing');
  commit(); renderAll();
}

VPB.resize = {
  start: startResize,
  /** The resize in progress ({ secId, ci, … }), or null. */
  current: function () { return resizing; }
};
})(window.VPB);
