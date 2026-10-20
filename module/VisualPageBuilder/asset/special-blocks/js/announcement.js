/* SPDX-License-Identifier: GPL-2.0-or-later */
/*
 * Special Blocks: Announcement bar close button.
 * Closing is remembered in this browser, per notice (a new text shows again).
 */
(function () {
  'use strict';
  function setUp(bar) {
    var key = bar.getAttribute('data-dismiss-key');
    var btn = bar.querySelector('.special-blocks-announcement-close');
    if (!key || !btn) return;
    try { if (localStorage.getItem(key) === 'closed') { bar.hidden = true; return; } } catch (e) { /* storage blocked */ }
    btn.hidden = false;
    btn.addEventListener('click', function () {
      bar.hidden = true;
      try { localStorage.setItem(key, 'closed'); } catch (e) { /* storage blocked */ }
    });
  }
  function init() { Array.prototype.forEach.call(document.querySelectorAll('.special-blocks-announcement'), setUp); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
