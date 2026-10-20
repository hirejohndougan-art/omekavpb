<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Stdlib\ItemSource;

/**
 * A shuffled selection of items, different on each visit.
 */
class RandomItems extends AbstractItemBlock
{
    const DESCRIPTION = 'Shows a different handful of items each time someone visits. It can draw from this site, other sites you choose, or all sites.'; // @translate

    const GOOD_FOR = 'Home pages that feel fresh on every visit.'; // @translate

    public function getLabel()
    {
        return 'Random items'; // @translate
    }

    public function schema(): array
    {
        return array_merge(
            ItemSource::fields(),
            [DisplayFields::heading()],
            DisplayFields::grid(6),
            DisplayFields::link()
        );
    }

    protected function findItems(array $data, SiteRepresentation $site): array
    {
        return $this->itemSource->items($data, $site, ['sort_by' => 'random', 'limit' => $data['count']]);
    }
}
