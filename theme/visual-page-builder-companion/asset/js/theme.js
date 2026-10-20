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
  /*
   * Browse filters on phones (Phase 4): the Filters button opens the filters as a
   * full-screen panel; "Show N results" or Escape closes it. On wide screens the
   * button is hidden and the filters stay in place (see style.css).
   */
  function setFilters(toggle, open) {
    var panel = document.getElementById(toggle.getAttribute('aria-controls'));
    if (!panel) return;
    panel.classList.toggle('is-open', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) {
      var done = panel.querySelector('.vpb-facets-done');
      if (done) done.focus(); else panel.focus();
    } else {
      toggle.focus();
    }
  }
  function initFilters() {
    document.addEventListener('click', function (event) {
      var toggle = event.target.closest && event.target.closest('.vpb-filters-toggle');
      if (toggle) {
        setFilters(toggle, toggle.getAttribute('aria-expanded') !== 'true');
        return;
      }
      var done = event.target.closest && event.target.closest('.vpb-facets-done');
      if (done) {
        var panel = done.closest('.vpb-facets');
        var btn = panel && document.querySelector('.vpb-filters-toggle[aria-controls="' + panel.id + '"]');
        if (btn) setFilters(btn, false);
      }
    });
    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Escape') return;
      var open = document.querySelector('.vpb-facets.is-open');
      var btn = open && document.querySelector('.vpb-filters-toggle[aria-controls="' + open.id + '"]');
      if (btn) setFilters(btn, false);
    });
  }
  function startAll() { init(); initFilters(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', startAll); else startAll();
})();
