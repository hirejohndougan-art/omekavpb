# Handoff: Visual Page Builder 1.7.0 (theme unchanged, 1.0.3) — Archive quiz

John: a block with questions and multiple choices, where visitors search the
archives to find the answers. Built directly (no mockup asked for).

- `ArchiveQuiz.php` (`specialArchiveQuiz`): intro; questions list: question,
  choices (one per line), correct (1-based), search words (→ link to
  `/s/<site>/item?fulltext_search=` or the site-wide `index/search`, new
  tab), hint (in `<details>`), item_id (shown after answering), explanation.
  Questions without text or with fewer than two choices are skipped. Display:
  search_in (items / whole site), show_score.
- Right answers are "lightly scrambled" (`scramble()`: base64 of n*7 +
  salt length*3 plus a hash tag; `quiz.js` reverses it). Not secure, and the
  docs say so: it's for fun.
- `js/quiz.js`: Check my answer (asks for a choice first), marks right/wrong
  choices, shows the right answer, explanation, item link; locks the question;
  score at the end ("You got N of M right"); Try again resets. Without JS:
  questions and search links work; a noscript note says checking needs JS.
- Test `special/quiz.test.js` (17). Full suite passes.
- Not mocked up; can add an artboard if John wants to see it.
