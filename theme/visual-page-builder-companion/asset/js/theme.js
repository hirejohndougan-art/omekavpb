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
  /*
   * While the full-screen panel is open, the page behind it is inert so Tab
   * cannot reach hidden links; the panel's own ancestors stay live.
   */
  function setPageInert(panel, on) {
    var node = panel;
    while (node && node !== document.body) {
      var parent = node.parentElement;
      if (!parent) break;
      Array.prototype.forEach.call(parent.children, function (sib) {
        if (sib === node) return;
        if (on) sib.setAttribute('inert', ''); else sib.removeAttribute('inert');
      });
      node = parent;
    }
  }
  function setFilters(toggle, open) {
    var panel = document.getElementById(toggle.getAttribute('aria-controls'));
    if (!panel) return;
    panel.classList.toggle('is-open', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    setPageInert(panel, open);
    if (open) {
      var done = panel.querySelector('.vpb-facets-done');
      if (done) done.focus(); else panel.focus();
    } else {
      toggle.focus();
    }
  }
  /* Keeps Tab and Shift+Tab cycling inside the open panel, never the browser bar. */
  function trapTab(panel, event) {
    var items = Array.prototype.filter.call(
      panel.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'),
      function (el) { return el.offsetParent !== null || el === document.activeElement; }
    );
    if (!items.length) { event.preventDefault(); panel.focus(); return; }
    var first = items[0];
    var last = items[items.length - 1];
    var active = document.activeElement;
    var inside = panel.contains(active);
    event.preventDefault();
    if (event.shiftKey) {
      if (!inside || active === first) last.focus(); else items[items.indexOf(active) - 1].focus();
    } else {
      if (!inside || active === last) first.focus(); else items[items.indexOf(active) + 1].focus();
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
      var open = document.querySelector('.vpb-facets.is-open');
      if (!open) return;
      if (event.key === 'Escape') {
        var btn = document.querySelector('.vpb-filters-toggle[aria-controls="' + open.id + '"]');
        if (btn) setFilters(btn, false);
        return;
      }
      if (event.key === 'Tab') trapTab(open, event);
    });
  }
  function startAll() { init(); initFilters(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', startAll); else startAll();
})();
