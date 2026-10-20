<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Validator\Csrf;
use Laminas\View\Model\ViewModel;
use Omeka\Mvc\Exception\NotFoundException;
use Omeka\Mvc\Exception\PermissionDeniedException;
use VisualPageBuilder\SiteRegions;

/**
 * "Visual Builder" under Modules in Omeka's admin: everything for one site on
 * one page: its header and footer, its menu, the theme, and its pages (each
 * opening in the visual editor), with a site switcher. With only one site
 * the site list is skipped.
 *
 * Routes (admin/visual-page-builder-pages):
 *   /admin/visual-builder               sites
 *   /admin/visual-builder/:site-slug    that site
 */
class PagesController extends AbstractActionController
{
    public function indexAction()
    {
        $slug = $this->params('site-slug');
        $picked = $this->params()->fromQuery('site');
        if (!$slug && is_string($picked) && preg_match('/^[a-zA-Z0-9_-]+$/', $picked)) {
            return $this->redirect()->toRoute('admin/visual-page-builder-pages', ['site-slug' => $picked]);
        }
        if ($slug) {
            return $this->siteAction($slug);
        }
        $sites = $this->editableSites();
        if (count($sites) === 1) {
            return $this->redirect()->toRoute('admin/visual-page-builder-pages', ['site-slug' => reset($sites)->slug()]);
        }
        return new ViewModel(['sites' => $sites]);
    }

    protected function siteAction(string $slug)
    {
        try {
            $site = $this->api()->read('sites', ['slug' => $slug])->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            throw new NotFoundException('Site not found.');
        }
        if (!$site->userIsAllowed('update')) {
            throw new PermissionDeniedException('You do not have permission to edit this site.');
        }
        if ($this->getRequest()->isPost()) {
            return $this->handlePost($site);
        }
        // Optional "Find a page" search (title or address).
        $q = trim((string) $this->params()->fromQuery('q', ''));
        $pages = [];
        foreach ($site->pages() as $page) {
            if (SiteRegions::isHiddenSlug($page->slug())) {
                continue; // The header and footer have their own buttons.
            }
            if ($q !== '' && stripos($page->title(), $q) === false && stripos($page->slug(), $q) === false) {
                continue;
            }
            $pages[] = $page;
        }
        usort($pages, function ($a, $b) {
            return strcasecmp($a->title(), $b->title());
        });
        $style = $this->siteSettings()->get('vpb_menu_style', MenuController::DEFAULT_STYLE, $site->id());
        $view = new ViewModel([
            'site' => $site,
            'sites' => $this->editableSites(),
            'pages' => $pages,
            'q' => $q,
            'header' => SiteRegions::find($site, 'header'),
            'footer' => SiteRegions::find($site, 'footer'),
            'menuCount' => $this->countLinks($site->navigation()),
            'menuStyle' => $this->translate(MenuController::STYLES[$style][0] ?? $style),
            'usesCompanion' => $site->theme() === 'visual-page-builder-companion',
            'shared' => $this->sharedSections($site),
            'csrf' => (new Csrf(['name' => 'vpb_hub_csrf']))->getHash(),
        ]);
        $view->setTemplate('visual-page-builder/admin/pages/site');
        return $view;
    }

    /** Sites the user may edit, by title. */
    protected function editableSites(): array
    {
        return array_values(array_filter($this->api()->search('sites', ['sort_by' => 'title'])->getContent(), function ($site) {
            return $site->userIsAllowed('update');
        }));
    }

    /** Number of links in a navigation tree, at every level. */
    protected function countLinks(array $links): int
    {
        $n = 0;
        foreach ($links as $link) {
            $n += 1 + $this->countLinks($link['links'] ?? []);
        }
        return $n;
    }

    /** The site's shared sections (hidden pages vpb-shared-…), by title. */
    protected function sharedSections($site): array
    {
        $out = [];
        foreach ($site->pages() as $page) {
            if (SiteRegions::isSharedSlug($page->slug())) {
                $out[] = $page;
            }
        }
        usort($out, function ($a, $b) {
            return strcasecmp($a->title(), $b->title());
        });
        return $out;
    }

