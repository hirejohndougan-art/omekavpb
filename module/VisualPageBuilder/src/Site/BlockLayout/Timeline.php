<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Stdlib\ItemSource;

/**
 * A timeline of items in date order, read from each item's date (Dublin Core
 * Date by default), with events you type added in. Horizontal (scrolls
 * sideways, with arrow buttons; special-blocks/js/timeline.js) or vertical.
 *
 * Dates are read loosely: the first four-digit year, plus month and day when
 * written YYYY-MM-DD ("1911-06-14", "June 1911", "circa 1920", "1920s" all
 * work). Items with no year are left out.
 */
class Timeline extends AbstractSchemaBlock
{
    const TEMPLATE = 'timeline';

    const DESCRIPTION = 'Puts items from the archive in date order on a timeline, with a photo and link for each. You can add your own events too.'; // @translate

    const GOOD_FOR = 'History pages, exhibits, and "through the years" features.'; // @translate

    public function getLabel()
    {
        return 'Timeline'; // @translate
    }

    public function schema(): array
    {
        return array_merge(ItemSource::fields(true, false), [
            ['key' => 'use_items', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Source', 'default' => true, 'label' => 'Also add dated items automatically'], // @translate
            ['key' => 'date_property', 'type' => Schema::TYPE_TEXT, 'group' => 'Source', 'default' => 'dcterms:date', 'label' => 'Date field', 'info' => 'The property holding each item\'s date, as prefix:name.'], // @translate
            ['key' => 'count', 'type' => Schema::TYPE_NUMBER, 'group' => 'Source', 'default' => 40, 'min' => 1, 'max' => 200, 'label' => 'Most items'], // @translate
            [
                'key' => 'events', 'type' => Schema::TYPE_LIST, 'group' => 'Entries', 'summary' => true, 'max' => 100,
                'label' => 'Entries', // @translate
                'info' => 'Each entry: a date, a title, an item from the archive if you like, a picture, and details. Linking an item fills in an empty title and date.', // @translate
                'fields' => [
                    ['key' => 'title', 'type' => Schema::TYPE_TEXT, 'label' => 'Title', 'placeholder' => 'What happened'], // @translate
                    ['key' => 'date', 'type' => Schema::TYPE_TEXT, 'label' => 'Date', 'placeholder' => 'e.g. 1900-09-08, June 1911, or circa 1920'], // @translate
                    ['key' => 'item_id', 'type' => Schema::TYPE_ITEM, 'label' => 'Link an item from the archive (optional)'], // @translate
                    [
                        'key' => 'img', 'type' => Schema::TYPE_SELECT, 'default' => 'item', 'label' => 'Picture', // @translate
                        'options' => ['none' => 'No picture', 'item' => 'The item\'s picture', 'asset' => 'Choose an image'], // @translate
                    ],
                    ['key' => 'asset_id', 'type' => Schema::TYPE_ASSET, 'label' => 'Image', 'show_if' => ['img' => ['asset']]], // @translate
                    ['key' => 'text', 'type' => Schema::TYPE_TEXTAREA, 'label' => 'Details (optional)'], // @translate
                ],
            ],
            DisplayFields::heading(),
            ['key' => 'layout', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'horizontal', 'label' => 'Layout', 'options' => ['horizontal' => 'Across (scrolls sideways)', 'vertical' => 'Down the page']], // @translate
            ['key' => 'order', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'asc', 'label' => 'Order', 'options' => ['asc' => 'Oldest first', 'desc' => 'Newest first']], // @translate
            ['key' => 'size', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'medium', 'label' => 'Card size', 'options' => ['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large']], // @translate
            ['key' => 'show_images', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Display', 'default' => true, 'label' => 'Show pictures'], // @translate
            ['key' => 'show_details', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Display', 'default' => true, 'label' => 'Show details'], // @translate
        ]);
    }

    /**
     * A sortable key and a label from loose date text, or null.
     * "1911-06-14" → ['1911-06-14', '1911', 1911]; "circa 1920" → ['1920-00-00', '1920', 1920].
     */
    public static function parseDate(string $text): ?array
    {
        if (!preg_match('/(?<!\d)(\d{4})(?!\d)(?:-(\d{2}))?(?:-(\d{2}))?/', $text, $m)) {
            return null;
        }
        $key = sprintf('%s-%s-%s', $m[1], $m[2] ?? '00', $m[3] ?? '00');
        return ['key' => $key, 'year' => (int) $m[1]];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $entries = [];
        // Your entries first (so an item linked to one isn't added twice).
        $linked = [];
        foreach ($data['events'] as $e) {
            $item = null;
            if ($e['item_id']) {
                $found = $this->itemSource->items(['site_scope' => ItemSource::SCOPE_ALL], $site, ['id' => $e['item_id'], 'limit' => 1]);
                $item = $found[0] ?? null;
            }
            $when = $e['date'] !== '' ? $e['date'] : ($item ? (string) $item->value('dcterms:date') : '');
            $date = self::parseDate($when);
            $title = $e['title'] !== '' ? $e['title'] : ($item ? $item->displayTitle() : '');
            if (!$date || $title === '') {
                continue;
            }
            if ($item) {
                $linked[$item->id()] = true;
            }
            $thumb = null;
            if (($e['img'] ?? 'item') === 'item' && $item) {
                $thumb = $item->thumbnailDisplayUrl('medium');
            } elseif (($e['img'] ?? '') === 'asset' && !empty($e['asset_id'])) {
                $thumb = \VisualPageBuilder\Stdlib\ImageSource::url($view, ['asset_id' => $e['asset_id']]);
            }
            $entries[] = [
                'key' => $date['key'], 'year' => $date['year'], 'when' => $when, 'title' => $title, 'text' => $e['text'],
                'thumb' => $thumb, 'url' => $item ? ItemSource::url($item, ['site_scope' => ItemSource::SCOPE_ALL], $site) : null,
            ];
        }
        if ($data['use_items']) {
            $term = preg_match('/^[a-z0-9_-]+:[A-Za-z0-9_-]+$/i', $data['date_property']) ? $data['date_property'] : 'dcterms:date';
            $query = ['property' => [['joiner' => 'and', 'property' => $term, 'type' => 'ex']], 'sort_by' => $term, 'sort_order' => 'asc', 'limit' => $data['count']];
            foreach ($this->itemSource->items($data, $site, $query) as $item) {
                if (isset($linked[$item->id()])) {
                    continue;
                }
                $raw = (string) $item->value($term);
                $date = self::parseDate($raw);
                if (!$date) {
                    continue;
                }
                $entries[] = [
                    'key' => $date['key'], 'year' => $date['year'], 'when' => $raw,
                    'title' => $item->displayTitle(), 'text' => '',
                    'thumb' => $item->thumbnailDisplayUrl('medium'),
                    'url' => ItemSource::url($item, $data, $site),
                ];
            }
        }
        usort($entries, function ($a, $b) {
            return strcmp($a['key'], $b['key']);
        });
        if ($data['order'] === 'desc') {
            $entries = array_reverse($entries);
        }
        return ['entries' => $entries, 'trackId' => 'vpb-tl-' . preg_replace('/[^a-z0-9]/i', '', $blockKey)];
    }
}
