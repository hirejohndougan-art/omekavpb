<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Stdlib\ItemSource;

/**
 * Item sets as cards: cover image, title, item count, description.
 *
 * Either chosen item sets (in your order) or every item set on the chosen
 * sites. Counts only include items on those sites. The cover is the item
 * set's own thumbnail, or else the first item in it that has one.
 */
class CollectionCards extends AbstractSchemaBlock
{
    const DESCRIPTION = 'Shows your item sets (collections) as picture cards, with how many items each one holds.'; // @translate

    const GOOD_FOR = 'A "browse our collections" page.'; // @translate

    const TEMPLATE = 'collection-cards';

    public function schema(): array
    {
        return array_merge(
            ItemSource::fields(false, false),
            [
                [
                    'key' => 'item_set_ids', 'type' => Schema::TYPE_ITEM_SETS, 'group' => 'Source', 'summary' => true,
                    'label' => 'Only these item sets', // @translate
                    'info' => 'Optional. Shown in the order chosen. Leave empty to show every item set on the sites above.', // @translate
                ],
                [
                    'key' => 'sort', 'type' => Schema::TYPE_SELECT, 'group' => 'Source', 'default' => 'title',
                    'label' => 'Order (when not chosen above)', // @translate
                    'options' => ['title' => 'Title', 'created' => 'Newest first'], // @translate
                ],
                DisplayFields::heading('Our collections'),
            ],
            DisplayFields::grid(12, 60),
            [[
                'key' => 'show_count', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Display', 'default' => true,
                'label' => 'Show how many items each has', // @translate
            ]]
        );
    }

    public function getLabel()
    {
        return 'Collection cards'; // @translate
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $api = $view->api();
        $query = ItemSource::query($data, $site, 'item_sets') + ['limit' => $data['count']];
        if ($data['item_set_ids']) {
            $query['id'] = $data['item_set_ids'];
        } else {
            $query += $data['sort'] === 'created' ? ['sort_by' => 'created', 'sort_order' => 'desc'] : ['sort_by' => 'title', 'sort_order' => 'asc'];
        }
        $sets = $api->search('item_sets', $query)->getContent();
        if ($data['item_set_ids']) {
            $order = array_flip($data['item_set_ids']);
            usort($sets, function ($a, $b) use ($order) {
                return ($order[$a->id()] ?? 0) <=> ($order[$b->id()] ?? 0);
            });
        }
        $cards = AbstractItemBlock::cards($sets, $data, $site);
        foreach ($cards as $i => $card) {
            $set = $sets[$i];
            $itemQuery = ['site_scope' => $data['site_scope'], 'site_ids' => $data['site_ids'], 'item_set_ids' => [$set->id()]];
            if ($data['show_count']) {
                $cards[$i]['count'] = $this->itemSource->countItems($itemQuery, $site);
            }
            if (!$card['thumb']) {
                $first = $this->itemSource->items($itemQuery + ['has_media' => true], $site, ['limit' => 1, 'sort_by' => 'id']);
                $cards[$i]['thumb'] = $first ? $first[0]->thumbnailDisplayUrl($data['thumbnail_type']) : null;
            }
        }
        return ['cards' => $cards];
    }
}
