<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder;

use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Module\AbstractModule;
use VisualPageBuilder\Api\MultiSiteFilter;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);
        // Any logged-in user may reach the editor route (admin routes already
        // require login). The controller then checks that the user is allowed
        // to update the specific page, and Omeka's API enforces it again on save.
        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        $acl->allow(null, ['VisualPageBuilder\Controller\Admin\Builder', 'VisualPageBuilder\Controller\Admin\Menu', 'VisualPageBuilder\Controller\Admin\Region', 'VisualPageBuilder\Controller\Admin\Pages']);
        // The public search endpoint (VPB Search) is open to every visitor, logged in or not.
        $acl->allow(null, 'VisualPageBuilder\Controller\Site\Search', 'search');
        // The diagnostics report is open to a request that carries the diagnostics token; the controller checks the token.
        $acl->allow(null, 'VisualPageBuilder\Controller\Admin\Diagnostics', 'report');
        $acl->allow(null, 'VisualPageBuilder\Controller\Admin\Diagnostics', 'mcp');
        $this->landOnHome($event);
        $this->useFriendlyLayout($event);
        $this->setTemplateContext($event);
    }

    /** The Phase 6 screens use the friendly admin layout: a VPB sidebar in place of Omeka's. */
    private function useFriendlyLayout(MvcEvent $event): void
    {
        $event->getApplication()->getEventManager()->attach(MvcEvent::EVENT_ROUTE, function (MvcEvent $e) {
            $match = $e->getRouteMatch();
            $name = $match ? (string) $match->getMatchedRouteName() : '';
            require_once __DIR__ . '/src/Mode.php';
            $services = $e->getApplication()->getServiceManager();
            $isAdminRoute = strpos($name, 'admin') === 0;
            // The mode is a per-person setting, so it is read only for a signed-in admin request.
            $mode = $isAdminRoute && $services->get('Omeka\AuthenticationService')->hasIdentity()
                ? \VisualPageBuilder\Mode::current($services)
                : \VisualPageBuilder\Mode::SIMPLE;
            // In Simple mode every admin screen keeps the friendly layout, Omeka's own screens included,
            // until the person chooses Expert. Expert keeps Omeka's layout for Omeka's own screens.
            $everyAdminScreen = $mode === \VisualPageBuilder\Mode::SIMPLE && $isAdminRoute;
            if (!$everyAdminScreen && !in_array($name, self::FRIENDLY_ROUTES, true)) {
                return null;
            }
            $viewModel = $e->getViewModel();
            $viewModel->setTemplate('visual-page-builder/admin/layout');
            $viewModel->setVariable('vpbCurrent', $name);
            $viewModel->setVariable('vpbMode', $mode);

            // Site switcher: pass the editable sites and current site slug to the layout.
            $session = new \Laminas\Session\Container('VisualPageBuilder');
            $routeSlug = $match ? $match->getParam('site-slug') : null;
            if ($routeSlug) {
                $session->offsetSet('currentSite', $routeSlug);
            }
            $currentSlug = $routeSlug ?: ($session->offsetExists('currentSite') ? $session->offsetGet('currentSite') : null);
            try {
                $allSites = $services->get('Omeka\ApiManager')->search('sites', ['sort_by' => 'title'])->getContent();
                $vpbSites = [];
                foreach ($allSites as $s) {
                    if ($s->userIsAllowed('update')) {
                        $vpbSites[] = $s;
                    }
                }
            } catch (\Exception $ex) {
                $vpbSites = [];
            }
            if (!$currentSlug && $vpbSites) {
                $currentSlug = $vpbSites[0]->slug();
                $session->offsetSet('currentSite', $currentSlug);
            }
            $viewModel->setVariable('vpbSites', $vpbSites);
            $viewModel->setVariable('vpbSiteSlug', $currentSlug);
            return null;
        }, -1000); // after Omeka sets its own layout
    }

    /** Routes that use the friendly admin layout. */
    private const FRIENDLY_ROUTES = [
        'admin/visual-page-builder-home',
        'admin/visual-page-builder-items',
        'admin/visual-page-builder-pages',
        'admin/visual-page-builder-item',
        'admin/visual-page-builder-files',
        'admin/visual-page-builder-item-sets',
        'admin/visual-page-builder-import',
        'admin/visual-page-builder-settings',
        'admin/visual-page-builder-people',
        'admin/visual-page-builder-glossary',
        'admin/visual-page-builder-add',
        'admin/visual-page-builder-questions',
    ];

    /**
     * People land on Home after signing in: the admin front page (/admin) sends a
     * signed-in user to VPB Home. Other admin pages are not affected.
     */
    private function landOnHome(MvcEvent $event): void
    {
        $application = $event->getApplication();
        $application->getEventManager()->attach(MvcEvent::EVENT_ROUTE, function (MvcEvent $e) {
            $match = $e->getRouteMatch();
            if (!$match) {
                return null;
            }
            $name = $match->getMatchedRouteName();
            require_once __DIR__ . '/src/Mode.php';
            // Signing out ends the sign-in, so the Simple or Expert question is asked again next time.
            if ($name === 'logout') {
                \VisualPageBuilder\Mode::markChosenThisSignIn(false);
                return null;
            }
            if ($name !== 'admin') {
                return null;
            }
            $services = $e->getApplication()->getServiceManager();
            if (!$services->get('Omeka\AuthenticationService')->hasIdentity()) {
                return null;
            }
            // After sign-in the person is asked Simple or Expert once; then they land on Home.
            $target = \VisualPageBuilder\Mode::chosenThisSignIn()
                ? 'admin/visual-page-builder-home'
                : 'admin/visual-page-builder-choose-mode';
            $home = $e->getRouter()->assemble([], ['name' => $target]);
            $response = $e->getResponse() ?: new \Laminas\Http\PhpEnvironment\Response();
            $response->getHeaders()->addHeaderLine('Location', $home);
            $response->setStatusCode(302);
            return $response;
        }, -10); // after the route is matched
    }

    /**
     * On public (site) routes, build the request context for the
     * LocationResolver (Blueprint-style template resolution) and make it
     * available to the Companion Theme's layout as the $vpbContext variable.
     *
     * Like Blueprint's Theme_Builder::resolve() reading WordPress's query
     * state on template_redirect, this reads Omeka's route match and the
     * record being shown, and puts the context on the layout view model.
     */
    private function setTemplateContext(MvcEvent $event): void
    {
        $event->getApplication()->getEventManager()->attach(MvcEvent::EVENT_DISPATCH, function (MvcEvent $e) {
            $match = $e->getRouteMatch();
            if (!$match) {
                return;
            }
            $name = (string) $match->getMatchedRouteName();
            // Only on public site routes (the Companion Theme's layout).
            if (strpos($name, 'site') !== 0 && $name !== 'site') {
                return;
            }
            $services = $e->getApplication()->getServiceManager();
            $params = $match->getParams();
            // Map the route to a screen.
            $screen = '';
            $record = [];
            $pageId = null;
            $isHome = false;
            $action = $params['action'] ?? 'index';
            $controller = $params['controller'] ?? '';
            // Item show.
            if (strpos($controller, 'Item') !== false && $action === 'show' && !empty($params['id'])) {
                $screen = 'item';
                try {
                    $item = $services->get('Omeka\ApiManager')->read('items', $params['id'])->getContent();
                    $record = [
                        'id' => $item->id(),
                        'resource_template' => ($rt = $item->resourceTemplate()) ? $rt->id() : null,
                        'class' => ($rc = $item->resourceClass()) ? $rc->id() : null,
                        'item_sets' => array_map(function ($s) { return $s->id(); }, $item->itemSets()),
                    ];
                } catch (\Exception $ex) {
                    // Record not found; screen still 'item' but no record context.
                }
            }
            // Item browse.
            if (strpos($controller, 'Item') !== false && $action === 'browse') {
                $screen = 'browse-items';
            }
            // Item set show.
            if (strpos($controller, 'ItemSet') !== false && $action === 'show') {
                $screen = 'item-set';
            }
            // Item set browse.
            if (strpos($controller, 'ItemSet') !== false && $action === 'browse') {
                $screen = 'browse-item-sets';
            }
            // Media show.
            if (strpos($controller, 'Media') !== false && $action === 'show') {
                $screen = 'media';
            }
            // Page.
            if (strpos($controller, 'Page') !== false && $action === 'show') {
                $slug = $params['page-slug'] ?? '';
                // Look up the page id.
                try {
                    $siteSlug = $params['site-slug'] ?? '';
                    $conn = $services->get('Omeka\Connection');
                    $siteId = $conn->fetchOne('SELECT id FROM site WHERE slug = ?', [$siteSlug]);
                    if ($siteId) {
                        $pid = $conn->fetchOne('SELECT id FROM site_page WHERE site_id = ? AND slug = ?', [(int) $siteId, $slug]);
                        if ($pid) {
                            $pageId = (int) $pid;
                        }
                    }
                } catch (\Exception $ex) {
                    // ignore
                }
            }
            // Home page (landing page, no action, no specific controller).
            if ($name === 'site' && ($action === 'index' || $action === 'show') && empty($params['id'])) {
                $isHome = !isset($params['page-slug']) || $params['page-slug'] === '';
            }

            $context = [
                'screen' => $screen,
                'record' => $record,
                'page_id' => $pageId,
                'is_home' => $isHome,
            ];
            $viewModel = $e->getViewModel();
            if ($viewModel) {
                $viewModel->setVariable('vpbContext', $context);
            }
        }, -900); // after the controller dispatches, before the view renders
    }

    /**
     * Header and footer pages (see SiteRegions) are shown inside other pages,
     * never on their own: answer "not found" if someone opens their address.
     */
    public function hideRegionPages(MvcEvent $event): void
    {
        $routeMatch = $event->getRouteMatch();
        if ($routeMatch && $routeMatch->getParam('action') === 'show' && (SiteRegions::isRegionSlug($routeMatch->getParam('page-slug')) || SiteRegions::isSharedSlug($routeMatch->getParam('page-slug')))) {
            throw new \Omeka\Mvc\Exception\NotFoundException('Page not found.');
        }
    }

    public function install(ServiceLocatorInterface $services)
    {
        $this->renameOldLayouts($services);
        // The module's classes aren't autoloaded until it's active.
        require_once __DIR__ . '/src/PageHistory.php';
        require_once __DIR__ . '/src/Search/Index.php';
        PageHistory::createTable($services->get('Omeka\Connection'));
        Search\Index::createTables($services->get('Omeka\Connection'));
        ItemHistory::createTable($services->get('Omeka\Connection'));
        Drafts::createTable($services->get('Omeka\Connection'));
        SearchLog::createTable($services->get('Omeka\Connection'));
    }

    /**
     * Removes only the module's cache (calendar feeds). Pages, headers,
     * footers, menus, and site settings are the society's content and are
     * kept, so reinstalling brings everything back.
     */
    public function uninstall(ServiceLocatorInterface $services)
    {
        // Page history goes with the module (the pages themselves stay).
        require_once __DIR__ . '/src/PageHistory.php';
        PageHistory::dropTable($services->get('Omeka\Connection'));
        require_once __DIR__ . '/src/ItemHistory.php';
        ItemHistory::dropTable($services->get('Omeka\Connection'));
        require_once __DIR__ . '/src/Drafts.php';
        require_once __DIR__ . '/src/SearchLog.php';
        Drafts::dropTable($services->get('Omeka\Connection'));
        SearchLog::dropTable($services->get('Omeka\Connection'));
        // The search index goes too. Omeka's own search keeps working without it.
        Search\Index::dropTables($services->get('Omeka\Connection'));
        $dir = OMEKA_PATH . '/files/visual-page-builder';
        foreach (glob($dir . '/calendar-*.json') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }

    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $services)
    {
        if (version_compare($oldVersion, '0.5.0', '<')) {
            $this->renameOldLayouts($services);
        }
        if (version_compare($oldVersion, '1.12.0', '<')) {
            require_once __DIR__ . '/src/PageHistory.php';
            PageHistory::createTable($services->get('Omeka\Connection'));
        }
        if (version_compare($oldVersion, '2.2.0', '<')) {
            // The index is filled by a reindex job, started from the search settings. The module's
            // classes are not autoloaded while an upgrade runs, so the file is loaded here.
            require_once __DIR__ . '/src/Search/Index.php';
            \VisualPageBuilder\Search\Index::createTables($services->get('Omeka\Connection'));
        }
        // Item history (Phase 9) and drafts (Phase 6). Their tables are created here or on first use.
        require_once __DIR__ . '/src/ItemHistory.php';
        ItemHistory::createTable($services->get('Omeka\Connection'));
        require_once __DIR__ . '/src/Drafts.php';
        require_once __DIR__ . '/src/SearchLog.php';
        Drafts::createTable($services->get('Omeka\Connection'));
        SearchLog::createTable($services->get('Omeka\Connection'));
    }

    /** Item history: save the item's values before each change made through the API. */
    public function snapshotItemBeforeUpdate(Event $event): void
    {
        require_once __DIR__ . '/src/ItemHistory.php';
        $services = $event->getTarget()->getServiceLocator();
        ItemHistory::snapshot(
            $services->get('Omeka\Connection'),
            $services->get('Omeka\ApiManager'),
            (int) $event->getParam('request')->getId()
        );
    }

    /** Drafts: an item deleted through the API is no longer a draft. */
    public function forgetDraftOnDelete(Event $event): void
    {
        require_once __DIR__ . '/src/Drafts.php';
        require_once __DIR__ . '/src/SearchLog.php';
        $services = $event->getTarget()->getServiceLocator();
        Drafts::forget($services->get('Omeka\Connection'), (int) $event->getParam('request')->getId());
    }

    /** Batch changes: save each item before the batch is applied. */
    public function snapshotItemsBeforeBatch(Event $event): void
    {
        require_once __DIR__ . '/src/ItemHistory.php';
        $services = $event->getTarget()->getServiceLocator();
        $conn = $services->get('Omeka\Connection');
        $api = $services->get('Omeka\ApiManager');
        foreach ((array) $event->getParam('request')->getIds() as $id) {
            ItemHistory::snapshot($conn, $api, (int) $id);
        }
    }

    /** Keeps the search index current when an item is created, changed or deleted. */
    public function indexItemAfterSave(Event $event): void
    {
        $services = $event->getTarget()->getServiceLocator();
        $conn = $services->get('Omeka\Connection');
        Search\Index::createTables($conn);
        // The response holds the saved entity; the index reads a representation.
        $adapter = $event->getTarget();
        $item = $adapter->getRepresentation($event->getParam('response')->getContent());
        Search\Index::indexItem($conn, $item);
    }

    public function removeItemFromIndex(Event $event): void
    {
        $services = $event->getTarget()->getServiceLocator();
        $conn = $services->get('Omeka\Connection');
        Search\Index::createTables($conn);
        Search\Index::forgetItem($conn, (int) $event->getParam('request')->getId());
    }

    /** Batch updates: re-read each changed item and index it again. */
    public function indexItemsAfterBatch(Event $event): void
    {
        $services = $event->getTarget()->getServiceLocator();
        $conn = $services->get('Omeka\Connection');
        $api = $services->get('Omeka\ApiManager');
        Search\Index::createTables($conn);
        foreach ($event->getParam('request')->getIds() as $id) {
            $item = $api->read('items', $id)->getContent();
            Search\Index::indexItem($conn, $item);
        }
    }

    /**
     * Special Blocks used to be a separate module, "Society Blocks", whose
     * layouts were named society*. Pages that used them now point at the same
     * blocks here (special*). Safe to run more than once.
     */
    protected function renameOldLayouts(ServiceLocatorInterface $services): void
    {
        // During install/upgrade Omeka hasn't loaded this module's classes yet.
        require_once __DIR__ . '/src/SpecialBlocks.php';
        $connection = $services->get('Omeka\Connection');
        foreach (array_keys(SpecialBlocks::LAYOUTS) as $name) {
            $connection->executeStatement(
                'UPDATE site_page_block SET layout = ? WHERE layout = ?',
                [$name, 'society' . substr($name, strlen('special'))]
            );
        }
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        // A site's header and footer pages can't be opened at their own address.
        $sharedEventManager->attach('Omeka\Controller\Site\PageController', 'dispatch', [$this, 'hideRegionPages'], 10);
        // Deleting a page also deletes its leftover "Preview on site" copy.
        $sharedEventManager->attach('Omeka\Api\Adapter\SitePageAdapter', 'api.delete.pre', [$this, 'notePreviewCopy']);
        $sharedEventManager->attach('Omeka\Api\Adapter\SitePageAdapter', 'api.delete.post', [$this, 'deletePreviewCopy']);

        // Adds the "Visit info" group to each site's settings (Visit info block).
        $sharedEventManager->attach('Omeka\Form\SiteSettingsForm', 'form.add_elements', [Form\VisitInfoSettings::class, 'addElements']);
        $sharedEventManager->attach('Omeka\Form\SiteSettingsForm', 'form.add_input_filters', [Form\VisitInfoSettings::class, 'addInputFilters']);

        // Lets Special Blocks search items and item sets on several sites at once.
        $filter = new MultiSiteFilter();
        foreach (['Omeka\Api\Adapter\ItemAdapter', 'Omeka\Api\Adapter\ItemSetAdapter'] as $adapter) {
            $sharedEventManager->attach($adapter, 'api.search.query', $filter);
        }

        // VPB Search: keep the item index in step with every item change.
        $items = 'Omeka\Api\Adapter\ItemAdapter';
        $sharedEventManager->attach($items, 'api.create.post', [$this, 'indexItemAfterSave']);
        $sharedEventManager->attach($items, 'api.update.post', [$this, 'indexItemAfterSave']);
        $sharedEventManager->attach($items, 'api.delete.post', [$this, 'removeItemFromIndex']);
        $sharedEventManager->attach($items, 'api.batch_update.post', [$this, 'indexItemsAfterBatch']);
        // Item history: a snapshot before each change.
        $sharedEventManager->attach($items, 'api.update.pre', [$this, 'snapshotItemBeforeUpdate']);
        $sharedEventManager->attach($items, 'api.batch_update.pre', [$this, 'snapshotItemsBeforeBatch']);
        $sharedEventManager->attach($items, 'api.delete.post', [$this, 'forgetDraftOnDelete']);

        $sharedEventManager->attach(
            'Omeka\Controller\SiteAdmin\Page',
            'view.edit.page_actions',
            [$this, 'addEditorButton']
        );
    }

    /**
     * Add an "Open visual editor" button next to Save on Omeka's page edit screen.
     */
    public function addEditorButton(Event $event)
    {
        $view = $event->getTarget();
        $page = $event->getParam('resource');
        if (!$page || !$page->userIsAllowed('update')) {
            return;
        }
        $url = $view->url('admin/visual-page-builder', [
            'site-slug' => $page->site()->slug(),
            'page-id' => $page->id(),
            'action' => 'edit',
        ]);
        echo sprintf(
            '<a class="button" href="%s">%s</a>',
            $view->escapeHtmlAttr($url),
            $view->escapeHtml($view->translate('Open visual editor'))
        );
    }

    /** Preview copies to delete once the page being deleted is gone: [site id, slug]. */
    protected $previewCopies = [];

    /** Before a page is deleted, note where its "Preview on site" copy would be. */
    public function notePreviewCopy(Event $event): void
    {
        try {
            $page = $this->getServiceLocator()->get('Omeka\ApiManager')->read('site_pages', $event->getParam('request')->getId())->getContent();
        } catch (\Exception $e) {
            return;
        }
        if (strpos($page->slug(), SiteRegions::PREVIEW_PREFIX) !== 0) {
            $this->previewCopies[] = [$page->site()->id(), SiteRegions::PREVIEW_PREFIX . $page->id()];
        }
        try {
            PageHistory::forget($this->getServiceLocator()->get('Omeka\Connection'), $page->id());
        } catch (\Exception $e) {
            // No history table yet.
        }
    }

    /** After a page is deleted, delete its private "Preview on site" copy, if any. */
    public function deletePreviewCopy(Event $event): void
    {
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        while ($this->previewCopies) {
            [$siteId, $slug] = array_pop($this->previewCopies);
            try {
                foreach ($api->search('site_pages', ['site_id' => $siteId, 'slug' => $slug])->getContent() as $copy) {
                    $api->delete('site_pages', $copy->id());
                }
            } catch (\Exception $e) {
                // A leftover private copy is harmless.
            }
        }
    }
}
