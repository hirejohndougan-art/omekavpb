/* SPDX-License-Identifier: GPL-2.0-or-later */
/*
 * Special Blocks: Map of places. Draws each .special-blocks-map[data-places]
 * with the module's own map (asset/map/vpb-map.js) and OpenStreetMap tiles,
 * fits the view to the pins, and opens a pin's note and item link on click.
 * As a tour, pins are numbered and joined by a line in order.
 */
(function () {
  'use strict';
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function setUp(el) {
    if (!window.VpbMap || el.getAttribute('data-ready')) return;
    var places;
    try { places = JSON.parse(el.getAttribute('data-places')) || []; } catch (e) { return; }
    if (!places.length) return;
    el.setAttribute('data-ready', '1');
    var tour = el.getAttribute('data-tour') === '1';
    var map = window.VpbMap.create(el, { wheelZoom: 'after-click', label: el.getAttribute('aria-label') || 'Map' });
    var points = [];
    places.forEach(function (p, i) {
      var ll = [p.lat, p.lng];
      points.push(ll);
      var html = '<strong>' + esc(p.name) + '</strong>' +
        (p.thumb ? '<br><img src="' + esc(p.thumb) + '" alt="" style="max-width:120px;margin-top:6px">' : '') +
        (p.note ? '<br>' + esc(p.note).replace(/\n/g, '<br>') : '') +
        (p.url ? '<br><a href="' + esc(p.url) + '">' + esc(el.getAttribute('data-view-item') || 'View the item') + '</a>' : '');
      map.addPin(ll, { text: tour ? String(i + 1) : '', title: (tour ? (i + 1) + '. ' : '') + p.name, popup: html, className: tour ? 'special-blocks-map-num' : '' });
    });
    if (tour && points.length > 1) map.addLine(points, { dashed: true });
    map.fitBounds(points, { padding: 30, maxZoom: 16 });
  }
  function init() { Array.prototype.forEach.call(document.querySelectorAll('.special-blocks-map[data-places]'), setUp); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
  window.addEventListener('load', init);
})();
