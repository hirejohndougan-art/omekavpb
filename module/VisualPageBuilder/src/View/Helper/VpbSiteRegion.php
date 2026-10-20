<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\SiteRegions;

/**
 * A site's built header or footer, as HTML, or '' if there isn't one (or it
 * has no blocks). Used by the Companion Theme:
 *   $this->vpbSiteRegion($site, 'header')
 */
class VpbSiteRegion extends AbstractHelper
{
    public function __invoke(SiteRepresentation $site, string $region): string
    {
        $page = SiteRegions::find($site, $region);
        if (!$page || !$page->blocks()) {
            return '';
        }
        return (string) $this->getView()->pageLayout()->render($page);
    }
}
