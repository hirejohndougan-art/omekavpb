<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder;

/**
 * The Details questions shared by One item and Add an item: each question maps to a Dublin Core
 * term, and multi-value questions take one value per line.
 */
class ItemForm
{
    /** The Details fields: form name => dcterms term. */
    public const FIELDS = [
        'title' => 'dcterms:title',
        'description' => 'dcterms:description',
        'creator' => 'dcterms:creator',
        'date' => 'dcterms:date',
        'subject' => 'dcterms:subject',
        'place' => 'dcterms:spatial',
    ];

    /**
     * Omeka values for the posted Details. A blank title is left out, so the old title is kept.
     *
     * @return array<string, array> term => values, for an API update with isPartial
     */
    public static function detailsData(array $post, $api): array
    {
        $data = [];
        foreach (self::FIELDS as $field => $term) {
            $property = (int) $api->search('properties', ['term' => $term])->getContent()[0]->id();
            $data[$term] = [];
            foreach (preg_split('/\r\n|\r|\n/', (string) ($post[$field] ?? '')) as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $data[$term][] = ['type' => 'literal', 'property_id' => $property, '@value' => $line];
                }
            }
        }
        if (trim((string) ($post['title'] ?? '')) === '') {
            // A title is required to publish; keep the old one rather than save a blank.
            unset($data['dcterms:title']);
        }
        return $data;
    }
}
