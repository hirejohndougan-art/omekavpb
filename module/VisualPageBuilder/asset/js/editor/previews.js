/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * Live previews fetched from the server (BuilderController::previewAction).
 *
 * Used for blocks whose preview needs the site's data: other modules' blocks
 * that render their own preview HTML (e.g. Society Blocks' random items), and
 * Omeka's Browse preview (the items its search would show).
 *
 * Results are cached per block type + settings, so the canvas doesn't refetch
 * on every redraw, and a random selection doesn't reshuffle while you edit.
 * While a request is out, callers get null and show the block's card instead;
 * the canvas redraws when the answer arrives.
 */
(function (VPB) {
'use strict';

var h = VPB.dom.h, CFG = VPB.cfg;
var cache = {};      // key → { state: 'loading' | 'done' | 'error', body }
var redrawTimer = null;
var DEBOUNCE_MS = 400; // Wait for typing to pause before asking the server.
var pending = {};

function keyOf(b) { return b.layout + '|' + JSON.stringify(b.data); }

function redrawSoon() {
  clearTimeout(redrawTimer);
  redrawTimer = setTimeout(function () { VPB.canvas.render(); }, 30);
}

function request(key, b) {
  cache[key] = { state: 'loading' };
  fetch(CFG.previewUrl, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ csrf: CFG.csrf, layout: b.layout, data: b.data })
  }).then(function (r) { return r.json(); })
    .then(function (body) { cache[key] = body && body.ok ? { state: 'done', body: body } : { state: 'error' }; redrawSoon(); })
    .catch(function () { cache[key] = { state: 'error' }; redrawSoon(); });
}

/**
 * The server's answer for this block's current settings ({html} or {items}),
 * or null while loading or if it failed.
 */
function get(b) {
  var key = keyOf(b), hit = cache[key];
  if (hit) return hit.state === 'done' ? hit.body : null;
  // Debounce per block, so typing a heading doesn't send a request per key.
  clearTimeout(pending[b.id]);
  pending[b.id] = setTimeout(function () { if (!cache[keyOf(b)]) request(keyOf(b), b); }, DEBOUNCE_MS);
  return null;
}

/** A preview node from server HTML: cleaned, and inert so it can't be used. */
function htmlNode(html) {
  var res = VPB.dom.sanitize(html, { keepForms: true });
  var div = h('div', { class: 'b-live', inert: true });
  // Previews are just pictures of the block: drop ids (and what points at
  // them) so two previews of the same block can't share an id on the page.
  Array.prototype.forEach.call(res.fragment.querySelectorAll('[id], [for], [aria-controls], [aria-labelledby], [aria-describedby]'), function (el) {
    ['id', 'for', 'aria-controls', 'aria-labelledby', 'aria-describedby'].forEach(function (a) { el.removeAttribute(a); });
  });
  div.appendChild(res.fragment);
  return div;
}

VPB.previews = { get: get, htmlNode: htmlNode };
})(window.VPB);
