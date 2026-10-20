<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Search;

/**
 * Typo tolerance for "Did you mean" (plan section 2): "Dickenson" -> "Dickinson".
 *
 * Short words must match exactly; longer words may be off by one or two letters.
 * Pure: takes plain strings and a list of known words.
 */
class Typo
{
    /** How many letter changes a word of this length may be off by. */
    public static function allowedDistance(string $word): int
    {
        $len = mb_strlen($word);
        if ($len <= 4) {
            return 0;
        }
        return $len >= 8 ? 2 : 1;
    }

    /**
     * The closest known word within the allowed distance, or null.
     * Ties go to the more frequent word, then the shorter one.
     *
     * @param array<string,int> $vocabulary word => how often it occurs
     */
    public static function suggest(string $word, array $vocabulary): ?string
    {
        $needle = mb_strtolower($word);
        $max = self::allowedDistance($needle);
        if ($max === 0) {
            return null;
        }
        $best = null;
        $bestDist = $max + 1;
        $bestFreq = -1;
        foreach ($vocabulary as $candidate => $freq) {
            $lower = mb_strtolower((string) $candidate);
            if ($lower === $needle || abs(mb_strlen($lower) - mb_strlen($needle)) > $max) {
                continue;
            }
            $d = levenshtein($needle, $lower);
            if ($d > $max) {
                continue;
            }
            if ($d < $bestDist || ($d === $bestDist && ($freq > $bestFreq
                || ($freq === $bestFreq && mb_strlen($lower) < mb_strlen((string) $best))))) {
                $best = (string) $candidate;
                $bestDist = $d;
                $bestFreq = $freq;
            }
        }
        return $best;
    }
}
