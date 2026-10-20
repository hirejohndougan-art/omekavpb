<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Validator\Csrf;
use Laminas\View\Model\ViewModel;
use Omeka\Api\Exception\ValidationException;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Mvc\Exception\NotFoundException;
use Omeka\Mvc\Exception\PermissionDeniedException;
use VisualPageBuilder\Schema\Normalizer;

/**
 * The menu builder: arrange a site's menu (Omeka's own site navigation) and
 * choose a menu style, with a live preview.
 *
 * Routes (admin/visual-page-builder-menu):
 *   /admin/visual-page-builder/menu                    list of sites
 *   /admin/visual-page-builder/menu/:site-slug         the builder
 *   /admin/visual-page-builder/menu/:site-slug/save    POST JSON
 *
 * The menu is saved to the site's navigation, so every theme shows it. The
 * style is saved as the site setting "vpb_menu_style", which the Companion
 * Theme for Visual Page Builder uses to draw the menu (other themes ignore it).
 */
class MenuController extends AbstractActionController
{
    const CSRF_NAME = 'visual_page_builder_menu_csrf';

    /** Menu styles the Companion Theme can draw: value => [label, description]. */
    const STYLES = [
        'bar' => ['Simple bar', 'One row of links. Sub-pages appear on the pages themselves, not in the menu.'],
        'dropdown' => ['Drop-down menus', 'One row of links; sub-pages drop down below their parent.'],
        'mega' => ['Large panels', 'Each top link opens a wide panel listing its sub-pages in columns, and theirs under them. Good for big sites.'],
        'centered' => ['Centered logo', 'Your logo or name centered at the top, with the menu centered below it. Sub-pages drop down.'],
        'sidebar' => ['Side menu', 'The whole menu runs down the left side of the page, with sub-pages indented.'],
        // Added in v0.12.0.
        'centered-band' => ['Centered, menu in a color band', 'Logo centered; the menu runs underneath in a full-width band of your accent color. Sub-pages drop down.'],
        'masthead' => ['Newspaper masthead', 'A large name centered like a newspaper, with the menu underneath between two thin rules.'],
        'centered-dividers' => ['Centered, with dividers', 'Logo centered; the menu underneath with thin lines between the links.'],
        'centered-caps' => ['Centered, small capitals', 'Logo centered; a quiet menu underneath in spaced capital letters.'],
        'split' => ['Logo in the middle', 'Your logo in the center of the header, with half the menu on each side.'],
        'right' => ['Logo left, menu right', 'Logo on the left and the menu lined up on the right. Sub-pages drop down.'],
        'pills' => ['Rounded buttons', 'Each link is a rounded button; the current page is filled with your accent color.'],
        'tabs' => ['Tabs', 'Logo above; the menu underneath as tabs, with a line marking the current page.'],
        'two-tier' => ['Two rows', 'A thin colored strip on top with the site name and search, and the logo and menu below it.'],
        'button' => ['Menu button only', 'A tidy header with just a Menu button; it opens a panel listing every page. Good for long menus.'],
        // Vertical menus, added in v1.16.0 (with "Side menu" above). In the
        // Companion Theme's own header they run down the side of the page;
        // a Site menu block in a built header stacks them in its column.
        'sidebar-right' => ['Side menu on the right', 'The menu runs down the right side of the page, with sub-pages indented under their parent.'],
        'vertical-list' => ['Vertical list, everything showing', 'Every page listed down the side, with sub-pages indented under their parent and always showing.'],
        'vertical-flyout' => ['Vertical, sub-pages fly out', 'Links down the side; a link\'s sub-pages open out to the right beside it.'],
        'vertical-boxed' => ['Vertical, in a panel', 'Links stacked in a colored panel with a line between each, like a table of contents. Sub-pages open under their parent.'],
    ];

    /** Vertical styles (the theme gives these a side column). */
    const VERTICAL = ['sidebar', 'sidebar-right', 'vertical-list', 'vertical-flyout', 'vertical-boxed'];

    const DEFAULT_STYLE = 'dropdown';
    const LINK_TYPES = ['page', 'url', 'browse', 'browseItemSets'];
    const MAX_LINKS = 300;
    const MAX_DEPTH = 4;

    public function indexAction()
    {
        // The site list lives in the Visual Builder now.
        return $this->redirect()->toRoute('admin/visual-page-builder-pages');
    }

