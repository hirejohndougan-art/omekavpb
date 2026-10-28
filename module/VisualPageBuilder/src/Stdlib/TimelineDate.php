<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Stdlib;

/**
 * Reads dates the way people write them, for the Story timeline block
 * (StoryTimeline), and places them on a time line.
 *
 *   "1900-09-08", "1900-09", "1900"             ISO, to day, month, or year
 *   "September 8, 1900", "8 Sept 1900"          month names (full or short)
 *   "Sept. 1900", "June 1911"                   month and year
 *   "9/8/1900"                                  month/day/year (US order)
 *   "circa 1920", "c. 1920", "ca. 1920", "about 1920"   approximate year
 *   "1920s", "early 1930s", "late 1930s"        a decade (a span)
 *   "1912-1918", "1912 to 1918"                 a span of years
 *   "500 BC", "500 BCE", "-500", "AD 79"        years before the common era
 *   "[year]", "n.d.", "undated", ""             no date (null)
 *
 * parse() returns null or:
 *   y, m, d     year (negative before the common era), month 1-12 or 0, day or 0
 *   precision   'day', 'month', 'year', or 'decade'
 *   approx      true for circa/about
 *   end         null, or the same shape (without end) for a span written in one text
 *   pos         the start as a decimal year (1900.68), for placing on the line
 *
 * Pure: no Omeka dependencies, so tests/special/php can check it directly.
 */
