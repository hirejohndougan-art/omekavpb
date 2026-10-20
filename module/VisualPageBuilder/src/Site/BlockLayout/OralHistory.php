<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\ItemRepresentation;
use VisualPageBuilder\Schema\Schema;

/**
 * An oral history: the item's audio or video player beside its transcript.
 *
 * When the transcript has times (typed stamps like [00:12:40], or a .vtt/.srt
 * caption file on the item), it syncs: the current line is highlighted and
 * scrolled to while playing, clicking a line or time jumps there, visitors
 * can search inside the interview, a topic index lists where subjects start,
 * and video gets captions (special-blocks/js/oral-history.js).
 */
class OralHistory extends AbstractTranscriptBlock
{
    const DESCRIPTION = 'Plays an interview recording from an item, with its transcript beside it. With times in the transcript, the text follows along, clicking a line jumps there, and visitors can search the interview and browse its topics.'; // @translate

    const GOOD_FOR = 'Oral history collections.'; // @translate

    public function getLabel()
    {
        return 'Oral history'; // @translate
    }

    const TEMPLATE = 'oral-history';

    public function schema(): array
    {
        return array_merge($this->commonFields('Which recording (1 = the first audio or video file)'), [ // @translate
            [
                'key' => 'transcript_source', 'type' => Schema::TYPE_SELECT, 'group' => 'Item', 'default' => 'field', 'label' => 'Transcript comes from', // @translate
                'options' => ['field' => 'The field above (times like [00:12:40] in the text sync it)', 'caption' => 'A caption file on the item (.vtt or .srt)'], // @translate
                'info' => 'With times in the transcript, the text follows the recording, and clicking a line jumps there. Without times it shows as before.', // @translate
            ],
            ['key' => 'follow', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Syncing', 'default' => true, 'label' => 'Highlight and scroll along while it plays'], // @translate
            ['key' => 'show_search', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Syncing', 'default' => true, 'label' => 'Search inside the interview'], // @translate
            ['key' => 'captions', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Syncing', 'default' => true, 'label' => 'Captions on video, from the transcript'], // @translate
            [
                'key' => 'topics', 'type' => Schema::TYPE_LIST, 'group' => 'Topic index', 'summary' => true, 'max' => 100,
                'label' => 'Topics (optional)', // @translate
                'info' => 'A table of contents for the interview: where each topic starts, with a title and, if you like, a summary and keywords.', // @translate
                'fields' => [
                    ['key' => 'title', 'type' => Schema::TYPE_TEXT, 'label' => 'Topic'], // @translate
                    ['key' => 'time', 'type' => Schema::TYPE_TEXT, 'label' => 'Starts at', 'placeholder' => 'e.g. 12:40 or 1:02:15'], // @translate
                    ['key' => 'summary', 'type' => Schema::TYPE_TEXTAREA, 'label' => 'Summary (optional)'], // @translate
                    ['key' => 'keywords', 'type' => Schema::TYPE_TEXT, 'label' => 'Keywords (optional)', 'placeholder' => 'e.g. farming, storm, depot'], // @translate
                ],
            ],
        ]);
    }

    /** "12:40", "1:02:15", "00:12:40.5", "12:40,250" → seconds, or null. */
    public static function seconds(string $t): ?float
    {
        if (!preg_match('/^\s*(?:(\d{1,2}):)?(\d{1,2}):(\d{2})(?:[.,](\d{1,3}))?\s*$/', $t, $m)) {
            return null;
        }
        return (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3] + (isset($m[4]) && $m[4] !== '' ? (float) ('0.' . $m[4]) : 0);
    }

    /**
     * Timed lines from a transcript with stamps like [00:12:40], (12:40), or
     * "12:40 " at the start of a line. Text before the first stamp starts at 0.
     * No stamps at all → [] (shown as plain paragraphs).
     *
     * @return array [['start' => float, 'text' => string], …]
     */
    public static function cuesFromText(string $text): array
    {
        $cues = [];
        $found = false;
        $cur = null;
        foreach (preg_split('/\R/', $text) as $line) {
            if (preg_match('/^\s*[\[(]?((?:\d{1,2}:)?\d{1,2}:\d{2}(?:[.,]\d{1,3})?)[\])]?\s*(.*)$/', $line, $m) && ($sec = self::seconds($m[1])) !== null) {
                $found = true;
                if ($cur && trim($cur['text']) !== '') {
                    $cues[] = $cur;
                }
                $cur = ['start' => $sec, 'text' => $m[2]];
            } elseif ($cur) {
                $cur['text'] .= ($cur['text'] === '' ? '' : "\n") . $line;
            } elseif (trim($line) !== '') {
                $cur = ['start' => 0.0, 'text' => $line];
            }
        }
        if ($cur && trim($cur['text']) !== '') {
            $cues[] = $cur;
        }
        foreach ($cues as &$c) {
            $c['text'] = trim(preg_replace('/\n{3,}/', "\n\n", $c['text']));
        }
        return $found ? $cues : [];
    }

    /** Timed lines from a WebVTT or SRT caption file's text. */
    public static function cuesFromCaptions(string $text): array
    {
        $cues = [];
        $text = str_replace("\r", '', preg_replace('/^\xEF\xBB\xBF/', '', $text));
        foreach (preg_split('/\n\s*\n/', $text) as $block) {
            $lines = explode("\n", trim($block));
            foreach ($lines as $i => $line) {
                if (preg_match('/^((?:\d{1,2}:)?\d{1,2}:\d{2}[.,]\d{1,3})\s*-->/', $line, $m)) {
                    $body = trim(strip_tags(implode("\n", array_slice($lines, $i + 1))));
                    $sec = self::seconds($m[1]);
                    if ($body !== '' && $sec !== null) {
                        $cues[] = ['start' => $sec, 'text' => html_entity_decode($body, ENT_QUOTES)];
                    }
                    break;
                }
            }
        }
        return $cues;
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $vars = parent::viewVars($view, $data, $site, $blockKey);
        $vars += ['cues' => [], 'topics' => [], 'captionUrl' => null, 'canSync' => false, 'ohId' => 'vpb-oh-' . preg_replace('/[^a-z0-9]/i', '', $blockKey)];
        if (!$vars['item']) {
            return $vars;
        }
        $media = $vars['media'];
        $vars['canSync'] = $media && (strpos((string) $media->mediaType(), 'audio/') === 0 || strpos((string) $media->mediaType(), 'video/') === 0);
        if ($data['transcript_source'] === 'caption') {
            foreach ($vars['item']->media() as $m) {
                $name = strtolower((string) ($m->source() ?: $m->filename()));
                if (!preg_match('/\.(vtt|srt)$/', $name) || !$m->hasOriginal()) {
                    continue;
                }
                $path = (defined('OMEKA_PATH') ? OMEKA_PATH : '') . '/files/original/' . $m->filename();
                if (is_file($path) && filesize($path) < 3 * 1024 * 1024) {
                    $vars['cues'] = self::cuesFromCaptions((string) file_get_contents($path));
                    if (substr($name, -4) === '.vtt') {
                        $vars['captionUrl'] = $m->originalUrl();
                    }
                }
                break;
            }
        } else {
            $vars['cues'] = self::cuesFromText($vars['transcript']);
        }
        foreach ($data['topics'] as $t) {
            $sec = self::seconds((string) $t['time']);
            if ($sec === null || $t['title'] === '') {
                continue;
            }
            $vars['topics'][] = ['start' => $sec, 'title' => $t['title'], 'summary' => $t['summary'], 'keywords' => $t['keywords']];
        }
        usort($vars['topics'], function ($a, $b) {
            return $a['start'] <=> $b['start'];
        });
        return $vars;
    }

    /** 75.5 → "1:15"; 3725 → "1:02:05". */
    public static function clock(float $sec): string
    {
        $s = (int) floor($sec);
        return $s >= 3600 ? sprintf('%d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60) : sprintf('%d:%02d', intdiv($s, 60), $s % 60);
    }

    protected function pickMedia(ItemRepresentation $item, array $data)
    {
        $recordings = array_values(array_filter($item->media(), function ($m) {
            $type = (string) $m->mediaType();
            return strpos($type, 'audio/') === 0 || strpos($type, 'video/') === 0 || in_array($m->ingester(), ['youtube', 'oembed'], true);
        }));
        return $recordings[$data['media_number'] - 1] ?? ($recordings[0] ?? null);
    }
}
