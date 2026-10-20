/* SPDX-License-Identifier: GPL-2.0-or-later */
/*
 * Special Blocks: standard page editor helpers.
 *
 * Fields with data-show-if='{"otherKey": [values…]}' only apply when another
 * field in the same block has one of those values (for example, "Sites" only
 * when "Show from" is "Chosen sites"). This hides the others and shows them
 * again when the controlling field changes. Hidden fields are still saved, so
 * switching back restores what was there.
 */
(function () {
  'use strict';

  /** The current value of a field in this block's form, by schema key. */
  function valueOf(form, key) {
    var wrap = form.querySelector('.special-blocks-field[data-key="' + key + '"]');
    if (!wrap) return null;
    var input = wrap.querySelector('select, input:not([type="hidden"]), textarea');
    if (!input) return null;
    if (input.type === 'checkbox') return input.checked;
    return input.value;
  }

  function refresh(form) {
    Array.prototype.forEach.call(form.querySelectorAll('.special-blocks-field[data-show-if]'), function (wrap) {
      var rule;
      try { rule = JSON.parse(wrap.getAttribute('data-show-if')); } catch (e) { return; }
      var show = Object.keys(rule).every(function (key) { return rule[key].indexOf(valueOf(form, key)) >= 0; });
      wrap.hidden = !show;
    });
  }

  function refreshAll() {
    Array.prototype.forEach.call(document.querySelectorAll('.special-blocks-form'), refresh);
  }

  document.addEventListener('change', function (e) {
    var form = e.target.closest && e.target.closest('.special-blocks-form');
    if (form) refresh(form);
  });
  // Omeka adds block forms to the page when a block is added; check again then.
  if (window.MutationObserver) {
    new MutationObserver(refreshAll).observe(document.documentElement, { childList: true, subtree: true });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', refreshAll);
  else refreshAll();
})();
