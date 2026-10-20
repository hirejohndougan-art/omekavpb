<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Stdlib\ItemSource;

/**
 * Live numbers, like "3,412 items · 58 collections", for the chosen sites.
 */
class CollectionCounts extends AbstractSchemaBlock
{
    const DESCRIPTION = 'Shows live numbers, such as how many items and collections you have.'; // @translate

    const GOOD_FOR = 'About pages and annual reports.'; // @translate

    const TEMPLATE = 'collection-counts';

    public function getLabel()
    {
        return 'Collection counts'; // @translate
    }

    public function schema(): array
    {
        return array_merge(
            ItemSource::fields(true, false),
            [
                DisplayFields::heading(),
                ['key' => 'show_items', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Numbers', 'default' => true, 'label' => 'Number of items'], // @translate
                ['key' => 'items_label', 'type' => Schema::TYPE_TEXT, 'group' => 'Numbers', 'default' => 'items', 'label' => 'Label for items', 'show_if' => ['show_items' => [true]]], // @translate
                ['key' => 'show_with_media', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Numbers', 'default' => false, 'label' => 'Number of items with images or files'], // @translate
                ['key' => 'with_media_label', 'type' => Schema::TYPE_TEXT, 'group' => 'Numbers', 'default' => 'digitized items', 'label' => 'Label for items with files', 'show_if' => ['show_with_media' => [true]]], // @translate
                ['key' => 'show_item_sets', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Numbers', 'default' => true, 'label' => 'Number of item sets'], // @translate
                ['key' => 'item_sets_label', 'type' => Schema::TYPE_TEXT, 'group' => 'Numbers', 'default' => 'collections', 'label' => 'Label for item sets', 'show_if' => ['show_item_sets' => [true]]], // @translate
                [
                    'key' => 'style', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'large',
                    'label' => 'Style', // @translate
                    'options' => ['large' => 'Large numbers', 'inline' => 'One line of text'], // @translate
                ],
            ]
        );
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $counts = [];
        if ($data['show_items']) {
            $counts[] = ['n' => $this->itemSource->countItems($data, $site), 'label' => $data['items_label']];
        }
        if ($data['show_with_media']) {
            $counts[] = ['n' => $this->itemSource->countItems($data, $site, ['has_media' => true]), 'label' => $data['with_media_label']];
        }
        if ($data['show_item_sets']) {
            $query = ItemSource::query($data, $site, 'item_sets');
            if ($data['item_set_ids']) {
                $query['id'] = $data['item_set_ids'];
            }
            $counts[] = ['n' => $view->api()->search('item_sets', $query + ['limit' => 0])->getTotalResults(), 'label' => $data['item_sets_label']];
        }
        return ['counts' => $counts];
    }
}
