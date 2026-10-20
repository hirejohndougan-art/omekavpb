/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * Widening the sidebar by dragging its edge.
 *
 * The handle (#sideResize in edit.phtml) is a focusable "separator": drag it
 * with a mouse or finger, or focus it and use the left/right arrow keys (Home
 * and End for narrowest/widest). Double-click puts the default width back.
 * The width is remembered in this browser.
 *
 * While dragging, the listeners are on the window, so letting go anywhere
 * (even outside the window) ends the drag, and the sidebar's edge follows the
 * pointer exactly.
 */
(function (VPB) {
'use strict';

var KEY = 'visual-page-builder-sidebar-width';
var DEFAULT_W = 300, MIN_W = 280, STEP = 16;

function maxWidth() { return Math.max(MIN_W, Math.min(640, Math.round(window.innerWidth * 0.6))); }
function clampW(w) { return Math.max(MIN_W, Math.min(maxWidth(), Math.round(w))); }

var width = DEFAULT_W;

function apply(w, remember) {
  width = clampW(w);
  document.documentElement.style.setProperty('--side-w', width + 'px');
  var handle = document.getElementById('sideResize');
  if (handle) {
    handle.setAttribute('aria-valuenow', String(width));
    handle.setAttribute('aria-valuemax', String(maxWidth()));
  }
  if (remember) {
    try { localStorage.setItem(KEY, String(width)); } catch (e) { /* storage unavailable */ }
  }
}

function startDrag(e) {
  if (e.button !== undefined && e.button !== 0) return;
  e.preventDefault();
  var side = document.getElementById('side');
  var left = side.getBoundingClientRect().left;
  document.body.classList.add('resizing-side');
  function move(ev) {
    // A mouse released outside the window sends no pointerup; this catches it.
    if (ev.pointerType === 'mouse' && ev.buttons === 0) { stop(); return; }
    apply(ev.clientX - left, false);
  }
  function stop() {
    window.removeEventListener('pointermove', move);
    window.removeEventListener('pointerup', stop);
    window.removeEventListener('pointercancel', stop);
    window.removeEventListener('blur', stop);
    document.body.classList.remove('resizing-side');
    apply(width, true);
  }
  window.addEventListener('pointermove', move);
  window.addEventListener('pointerup', stop);
  window.addEventListener('pointercancel', stop);
  window.addEventListener('blur', stop);
}

function onKey(e) {
  var next = null;
  if (e.key === 'ArrowLeft') next = width - STEP;
  else if (e.key === 'ArrowRight') next = width + STEP;
  else if (e.key === 'Home') next = MIN_W;
  else if (e.key === 'End') next = maxWidth();
  if (next === null) return;
  e.preventDefault();
  apply(next, true);
}

function init() {
  var handle = document.getElementById('sideResize');
  var saved = null;
  try { saved = parseInt(localStorage.getItem(KEY), 10); } catch (e) { /* storage unavailable */ }
  apply(saved > 0 ? saved : DEFAULT_W, false);
  if (!handle) return;
  handle.setAttribute('aria-valuemin', String(MIN_W));
  handle.addEventListener('pointerdown', startDrag);
  handle.addEventListener('keydown', onKey);
  handle.addEventListener('dblclick', function () { apply(DEFAULT_W, true); });
  window.addEventListener('resize', function () { apply(width, false); });
}

VPB.sideResize = { init: init, width: function () { return width; }, set: function (w) { apply(w, true); } };
})(window.VPB);
