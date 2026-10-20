<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;

/**
 * Links to the previous and next item in the item set the item belongs to, by title.
 */
class ItemPrevNext extends AbstractItemField
{
    const DESCRIPTION = 'Links to the previous and next item in the item set the item belongs to, by title.'; // @translate

    const GOOD_FOR = 'Item templates in a collection that is browsed in order.'; // @translate

    const TEMPLATE = 'item-prev-next';
    const SAMPLE = 'Previous and next item'; // @translate

    public function getLabel()
    {
        return 'Previous and next'; // @translate
    }

    public function schema(): array
    {
        return [];
    }

    protected function fieldVars(PhpRenderer $view, array $data, SiteRepresentation $site, $item): array
    {
        $prev = $next = null;
        $sets = $item ? array_values($item->itemSets()) : [];
        if ($sets) {
            $setId = $sets[0]->id();
            $siblings = array_values($view->api()->search('items', ['item_set_id' => $setId, 'sort_by' => 'title', 'sort_order' => 'asc', 'per_page' => 1000])->getContent());
            $ids = array_map(function ($i) {
                return $i->id();
            }, $siblings);
            $at = array_search($item->id(), $ids, true);
            if ($at !== false) {
                $before = $at > 0 ? $siblings[$at - 1] : null;
                $after = $at < count($siblings) - 1 ? $siblings[$at + 1] : null;
                $prev = $before ? ['title' => (string) $before->displayTitle(), 'url' => $before->siteUrl($site->slug())] : null;
                $next = $after ? ['title' => (string) $after->displayTitle(), 'url' => $after->siteUrl($site->slug())] : null;
            }
        }
        return ['prev' => $prev, 'next' => $next];
    }
}
