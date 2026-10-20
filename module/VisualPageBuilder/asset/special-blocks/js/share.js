/* SPDX-License-Identifier: GPL-2.0-or-later */
/*
 * Special Blocks: Share this page's Copy link and Print buttons
 * (hidden unless the browser can do them).
 */
(function () {
  'use strict';
  function init() {
    Array.prototype.forEach.call(document.querySelectorAll('.special-blocks-share-copy'), function (btn) {
      if (!navigator.clipboard) return;
      btn.hidden = false;
      var label = btn.textContent;
      btn.addEventListener('click', function () {
        navigator.clipboard.writeText(btn.getAttribute('data-url')).then(function () {
          btn.textContent = btn.getAttribute('data-done') || 'Copied';
          setTimeout(function () { btn.textContent = label; }, 2000);
        });
      });
    });
    Array.prototype.forEach.call(document.querySelectorAll('.special-blocks-share-print'), function (btn) {
      if (typeof window.print !== 'function') return;
      btn.hidden = false;
      btn.addEventListener('click', function () { window.print(); });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
