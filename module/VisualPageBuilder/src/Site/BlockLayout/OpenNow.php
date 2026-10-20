<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use DateTime;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;

/**
 * "Open now until 4 pm" or "Closed now · opens Tuesday at 10 am".
 *
 * Hours come from the site's Visit info settings (lines like "Tuesday to
 * Saturday | 10 am to 4 pm", read by hoursFromVisitInfo()) and its closed
 * dates, or from hours typed into the block itself. The server works out the
 * status when the page is made, and special-blocks/js/open-now.js works it out
 * again in the visitor's browser every minute (in Omeka's time zone), so a
 * cached or long-open page stays right.
 *
 * Hours text per day: "10-4", "10 am - 4 pm", "10:00-16:00"; empty = closed.
 */
class OpenNow extends AbstractSchemaBlock
{
    /** On pages, and in the site's header and footer. */
    const PLACEMENT = 'both';

    const TEMPLATE = 'open-now';

    const DESCRIPTION = 'Tells visitors whether you\'re open right now, and when you open or close next, from your weekly hours and closed dates.'; // @translate

    const GOOD_FOR = 'Home, visit, and contact pages, and headers.'; // @translate

    const DAYS = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];

    public function getLabel()
    {
        return 'Open now'; // @translate
    }

    public function schema(): array
    {
        $fields = [[
            'key' => 'source', 'type' => Schema::TYPE_SELECT, 'group' => 'Hours', 'summary' => true, 'default' => 'site',
            'label' => 'Where the hours come from', // @translate
            'info' => 'A site\'s Visit info is under Sites › the site › Settings. Write its hours like "Tuesday to Saturday | 10 am to 4 pm".', // @translate
            'options' => ['site' => 'The site\'s Visit info', 'block' => 'Typed into this block'], // @translate
        ], [
            'key' => 'source_site', 'type' => Schema::TYPE_SITE, 'group' => 'Hours', 'label' => 'Which site', 'placeholder' => 'This site', // @translate
            'show_if' => ['source' => ['site']],
        ]];
        foreach (self::DAYS as $key => $day) {
            $fields[] = ['key' => 'hours_' . $key, 'type' => Schema::TYPE_TEXT, 'group' => 'Weekly hours', 'label' => $day, 'placeholder' => 'e.g. 10-4, or leave empty if closed', 'show_if' => ['source' => ['block']]];
        }
        $fields[] = [
            'key' => 'closures', 'type' => Schema::TYPE_LIST, 'group' => 'Closed dates', 'max' => 60, 'show_if' => ['source' => ['block']],
            'label' => 'Closed dates', // @translate
            'info' => 'Holidays and other days you\'re closed.', // @translate
            'fields' => [
                ['key' => 'date', 'type' => Schema::TYPE_DATE, 'label' => 'Date'], // @translate
                ['key' => 'note', 'type' => Schema::TYPE_TEXT, 'label' => 'Reason (optional)', 'placeholder' => 'e.g. Thanksgiving'], // @translate
            ],
        ];
        $fields[] = ['key' => 'show_week', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Display', 'default' => false, 'label' => 'Also list the week\'s hours'];  // @translate
        $fields[] = ['key' => 'align', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'left', 'label' => 'Alignment', 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right']]; // @translate
        return $fields;
    }

    /**
     * Minutes after midnight for an opening and closing time, from text like
     * "10-4", "10 am - 4 pm", "9:30am–5pm", "10:00-16:00". Times without am/pm
     * from 1 to 7 are read as afternoon closing times ("10-4" = 10 am to 4 pm).
     *
     * @return int[]|null [open, close], or null if it can't be read
     */
    public static function parseRange(string $text): ?array
    {
        $text = strtolower(trim($text));
        if ($text === '' || !preg_match('/^(\d{1,2})(?::(\d{2}))?\s*(am|pm|a\.m\.|p\.m\.)?\s*(?:-|–|—|to)\s*(\d{1,2})(?::(\d{2}))?\s*(am|pm|a\.m\.|p\.m\.)?$/', $text, $m)) {
            return null;
        }
        $toMinutes = function ($h, $min, $ampm, $isClose) {
            $h = (int) $h;
            $ampm = $ampm ? $ampm[0] : '';
            if ($ampm === 'p' && $h < 12) {
                $h += 12;
            } elseif ($ampm === 'a' && $h === 12) {
                $h = 0;
            } elseif (!$ampm && $isClose && $h >= 1 && $h <= 7) {
                $h += 12;
            }
            return $h * 60 + (int) $min;
        };
        $open = $toMinutes($m[1], $m[2] ?? 0, $m[3] ?? '', false);
        $close = $toMinutes($m[4], $m[5] ?? 0, $m[6] ?? '', true);
        return $close > $open && $close <= 24 * 60 ? [$open, $close] : null;
    }

    /**
     * Day keys (mon…sun) named by text like "Tuesday to Saturday",
     * "Tue-Sat", "Mon, Wed, Fri", "Weekends", "Weekdays", "Daily".
     */
    public static function parseDays(string $text): array
    {
        $keys = array_keys(self::DAYS);
        $text = strtolower(trim($text));
        if (preg_match('/^(daily|every day|everyday|7 days)/', $text)) {
            return $keys;
        }
        if (strpos($text, 'weekend') !== false) {
            return ['sat', 'sun'];
        }
        if (strpos($text, 'weekday') !== false) {
            return ['mon', 'tue', 'wed', 'thu', 'fri'];
        }
        $day = function ($word) use ($keys) {
            foreach ($keys as $k) {
                if (strpos($word, $k) === 0 || ($k === 'thu' && strpos($word, 'thur') === 0) || ($k === 'tue' && strpos($word, 'tues') === 0)) {
                    return $k;
                }
            }
            return null;
        };
        $out = [];
        foreach (preg_split('/\s*(?:,|&|\band\b)\s*/', $text) as $part) {
            if (preg_match('/^([a-z]+)\.?\s*(?:-|–|—|to|through|thru)\s*([a-z]+)/', $part, $m)) {
                $a = $day($m[1]);
                $b = $day($m[2]);
                if ($a && $b) {
                    for ($i = array_search($a, $keys), $n = 0; $n < 7; $i = ($i + 1) % 7, $n++) {
                        $out[] = $keys[$i];
                        if ($keys[$i] === $b) {
                            break;
                        }
                    }
                }
            } elseif (preg_match('/^([a-z]+)/', $part, $m) && ($k = $day($m[1]))) {
                $out[] = $k;
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * The block's per-day hours fields, filled from Visit info hours rows
     * ([['days' => 'Tuesday to Saturday', 'times' => '10 am to 4 pm'], …]).
     * Rows that can't be read are skipped; "Closed" rows leave days closed.
     */
    public static function hoursFromVisitInfo(array $rows): array
    {
        $out = [];
        foreach (array_keys(self::DAYS) as $k) {
            $out['hours_' . $k] = '';
        }
        foreach ($rows as $row) {
            $times = trim($row['times'] ?? '');
            if (self::parseRange($times) === null) {
                continue;
            }
            foreach (self::parseDays($row['days'] ?? '') as $k) {
                $out['hours_' . $k] = $times;
            }
        }
        return $out;
    }

    /** "4 pm", "9:30 am". */
    public static function formatTime(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        $suffix = $h >= 12 ? 'pm' : 'am';
        $h12 = $h % 12 ?: 12;
        return $h12 . ($m ? sprintf(':%02d', $m) : '') . ' ' . $suffix;
    }

    /**
     * Open or closed at $now, and when that changes.
     *
     * @return array ['open' => bool, 'until' => '4 pm' | null, 'next' => ['day' => 'Tuesday', 'time' => '10 am'] | null, 'note' => closed-date reason]
     */
    public static function status(array $data, DateTime $now): array
    {
        $closed = [];
        foreach ($data['closures'] ?? [] as $c) {
            if (!empty($c['date'])) {
                $closed[$c['date']] = $c['note'] ?? '';
            }
        }
        $keys = array_keys(self::DAYS);
        $minutes = (int) $now->format('G') * 60 + (int) $now->format('i');
        $todayKey = $keys[(int) $now->format('N') - 1];
        $today = isset($closed[$now->format('Y-m-d')]) ? null : self::parseRange($data['hours_' . $todayKey] ?? '');
        if ($today && $minutes >= $today[0] && $minutes < $today[1]) {
            return ['open' => true, 'until' => self::formatTime($today[1]), 'next' => null, 'note' => ''];
        }
        // Closed: find the next opening, today (later) or in the next 14 days.
        for ($i = 0; $i <= 14; $i++) {
            $day = (clone $now)->modify('+' . $i . ' days');
            if (isset($closed[$day->format('Y-m-d')])) {
                continue;
            }
            $range = self::parseRange($data['hours_' . $keys[(int) $day->format('N') - 1]] ?? '');
            if (!$range || ($i === 0 && $minutes >= $range[0])) {
                continue;
            }
            $label = $i === 0 ? 'today' : ($i === 1 ? 'tomorrow' : self::DAYS[$keys[(int) $day->format('N') - 1]]);
            return ['open' => false, 'until' => null, 'next' => ['day' => $label, 'time' => self::formatTime($range[0])], 'note' => $closed[$now->format('Y-m-d')] ?? ''];
        }
        return ['open' => false, 'until' => null, 'next' => null, 'note' => $closed[$now->format('Y-m-d')] ?? ''];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        if ($data['source'] === 'site') {
            $info = \VisualPageBuilder\Form\VisitInfoSettings::read($view, $data['source_site'] ?: $site->id());
            $fromSite = self::hoursFromVisitInfo($info['hours']);
            // Blocks made before 0.11.1 have their own hours; keep using them
            // if the site's Visit info has none that can be read.
            if (array_filter($fromSite) || !array_filter(array_intersect_key($data, $fromSite))) {
                $data = array_merge($data, $fromSite, ['closures' => $info['closures']]);
            }
        }
        $week = [];
        foreach (self::DAYS as $key => $day) {
            $range = self::parseRange($data['hours_' . $key]);
            $week[] = ['day' => $view->translate($day), 'hours' => $range ? self::formatTime($range[0]) . ' – ' . self::formatTime($range[1]) : $view->translate('Closed')];
        }
        $hasHours = (bool) array_filter(array_keys(self::DAYS), function ($k) use ($data) {
            return self::parseRange($data['hours_' . $k]) !== null;
        });
        // For open-now.js: each day's [open, close] minutes (Monday first),
        // closed dates, and the time zone, so the browser can keep it current.
        $ranges = [];
        foreach (array_keys(self::DAYS) as $key) {
            $ranges[] = self::parseRange($data['hours_' . $key]);
        }
        $closed = [];
        foreach ($data['closures'] as $c) {
            if (!empty($c['date'])) {
                $closed[$c['date']] = $c['note'] ?? '';
            }
        }
        $t = [$view, 'translate'];
        $clientData = [
            'week' => $ranges, 'closed' => (object) $closed, 'tz' => $this->zone()->getName(),
            'days' => array_map($t, array_values(self::DAYS)),
            'text' => ['open' => $t('Open now'), 'closed' => $t('Closed now'), 'until' => $t('until %s'), 'opens' => $t('opens %1$s at %2$s'), 'today' => $t('today'), 'tomorrow' => $t('tomorrow')],
        ];
        return ['status' => self::status($data, $this->now()), 'week' => $week, 'hasHours' => $hasHours, 'clientData' => $clientData];
    }
}
