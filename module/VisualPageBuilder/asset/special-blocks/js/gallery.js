/* SPDX-License-Identifier: GPL-2.0-or-later */
/*
 * Special Blocks: Gallery viewer.
 *
 * Clicking a photo opens it large in a dialog with Previous/Next, its caption,
 * and a link to the item. Arrow keys move between photos and Escape closes
 * (the browser's <dialog> handles focus and Escape). Without this script, each
 * photo simply links to its item page.
 */
(function () {
  'use strict';

  var dialog, img, caption, link, links = [], index = 0;

  function build() {
    dialog = document.createElement('dialog');
    dialog.className = 'special-blocks-viewer';
    dialog.setAttribute('aria-label', 'Photo viewer');
    dialog.innerHTML =
      '<button type="button" class="special-blocks-viewer-close" aria-label="Close">\u00D7</button>' +
      '<button type="button" class="special-blocks-viewer-prev" aria-label="Previous photo">\u2039</button>' +
      '<figure><img alt=""><figcaption><span class="special-blocks-viewer-caption"></span> <a class="special-blocks-viewer-link">View the item</a></figcaption></figure>' +
      '<button type="button" class="special-blocks-viewer-next" aria-label="Next photo">\u203A</button>';
    document.body.appendChild(dialog);
    img = dialog.querySelector('img');
    caption = dialog.querySelector('.special-blocks-viewer-caption');
    link = dialog.querySelector('.special-blocks-viewer-link');
    dialog.querySelector('.special-blocks-viewer-close').addEventListener('click', function () { dialog.close(); });
    dialog.querySelector('.special-blocks-viewer-prev').addEventListener('click', function () { show(index - 1); });
    dialog.querySelector('.special-blocks-viewer-next').addEventListener('click', function () { show(index + 1); });
    dialog.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') { e.preventDefault(); show(index - 1); }
      if (e.key === 'ArrowRight') { e.preventDefault(); show(index + 1); }
    });
    // Clicking the dark area outside the photo closes it.
    dialog.addEventListener('click', function (e) { if (e.target === dialog) dialog.close(); });
  }

  function show(i) {
    index = (i + links.length) % links.length;
    var a = links[index];
    img.src = a.getAttribute('data-large');
    img.alt = a.getAttribute('data-caption') || '';
    caption.textContent = a.getAttribute('data-caption') || '';
    var url = a.getAttribute('data-item-url');
    link.hidden = !url;
    if (url) link.href = url;
  }

  function init() {
    if (typeof HTMLDialogElement === 'undefined') return; // Very old browsers: plain links.
    Array.prototype.forEach.call(document.querySelectorAll('.special-blocks-gallery'), function (gallery) {
      var mine = Array.prototype.slice.call(gallery.querySelectorAll('.special-blocks-gallery-link'));
      mine.forEach(function (a, i) {
        a.addEventListener('click', function (e) {
          e.preventDefault();
          if (!dialog) build();
          links = mine;
          show(i);
          dialog.showModal();
        });
      });
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
