<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Validator\Csrf;
use Laminas\View\Model\ViewModel;
use Omeka\Api\Exception\ValidationException;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Mvc\Exception\NotFoundException;
use Omeka\Mvc\Exception\PermissionDeniedException;
use Omeka\Site\BlockLayout\Manager as BlockLayoutManager;
use VisualPageBuilder\Stdlib\ResourceLookup;

/**
 * The visual editor for one site page.
 *
 * Routes (admin/visual-page-builder): /admin/visual-page-builder/:site-slug/:page-id[/:action]
 *   edit    the editor page (default)
 *   save    POST the page's blocks as JSON
 *   lookup  GET item/asset search for the sidebar pickers
 *   preview POST a block's unsaved settings, get back what it will show
 *
 * Every action first checks that the user may update the page; Omeka's API
 * checks again when saving.
 */
class BuilderController extends AbstractActionController
{
    const CSRF_NAME = 'visual_page_builder_csrf';
    const MAX_BLOCKS = 1000;

    /**
     * @var ResourceLookup
     */
    protected $lookup;

    /**
     * @var BlockLayoutManager
     */
    protected $blockLayouts;

    /**
     * @var string[] Thumbnail sizes configured in Omeka (large, medium, square…)
     */
    protected $thumbnailTypes;

    public function __construct(ResourceLookup $lookup, BlockLayoutManager $blockLayouts, array $thumbnailTypes)
    {
        $this->lookup = $lookup;
        $this->blockLayouts = $blockLayouts;
        $this->thumbnailTypes = $thumbnailTypes;
    }

    public function editAction()
    {
        $page = $this->loadPage();
        $csrf = new Csrf(['name' => self::CSRF_NAME, 'timeout' => 14400]);
        $route = function ($action) use ($page) {
            return $this->url()->fromRoute('admin/visual-page-builder', [
                'site-slug' => $page->site()->slug(),
                'page-id' => $page->id(),
                'action' => $action,
            ]);
        };

        $layouts = $this->blockLayouts();
        // Read by the editor scripts as VPB.cfg (see asset/js/editor/core.js).
        $config = [
            'page' => $page->jsonSerialize(),
            'pageTitle' => $page->title(),
            'siteTitle' => $page->site()->title(),
            'siteId' => $page->site()->id(),
            'layouts' => $layouts,
            'stylesheets' => $this->stylesheets($layouts),
            'csrf' => $csrf->getHash(),
            'saveUrl' => $route('save'),
            'lookupUrl' => $route('lookup'),
            'previewUrl' => $route('preview'),
            // The page's public address, for "Copy link" on link names.
            'publicUrl' => $page->siteUrl(null, true),
            'themePreviewUrl' => $route('theme-preview'),
            // The order of the Special Blocks' palette groups.
            'subgroups' => array_map([$this, 'translate'], array_keys(\VisualPageBuilder\SpecialBlocks::GROUPS)),
            // Shared sections (reusable sections): this site's list, and
            // whether this page IS one.
            'isShared' => \VisualPageBuilder\SiteRegions::isSharedSlug($page->slug()),
            'sharedSections' => $this->sharedList($page),
            'makeSharedUrl' => $route('make-shared'),
            'sharedHubUrl' => $this->url()->fromRoute('admin/visual-page-builder-pages', ['site-slug' => $page->site()->slug()]) . '#vpb-shared',
            // Page history (earlier saved versions).
            'historyUrl' => $route('history'),
            // Back to this site's Visual Builder page (under Modules).
            'hubUrl' => $this->url()->fromRoute('admin/visual-page-builder-pages', ['site-slug' => $page->site()->slug()]),
            'menuUrl' => $this->url()->fromRoute('admin/visual-page-builder-menu', ['site-slug' => $page->site()->slug(), 'action' => 'edit']),
            'standardEditUrl' => $page->adminUrl('edit'),
            'publicUrl' => $page->siteUrl(),
            'pagesUrl' => $this->url()->fromRoute('admin/site/slug/page', [
                'site-slug' => $page->site()->slug(),
            ]),
            'thumbnailTypes' => $this->thumbnailTypes,
            'sitePages' => $this->sitePages($page),
            'subPages' => $this->subPages($page),
            // 'header' or 'footer' when editing the site's header or footer.
            'region' => \VisualPageBuilder\SiteRegions::regionOf($page),
            // A Theme Builder template: the screen it designs (the item field blocks show only here).
            'screen' => \VisualPageBuilder\Templates\Store::isTemplate($page) ? (\VisualPageBuilder\Templates\Store::meta($page)['screen'] ?? null) : null,
            // Block layouts the template's screen needs (they can't be deleted from it).
            'required' => $this->requiredLayouts($page),
            // The site's built header and footer, shown (read-only) around the
            // page in the editor; clicking one offers to go and edit it.
            'regionHtml' => $this->regionHtml($page),
            // When editing a header or footer opened from a page: that page.
            'fromPage' => $this->fromPage($page),
            'regionUrls' => [
                'header' => $this->url()->fromRoute('admin/visual-page-builder-region', ['site-slug' => $page->site()->slug(), 'region' => 'header']),
                'footer' => $this->url()->fromRoute('admin/visual-page-builder-region', ['site-slug' => $page->site()->slug(), 'region' => 'footer']),
            ],
            'sites' => $this->lookup->sites(),
            'itemSets' => $this->lookup->itemSets(),
            'refs' => $this->lookup->pageReferences($page, $this->schemas($layouts)),
        ];

        $view = new ViewModel(['config' => $config, 'page' => $page]);
        $view->setTemplate('visual-page-builder/admin/builder/edit');
        $view->setTerminal(true);
        return $view;
    }

