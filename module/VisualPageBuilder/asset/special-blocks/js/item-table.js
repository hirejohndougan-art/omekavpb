/* SPDX-License-Identifier: GPL-2.0-or-later */
/*
 * Special Blocks: Item table sorting. Each column heading becomes a button;
 * clicking sorts by that column (again to reverse). Dates and numbers sort
 * naturally ("1911" before "1925", "item 2" before "item 10").
 */
(function () {
  'use strict';
  var collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });
  function setUp(table) {
    var heads = Array.prototype.slice.call(table.querySelectorAll('thead th'));
    var body = table.querySelector('tbody');
    heads.forEach(function (th, col) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'special-blocks-sort';
      btn.textContent = th.textContent;
      th.textContent = '';
      th.appendChild(btn);
      th.setAttribute('aria-sort', 'none');
      btn.addEventListener('click', function () {
        var asc = th.getAttribute('aria-sort') !== 'ascending';
        heads.forEach(function (h) { h.setAttribute('aria-sort', 'none'); });
        th.setAttribute('aria-sort', asc ? 'ascending' : 'descending');
        var rows = Array.prototype.slice.call(body.rows);
        rows.sort(function (a, b) {
          var x = a.cells[col].textContent.trim(), y = b.cells[col].textContent.trim();
          if (!x !== !y) return x ? -1 : 1; // Empty cells last.
          return asc ? collator.compare(x, y) : collator.compare(y, x);
        });
        rows.forEach(function (r) { body.appendChild(r); });
      });
    });
  }
  function init() { Array.prototype.forEach.call(document.querySelectorAll('.special-blocks-item-table table'), setUp); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
