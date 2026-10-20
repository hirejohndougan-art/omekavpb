/* SPDX-License-Identifier: GPL-2.0-or-later */
/*
 * Special Blocks: synced oral history. While the recording plays, the line
 * being spoken is highlighted (and, with "Follow along", scrolled into view
 * inside the transcript box only). Time buttons, lines, and topics jump the
 * recording there. "Search this interview" marks matching lines and counts
 * them. On video, captions are made from the transcript (or the item's .vtt).
 */
(function () {
  'use strict';
  function escapeRe(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
  function vttTime(sec) {
    var h = Math.floor(sec / 3600), m = Math.floor(sec % 3600 / 60), s = sec % 60;
    return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s.toFixed(3);
  }
  function setUp(box) {
    var player = box.querySelector('.special-blocks-transcript-media audio, .special-blocks-transcript-media video');
    if (!player || box.getAttribute('data-ready')) return;
    box.setAttribute('data-ready', '1');
    var scroller = box.querySelector('.special-blocks-transcript-text');
    var cues = Array.prototype.slice.call(box.querySelectorAll('.special-blocks-oh-cue'));
    var starts = cues.map(function (c) { return parseFloat(c.getAttribute('data-start')) || 0; });
    var tools = box.querySelector('.special-blocks-oh-tools');
    var follow = box.querySelector('.special-blocks-oh-follow input');
    var count = box.querySelector('.special-blocks-oh-count');
    var current = -1, userScrolledAt = 0;
    if (tools) tools.hidden = false;

    function seek(sec) {
      try { player.currentTime = sec; } catch (e) { return; }
      var p = player.play && player.play();
      if (p && p.catch) p.catch(function () { /* the visitor can press play */ });
      mark(sec, true);
    }
    function indexAt(t) {
      var i = -1;
      for (var k = 0; k < starts.length; k++) { if (starts[k] <= t + 0.05) i = k; else break; }
      return i;
    }
    function mark(t, force) {
      var i = indexAt(t);
      if (i === current && !force) return;
      if (current >= 0 && cues[current]) { cues[current].classList.remove('is-current'); cues[current].removeAttribute('aria-current'); }
      current = i;
      if (i < 0) return;
      cues[i].classList.add('is-current');
      cues[i].setAttribute('aria-current', 'true');
      // Scroll the transcript box (not the page), unless the visitor is scrolling it.
      if (follow && follow.checked && Date.now() - userScrolledAt > 4000 && scroller.scrollHeight > scroller.clientHeight) {
        scroller.scrollTop = Math.max(0, cues[i].offsetTop - scroller.offsetTop - scroller.clientHeight / 3);
      }
    }
    player.addEventListener('timeupdate', function () { mark(player.currentTime, false); });
    player.addEventListener('seeked', function () { mark(player.currentTime, true); });
    ['wheel', 'touchmove', 'keydown'].forEach(function (ev) { scroller.addEventListener(ev, function () { userScrolledAt = Date.now(); }, { passive: true }); });
    box.addEventListener('click', function (e) {
      var b = e.target.closest('.special-blocks-oh-seek');
      if (b && box.contains(b)) { e.preventDefault(); seek(parseFloat(b.getAttribute('data-start')) || 0); }
    });

    // Search inside the interview.
    var search = box.querySelector('.special-blocks-oh-search input');
    if (search) {
      cues.forEach(function (c) { var t = c.querySelector('.special-blocks-oh-text'); t.setAttribute('data-plain', t.textContent); });
      search.addEventListener('input', function () {
        var q = search.value.trim(), hits = 0, first = null;
        cues.forEach(function (c) {
          var t = c.querySelector('.special-blocks-oh-text'), plain = t.getAttribute('data-plain');
          t.textContent = plain;
          c.classList.remove('is-hit');
          if (q.length < 2) return;
          var re = new RegExp(escapeRe(q), 'gi');
          if (!re.test(plain)) return;
          hits++; if (!first) first = c;
          c.classList.add('is-hit');
          // Highlight the matches with <mark> (built from text, never HTML).
          t.textContent = '';
          var last = 0; plain.replace(new RegExp(escapeRe(q), 'gi'), function (m, at) {
            t.appendChild(document.createTextNode(plain.slice(last, at)));
            var mk = document.createElement('mark'); mk.textContent = m; t.appendChild(mk);
            last = at + m.length; return m;
          });
          t.appendChild(document.createTextNode(plain.slice(last)));
        });
        count.textContent = q.length < 2 ? '' : hits === 0 ? box.getAttribute('data-no-results') : hits === 1 ? box.getAttribute('data-one-result') : box.getAttribute('data-results').replace('%s', hits);
        if (first) { userScrolledAt = Date.now(); scroller.scrollTop = Math.max(0, first.offsetTop - scroller.offsetTop - 12); }
      });
    }

    // Captions for video.
    if (player.tagName === 'VIDEO' && box.getAttribute('data-captions') === '1' && !player.querySelector('track')) {
      var src = box.getAttribute('data-caption-url');
      if (!src && cues.length && window.Blob && window.URL && URL.createObjectURL) {
        var vtt = 'WEBVTT\n\n' + cues.map(function (c, i) {
          var end = i + 1 < starts.length ? starts[i + 1] : starts[i] + 6;
          return vttTime(starts[i]) + ' --> ' + vttTime(Math.max(end, starts[i] + 0.5)) + '\n' + c.querySelector('.special-blocks-oh-text').textContent.trim();
        }).join('\n\n');
        src = URL.createObjectURL(new Blob([vtt], { type: 'text/vtt' }));
      }
      if (src) {
        var track = document.createElement('track');
        track.kind = 'captions'; track.label = 'Transcript'; track.srclang = document.documentElement.lang || 'en'; track.src = src;
        player.appendChild(track);
      }
    }
    mark(0, false);
    box.oralHistory = { seek: seek, current: function () { return current; } };
  }
  function init() { Array.prototype.forEach.call(document.querySelectorAll('.special-blocks-oh-synced'), setUp); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
