/* SPDX-License-Identifier: GPL-2.0-or-later */
/*
 * Special Blocks: Timeline arrows. Shows Earlier / Later buttons under an
 * "across" timeline and scrolls it by most of its width. (The timeline also
 * scrolls with a trackpad, touch, or the keyboard once focused.)
 */
(function () {
  'use strict';
  function setUp(box) {
    var track = box.querySelector('.special-blocks-tl-track');
    var nav = box.querySelector('.special-blocks-tl-nav');
    if (!track || !nav) return;
    nav.hidden = false;
    var prev = nav.querySelector('.special-blocks-tl-prev'), next = nav.querySelector('.special-blocks-tl-next');
    function update() {
      prev.disabled = track.scrollLeft <= 2;
      next.disabled = track.scrollLeft + track.clientWidth >= track.scrollWidth - 2;
    }
    function go(dir) {
      var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      track.scrollBy({ left: dir * track.clientWidth * 0.8, behavior: reduce ? 'auto' : 'smooth' });
    }
    prev.addEventListener('click', function () { go(-1); });
    next.addEventListener('click', function () { go(1); });
    track.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    update();
  }
  function init() { Array.prototype.forEach.call(document.querySelectorAll('.special-blocks-timeline-horizontal'), setUp); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
