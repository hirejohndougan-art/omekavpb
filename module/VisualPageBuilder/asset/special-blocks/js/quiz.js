/* SPDX-License-Identifier: GPL-2.0-or-later */
/*
 * Special Blocks: Archive quiz. "Check my answer" marks the choice right or
 * wrong, shows the right answer, the explanation, and where the answer is;
 * after the last question, the score and "Try again". Nothing is stored.
 * The right answer is lightly scrambled in the page (ArchiveQuiz::scramble).
 */
(function () {
  'use strict';
  function unscramble(key, salt) {
    try { return (parseInt(atob(key).split(':')[0], 10) - salt.length * 3) / 7; } catch (e) { return -1; }
  }
  function fill(s, a, b) { return s.replace('%1$s', a).replace('%2$s', b).replace('%s', a); }
  function setUp(quiz) {
    var qs = Array.prototype.slice.call(quiz.querySelectorAll('.special-blocks-quiz-question'));
    var end = quiz.querySelector('.special-blocks-quiz-end');
    var answered = 0, right = 0;
    function finish() {
      if (answered < qs.length || quiz.getAttribute('data-show-score') !== '1') return;
      end.hidden = false;
      end.querySelector('.special-blocks-quiz-score').textContent = fill(quiz.getAttribute('data-score'), right, qs.length);
    }
    qs.forEach(function (li) {
      var btn = li.querySelector('.special-blocks-quiz-check');
      var fb = li.querySelector('.special-blocks-quiz-feedback');
      var verdict = li.querySelector('.special-blocks-quiz-verdict');
      var correct = unscramble(li.getAttribute('data-key'), li.getAttribute('data-salt'));
      btn.hidden = false;
      btn.addEventListener('click', function () {
        var picked = li.querySelector('input[type="radio"]:checked');
        if (!picked) { fb.hidden = false; verdict.textContent = quiz.getAttribute('data-pick'); return; }
        var ok = parseInt(picked.value, 10) === correct;
        var labels = li.querySelectorAll('.special-blocks-quiz-choices label');
        Array.prototype.forEach.call(labels, function (l, i) {
          l.classList.toggle('is-right', i + 1 === correct);
          l.classList.toggle('is-wrong', !ok && l.contains(picked));
          l.querySelector('input').disabled = true;
        });
        var rightText = labels[correct - 1] ? labels[correct - 1].textContent.trim() : '';
        verdict.textContent = ok ? quiz.getAttribute('data-right') : quiz.getAttribute('data-wrong') + ' ' + fill(quiz.getAttribute('data-answer'), rightText);
        verdict.className = 'special-blocks-quiz-verdict ' + (ok ? 'is-right' : 'is-wrong');
        fb.hidden = false;
        btn.hidden = true;
        answered++; if (ok) right++;
        finish();
      });
    });
    quiz.querySelector('.special-blocks-quiz-reset').addEventListener('click', function () {
      answered = 0; right = 0; end.hidden = true;
      qs.forEach(function (li) {
        Array.prototype.forEach.call(li.querySelectorAll('label'), function (l) { l.classList.remove('is-right', 'is-wrong'); var r = l.querySelector('input'); if (r) { r.disabled = false; r.checked = false; } });
        li.querySelector('.special-blocks-quiz-feedback').hidden = true;
        li.querySelector('.special-blocks-quiz-check').hidden = false;
      });
      var first = qs[0] && qs[0].querySelector('input'); if (first) first.focus();
    });
  }
  function init() { Array.prototype.forEach.call(document.querySelectorAll('.special-blocks-quiz'), setUp); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
