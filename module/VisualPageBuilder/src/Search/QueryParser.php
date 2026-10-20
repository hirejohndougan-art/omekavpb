<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Search;

/**
 * Reads what a visitor typed into the search box (plan section 2).
 *
 *   depots 1910s           -> terms [depot], years 1910..1919
 *   "oral history"         -> phrases ["oral history"]
 *   rail                   -> terms [rail], prefix on the last word (suggestions)
 *   rr                     -> terms [rr, railroad] (Synonyms)
 *
 * Years are pulled out first with DateParser, so "1910s" never reaches the word
 * index. Pure: takes plain strings and a Synonyms object.
 */
class QueryParser
{
    /** Words too common to search for. */
    private const STOPWORDS = ['a', 'an', 'and', 'the', 'of', 'in', 'on', 'at', 'to', 'for', 'or', 'by', 'with'];

    /**
     * @return array{phrases: string[], terms: string[], prefixLast: bool, years: ?array, raw: string}
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

        $prefixLast = (bool) preg_match('/\S$/u', $text) && !preg_match('/\s$/u', $raw) && $phrases === [];
        $words = preg_split('/[^\p{L}\p{N}&]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        $words = array_values(array_filter($words, fn ($w) => !in_array($w, self::STOPWORDS, true)));

        $terms = [];
        foreach ($words as $word) {
            $variants = $synonyms ? $synonyms->expand($word) : [$word];
            foreach ($variants as $v) {
                // A synonym may be several words ("rail road"); each becomes its own term.
                foreach (preg_split('/[^\p{L}\p{N}&]+/u', $v, -1, PREG_SPLIT_NO_EMPTY) as $part) {
                    $terms[] = Stemmer::stem($part);
                }
            }
        }

        return [
            'phrases' => $phrases,
            'terms' => array_values(array_unique($terms)),
            'prefixLast' => $prefixLast && $words !== [],
            'years' => $years,
            'raw' => $raw,
        ];
    }
}
