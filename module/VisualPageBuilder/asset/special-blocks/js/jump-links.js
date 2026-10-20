/* SPDX-License-Identifier: GPL-2.0-or-later */
/*
 * Special Blocks: Jump links. Glides to the spot (jumps with reduced motion),
 * updates the address so the link can be shared, and moves keyboard focus
 * there so keyboard and screen-reader users land in the right place.
 */
(function () {
  'use strict';
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('.special-blocks-jump-links a[href^="#"]');
    if (!a) return;
    var target = document.getElementById(decodeURIComponent(a.getAttribute('href').slice(1)));
    if (!target) return;
    e.preventDefault();
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    target.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
    if (history.pushState) history.pushState(null, '', '#' + target.id);
    if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
    target.focus({ preventScroll: true });
  });
})();
