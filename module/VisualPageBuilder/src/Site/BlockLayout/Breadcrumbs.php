<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;

/**
 * "Home › Exhibits › Railroad Days": where the visitor is, from the site's
 * menu. Works in a page or in the site's header. On a page that isn't in the
 * menu it shows Home › that page; on the home page it shows nothing.
 */
class Breadcrumbs extends AbstractSchemaBlock
{
    /** On pages, and in the site's header and footer. */
    const PLACEMENT = 'both';

    const TEMPLATE = 'breadcrumbs';

    const DESCRIPTION = 'Shows where the visitor is in your site, like "Home › Exhibits › Railroad Days", built from your menu.'; // @translate

    const GOOD_FOR = 'Headers and deep pages.'; // @translate

    public function getLabel()
    {
        return 'Breadcrumbs'; // @translate
    }

    public function schema(): array
    {
        return [
            ['key' => 'home_label', 'type' => Schema::TYPE_TEXT, 'group' => 'Display', 'default' => 'Home', 'label' => 'First link'], // @translate
            ['key' => 'separator', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'chevron', 'label' => 'Between links', 'options' => ['chevron' => '›', 'slash' => '/', 'arrow' => '→']],  // @translate
        ];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $crumbs = [];
        $nav = $site->publicNav();
        $active = $nav->findActive($nav->getContainer());
        if ($active) {
            for ($page = $active['page']; $page && method_exists($page, 'getLabel'); $page = $page->getParent()) {
                array_unshift($crumbs, ['label' => (string) $page->getLabel(), 'url' => $page->getHref()]);
            }
        } else {
            // Not in the menu: the current page by its address, if it's a page.
            $slug = $view->params()->fromRoute('page-slug');
            if ($slug && !\VisualPageBuilder\SiteRegions::isRegionSlug($slug)) {
                try {
                    $page = $view->api()->read('site_pages', ['slug' => $slug, 'site' => $site->id()])->getContent();
                    $crumbs[] = ['label' => $page->title(), 'url' => $page->siteUrl()];
                } catch (\Exception $e) {
                }
            }
        }
        $isHome = $view->params()->fromRoute('page-slug') === null && $view->params()->fromRoute('controller') === 'Omeka\Controller\Site\Index';
        if ($isHome || !$crumbs) {
            return ['crumbs' => [], 'preview' => $blockKey === 'preview'];
        }
        array_unshift($crumbs, ['label' => $data['home_label'] !== '' ? $data['home_label'] : $site->title(), 'url' => $site->siteUrl()]);
        return ['crumbs' => $crumbs, 'preview' => false];
    }
}
