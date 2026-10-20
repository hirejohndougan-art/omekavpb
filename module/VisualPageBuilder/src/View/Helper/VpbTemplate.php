<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Templates\CurrentRecord;
use VisualPageBuilder\Templates\Resolver;
use VisualPageBuilder\Templates\Store;

/**
 * The Theme Builder template for an item page, drawn as HTML, or null when no
 * live template applies (the theme then shows Omeka's own item page). Used by
 * the Companion Theme's item show view:
 *   $html = $this->vpbTemplate($site, $item);
 *
 * The item is put on CurrentRecord while the template draws, so the item field
 * blocks (Title, Main image, and so on) show that item's parts.
 */
class VpbTemplate extends AbstractHelper
{
    public function __invoke(SiteRepresentation $site, ItemRepresentation $item): ?string
    {
        $record = [
            'id' => $item->id(),
            'resource_template' => ($rt = $item->resourceTemplate()) ? $rt->id() : null,
            'class' => ($class = $item->resourceClass()) ? $class->id() : null,
            'item_sets' => array_map(function ($set) {
                return $set->id();
            }, $item->itemSets()),
        ];
        $chosen = (new Resolver)->choose('item', $record, Store::live($site));
        if (!$chosen) {
            return null;
        }
        $page = $chosen['page'];
        CurrentRecord::push($item);
        try {
            return (string) $this->getView()->pageLayout()->render($page);
        } finally {
            CurrentRecord::pop();
        }
    }
}
