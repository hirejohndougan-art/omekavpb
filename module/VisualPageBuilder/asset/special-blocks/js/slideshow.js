/* SPDX-License-Identifier: GPL-2.0-or-later */
/*
 * Special Blocks: Featured slideshow.
 *
 * Shows one slide at a time with Previous/Next buttons, dots, and a
 * Pause/Play button. It advances by itself every data-seconds seconds (0 =
 * never), pauses while hovered or focused, and never moves by itself when the
 * visitor's device asks for reduced motion. Only the visible slide can be
 * reached with the keyboard.
 */
(function () {
  'use strict';

  function button(cls, label, text) {
    var b = document.createElement('button');
    b.type = 'button'; b.className = cls; b.setAttribute('aria-label', label); b.textContent = text;
    return b;
  }

  function setUp(show) {
    var list = show.querySelector('.special-blocks-slides');
    var slides = Array.prototype.slice.call(show.querySelectorAll('.special-blocks-slide'));
    if (!list || slides.length < 2 || show.classList.contains('is-ready')) return;
    var seconds = parseInt(show.getAttribute('data-seconds'), 10) || 0;
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var current = 0, timer = null, playing = seconds > 0 && !reduced, hovering = false;

    var controls = document.createElement('div');
    controls.className = 'special-blocks-slide-controls';
    var prev = button('special-blocks-slide-prev', 'Previous slide', '\u2039');
    var next = button('special-blocks-slide-next', 'Next slide', '\u203A');
    var pause = button('special-blocks-slide-pause', '', '');
    var dots = document.createElement('div');
    dots.className = 'special-blocks-slide-dots';
    slides.forEach(function (s, i) {
      var d = button('special-blocks-slide-dot', 'Show slide ' + (i + 1), '');
      d.addEventListener('click', function () { go(i, true); });
      dots.appendChild(d);
    });
    controls.appendChild(prev); controls.appendChild(dots); controls.appendChild(next);
    if (seconds > 0 && !reduced) controls.appendChild(pause);
    show.appendChild(controls);

    function setPause() {
      pause.textContent = playing ? '\u275A\u275A' : '\u25B6';
      pause.setAttribute('aria-label', playing ? 'Pause slideshow' : 'Play slideshow');
      list.setAttribute('aria-live', playing ? 'off' : 'polite');
    }
    function go(i, byUser) {
      current = (i + slides.length) % slides.length;
      slides.forEach(function (s, j) {
        var on = j === current;
        s.hidden = !on;
        s.setAttribute('aria-hidden', String(!on));
      });
      Array.prototype.forEach.call(dots.children, function (d, j) { d.setAttribute('aria-current', j === current ? 'true' : 'false'); });
      if (byUser) restart();
    }
    function tick() { if (playing && !hovering) go(current + 1, false); }
    function restart() { clearInterval(timer); if (playing) timer = setInterval(tick, seconds * 1000); }

    prev.addEventListener('click', function () { go(current - 1, true); });
    next.addEventListener('click', function () { go(current + 1, true); });
    pause.addEventListener('click', function () { playing = !playing; setPause(); restart(); });
    show.addEventListener('mouseenter', function () { hovering = true; });
    show.addEventListener('mouseleave', function () { hovering = false; });
    show.addEventListener('focusin', function () { hovering = true; });
    show.addEventListener('focusout', function () { hovering = false; });

    show.classList.add('is-ready');
    setPause();
    go(0, false);
    restart();
  }

  function init() { Array.prototype.forEach.call(document.querySelectorAll('.special-blocks-slideshow'), setUp); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
