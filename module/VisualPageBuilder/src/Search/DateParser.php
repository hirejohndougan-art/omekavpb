<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Search;

/**
 * Turns how people write dates into a year range (plan section 2, "Date understanding").
 *
 *   "1910"              1910..1910
 *   "about 1910", "ca. 1910", "circa 1910"   1910 -/+ $window
 *   "1920s"             1920..1929
 *   "early 1930s"       1930..1934;  "mid" 1935..1937;  "late" 1936..1939
 *   "1912-1918", "1912–1918"   1912..1918
 *   "March 3, 1912"     1912..1912 (the year in the text)
 *   "[year]", ""        null (not dated)
 *
 * Pure: takes a string, returns ['start' => int, 'end' => int] or null.
 */
class DateParser
{
    public static function parse(string $text, int $window = 5): ?array
    {
        $t = strtolower(trim($text));
        // Brackets mark a date the cataloguer supplied; "[year]" means no date.
        $t = trim($t, "[]?. ");
        if ($t === '' || $t === 'year' || $t === 'undated' || $t === 'n.d.') {
            return null;
        }

        // Range: 1912-1918, 1912 – 1918, 1912 to 1918.
        if (preg_match('/^(\d{4})\s*(?:-|–|—|to)\s*(\d{4})$/u', $t, $m)) {
            return self::range((int) $m[1], (int) $m[2]);
        }

        // Decade: 1920s, early 1930s, late 1930s, mid 1930s.
        if (preg_match('/^(?:(early|mid|late)\s+)?(\d{3})0s$/', $t, $m)) {
            $start = (int) ($m[2] . '0');
            $part = $m[1] ?? '';
            return match ($part) {
                'early' => self::range($start, $start + 4),
                'mid' => self::range($start + 3, $start + 6),
                'late' => self::range($start + 6, $start + 9),
                default => self::range($start, $start + 9),
            };
        }

        // Approximate: about 1910, ca. 1910, c. 1910, circa 1910.
        if (preg_match('/^(?:about|abt\.?|ca\.?|c\.|circa)\s*(\d{4})$/', $t, $m)) {
            $y = (int) $m[1];
            return self::range($y - $window, $y + $window);
        }

        // A bare year, or a full date with the year at the end or in the middle.
        if (preg_match('/(?<!\d)(\d{4})(?!\d)/', $t, $m)) {
            $y = (int) $m[1];
            return self::range($y, $y);
        }

        return null;
    }

    private static function range(int $a, int $b): array
    {
        return ['start' => min($a, $b), 'end' => max($a, $b)];
    }
}
