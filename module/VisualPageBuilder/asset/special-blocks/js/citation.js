/* SPDX-License-Identifier: GPL-2.0-or-later */
/*
 * Special Blocks: "Copy citation" button (shown only where copying works).
 */
(function () {
  'use strict';
  function setUp(box) {
    var btn = box.querySelector('.special-blocks-copy');
    var text = box.querySelector('.special-blocks-citation-text');
    if (!btn || !text || !navigator.clipboard) return;
    btn.hidden = false;
    var label = btn.textContent;
    btn.addEventListener('click', function () {
      navigator.clipboard.writeText(text.textContent.trim()).then(function () {
        btn.textContent = btn.getAttribute('data-copied') || 'Copied';
        setTimeout(function () { btn.textContent = label; }, 2000);
      });
    });
  }
  function init() { Array.prototype.forEach.call(document.querySelectorAll('.special-blocks-citation'), setUp); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
