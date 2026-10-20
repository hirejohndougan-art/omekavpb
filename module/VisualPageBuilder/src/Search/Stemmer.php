<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Search;

/**
 * A light English stemmer for VPB Search (plan section 2: "depots" finds "depot").
 *
 * It strips the common plural and verb endings. It is not full Porter; the rule
 * that matters is that the index and the query use the same function, so both
 * sides agree on the stem. Pure: takes and returns plain strings.
 */
class Stemmer
{
    public static function stem(string $word): string
    {
        $w = mb_strtolower($word);
        $len = mb_strlen($w);
        if ($len <= 3 || !preg_match('/^[a-z0-9]+$/', $w)) {
            return $w;
        }

        // Plurals.
        if (substr($w, -4) === 'sses') {
            $w = substr($w, 0, -2);
        } elseif (substr($w, -3) === 'ies' && strlen($w) > 4) {
            $w = substr($w, 0, -3) . 'y';
        } elseif (substr($w, -1) === 's' && !in_array(substr($w, -2, 1), ['s', 'u', 'i'], true)
            && strlen($w) > 3) {
            $w = substr($w, 0, -1);
        }

        // Past tense and -ing, only when a vowel remains in the stem.
        foreach (['ing', 'ed'] as $suffix) {
            if (substr($w, -strlen($suffix)) === $suffix) {
                $stem = substr($w, 0, -strlen($suffix));
                if (strlen($stem) >= 3 && preg_match('/[aeiouy]/', $stem)) {
                    $w = $stem;
                    // "chatted" -> "chatt" -> "chat": drop a doubled final consonant.
                    if (preg_match('/([^aeiouylsz])\1$/', $w)) {
                        $w = substr($w, 0, -1);
                    }
                }
                break;
            }
        }

        // -ly adverbs ("officially" -> "official").
        if (substr($w, -2) === 'ly' && strlen($w) > 5) {
            $w = substr($w, 0, -2);
        }

        return $w;
    }
}
