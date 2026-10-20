<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Stdlib\ItemSource;

/**
 * The newest items, by when they were added or last changed.
 */
class RecentItems extends AbstractItemBlock
{
    const DESCRIPTION = 'Shows the newest items added to the collection, from this site, sites you choose, or all sites.'; // @translate

    const GOOD_FOR = 'Showing that the collection keeps growing.'; // @translate

    public function getLabel()
    {
        return 'Recently added items'; // @translate
    }

    public function schema(): array
    {
        return array_merge(
            ItemSource::fields(),
            [[
                'key' => 'order', 'type' => Schema::TYPE_SELECT, 'group' => 'Source',
                'label' => 'Newest by', // @translate
                'options' => ['created' => 'Date added', 'modified' => 'Date last changed'], // @translate
                'default' => 'created',
            ]],
            [DisplayFields::heading('Recently added')],
            DisplayFields::grid(6),
            DisplayFields::link('Browse all items')
        );
    }

    protected function findItems(array $data, SiteRepresentation $site): array
    {
        return $this->itemSource->items($data, $site, ['sort_by' => $data['order'], 'sort_order' => 'desc', 'limit' => $data['count']]);
    }
}
