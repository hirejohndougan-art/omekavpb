/* SPDX-License-Identifier: GPL-2.0-or-later */
/*
 * Special Blocks: Open now, kept current in the visitor's browser.
 *
 * The page arrives with the status worked out on the server. This works it
 * out again now and every minute, in the society's time zone (from Omeka's
 * settings), so a page that was cached, or left open, never shows the wrong
 * status. Same rules as OpenNow::status() in PHP.
 */
(function () {
  'use strict';

  /** Now in time zone tz: {date: 'YYYY-MM-DD', day: 0 (Mon)–6, minutes}. */
  function nowIn(tz, offsetDays) {
    var d = new Date(Date.now() + (offsetDays || 0) * 864e5);
    var parts = {};
    new Intl.DateTimeFormat('en-US', { timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit', weekday: 'short', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })
      .formatToParts(d).forEach(function (p) { parts[p.type] = p.value; });
    return {
      date: parts.year + '-' + parts.month + '-' + parts.day,
      day: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].indexOf(parts.weekday),
      minutes: parseInt(parts.hour, 10) * 60 + parseInt(parts.minute, 10)
    };
  }
  function time(min) {
    var h = Math.floor(min / 60), m = min % 60, h12 = h % 12 || 12;
    return h12 + (m ? ':' + (m < 10 ? '0' : '') + m : '') + ' ' + (h >= 12 ? 'pm' : 'am');
  }
  function fill(s, a, b) { return s.replace('%1$s', a).replace('%2$s', b).replace('%s', a); }

  function update(box, cfg) {
    var p = box.querySelector('.special-blocks-open-status');
    var now = nowIn(cfg.tz), t = cfg.text, closed = cfg.closed || {};
    var today = Object.prototype.hasOwnProperty.call(closed, now.date) ? null : cfg.week[now.day];
    var html;
    if (today && now.minutes >= today[0] && now.minutes < today[1]) {
      p.className = 'special-blocks-open-status is-open';
      html = '<strong></strong> · <span></span>';
      p.innerHTML = '<span class="special-blocks-open-dot" aria-hidden="true"></span>' + html;
      p.querySelector('strong').textContent = t.open;
      p.querySelector('span:last-child').textContent = fill(t.until, time(today[1]));
      return;
    }
    var next = null;
    for (var i = 0; i <= 14 && !next; i++) {
      var day = nowIn(cfg.tz, i);
      var range = Object.prototype.hasOwnProperty.call(closed, day.date) ? null : cfg.week[day.day];
      if (!range || (i === 0 && now.minutes >= range[0])) continue;
      next = { day: i === 0 ? t.today : i === 1 ? t.tomorrow : cfg.days[day.day], time: time(range[0]) };
    }
    p.className = 'special-blocks-open-status is-closed';
    p.innerHTML = '<span class="special-blocks-open-dot" aria-hidden="true"></span><strong></strong><span></span>';
    p.querySelector('strong').textContent = t.closed;
    var note = closed[now.date] ? ' (' + closed[now.date] + ')' : '';
    p.querySelector('span:last-child').textContent = note + (next ? ' · ' + fill(t.opens, next.day, next.time) : '');
  }

  function init() {
    if (!window.Intl || !Intl.DateTimeFormat.prototype.formatToParts) return;
    Array.prototype.forEach.call(document.querySelectorAll('.special-blocks-open-now[data-hours]'), function (box) {
      var cfg;
      try { cfg = JSON.parse(box.getAttribute('data-hours')); } catch (e) { return; }
      try { update(box, cfg); } catch (e) { return; } // Unknown time zone: keep the server's text.
      setInterval(function () { update(box, cfg); }, 60000);
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
