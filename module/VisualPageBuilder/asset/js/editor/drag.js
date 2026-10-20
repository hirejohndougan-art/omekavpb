/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * Drag and drop with pointer events: new sections and blocks from the palette,
 * and moving existing sections and blocks. While dragging, the canvas shows a
 * placeholder where the item will land (drag.target).
 */
(function (VPB) {
'use strict';

var D = VPB.dom, h = D.h, svg = D.svg, presetIcon = D.presetIcon, I = VPB.icons;
var M = VPB.model, S = VPB.store, state = S.state;
var findSec = S.findSec, findCol = S.findCol, findBlock = S.findBlock;
function commit() { S.commit(); }
function renderAll() { VPB.app.renderAll(); }
function renderCanvas() { VPB.canvas.render(); }
function isMobile() { return VPB.app.isMobile(); }
function closeSheets() { VPB.app.closeSheets(); }
function blockName(b) { return VPB.blocks.name(b); }

var drag = null, suppressClick = false, ghost = null, lastPt = null, raf = null;

function startPointer(e, info) {
  if (state.preview) return;
  if (e.button !== undefined && e.button !== 0) return;
  drag = { kind: info.kind, layout: info.layout, html: info.html, label: info.label, preset: info.preset, id: info.id, x0: e.clientX, y0: e.clientY, started: false, target: null };
  window.addEventListener('pointermove', onMove);
  window.addEventListener('pointerup', onUp);
  window.addEventListener('pointercancel', onCancel);
}
function dragInfo() {
  if (drag.kind === 'newSec') return { text: drag.preset.name + ' section', icon: presetIcon(drag.preset.w), sec: true };
  if (drag.kind === 'moveSec') return { text: 'Section', icon: presetIcon(findSec(drag.id).sec.cols.map(function (c) { return c.w; })), sec: true };
  if (drag.kind === 'newBlock') return { text: drag.label, icon: I.block, sec: false };
  return { text: blockName(findBlock(drag.id).block), icon: I.block, sec: false };
}
function beginDrag() {
  drag.started = true;
  document.body.classList.add('dragging');
  var info = dragInfo();
  ghost = h('div', { class: 'ghost' + (info.sec ? ' secghost' : '') });
  ghost.innerHTML = svg(info.icon, 16);
  ghost.appendChild(h('span', { text: info.text }));
  document.body.appendChild(ghost);
  if (drag.kind === 'moveBlock') { var r = findBlock(drag.id); drag.target = { mode: 'col', cid: r.col.id, index: r.bi }; }
  if (drag.kind === 'moveSec') drag.target = { mode: 'sec', index: findSec(drag.id).si };
  renderCanvas();
  if (isMobile()) closeSheets();
  raf = requestAnimationFrame(autoScroll);
}
function onMove(e) {
  if (!drag) return;
  if (!drag.started) {
    if (Math.abs(e.clientX - drag.x0) + Math.abs(e.clientY - drag.y0) < 6) return;
    beginDrag();
  }
  e.preventDefault();
  lastPt = { x: e.clientX, y: e.clientY };
  ghost.style.left = e.clientX + 'px';
  ghost.style.top = e.clientY + 'px';
  computeDrop(e.clientX, e.clientY);
}
function secIndexAt(y) {
  var secs = document.querySelectorAll('#secs > .sec:not(.ph)');
  for (var i = 0; i < secs.length; i++) { var r = secs[i].getBoundingClientRect(); if (y < r.top + r.height / 2) return i; }
  return secs.length;
}
function colDrop(colNode, y) {
  var bl = colNode.querySelectorAll(':scope > .blk:not(.ph)'), index = bl.length;
  for (var i = 0; i < bl.length; i++) { var r = bl[i].getBoundingClientRect(); if (y < r.top + r.height / 2) { index = +bl[i].getAttribute('data-bi'); break; } }
  return { mode: 'col', cid: colNode.getAttribute('data-id'), index: index };
}
function computeDrop(x, y) {
  var wr = document.getElementById('canvasWrap').getBoundingClientRect();
  var next = drag.target;
  if (x < wr.left || x > wr.right || y < wr.top || y > wr.bottom) next = null;
  else {
    var el = document.elementFromPoint(x, y);
    var secDrag = drag.kind === 'newSec' || drag.kind === 'moveSec';
    var overSecPh = el && el.closest && el.closest('.sec.ph');
    var overBlkPh = el && el.closest && el.closest('.blk.ph');
    if (secDrag) { if (!overSecPh) next = { mode: 'sec', index: secIndexAt(y) }; }
    else if (!overBlkPh && !overSecPh) {
      var col = el && el.closest ? el.closest('.col') : null;
      if (!col) {
        var sec = el && el.closest ? el.closest('.sec:not(.ph)') : null;
        if (sec) {
          var cols = sec.querySelectorAll('.col'), best = null, bd = 1e9;
          for (var i = 0; i < cols.length; i++) {
            var r = cols[i].getBoundingClientRect();
            var d = (x < r.left ? r.left - x : x > r.right ? x - r.right : 0) + (y < r.top ? r.top - y : y > r.bottom ? y - r.bottom : 0);
            if (d < bd) { bd = d; best = cols[i]; }
          }
          col = best;
        }
      }
      next = col ? colDrop(col, y) : { mode: 'sec', index: secIndexAt(y) };
    }
  }
  if (JSON.stringify(next) !== JSON.stringify(drag.target)) { drag.target = next; renderCanvas(); }
}
function autoScroll() {
  if (!drag || !drag.started) return;
  if (lastPt) {
    var cw = document.getElementById('canvasWrap'), r = cw.getBoundingClientRect();
    if (lastPt.y < r.top + 60 && lastPt.y > r.top - 40) cw.scrollTop -= 10;
    else if (lastPt.y > r.bottom - 60 && lastPt.y < r.bottom + 80) cw.scrollTop += 10;
  }
  raf = requestAnimationFrame(autoScroll);
}
function endDrag(apply) {
  window.removeEventListener('pointermove', onMove);
  window.removeEventListener('pointerup', onUp);
  window.removeEventListener('pointercancel', onCancel);
  if (!drag) return;
  var d = drag; drag = null;
  if (raf) cancelAnimationFrame(raf); raf = null;
  if (ghost) { ghost.remove(); ghost = null; }
  document.body.classList.remove('dragging');
  if (!d.started) return;
  suppressClick = true; setTimeout(function () { suppressClick = false; }, 0);
  var tg = d.target;
  if (apply && tg) {
    if (d.kind === 'newSec') {
      var ns = M.newSection(d.preset.w.slice()); state.sections.splice(tg.index, 0, ns); state.sel = { kind: 'sec', id: ns.id };
    } else if (d.kind === 'moveSec') {
      var rs = findSec(d.id); state.sections.splice(rs.si, 1); state.sections.splice(tg.index, 0, rs.sec); state.sel = { kind: 'sec', id: rs.sec.id };
    } else {
      var blk;
      if (d.kind === 'newBlock') blk = M.newBlock(d.layout, d.html);
      else { var rb = findBlock(d.id); blk = rb.block; rb.col.blocks.splice(rb.bi, 1); }
      if (tg.mode === 'col') findCol(tg.cid).col.blocks.splice(tg.index, 0, blk);
      else state.sections.splice(tg.index, 0, M.sectionWith(blk));
      state.sel = { kind: 'block', id: blk.id };
    }
    commit();
  }
  renderAll();
}
function onUp() { endDrag(true); }
function onCancel() { endDrag(false); }

/** True (once) if a click should be ignored because it ended a drag. */
function consumeClick() { if (suppressClick) { suppressClick = false; return true; } return false; }

VPB.drag = {
  start: startPointer, cancel: onCancel, consumeClick: consumeClick,
  /** The drag in progress, or null. */
  current: function () { return drag; }
};
})(window.VPB);
