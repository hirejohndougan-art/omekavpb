/* SPDX-License-Identifier: GPL-2.0-or-later */
/*
 * Companion Theme: menu behavior.
 * - "Menu" button opens and closes the menu on small screens.
 * - Each sub-menu has a button that opens it (keyboard and touch friendly).
 *   On wide screens, pointing at an item opens it too (see style.css).
 * - Escape closes open sub-menus; clicking elsewhere closes them.
 */
(function () {
  'use strict';
  function closeAll(except) {
    Array.prototype.forEach.call(document.querySelectorAll('.pb-sub-toggle[aria-expanded="true"]'), function (b) {
      if (b !== except && !(except && b.parentNode.contains(except))) b.setAttribute('aria-expanded', 'false');
    });
  }
  function init() {
    var nav = document.querySelector('.pb-nav');
    if (!nav) return;
    var toggle = nav.querySelector('.pb-nav-toggle');
    toggle.addEventListener('click', function () {
      toggle.setAttribute('aria-expanded', String(toggle.getAttribute('aria-expanded') !== 'true'));
    });
    nav.addEventListener('click', function (e) {
      var b = e.target.closest('.pb-sub-toggle');
      if (!b) return;
      var open = b.getAttribute('aria-expanded') === 'true';
      closeAll(b);
      b.setAttribute('aria-expanded', String(!open));
    });
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      var open = nav.querySelector('.pb-sub-toggle[aria-expanded="true"]');
      closeAll(null);
      if (open) { open.focus(); return; }
      // Then close the menu itself (phones, and the "Menu button only" style).
      if (toggle.getAttribute('aria-expanded') === 'true') { toggle.setAttribute('aria-expanded', 'false'); toggle.focus(); }
    });
    document.addEventListener('click', function (e) {
      if (nav.contains(e.target)) return;
      closeAll(null);
      if (nav.classList.contains('pb-nav-button')) toggle.setAttribute('aria-expanded', 'false');
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