    public function saveAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->jsonResponse(['ok' => false, 'errors' => ['Saving requires a POST request.']], 405);
        }
        $page = $this->loadPage();

        $payload = json_decode($this->getRequest()->getContent(), true);
        if (!is_array($payload)) {
            return $this->jsonResponse(['ok' => false, 'errors' => ['The editor sent data the server could not read.']], 400);
        }

        $csrf = new Csrf(['name' => self::CSRF_NAME]);
        if (!isset($payload['csrf']) || !is_string($payload['csrf']) || !$csrf->isValid($payload['csrf'])) {
            return $this->jsonResponse(['ok' => false, 'errors' => ['Your editing session expired. Reload the editor and try again. Copy any text you need first.']], 403);
        }

        if (!isset($payload['blocks']) || !is_array($payload['blocks']) || count($payload['blocks']) > self::MAX_BLOCKS) {
            return $this->jsonResponse(['ok' => false, 'errors' => ['The page layout was missing or too large.']], 400);
        }

        $blocks = [];
        foreach ($payload['blocks'] as $input) {
            $block = $this->cleanBlock($input);
            if ($block === null) {
                return $this->jsonResponse(['ok' => false, 'errors' => ['One of the blocks was malformed, so nothing was saved.']], 400);
            }
            $blocks[] = $block;
        }

        $layoutData = $page->layoutData();
        if (!is_array($layoutData)) {
            $layoutData = [];
        }
        $layoutData['grid_columns'] = 12;
        $layoutData['grid_column_gap'] ??= 16;
        $layoutData['grid_row_gap'] ??= 16;
        $layoutData['visual_page_builder'] = ['version' => 1];
        // A theme "page template" draws blocks its own way and would ignore
        // the sections and columns, so builder pages use the standard one.
        unset($layoutData['template_name']);

        // A full (non-partial) update: Omeka replaces the block list with ours.
        $data = [
            'o:title' => $page->title(),
            'o:slug' => $page->slug(),
            'o:is_public' => $page->isPublic(),
            'o:layout' => 'grid',
            'o:layout_data' => $layoutData,
            'o:block' => $blocks,
        ];

        try {
            // Keep the page as it was, for Page history.
            try {
                $user = $this->identity();
                \VisualPageBuilder\PageHistory::keep($this->connection(), $page, $user ? $user->getName() : '');
            } catch (\Exception $e) {
                // History is a convenience; never block a save over it.
            }
            $response = $this->api(null, true)->update('site_pages', $page->id(), $data);
        } catch (ValidationException $e) {
            $messages = [];
            foreach ($e->getErrorStore()->getErrors() as $fieldErrors) {
                foreach ((array) $fieldErrors as $message) {
                    $messages[] = is_string($message) ? $message : json_encode($message);
                }
            }
            return $this->jsonResponse(['ok' => false, 'errors' => $messages ?: ['Omeka rejected the page.']], 422);
        } catch (\Laminas\Http\Exception\ExceptionInterface | \ErrorException $e) {
            // A block that fetches from the web while saving (oEmbed) couldn't
            // reach it. Omeka usually reports this itself; some servers raise
            // the network error instead. Nothing was saved.
            return $this->jsonResponse(['ok' => false, 'errors' => [sprintf(
                $this->translate('Omeka couldn\'t reach the web to fetch an embedded item (oEmbed): %s'), // @translate
                $e->getMessage()
            )]], 422);
        }

        $saved = $response->getContent();
        // The page is saved, so its "Preview on site" copy isn't needed.
        try {
            foreach ($this->api()->search('site_pages', ['site_id' => $page->site()->id(), 'slug' => \VisualPageBuilder\SiteRegions::PREVIEW_PREFIX . $page->id()])->getContent() as $copy) {
                $this->api()->delete('site_pages', $copy->id());
            }
        } catch (\Exception $e) {
            // Leaving a private copy behind is harmless.
        }
        return $this->jsonResponse([
            'ok' => true,
            'page' => $saved->jsonSerialize(),
            'csrf' => $csrf->getHash(),
        ]);
    }

    /**
     * Live preview of one block from its unsaved settings.
     * POST JSON {csrf, layout, data} → {ok, html} or {ok, items}.
     *
     * - A layout with a visualPageBuilderPreview() method (see README, "Blocks
     *   from other modules") returns its own HTML.
     * - Omeka's Browse preview returns the items its search would show.
     * Other layouts are previewed by the editor itself and get a 400 here.
     */
    /** Rendered header and footer HTML for the editor ('' when not built). */
    protected function connection(): \Doctrine\DBAL\Connection
    {
        return $this->getEvent()->getApplication()->getServiceManager()->get('Omeka\Connection');
    }

    /**
     * Page history: GET → the kept versions of this page; GET ?version=id →
     * that version's page data (title, layout, layout data, blocks), for the
     * editor to load as unsaved changes.
     */
    public function historyAction()
    {
        $page = $this->loadPage();
        $id = (int) $this->params()->fromQuery('version', 0);
        try {
            if ($id) {
                $data = \VisualPageBuilder\PageHistory::get($this->connection(), $page->id(), $id);
                return $data ? $this->jsonResponse(['ok' => true, 'page' => $data]) : $this->jsonResponse(['ok' => false, 'errors' => ['That version wasn\'t found.']], 404);
            }
            return $this->jsonResponse(['ok' => true, 'versions' => \VisualPageBuilder\PageHistory::listFor($this->connection(), $page->id())]);
        } catch (\Exception $e) {
            return $this->jsonResponse(['ok' => false, 'errors' => ['Page history isn\'t available. Click Upgrade for Visual Page Builder under Modules.']], 500);
        }
    }

    /** [{id, title, url}] of the site's shared sections (not this page). */
    protected function sharedList(SitePageRepresentation $page): array
    {
        $out = [];
        foreach ($page->site()->pages() as $p) {
            if (\VisualPageBuilder\SiteRegions::isSharedSlug($p->slug()) && $p->id() !== $page->id()) {
                $out[] = ['id' => $p->id(), 'title' => $p->title(), 'url' => $this->url()->fromRoute('admin/visual-page-builder', ['site-slug' => $page->site()->slug(), 'page-id' => $p->id()])];
            }
        }
        usort($out, function ($a, $b) {
            return strcasecmp($a['title'], $b['title']);
        });
        return $out;
    }

    /**
     * "Make this a shared section": saves the posted blocks (one section) as a
     * new shared section and returns its id; the editor then shows it on this
     * page through a Shared section block.
     */
    public function makeSharedAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->jsonResponse(['ok' => false, 'errors' => ['This requires a POST request.']], 405);
        }
        $page = $this->loadPage();
        $payload = json_decode($this->getRequest()->getContent(), true);
        $csrf = new Csrf(['name' => self::CSRF_NAME]);
        if (!is_array($payload) || !is_string($payload['csrf'] ?? null) || !$csrf->isValid($payload['csrf'])) {
            return $this->jsonResponse(['ok' => false, 'errors' => ['Your editing session expired. Reload the editor and try again.']], 403);
        }
        if (!isset($payload['blocks']) || !is_array($payload['blocks']) || count($payload['blocks']) > self::MAX_BLOCKS) {
            return $this->jsonResponse(['ok' => false, 'errors' => ['The section was missing or too large.']], 400);
        }
        $blocks = [];
        foreach ($payload['blocks'] as $input) {
            $block = $this->cleanBlock($input);
            if ($block === null) {
                return $this->jsonResponse(['ok' => false, 'errors' => ['One of the blocks was malformed.']], 400);
            }
            $blocks[] = $block;
        }
        $title = trim((string) ($payload['title'] ?? '')) ?: 'Shared section';
        try {
            $shared = $this->api(null, true)->create('site_pages', [
                'o:site' => ['o:id' => $page->site()->id()],
                'o:title' => mb_substr($title, 0, 190),
                'o:slug' => \VisualPageBuilder\SiteRegions::SHARED_PREFIX . bin2hex(random_bytes(4)),
                'o:is_public' => true,
                'o:layout' => 'grid',
                'o:layout_data' => ['grid_columns' => 12, 'grid_column_gap' => 16, 'grid_row_gap' => 16, 'visual_page_builder' => ['version' => 1]],
                'o:block' => $blocks,
            ])->getContent();
        } catch (ValidationException $e) {
            return $this->jsonResponse(['ok' => false, 'errors' => ['Omeka couldn\'t save the shared section.']], 422);
        }
        return $this->jsonResponse(['ok' => true, 'id' => $shared->id(), 'title' => $shared->title(),
            'url' => $this->url()->fromRoute('admin/visual-page-builder', ['site-slug' => $page->site()->slug(), 'page-id' => $shared->id()]),
            'csrf' => $csrf->getHash()]);
    }

    protected function regionHtml(SitePageRepresentation $page): array
    {
        $out = ['header' => '', 'footer' => ''];
        if (\VisualPageBuilder\SiteRegions::isSharedSlug($page->slug())) {
            return $out; // A shared section is edited on its own.
        }
        $helper = $this->viewHelpers()->get('vpbSiteRegion');
        foreach (array_keys($out) as $region) {
            if (\VisualPageBuilder\SiteRegions::regionOf($page) === $region) {
                continue; // That's the one being edited.
            }
            try {
                $out[$region] = (string) $helper($page->site(), $region);
            } catch (\Throwable $e) {
                $out[$region] = ''; // Leave it out rather than break the editor.
            }
        }
        return $out;
    }

    /** {id, title, url} of the page a header/footer was opened from (?from=), or null. */
    protected function fromPage(SitePageRepresentation $page): ?array
    {
        $from = (int) $this->params()->fromQuery('from', 0);
        if (!$from || !\VisualPageBuilder\SiteRegions::regionOf($page)) {
            return null;
        }
        foreach ($page->site()->pages() as $p) {
            if ($p->id() === $from && !\VisualPageBuilder\SiteRegions::isHiddenSlug($p->slug())) {
                return ['id' => $p->id(), 'title' => $p->title(), 'url' => $this->url()->fromRoute('admin/visual-page-builder', ['site-slug' => $page->site()->slug(), 'page-id' => $p->id()])];
            }
        }
        return null;
    }

    /**
     * "Preview on site": the unsaved page, shown on the real site with its
     * theme, header, and footer. Saves the posted blocks to a private copy
     * of the page (slug vpb-preview-<page id>, hidden from page lists and
     * from visitors) and returns its address for the editor to show in a
     * frame. The copy is replaced on each preview and deleted when the page
     * is saved.
     */
    public function themePreviewAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->jsonResponse(['ok' => false, 'errors' => ['Previewing requires a POST request.']], 405);
        }
        $page = $this->loadPage();
        $payload = json_decode($this->getRequest()->getContent(), true);
        $csrf = new Csrf(['name' => self::CSRF_NAME]);
        if (!is_array($payload) || !is_string($payload['csrf'] ?? null) || !$csrf->isValid($payload['csrf'])) {
            return $this->jsonResponse(['ok' => false, 'errors' => ['Your editing session expired. Reload the editor and try again.']], 403);
        }
        $slug = \VisualPageBuilder\SiteRegions::PREVIEW_PREFIX . $page->id();
        // "Back to editing": the copy isn't needed any more.
        if (!empty($payload['discard'])) {
            foreach ($this->api()->search('site_pages', ['site_id' => $page->site()->id(), 'slug' => $slug])->getContent() as $copy) {
                $this->api()->delete('site_pages', $copy->id());
            }
            return $this->jsonResponse(['ok' => true, 'csrf' => $csrf->getHash()]);
        }
        if (!isset($payload['blocks']) || !is_array($payload['blocks']) || count($payload['blocks']) > self::MAX_BLOCKS) {
            return $this->jsonResponse(['ok' => false, 'errors' => ['The page layout was missing or too large.']], 400);
        }
        $blocks = [];
        foreach ($payload['blocks'] as $input) {
            $block = $this->cleanBlock($input);
            if ($block === null) {
                return $this->jsonResponse(['ok' => false, 'errors' => ['One of the blocks was malformed.']], 400);
            }
            $blocks[] = $block;
        }
        $layoutData = is_array($page->layoutData()) ? $page->layoutData() : [];
        $layoutData['grid_columns'] = 12;
        $layoutData['grid_column_gap'] ??= 16;
        $layoutData['grid_row_gap'] ??= 16;
        $layoutData['visual_page_builder'] = ['version' => 1];
        unset($layoutData['template_name']);
        $data = [
            'o:site' => ['o:id' => $page->site()->id()],
            // Named so it can't be mistaken for the real page in Omeka's page list.
            'o:title' => sprintf($this->translate('Preview copy of "%s" (not published)'), $page->title()),
            'o:slug' => $slug,
            'o:is_public' => false,
            'o:layout' => 'grid',
            'o:layout_data' => $layoutData,
            'o:block' => $blocks,
        ];
        try {
            $existing = $this->api()->search('site_pages', ['site_id' => $page->site()->id(), 'slug' => $slug])->getContent();
            $copy = $existing
                ? $this->api(null, true)->update('site_pages', $existing[0]->id(), $data)->getContent()
                : $this->api(null, true)->create('site_pages', $data)->getContent();
        } catch (ValidationException $e) {
            return $this->jsonResponse(['ok' => false, 'errors' => ['Omeka couldn\'t make the preview.']], 422);
        }
        return $this->jsonResponse(['ok' => true, 'url' => $copy->siteUrl(), 'csrf' => $csrf->getHash()]);
    }

    public function previewAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->jsonResponse(['ok' => false, 'errors' => ['Previews need a POST request.']], 405);
        }
        $page = $this->loadPage();
        $payload = json_decode($this->getRequest()->getContent(), true);
        $csrf = new Csrf(['name' => self::CSRF_NAME]);
        if (!is_array($payload) || !isset($payload['csrf']) || !is_string($payload['csrf']) || !$csrf->isValid($payload['csrf'])) {
            return $this->jsonResponse(['ok' => false, 'errors' => ['Your editing session expired. Reload the editor.']], 403);
        }
        $name = $payload['layout'] ?? null;
        $data = $payload['data'] ?? [];
        if (!is_string($name) || !is_array($data) || !in_array($name, $this->blockLayouts->getRegisteredNames(), true)) {
            return $this->jsonResponse(['ok' => false, 'errors' => ['Unknown block type.']], 400);
        }
        $layout = $this->blockLayouts->get($name);
        if (method_exists($layout, 'visualPageBuilderPreview')) {
            $view = $this->getEvent()->getApplication()->getServiceManager()->get('ViewRenderer');
            return $this->jsonResponse(['ok' => true, 'html' => (string) $layout->visualPageBuilderPreview($view, $data, $page->site())]);
        }
        if ($name === 'browsePreview') {
            return $this->jsonResponse(['ok' => true, 'items' => $this->lookup->browsePreview($data, $page->site())]);
        }
        return $this->jsonResponse(['ok' => false, 'errors' => ['This block type has no server preview.']], 400);
    }

    /**
     * Search or look up items and assets for the sidebar pickers.
     * GET ?type=items|assets and either &q=search or &ids=1,2,3
     */
    public function lookupAction()
    {
        $this->loadPage(); // Same permission check as the editor itself.
        $type = $this->params()->fromQuery('type', 'items') === 'assets' ? 'assets' : 'items';
        $q = trim((string) $this->params()->fromQuery('q', ''));
        $ids = ResourceLookup::parseIds($this->params()->fromQuery('ids', ''));

        if ($type === 'assets') {
            $rows = $ids ? $this->lookup->assets($ids) : $this->lookup->searchAssets($q);
        } else {
            $rows = $ids ? $this->lookup->items($ids) : $this->lookup->searchItems($q);
        }
        return $this->jsonResponse(['ok' => true, $type => $rows]);
    }

    /**
     * Block layouts Omeka offers, for the palette. Block groups are left out:
     * sections are managed by the editor itself.
     *
     * A layout from another module can describe its settings for the sidebar
     * by having a public visualPageBuilderSchema() method (see README,
     * "Blocks from other modules"); its schema is passed along as `schema`.
     */
    protected function blockLayouts(): array
    {
        $layouts = [];
        foreach ($this->blockLayouts->getRegisteredNames(true) as $name) {
            if ($name === 'blockGroup') {
                continue;
            }
            $layout = $this->blockLayouts->get($name);
            $row = [
                'name' => $name,
                'label' => $this->translate($layout->getLabel()),
            ];
            if (method_exists($layout, 'visualPageBuilderSchema')) {
                $schema = $layout->visualPageBuilderSchema();
                if (isset($schema['fields']) && is_array($schema['fields'])) {
                    $row['schema'] = ['fields' => $this->translateFields($schema['fields'])];
                }
                // Pages only (the default), header and footer only, or both.
                $row['placement'] = in_array($schema['placement'] ?? 'page', ['page', 'region', 'both', 'item'], true) ? ($schema['placement'] ?? 'page') : 'page';
                if (isset($schema['paletteGroup']) && is_string($schema['paletteGroup'])) {
                    $row['group'] = $this->translate($schema['paletteGroup']);
                    $row['groupFeatured'] = !empty($schema['paletteFeatured']);
                }
                foreach (['description', 'goodFor', 'worksWith', 'paletteSubgroup'] as $key) {
                    if (isset($schema[$key]) && is_string($schema[$key]) && $schema[$key] !== '') {
                        $row[$key === 'paletteSubgroup' ? 'subgroup' : $key] = $this->translate($schema[$key]);
                    }
                }
                if (!empty($schema['stylesheets']) && is_array($schema['stylesheets'])) {
                    $row['stylesheets'] = $schema['stylesheets'];
                }
                $row['preview'] = !empty($schema['preview']) && method_exists($layout, 'visualPageBuilderPreview');
            }
            $layouts[] = $row;
        }
        return $layouts;
    }

    /**
     * Translate the labels, help text, placeholders, and option names in a
     * schema (they are written in English with @translate markers).
     */
    protected function translateFields(array $fields): array
    {
        foreach ($fields as &$field) {
            foreach (['label', 'info', 'placeholder', 'group'] as $key) {
                if (isset($field[$key]) && is_string($field[$key])) {
                    $field[$key] = $this->translate($field[$key]);
                }
            }
            if (isset($field['options']) && is_array($field['options'])) {
                // Keep option order: a JSON object's key order isn't reliable
                // for numeric-looking keys, so send [value, label] pairs.
                $options = [];
                foreach ($field['options'] as $value => $label) {
                    $options[] = [(string) $value, $this->translate((string) $label)];
                }
                $field['options'] = $options;
            }
            if (isset($field['fields']) && is_array($field['fields'])) {
                $field['fields'] = $this->translateFields($field['fields']);
            }
        }
        return $fields;
    }

    /**
     * The pages under this page in the site's navigation, as a tree of
     * {title, children}, for the Table of contents preview. Empty if the page
     * isn't in the navigation.
     */
    protected function subPages(SitePageRepresentation $page): array
    {
        $titles = [];
        foreach ($page->site()->pages() as $p) {
            $titles[$p->id()] = $p->title();
        }
        $tree = function (array $links, int $depth) use (&$tree, $titles) {
            $out = [];
            foreach ($links as $link) {
                $data = $link['data'] ?? [];
                $label = trim((string) ($data['label'] ?? ''));
                if ($label === '' && ($link['type'] ?? '') === 'page') {
                    $label = $titles[$data['id'] ?? 0] ?? '';
                }
                $out[] = [
                    'title' => $label !== '' ? $label : ucfirst((string) ($link['type'] ?? 'link')),
                    'children' => $depth < 6 ? $tree($link['links'] ?? [], $depth + 1) : [],
                ];
            }
            return $out;
        };
        $find = function (array $links) use (&$find, $page) {
            foreach ($links as $link) {
                if (($link['type'] ?? '') === 'page' && (int) ($link['data']['id'] ?? 0) === $page->id()) {
                    return $link['links'] ?? [];
                }
                $found = $find($link['links'] ?? []);
                if ($found !== null) {
                    return $found;
                }
            }
            return null;
        };
        $children = $find($page->site()->navigation());
        return $children ? $tree($children, 1) : [];
    }

    /**
     * Stylesheet URLs other modules' blocks need for their previews. Each entry
     * is ['module' => 'Name', 'file' => 'css/…'], resolved like Omeka's assetUrl().
     */
    protected function stylesheets(array &$layouts): array
    {
        $assetUrl = $this->viewHelpers()->get('assetUrl');
        $urls = [];
        foreach ($layouts as &$layout) {
            foreach ($layout['stylesheets'] ?? [] as $sheet) {
                if (isset($sheet['module'], $sheet['file']) && is_string($sheet['module']) && is_string($sheet['file'])) {
                    $urls[] = $assetUrl($sheet['file'], $sheet['module']);
                }
            }
            unset($layout['stylesheets']);
        }
        return array_values(array_unique($urls));
    }

    /** layout name => schema fields, for layouts that have a schema. */
    protected function schemas(array $layouts): array
    {
        $out = [];
        foreach ($layouts as $layout) {
            if (isset($layout['schema'])) {
                $out[$layout['name']] = $layout['schema']['fields'];
            }
        }
        return $out;
    }

    /**
     * The site's pages, for the Asset block's "Link to page" choice.
     */
    protected function sitePages(SitePageRepresentation $page): array
    {
        $rows = [];
        foreach ($page->site()->pages() as $sitePage) {
            if (\VisualPageBuilder\SiteRegions::isHiddenSlug($sitePage->slug())) {
                continue; // The site's header and footer aren't link targets.
            }
            $rows[] = ['id' => $sitePage->id(), 'title' => $sitePage->title()];
        }
        return $rows;
    }

    /**
     * Keep only the keys Omeka expects for a block, with the right types.
     */
    protected function cleanBlock($input)
    {
        if (!is_array($input) || !isset($input['o:layout']) || !is_string($input['o:layout'])
            || !preg_match('/^[A-Za-z0-9_.:-]{1,190}$/', $input['o:layout'])
        ) {
            return null;
        }
        $data = $input['o:data'] ?? [];
        $layoutData = $input['o:layout_data'] ?? [];
        if (!is_array($data) || !is_array($layoutData)) {
            return null;
        }
        $attachments = [];
        foreach ($input['o:attachment'] ?? [] as $att) {
            if (!is_array($att)) {
                continue;
            }
            $itemId = $att['o:item']['o:id'] ?? null;
            $mediaId = $att['o:media']['o:id'] ?? null;
            $attachments[] = [
                'o:item' => ['o:id' => is_numeric($itemId) ? (int) $itemId : null],
                'o:media' => is_numeric($mediaId) ? ['o:id' => (int) $mediaId] : null,
                'o:caption' => isset($att['o:caption']) && is_string($att['o:caption']) ? $att['o:caption'] : '',
            ];
        }
        return [
            'o:layout' => $input['o:layout'],
            'o:data' => $data,
            'o:layout_data' => $layoutData,
            'o:attachment' => $attachments,
        ];
    }

    /**
     * Load the page from the route and make sure the user may edit it.
     */
    protected function loadPage(): SitePageRepresentation
    {
        $pageId = (int) $this->params('page-id');
        try {
            $page = $this->api()->read('site_pages', $pageId)->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            throw new NotFoundException('Page not found.');
        }
        if ($page->site()->slug() !== $this->params('site-slug')) {
            throw new NotFoundException('Page not found in this site.');
        }
        if (!$page->userIsAllowed('update')) {
            throw new PermissionDeniedException('You do not have permission to edit this page.');
        }
        return $page;
    }

    protected function jsonResponse(array $body, $status = 200)
    {
        $response = $this->getResponse();
        $response->setStatusCode($status);
        $response->getHeaders()->addHeaderLine('Content-Type', 'application/json; charset=utf-8');
        $response->setContent(json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $response;
    }

    /** The block layouts a Theme Builder template must keep (empty elsewhere). */
    protected function requiredLayouts($page): array
    {
        if (!\VisualPageBuilder\Templates\Store::isTemplate($page)) {
            return [];
        }
        $screen = \VisualPageBuilder\Templates\Store::meta($page)['screen'] ?? '';
        return \VisualPageBuilder\Templates\Screens::SCREENS[$screen]['required'] ?? [];
    }
}
