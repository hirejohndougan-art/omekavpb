<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use DateTime;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Stdlib\ItemSource;

/**
 * Items dated to today's day (or month) in past years.
 *
 * Matches date values written as YYYY-MM-DD (or starting that way, like
 * 1911-06-14T10:00). Undated items and approximate dates ("circa 1920s")
 * are skipped. Dates use the time zone set in Omeka's settings.
 */
class OnThisDay extends AbstractItemBlock
{
    const DESCRIPTION = 'Shows items dated to today\'s date in past years. Item dates need to be written like 1911-06-14.'; // @translate

    const GOOD_FOR = 'History anniversaries that change every day.'; // @translate

    public function getLabel()
    {
        return 'On this day'; // @translate
    }

    public function schema(): array
    {
        return array_merge(
            ItemSource::fields(),
            [
                [
                    'key' => 'date_property', 'type' => Schema::TYPE_TEXT, 'group' => 'Source', 'default' => 'dcterms:date',
                    'label' => 'Date field', // @translate
                    'info' => 'The property holding each item\'s date, as prefix:name. Dates must be written YYYY-MM-DD.', // @translate
                ],
                [
                    'key' => 'match', 'type' => Schema::TYPE_SELECT, 'group' => 'Source', 'summary' => true, 'default' => 'day',
                    'label' => 'Match', // @translate
                    'options' => ['day' => 'Same day and month', 'month' => 'Same month'], // @translate
                ],
                [
                    'key' => 'order', 'type' => Schema::TYPE_SELECT, 'group' => 'Source', 'default' => 'random',
                    'label' => 'Order', // @translate
                    'options' => ['random' => 'Random', 'oldest' => 'Oldest first', 'newest' => 'Newest first'], // @translate
                ],
                DisplayFields::heading('On this day in history'),
            ],
            DisplayFields::grid(4),
            [[
                'key' => 'empty_text', 'type' => Schema::TYPE_TEXT, 'group' => 'Display',
                'label' => 'When nothing matches today', // @translate
                'info' => 'Leave empty to hide the block on those days.', // @translate
            ]],
            DisplayFields::link()
        );
    }

    /** The text every matching date contains, e.g. "-06-14" or "-06-". */
    public static function needle(string $match, ?DateTime $today = null): string
    {
        $today = $today ?: new DateTime('now');
        return $match === 'month' ? $today->format('-m-') : $today->format('-m-d');
    }

    protected function findItems(array $data, SiteRepresentation $site): array
    {
        $property = preg_match('/^[a-z0-9_-]+:[A-Za-z0-9_-]+$/i', $data['date_property']) ? $data['date_property'] : 'dcterms:date';
        $sort = $data['order'] === 'random'
            ? ['sort_by' => 'random']
            : ['sort_by' => $property, 'sort_order' => $data['order'] === 'oldest' ? 'asc' : 'desc'];
        return $this->itemSource->items($data, $site, $sort + [
            'limit' => $data['count'],
            'property' => [['joiner' => 'and', 'property' => $property, 'type' => 'in', 'text' => self::needle($data['match'], $this->now())]],
        ]);
    }
}
