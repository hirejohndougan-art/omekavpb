<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;

/**
 * The collections (item sets) the item belongs to, each linked to its page.
 */
class ItemSets extends AbstractItemField
{
    const DESCRIPTION = 'The collections (item sets) the item belongs to, each linked to its page.'; // @translate

    const GOOD_FOR = 'Item templates.'; // @translate

    const TEMPLATE = 'item-sets';
    const SAMPLE = 'Item sets (the collections it is in)'; // @translate

    public function getLabel()
    {
        return 'Item sets'; // @translate
    }

    public function schema(): array
    {
        return [];
    }

    protected function fieldVars(PhpRenderer $view, array $data, SiteRepresentation $site, $item): array
    {
        $sets = [];
        if ($item) {
            foreach ($item->itemSets() as $set) {
                $sets[] = [
                    'title' => (string) $set->displayTitle(),
                    'url' => $view->url('site/item-set', ['site-slug' => $site->slug(), 'item-set-id' => $set->id()]),
                ];
            }
        }
        return ['sets' => $sets];
    }
}
