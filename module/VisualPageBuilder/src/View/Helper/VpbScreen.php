<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use VisualPageBuilder\Templates\CurrentRecord;
use VisualPageBuilder\Templates\CurrentScreen;
use VisualPageBuilder\Templates\Resolver;
use VisualPageBuilder\Templates\Store;

/**
 * The Theme Builder template for a page-wide screen (Browse items, Browse item
 * sets, and so on), drawn as HTML, or null when no live template applies. The
 * Companion Theme uses it before drawing Omeka's own page:
 *   $html = $this->vpbScreen($this->currentSite(), 'browse-items');
 * The item set screen passes the item set as its record:
 *   $html = $this->vpbScreen($this->currentSite(), 'item-set', $itemSet);
 */
class VpbScreen extends AbstractHelper
{
    public function __invoke(SiteRepresentation $site, string $screen, ?AbstractResourceEntityRepresentation $record = null): ?string
    {
        $resolverRecord = $record ? ['id' => $record->id()] : [];
        $chosen = (new Resolver)->choose($screen, $resolverRecord, Store::live($site));
        if (!$chosen) {
            return null;
        }
        CurrentScreen::push($screen);
        if ($record) {
            CurrentRecord::push($record);
        }
        try {
            return (string) $this->getView()->pageLayout()->render($chosen['page']);
        } finally {
            if ($record) {
                CurrentRecord::pop();
            }
            CurrentScreen::pop();
        }
    }
}
