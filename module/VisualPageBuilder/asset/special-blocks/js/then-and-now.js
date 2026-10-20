/* SPDX-License-Identifier: GPL-2.0-or-later */
/*
 * Special Blocks: Then and now slider.
 *
 * Turns each .special-blocks-then-and-now figure (two stacked photos) into an
 * overlay with a draggable divider. The divider is a real range input, so it
 * works with a mouse, touch, and the keyboard (arrow keys), and screen readers
 * announce it. Without this script, both photos show one after the other.
 */
(function () {
  'use strict';

  function setUp(figure) {
    var box = figure.querySelector('.special-blocks-compare');
    var slider = figure.querySelector('.special-blocks-compare-slider');
    if (!box || !slider || box.classList.contains('is-slider')) return;
    function update() { box.style.setProperty('--sb-split', slider.value + '%'); }
    slider.hidden = false;
    box.classList.add('is-slider');
    slider.addEventListener('input', update);
    update();
  }

  function init() {
    Array.prototype.forEach.call(document.querySelectorAll('.special-blocks-then-and-now'), setUp);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