    public function editAction()
    {
        $site = $this->loadSite();
        $pages = [];
        foreach ($site->pages() as $page) {
            if (\VisualPageBuilder\SiteRegions::isHiddenSlug($page->slug())) {
                continue; // The site's header and footer don't go in menus.
            }
            $pages[] = ['id' => $page->id(), 'title' => $page->title()];
        }
        $styles = [];
        foreach (self::STYLES as $value => [$label, $info]) {
            $styles[] = ['value' => $value, 'label' => $this->translate($label), 'info' => $this->translate($info), 'vertical' => in_array($value, self::VERTICAL, true)];
        }
        $schemes = [];
        foreach (\VisualPageBuilder\MenuSettings::SCHEMES as $value => [$label, $colors]) {
            $schemes[] = ['value' => $value, 'label' => $this->translate($label), 'colors' => $colors];
        }
        // A header built in the visual editor shows the menu only through a
        // Site menu block: say so if it has none.
        $header = \VisualPageBuilder\SiteRegions::find($site, 'header');
        $headerWithoutMenu = false;
        if ($header && $header->blocks()) {
            $headerWithoutMenu = true;
            foreach ($header->blocks() as $block) {
                if ($block->layout() === 'specialSiteMenu') {
                    $headerWithoutMenu = false;
                }
            }
        }
        $csrf = new Csrf(['name' => self::CSRF_NAME, 'timeout' => 14400]);
        $config = [
            'headerWithoutMenu' => $headerWithoutMenu,
            'headerUrl' => $this->url()->fromRoute('admin/visual-page-builder-region', ['site-slug' => $site->slug(), 'region' => 'header']),
            'siteTitle' => $site->title(),
            'navigation' => $site->navigation(),
            'pages' => $pages,
            'style' => $this->siteSettings()->get('vpb_menu_style', self::DEFAULT_STYLE, $site->id()),
            'styles' => $styles,
            'csrf' => $csrf->getHash(),
            'saveUrl' => $this->url()->fromRoute('admin/visual-page-builder-menu', ['site-slug' => $site->slug(), 'action' => 'save']),
            'draftUrl' => $this->url()->fromRoute('admin/visual-page-builder-menu', ['site-slug' => $site->slug(), 'action' => 'draft']),
            'colors' => \VisualPageBuilder\MenuSettings::decode($this->siteSettings()->get('vpb_menu_colors', null, $site->id())),
            'schemes' => $schemes,
            'publicUrl' => $site->siteUrl(),
            'adminUrl' => $site->adminUrl(),
            'themeIsCompanion' => $site->theme() === 'visual-page-builder-companion',
        ];
        $view = new ViewModel(['config' => $config, 'site' => $site]);
        $view->setTerminal(true);
        return $view;
    }

