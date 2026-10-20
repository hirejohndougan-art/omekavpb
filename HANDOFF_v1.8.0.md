# Handoff: Visual Page Builder 1.8.0 (theme unchanged, 1.0.3) — synced oral history

From the forum/other-archives list (OHMS-style). John: "Build it."

- `OralHistory.php` now has its own template (`oral-history.phtml`; Document
  and transcription still uses `transcript.phtml`). New settings:
  transcript_source (field | caption file), follow, show_search, captions,
  topics (title, time, summary, keywords).
- Times: `seconds()` reads h:mm:ss / mm:ss with optional .ms or ,ms;
  `cuesFromText()` takes [hh:mm:ss], (mm:ss) or a bare time at a line start
  (text before the first stamp starts at 0; no stamps → [] → plain
  paragraphs as before); `cuesFromCaptions()` reads WebVTT and SRT (tags
  stripped). Caption files are read from `files/original/<filename>` (<3 MB);
  a .vtt's URL is also used as the video's caption track.
- Synced only when there are timed lines AND the recording is an audio/video
  file (YouTube media: no sync; lines show with their times).
- `js/oral-history.js`: highlight current line (aria-current), Follow along
  scrolls the transcript box only (pauses 4 s after the visitor scrolls it),
  time buttons/topics seek and play, search marks matches with <mark> built
  from text and counts them, captions from the lines via a Blob VTT.
- Omeka rejects .vtt/.srt uploads by default: docs tell John to allow them in
  Settings › Security (extensions vtt, srt; types text/vtt, text/plain,
  application/x-subrip). The test temporarily disables file validation.
- Tests: `special/oral-history-sync.test.js` (14), PHP unit +4. Full suite
  passes. Not verified in a real browser with real recordings.
