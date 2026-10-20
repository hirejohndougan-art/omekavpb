<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Import;

/**
 * Matches the column headings of a spreadsheet to Omeka properties (Phase 8, the import
 * wizard's "Match the columns" step). Pure: takes the headings and the properties.
 *
 * A heading is "Matched" when it is a property's term or its label, ignoring case,
 * spaces and punctuation. A heading close to one of them (a typo or a plural) is
 * "Please check", with the suggestion. Anything else is "Please check" with no suggestion.
 */
class ColumnMatch
{
    /**
     * @param string[] $headings  the spreadsheet's column headings, in order
     * @param array<string, string> $properties  term => label (e.g. 'dcterms:title' => 'Title')
     * @return list<array{heading: string, term: ?string, status: string, suggestion: ?string}>
     */
    public static function match(array $headings, array $properties): array
    {
        $names = [];
        foreach ($properties as $term => $label) {
            $names[$term] = self::key($term);
            $names[$term . '#label'] = self::key($label);
        }
        $out = [];
        foreach ($headings as $heading) {
            $key = self::key((string) $heading);
            $exact = null;
            $close = null;
            $closeDistance = PHP_INT_MAX;
            foreach ($names as $name => $value) {
                $term = str_replace('#label', '', $name);
                if ($key !== '' && $value === $key) {
                    $exact = $term;
                    break;
                }
                // Only close misspellings of a real name count as suggestions (up to two edits).
                $distance = $key === '' || $value === '' ? PHP_INT_MAX : levenshtein($key, $value);
                if ($distance <= 2 && $distance < $closeDistance) {
                    $close = $term;
                    $closeDistance = $distance;
                }
            }
            if ($exact !== null) {
                $out[] = ['heading' => (string) $heading, 'term' => $exact, 'status' => 'Matched', 'suggestion' => null];
            } else {
                $out[] = ['heading' => (string) $heading, 'term' => null, 'status' => 'Please check',
                    'suggestion' => $close !== null ? $properties[$close] ?? $close : null];
            }
        }
        return $out;
    }

    /** Lowercase letters and digits only: "Date (created)" and "date created" compare equal. */
    public static function key(string $text): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($text));
    }
}