    public function saveAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->json(['ok' => false, 'errors' => ['Saving requires a POST request.']], 405);
        }
        $site = $this->loadSite();
        $payload = json_decode($this->getRequest()->getContent(), true);
        $csrf = new Csrf(['name' => self::CSRF_NAME]);
        if (!is_array($payload) || !is_string($payload['csrf'] ?? null) || !$csrf->isValid($payload['csrf'])) {
            return $this->json(['ok' => false, 'errors' => ['Your session expired. Reload the menu builder and try again.']], 403);
        }
        $pageIds = [];
        foreach ($site->pages() as $page) {
            $pageIds[$page->id()] = true;
        }
        $count = 0;
        $navigation = $this->cleanLinks($payload['navigation'] ?? null, $pageIds, 1, $count);
        if ($navigation === null) {
            return $this->json(['ok' => false, 'errors' => ['The menu was malformed or too large, so nothing was saved.']], 400);
        }
        $style = array_key_exists($payload['style'] ?? '', self::STYLES) ? $payload['style'] : self::DEFAULT_STYLE;
        $colors = \VisualPageBuilder\MenuSettings::cleanColors($payload['colors'] ?? []);
        try {
            $site = $this->api()->update('sites', $site->id(), ['o:navigation' => $navigation], [], ['isPartial' => true])->getContent();
        } catch (ValidationException $e) {
            $messages = [];
            foreach ($e->getErrorStore()->getErrors() as $errors) {
                foreach ((array) $errors as $m) {
                    $messages[] = is_string($m) ? $m : json_encode($m);
                }
            }
            return $this->json(['ok' => false, 'errors' => $messages ?: ['Omeka rejected the menu.']], 422);
        }
        $this->siteSettings()->set('vpb_menu_style', $style, $site->id());
        $this->siteSettings()->set('vpb_menu_colors', $colors, $site->id());
        $this->forgetDraft($site);
        return $this->json(['ok' => true, 'navigation' => $site->navigation(), 'style' => $style, 'colors' => (object) $colors, 'csrf' => $csrf->getHash()]);
    }

    /**
     * Keep the Menu page's unsaved menu, style, and colors in the editor's
     * session, so the preview (the site itself, loaded with ?vpb_menu_draft=1
     * at desktop, tablet, or phone width) can show them. Nothing is saved to
     * the site. POST JSON {csrf, navigation, style, colors} → {ok, url}.
     */
    public function draftAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->json(['ok' => false, 'errors' => ['A POST request is needed.']], 405);
        }
        $site = $this->loadSite();
        $payload = json_decode($this->getRequest()->getContent(), true);
        $csrf = new Csrf(['name' => self::CSRF_NAME]);
        if (!is_array($payload) || !is_string($payload['csrf'] ?? null) || !$csrf->isValid($payload['csrf'])) {
            return $this->json(['ok' => false, 'errors' => ['Your session expired. Reload the menu builder and try again.']], 403);
        }
        $pageIds = [];
        foreach ($site->pages() as $page) {
            $pageIds[$page->id()] = true;
        }
        $count = 0;
        $navigation = $this->cleanLinks($payload['navigation'] ?? null, $pageIds, 1, $count);
        if ($navigation === null) {
            return $this->json(['ok' => false, 'errors' => ['The menu was malformed or too large.']], 400);
        }
        $session = new \Laminas\Session\Container(\VisualPageBuilder\View\Helper\VpbMenu::SESSION);
        $all = $session->offsetGet('drafts');
        $all = is_array($all) ? $all : [];
        $all[$site->id()] = [
            'navigation' => $navigation,
            'style' => array_key_exists($payload['style'] ?? '', self::STYLES) ? $payload['style'] : self::DEFAULT_STYLE,
            'colors' => \VisualPageBuilder\MenuSettings::cleanColors($payload['colors'] ?? []),
        ];
        $session->offsetSet('drafts', $all);
        // A page's own address: the site's address redirects (to its home
        // page or first menu link), which would drop ?vpb_menu_draft.
        $url = $site->siteUrl();
        $home = $site->homepage();
        if ($home) {
            $url = $home->siteUrl();
        } else {
            foreach ($site->pages() as $page) {
                if (!\VisualPageBuilder\SiteRegions::isHiddenSlug($page->slug())) {
                    $url = $page->siteUrl();
                    break;
                }
            }
        }
        return $this->json(['ok' => true, 'url' => $url . (strpos($url, '?') === false ? '?' : '&') . 'vpb_menu_draft=1']);
    }

    protected function forgetDraft(SiteRepresentation $site): void
    {
        $session = new \Laminas\Session\Container(\VisualPageBuilder\View\Helper\VpbMenu::SESSION);
        $all = $session->offsetGet('drafts');
        if (is_array($all) && isset($all[$site->id()])) {
            unset($all[$site->id()]);
            $session->offsetSet('drafts', $all);
        }
    }

    /**
     * Keep only well-formed links: known types, a label, the data each type
     * needs (an existing page, a safe address, a search query), limited depth
     * and count. Returns null if the input isn't a list.
     */
    protected function cleanLinks($links, array $pageIds, int $depth, int &$count): ?array
    {
        if (!is_array($links) || $depth > self::MAX_DEPTH) {
            return null;
        }
        $out = [];
        foreach ($links as $link) {
            if (!is_array($link) || !in_array($link['type'] ?? null, self::LINK_TYPES, true) || ++$count > self::MAX_LINKS) {
                return null;
            }
            $data = is_array($link['data'] ?? null) ? $link['data'] : [];
            $label = trim((string) ($data['label'] ?? ''));
            $clean = ['label' => $label];
            switch ($link['type']) {
                case 'page':
                    $id = (int) ($data['id'] ?? 0);
                    if (!isset($pageIds[$id])) {
                        continue 2; // A deleted page: drop the link.
                    }
                    $clean['id'] = $id;
                    $clean['is_public'] = true;
                    break;
                case 'url':
                    $clean['url'] = Normalizer::safeUrl($data['url'] ?? '');
                    if ($clean['url'] === '' || $label === '') {
                        continue 2;
                    }
                    break;
                case 'browse':
                case 'browseItemSets':
                    $clean['query'] = is_string($data['query'] ?? null) ? ltrim(trim($data['query']), '?') : '';
                    break;
            }
            $children = $this->cleanLinks($link['links'] ?? [], $pageIds, $depth + 1, $count);
            if ($children === null) {
                return null;
            }
            $out[] = ['type' => $link['type'], 'data' => $clean, 'links' => $children];
        }
        return $out;
    }

    protected function loadSite(): SiteRepresentation
    {
        try {
            $site = $this->api()->read('sites', ['slug' => $this->params('site-slug')])->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            throw new NotFoundException('Site not found.');
        }
        if (!$site->userIsAllowed('update')) {
            throw new PermissionDeniedException('You do not have permission to edit this site\'s menu.');
        }
        return $site;
    }

    protected function json(array $body, int $status = 200)
    {
        $response = $this->getResponse();
        $response->setStatusCode($status);
        $response->getHeaders()->addHeaderLine('Content-Type', 'application/json; charset=utf-8');
        $response->setContent(json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $response;
    }
}