    /**
     * Buttons on the Visual Builder page: duplicate a page, add or delete a
     * shared section. All are forms with a CSRF token.
     */
    protected function handlePost($site)
    {
        $post = $this->params()->fromPost();
        $back = $this->url()->fromRoute('admin/visual-page-builder-pages', ['site-slug' => $site->slug()]);
        if (!(new Csrf(['name' => 'vpb_hub_csrf']))->isValid($post['vpb_hub_csrf'] ?? '')) {
            $this->messenger()->addError('That form expired. Please try again.'); // @translate
            return $this->redirect()->toUrl($back);
        }
        $action = $post['vpb_action'] ?? '';
        if ($action === 'new_shared') {
            $title = trim((string) ($post['title'] ?? '')) ?: $this->translate('Shared section');
            $page = $this->api()->create('site_pages', [
                'o:site' => ['o:id' => $site->id()],
                'o:title' => mb_substr($title, 0, 190),
                'o:slug' => SiteRegions::SHARED_PREFIX . bin2hex(random_bytes(4)),
                'o:is_public' => true, // Visitors must be able to see it on other pages; its own address is hidden.
                'o:layout' => 'grid',
                'o:layout_data' => ['grid_columns' => 12, 'visual_page_builder' => ['version' => 1]],
                'o:block' => [],
            ])->getContent();
            return $this->redirect()->toRoute('admin/visual-page-builder', ['site-slug' => $site->slug(), 'page-id' => $page->id()]);
        }
        $page = null;
        try {
            $page = $this->api()->read('site_pages', (int) ($post['page_id'] ?? 0))->getContent();
        } catch (\Exception $e) {
        }
        if (!$page || $page->site()->id() !== $site->id()) {
            $this->messenger()->addError('Page not found.'); // @translate
            return $this->redirect()->toUrl($back);
        }
        if ($action === 'delete_shared' && SiteRegions::isSharedSlug($page->slug())) {
            $this->api()->delete('site_pages', $page->id());
            $this->messenger()->addSuccess(sprintf($this->translate('Deleted the shared section "%s". Pages that showed it now skip it.'), $page->title()));
            return $this->redirect()->toUrl($back);
        }
        if ($action === 'duplicate' && !SiteRegions::isHiddenSlug($page->slug())) {
            $copy = $this->api()->create('site_pages', [
                'o:site' => ['o:id' => $site->id()],
                'o:title' => sprintf($this->translate('Copy of %s'), $page->title()),
                'o:slug' => $this->freeSlug($site, $page->slug() . '-copy'),
                'o:is_public' => false, // Private until you're ready.
                'o:layout' => $page->layout(),
                'o:layout_data' => $page->layoutData(),
                'o:block' => self::copyBlocks($page),
            ])->getContent();
            $this->messenger()->addSuccess(sprintf($this->translate('Made "%s". It\'s private until you make it public.'), $copy->title()));
            return $this->redirect()->toRoute('admin/visual-page-builder', ['site-slug' => $site->slug(), 'page-id' => $copy->id()]);
        }
        return $this->redirect()->toUrl($back);
    }

    /** A page's blocks as API data, for a copy (layout, data, layout data, attachments). */
    public static function copyBlocks($page): array
    {
        $blocks = [];
        foreach ($page->blocks() as $block) {
            $attachments = [];
            foreach ($block->attachments() as $att) {
                $attachments[] = [
                    'o:item' => $att->item() ? ['o:id' => $att->item()->id()] : null,
                    'o:media' => $att->media() ? ['o:id' => $att->media()->id()] : null,
                    'o:caption' => $att->caption(),
                ];
            }
            $blocks[] = [
                'o:layout' => $block->layout(),
                'o:data' => $block->data(),
                'o:layout_data' => $block->layoutData(),
                'o:attachment' => $attachments,
            ];
        }
        return $blocks;
    }

    /** $base, or $base-2, $base-3… whichever isn't used in the site yet. */
    protected function freeSlug($site, string $base): string
    {
        $used = [];
        foreach ($site->pages() as $p) {
            $used[$p->slug()] = true;
        }
        $base = substr($base, 0, 180);
        $slug = $base;
        for ($n = 2; isset($used[$slug]); $n++) {
            $slug = $base . '-' . $n;
        }
        return $slug;
    }
}
