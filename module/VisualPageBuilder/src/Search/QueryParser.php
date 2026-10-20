<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Search;

/**
 * Reads what a visitor typed into the search box (plan section 2).
 *
 *   depots 1910s           -> groups [[[depot]]], years 1910..1919
 *   "oral history"         -> phrases ["oral history"]
 *   rail                   -> prefix on the last word (suggestions while typing)
 *   rr                     -> one word with two variants: [rr] OR [railroad]
 *
 * Each word is one group. A group matches when any of its variants matches
 * (synonyms are OR-ed); a variant is a list of stems that must all match
 * (a multi-word synonym such as "rail road"). Groups are AND-ed together.
 * Years are pulled out first, so "1910s" never reaches the word index.
 * Pure: takes plain strings and an optional Synonyms object.
 */
class QueryParser
{
    /** Words too common to search for. */
    public const STOPWORDS = ['a', 'an', 'and', 'the', 'of', 'in', 'on', 'at', 'to', 'for', 'or', 'by', 'with'];

    /**
     * @return array{phrases: string[], terms: string[], groups: array, prefixLast: bool, years: ?array, words: string[], raw: string}
     */
    public static function parse(string $raw, ?Synonyms $synonyms = null): array
    {
        $years = null;
        $phrases = [];
        $text = $raw;

        // Quoted phrases, taken out first.
        if (preg_match_all('/"([^"]+)"/u', $text, $m)) {
            foreach ($m[1] as $p) {
                $phrases[] = mb_strtolower(trim($p));
            }
            $text = preg_replace('/"[^"]*"/u', ' ', $text);
        }

        // Year phrases: "1910", "1920s", "early 1930s", "about 1910", "1912-1918".
        $yearPattern = '/(?:\b(?:early|mid|late|about|abt|ca|c|circa)\.?\s+)?'
            . '\b\d{4}s?\b(?:\s*[-–—]\s*\d{4}\b)?/iu';
        if (preg_match($yearPattern, $text, $m)) {
            $years = DateParser::parse($m[0]);
            $text = preg_replace($yearPattern, ' ', $text, 1);
        }

        // A trailing letter with no space after it is still being typed: it is a prefix.
        $prefixLast = $phrases === [] && (bool) preg_match('/\S$/u', $text) && !preg_match('/\s$/u', $raw);
        $words = preg_split('/[^\p{L}\p{N}&]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        $words = array_values(array_filter($words, fn ($w) => !in_array($w, self::STOPWORDS, true)));

        $groups = [];
        $terms = [];
        foreach ($words as $i => $word) {
            $variants = $synonyms ? $synonyms->expand($word) : [$word];
            $group = [];
            foreach ($variants as $v) {
                // A synonym may be several words ("rail road"): all of them must match.
                $stems = [];
                foreach (preg_split('/[^\p{L}\p{N}&]+/u', $v, -1, PREG_SPLIT_NO_EMPTY) as $part) {
                    $stems[] = Stemmer::stem($part);
                }
                if ($stems && !in_array($stems, $group, true)) {
                    $group[] = $stems;
                    array_push($terms, ...$stems);
                }
            }
            $groups[] = [
                'word' => $word,
                'variants' => $group,
                'prefix' => $prefixLast && $i === count($words) - 1,
            ];
        }

        return [
            'phrases' => $phrases,
            'terms' => array_values(array_unique($terms)),
            'groups' => $groups,
            'prefixLast' => $prefixLast && $words !== [],
            'years' => $years,
            'words' => $words,
            'raw' => $raw,
        ];
    }
}
