<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Templates\Resolver;
use VisualPageBuilder\Templates\Store;

/**
 * The Theme Builder template for a page-wide screen (Browse items, Search
 * results, and so on), drawn as HTML, or null when no live template applies.
 * The Companion Theme uses it before drawing Omeka's own page:
 *   $html = $this->vpbScreen($this->currentSite(), 'browse-items');
 */
class VpbScreen extends AbstractHelper
{
    public function __invoke(SiteRepresentation $site, string $screen): ?string
    {
        $chosen = (new Resolver)->choose($screen, [], Store::live($site));
        if (!$chosen) {
            return null;
        }
        return (string) $this->getView()->pageLayout()->render($chosen['page']);
    }
}