class TimelineDate
{
    const MONTHS = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
    ];

    const MONTH_NAMES = [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    public static function parse(string $text): ?array
    {
        $t = strtolower(trim($text));
        $t = trim($t, "[]?. \t");
        $t = preg_replace('/\s+/', ' ', $t);
        if ($t === '' || in_array($t, ['year', 'undated', 'n.d', 'nd', 'unknown'], true)) {
            return null;
        }

        // A span of years: 1912-1918, 1912 – 1918, 1912 to 1918.
        if (preg_match('/^(\d{3,4})\s*(?:-|–|—|to|until)\s*(\d{3,4})$/u', $t, $m) && (int) $m[2] >= (int) $m[1]) {
            $start = self::point((int) $m[1], 0, 0, 'year');
            $start['end'] = self::point((int) $m[2], 0, 0, 'year');
            return $start;
        }

        // A decade: 1920s, early 1930s, mid 1930s, late 1930s.
        if (preg_match('/^(?:(early|mid|late)[ -])?(\d{3})0\'?s$/', $t, $m)) {
            $base = (int) ($m[2] . '0');
            [$from, $to] = match ($m[1]) {
                'early' => [$base, $base + 3],
                'mid' => [$base + 3, $base + 6],
                'late' => [$base + 6, $base + 9],
                default => [$base, $base + 9],
            };
            $start = self::point($from, 0, 0, 'decade');
            $start['end'] = self::point($to, 0, 0, 'year');
            $start['decade'] = $m[1] ? $m[1] . ' ' . $base . 's' : $base . 's';
            return $start;
        }

        // Approximate: circa, c., ca., about, abt.
        $approx = false;
        if (preg_match('/^(?:circa|ca\.?|c\.|about|abt\.?|approx\.?|around)\s*(.+)$/', $t, $m)) {
            $approx = true;
            $t = trim($m[1]);
        }

        // Before or in the common era: 500 BC, 500 BCE, AD 79, 79 CE.
        $sign = 1;
        if (preg_match('/^(\d{1,4})\s*(b\.?c\.?e?\.?)$/', $t, $m)) {
            return self::mark(self::point(-(int) $m[1], 0, 0, 'year'), $approx);
        }
        if (preg_match('/^(?:a\.?d\.?\s*(\d{1,4})|(\d{1,4})\s*c\.?e\.?)$/', $t, $m)) {
            return self::mark(self::point((int) ($m[1] !== '' ? $m[1] : $m[2]), 0, 0, 'year'), $approx);
        }
        if (preg_match('/^-(\d{1,4})$/', $t, $m)) {
            return self::mark(self::point(-(int) $m[1], 0, 0, 'year'), $approx);
        }
        // A plain year with fewer than four digits ("79", "800").
        if (preg_match('/^\d{1,3}$/', $t)) {
            return self::mark(self::point((int) $t, 0, 0, 'year'), $approx);
        }

        // ISO: 1900-09-08, 1900-09, 1900.
        if (preg_match('/^(\d{4})(?:-(\d{1,2}))?(?:-(\d{1,2}))?(?:[t ].*)?$/', $t, $m)) {
            $y = (int) $m[1];
            $mo = isset($m[2]) ? (int) $m[2] : 0;
            $d = isset($m[3]) ? (int) $m[3] : 0;
            return self::mark(self::checked($y, $mo, $d), $approx);
        }

        // US order: 9/8/1900, 9-8-1900.
        if (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/', $t, $m)) {
            return self::mark(self::checked((int) $m[3], (int) $m[1], (int) $m[2]), $approx);
        }

        // Month names: September 8, 1900 / 8 September 1900 / Sept. 1900.
        $mon = '(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\.?';
        if (preg_match("/^$mon\\s+(\\d{1,2})(?:st|nd|rd|th)?,?\\s+(\\d{3,4})$/", $t, $m)) {
            return self::mark(self::checked((int) $m[3], self::MONTHS[$m[1]], (int) $m[2]), $approx);
        }
        if (preg_match("/^(\\d{1,2})(?:st|nd|rd|th)?\\s+(?:of\\s+)?$mon,?\\s+(\\d{3,4})$/", $t, $m)) {
            return self::mark(self::checked((int) $m[3], self::MONTHS[$m[2]], (int) $m[1]), $approx);
        }
        if (preg_match("/^$mon,?\\s+(\\d{3,4})$/", $t, $m)) {
            return self::mark(self::checked((int) $m[2], self::MONTHS[$m[1]], 0), $approx);
        }

        // Anything else with a four-digit year in it ("Spring 1925", "before 1890").
        // Marked "loose": show the text as written, since label() can't say it better.
        if (preg_match('/(?<!\d)(\d{4})(?!\d)/', $t, $m)) {
            $p = self::mark(self::point((int) $m[1], 0, 0, 'year'), true);
            $p['loose'] = true;
            return $p;
        }
        return null;
    }

    /**
     * The date as people read it: "September 8, 1900", "June 1911", "1911",
     * "c. 1920", "1920s", "500 BC". A span gets " – " and its end.
     */
    public static function label(array $p): string
    {
        if ($p['precision'] === 'decade') {
            return $p['decade'];
        }
        $text = self::one($p);
        if (!empty($p['end'])) {
            $text .= ' – ' . self::one($p['end']);
        }
        return $text;
    }

    /** One date without its span. */
    protected static function one(array $p): string
    {
        $year = $p['y'] < 0 ? abs($p['y']) . ' BC' : (string) $p['y'];
        $text = match ($p['precision']) {
            'day' => self::MONTH_NAMES[$p['m']] . ' ' . $p['d'] . ', ' . $year,
            'month' => self::MONTH_NAMES[$p['m']] . ' ' . $year,
            default => $year,
        };
        return !empty($p['approx']) ? 'c. ' . $text : $text;
    }

    /**
     * The end of a date as a decimal year: the last moment it covers. "1911"
     * ends at 1912.0, "June 1911" at 1911.5, so a span drawn from a start to
     * this end covers the whole of its last year, month, or day.
     */
    public static function endPos(array $p): float
    {
        return match ($p['precision']) {
            'day' => $p['pos'] + 1 / 365,
            'month' => $p['pos'] + 1 / 12,
            default => $p['pos'] + 1,
        };
    }

    /** A sortable key: earlier dates sort first, including BC. */
    public static function sortKey(array $p): string
    {
        return sprintf('%012.5f', $p['pos'] + 100000);
    }

    protected static function checked(int $y, int $m, int $d): ?array
    {
        if ($m < 0 || $m > 12) {
            return null;
        }
        if ($m === 0) {
            return self::point($y, 0, 0, 'year');
        }
        if ($d === 0) {
            return self::point($y, $m, 0, 'month');
        }
        if ($d < 1 || $d > 31 || ($y > 0 && !checkdate($m, $d, $y))) {
            return null;
        }
        return self::point($y, $m, $d, 'day');
    }

    protected static function point(int $y, int $m, int $d, string $precision): array
    {
        $pos = $y + ($m ? ($m - 1) / 12 : 0) + ($d ? ($d - 1) / 365 : 0);
        return ['y' => $y, 'm' => $m, 'd' => $d, 'precision' => $precision, 'approx' => false, 'end' => null, 'pos' => round($pos, 5)];
    }

    protected static function mark(?array $p, bool $approx): ?array
    {
        if ($p) {
            $p['approx'] = $approx;
        }
        return $p;
    }
}
