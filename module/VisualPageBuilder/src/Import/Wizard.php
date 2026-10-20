<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Import;

use VisualPageBuilder\ItemForm;

/**
 * The spreadsheet import wizard's rules (Phase 8), kept apart from the controller so they can be
 * tested on their own: matching columns to fields, reading a preview, and the arguments for
 * CSV Import's own job.
 */
class Wizard
{
    /** The catalog number field: it is used to tell which rows are already in the collection. */
    public const CATALOG = 'catalog';

    /** Plain words for each field, as people write them in a spreadsheet header. */
    public const SYNONYMS = [
        'title' => ['title', 'name', 'item title'],
        'description' => ['description', 'desc', 'summary', 'notes'],
        'creator' => ['creator', 'creators', 'author', 'photographer', 'maker'],
        'date' => ['date', 'year', 'dates'],
        'subject' => ['subject', 'subjects', 'topic', 'topics', 'keywords'],
        'place' => ['place', 'places', 'location', 'spatial', 'place name'],
        self::CATALOG => ['catalog number', 'catalog', 'catalogue number', 'identifier', 'id', 'object id', 'accession number'],
    ];

    /** Every field a column can be sent to, with the Omeka term it fills. */
    public static function fields(): array
    {
        return ItemForm::FIELDS + [self::CATALOG => 'dcterms:identifier'];
    }

    /** Which field a column header names, or null. Headers may also be Omeka terms such as dcterms:title. */
    public static function guessField(string $header): ?string
    {
        $text = strtolower(trim(preg_replace('/\s+/', ' ', $header)));
        foreach (self::fields() as $field => $term) {
            if ($text === $term || $text === strtolower($term)) {
                return $field;
            }
        }
        foreach (self::SYNONYMS as $field => $words) {
            if (in_array($text, $words, true)) {
                return $field;
            }
        }
        return null;
    }

    /**
     * For each column: the field it is matched to (or null) and whether that is certain.
     * "Matched" when the header names a field; "Please check" when it does not.
     *
     * @return array<int, array{header: string, field: ?string, status: string}>
     */
    public static function matchColumns(array $headers): array
    {
        $out = [];
        foreach (array_values($headers) as $index => $header) {
            $field = self::guessField((string) $header);
            $out[$index] = [
                'header' => (string) $header,
                'field' => $field,
                'status' => $field === null ? 'Please check' : 'Matched',
            ];
        }
        return $out;
    }

    /** The delimiter for a file type: tab for TSV, comma otherwise. Null for unsupported types. */
    public static function delimiter(string $filename): ?string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($extension === 'csv') {
            return ',';
        }
        if ($extension === 'tsv' || $extension === 'tab') {
            return "\t";
        }
        return null;
    }

    /** The MIME type CSV Import expects for a delimiter. */
    public static function mediaType(string $delimiter): string
    {
        return $delimiter === "\t" ? 'text/tab-separated-values' : 'text/csv';
    }

    /**
     * Headers and the first rows of a CSV or TSV file, for the preview.
     *
     * @return array{headers: string[], rows: array<int, string[]>, count: int}
     */
    public static function readPreview(string $path, string $delimiter, int $rows = 5): array
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            return ['headers' => [], 'rows' => [], 'count' => 0];
        }
        $headers = fgetcsv($handle, 0, $delimiter, '"', '\\');
        $headers = is_array($headers) ? array_map(fn ($h) => trim((string) $h, "\xEF\xBB\xBF \t"), $headers) : [];
        $preview = [];
        $count = 0;
        while (($line = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            if ($line === [null]) {
                continue;
            }
            $count++;
            if (count($preview) < $rows) {
                $preview[] = $line;
            }
        }
        fclose($handle);
        return ['headers' => $headers, 'rows' => $preview, 'count' => $count];
    }

    /**
     * What a plan says to do with rows whose catalog number is already in Omeka.
     * "add": every row is a new item. "update": matching items are updated, the rest are added.
     * "update_only": matching items are updated, the rest are skipped.
     */
    public const DUPLICATE_CHOICES = ['add', 'update', 'update_only'];

    /**
     * Arguments for CSV Import's Import job, built from the wizard's plan.
     *
     * @param array $plan  filepath, delimiter, mediaType, columns (index => field), duplicate, public, itemSet (int|null)
     * @param array $propertyIds  property id by Omeka term, e.g. ['dcterms:title' => 1]
     */
    public static function jobArgs(array $plan, array $propertyIds): array
    {
        $args = [
            'filepath' => $plan['filepath'],
            'resource_type' => 'items',
            'media_type' => $plan['mediaType'],
            'delimiter' => $plan['delimiter'],
            'enclosure' => '"',
            'escape' => '\\',
            'comment' => '',
            'multivalue_separator' => '|',
            'rows_by_batch' => 100,
            'column-property' => [],
            'column-data-type' => [],
            'column-multivalue' => [],
        ];
        $identifierColumn = null;
        foreach ($plan['columns'] as $index => $field) {
            if (!$field || empty($propertyIds[self::fields()[$field]])) {
                continue;
            }
            $term = self::fields()[$field];
            // CSV Import reads each column's property as term => id, and its data type as a plain word.
            $args['column-property'][$index] = [$term => (int) $propertyIds[$term]];
            $args['column-data-type'][$index] = 'literal';
            if (in_array($field, ['creator', 'subject', 'place'], true)) {
                $args['column-multivalue'][$index] = '1';
            }
            if ($field === self::CATALOG) {
                $identifierColumn = $index;
            }
        }
        $duplicate = in_array($plan['duplicate'], self::DUPLICATE_CHOICES, true) ? $plan['duplicate'] : 'add';
        if ($duplicate === 'add' || $identifierColumn === null) {
            $args['action'] = 'create';
            // The item set and visibility are applied to new items only. On an update, CSV Import would
            // replace the sets and visibility of the matched items, so those are left alone.
            $args['o:is_public'] = $plan['public'] ? '1' : '0';
            if (!empty($plan['itemSet'])) {
                $args['o:item_set'] = [(int) $plan['itemSet']];
            }
        } else {
            $args['action'] = 'update';
            $args['action_unidentified'] = $duplicate === 'update' ? 'create' : 'skip';
            // CSV Import matches rows on the column named here (the catalog number) and on this property.
            $args['identifier_column'] = (int) $identifierColumn;
            $args['identifier_property'] = (string) ($propertyIds['dcterms:identifier'] ?? '');
        }
        return $args;
    }
}
