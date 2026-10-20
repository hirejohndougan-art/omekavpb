<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Omeka\Mvc\Exception\NotFoundException;
use Omeka\Mvc\Exception\PermissionDeniedException;
use VisualPageBuilder\SiteRegions;

/**
 * "Header & footer": lists sites, and opens a site's header or footer in the
 * visual editor (making the page the first time). See SiteRegions.
 *
 * Routes (admin/visual-page-builder-region):
 *   /admin/visual-page-builder/region                       list of sites
 *   /admin/visual-page-builder/region/:site-slug/:region    open the editor
 */
class RegionController extends AbstractActionController
{
    public function indexAction()
    {
        if ($this->params('region')) {
            return $this->editAction();
        }
        // The site list lives in the Visual Builder now.
        return $this->redirect()->toRoute('admin/visual-page-builder-pages');
    }

    public function editAction()
    {
        $region = $this->params('region');
        if (!isset(SiteRegions::REGIONS[$region])) {
            throw new NotFoundException('Unknown region.');
        }
        try {
            $site = $this->api()->read('sites', ['slug' => $this->params('site-slug')])->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            throw new NotFoundException('Site not found.');
        }
        if (!$site->userIsAllowed('update')) {
            throw new PermissionDeniedException('You do not have permission to edit this site.');
        }
        $api = $this->getEvent()->getApplication()->getServiceManager()->get('Omeka\ApiManager');
        $page = SiteRegions::ensure($api, $site, $region);
        $from = (int) $this->params()->fromQuery('from', 0);
        return $this->redirect()->toRoute('admin/visual-page-builder', ['site-slug' => $site->slug(), 'page-id' => $page->id()], $from ? ['query' => ['from' => $from]] : []);
    }
}
