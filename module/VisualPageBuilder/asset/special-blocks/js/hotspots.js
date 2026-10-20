/* SPDX-License-Identifier: GPL-2.0-or-later */
/*
 * Special Blocks: Who's who in this photo.
 * Tapping a numbered marker shows its name and note in a bubble beside it;
 * tapping it again, pressing Escape, or tapping elsewhere hides it.
 */
(function () {
  'use strict';
  function setUp(fig) {
    var bubble = fig.querySelector('.special-blocks-hotspot-bubble');
    if (!bubble) return;
    var current = null;
    function hide() { bubble.hidden = true; if (current) current.setAttribute('aria-expanded', 'false'); current = null; }
    Array.prototype.forEach.call(fig.querySelectorAll('.special-blocks-hotspot'), function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (current === btn) { hide(); return; }
        hide();
        var item = document.getElementById(btn.getAttribute('aria-describedby'));
        bubble.textContent = item ? item.textContent.trim() : btn.getAttribute('aria-label');
        bubble.style.left = btn.style.left;
        bubble.style.top = btn.style.top;
        bubble.hidden = false;
        btn.setAttribute('aria-expanded', 'true');
        current = btn;
      });
    });
    document.addEventListener('click', function (e) { if (!fig.contains(e.target)) hide(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && current) { var b = current; hide(); b.focus(); } });
  }
  function init() { Array.prototype.forEach.call(document.querySelectorAll('.special-blocks-photo-hotspots'), setUp); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
