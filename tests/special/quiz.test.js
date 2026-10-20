'use strict';
// v1.7.0: Archive quiz: multiple choice, answers found by searching the archive.
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');
const H = require('../lib/harness');

(async () => {
  const t = H.suite('special blocks: archive quiz');
  const core = await H.fixtures();
  const pageId = await H.createPage('Archive quiz', [{ 'o:layout': 'specialArchiveQuiz', 'o:data': { heading: 'Railroad scavenger hunt', questions: [
    { question: 'What is pictured in the first railroad photo?', choices: 'A school\nA depot\nA church', correct: 2, search: 'railroad', hint: 'Look at the photos.', item_id: core.items[0], explanation: 'The photo shows the depot.' },
    { question: 'Which map is shared between sites?', choices: 'The shared map\nThe county map', correct: 1, search: 'map' },
    { question: 'Only one choice', choices: 'Lonely', correct: 1 },
    { question: '', choices: 'A\nB', correct: 1 }
  ] } }]);
  const page = await H.api('GET', 'site_pages/' + pageId);
  const html = await (await fetch(`${H.BASE}/s/${H.SITE}/page/${page['o:slug']}`)).text();
  const d = new JSDOM(html).window.document;
  t.ok(!/Fatal error|Warning:|Notice:/.test(html), 'renders without PHP errors');
  const qs = d.querySelectorAll('.special-blocks-quiz-question');
  t.ok(qs.length === 2, 'questions without text or with fewer than two choices are left out');
  t.ok(qs[0].querySelector('legend') && qs[0].querySelectorAll('input[type="radio"]').length === 3, 'each question is a labeled group of choices');
  const search = qs[0].querySelector('.special-blocks-quiz-search a');
  t.ok(search && /\/s\/dhs\/item\?fulltext_search=railroad/.test(search.getAttribute('href')) && search.getAttribute('target') === '_blank', 'a "Search the archive" link with the suggested words, in a new tab');
  t.ok(qs[0].querySelector('details summary') && /Look at the photos/.test(qs[0].textContent), 'hint tucked away until opened');
  t.ok(!/data-correct|correct="2"/.test(html) && !/^\d+$/.test(qs[0].getAttribute('data-key')), 'the right answer isn\u2019t written plainly in the page');
  t.ok(d.querySelector('noscript') && qs[0].querySelector('.special-blocks-quiz-check[hidden]'), 'without JavaScript: questions and searches work, checking is off');

  // Play it.
  const live = new JSDOM(html, { runScripts: 'outside-only', pretendToBeVisual: true });
  const w = live.window;
  w.eval(fs.readFileSync(path.join(H.MODULE_DIR, 'asset/special-blocks/js/quiz.js'), 'utf8'));
  await H.sleep(200); // the script starts once the page has loaded
  const L = [...w.document.querySelectorAll('.special-blocks-quiz-question')];
  const check = li => li.querySelector('.special-blocks-quiz-check').click();
  t.ok(!L[0].querySelector('.special-blocks-quiz-check').hidden, 'Check my answer appears');
  check(L[0]);
  t.ok(/Choose an answer first/.test(L[0].querySelector('.special-blocks-quiz-verdict').textContent), 'checking with nothing chosen asks for a choice');
  L[0].querySelectorAll('input')[0].checked = true; check(L[0]);
  const v0 = L[0].querySelector('.special-blocks-quiz-verdict');
  t.ok(/Not quite/.test(v0.textContent) && /A depot/.test(v0.textContent) && L[0].querySelectorAll('label')[1].classList.contains('is-right') && L[0].querySelectorAll('label')[0].classList.contains('is-wrong'), 'a wrong answer is marked, and the right one shown');
  t.ok(/shows the depot/.test(L[0].textContent) && L[0].querySelector('.special-blocks-quiz-feedback a[href*="/item/"]'), 'the explanation and the item where the answer is');
  t.ok([...L[0].querySelectorAll('input')].every(x => x.disabled), 'answered questions lock');
  L[1].querySelectorAll('input')[0].checked = true; check(L[1]);
  t.ok(/Right!/.test(L[1].querySelector('.special-blocks-quiz-verdict').textContent), 'a right answer says so');
  const end = w.document.querySelector('.special-blocks-quiz-end');
  t.ok(!end.hidden && /You got 1 of 2 right/.test(end.textContent), 'the score at the end');
  end.querySelector('.special-blocks-quiz-reset').click();
  t.ok(end.hidden && [...L[0].querySelectorAll('input')].every(x => !x.disabled && !x.checked), 'Try again starts over');
  live.window.close();

  const ed = await H.openEditor(pageId);
  t.ok([...ed.d.querySelectorAll('.pal-label')].some(x => x.textContent === 'Archive quiz'), 'in the palette');
  t.ok(!ed.errs.length, 'no editor errors');
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
