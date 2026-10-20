<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Search;

/**
 * The Filters block's settings, as plain rules: which groups show, how many text
 * values list before "Show all", and whether text values are in count or A–Z order.
 * Kept apart from the block so the rules can be checked without Omeka.
 */
class FacetSettings
{
    /** Filter field => the block setting that shows it. */
    const SHOW = [
        'item_set' => 'show_collection',
        'class' => 'show_type',
        'subject' => 'show_subject',
        'creator' => 'show_creator',
        'place' => 'show_place',
        'decade' => 'show_date',
        'has_images' => 'show_images',
        'has_description' => 'show_description',
        'has_transcript' => 'show_transcript',
    ];

    /** Text values listed before "Show all" when the setting is not given. */
    const TEXT_SHOWN = 10;

    /** Most items first (the default) or A to Z. */
    const ORDERS = ['count', 'az'];

    /** Whether a group is shown. A group with no setting in the data is shown. */
    public static function shown(string $field, array $data): bool
    {
        $key = self::SHOW[$field] ?? null;
        return $key === null || !array_key_exists($key, $data) || !empty($data[$key]);
    }

    /** Text values listed before "Show all", between 1 and 100. */
    public static function textShown(array $data): int
    {
        $n = (int) ($data['text_shown'] ?? self::TEXT_SHOWN);
        return max(1, min(100, $n));
    }

    /** Text values in the chosen order. Count order (the search's own order) is kept as it comes. */
    public static function ordered(array $options, array $data): array
    {
        if (($data['text_order'] ?? 'count') !== 'az') {
            return $options;
        }
        usort($options, fn ($a, $b) => strcasecmp((string) $a['label'], (string) $b['label']));
        return $options;
    }
}
