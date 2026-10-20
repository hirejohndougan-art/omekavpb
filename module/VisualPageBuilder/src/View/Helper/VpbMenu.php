<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\View\Helper;

use Laminas\Navigation\Service\ConstructedNavigationFactory;
use Laminas\Session\Container;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\SiteRepresentation;
use Interop\Container\ContainerInterface;
use VisualPageBuilder\Controller\Admin\MenuController;
use VisualPageBuilder\MenuSettings;

/**
 * How to draw a site's menu: style, colors, and the links. Used by the
 * Companion Theme's header and by the Site menu block, so both follow the
 * Menu page.
 *
 *   $this->vpbMenu($site) → [
 *     'style' => 'dropdown', 'colors' => [...], 'vars' => '--pb-nav-bg:#…',
 *     'classes' => 'pb-nav-colored …', 'container' => Navigation|null,
 *     'draft' => bool,
 *   ]
 *
 * 'container' is null when the site's saved menu should be used. While the
 * Menu page previews unsaved changes, the page is loaded with
 * ?vpb_menu_draft=1 and the editor's draft (kept in their session by
 * MenuController::draftAction) is used instead, only for someone who may
 * edit the site.
 */
class VpbMenu extends AbstractHelper
{
    const SESSION = 'VisualPageBuilderMenuDraft';

    /** @var ContainerInterface */
    protected $services;

    /** Per site id, for this request. */
    protected $cache = [];

    public function __construct(ContainerInterface $services)
    {
        $this->services = $services;
    }

    public function __invoke(SiteRepresentation $site): array
    {
        if (isset($this->cache[$site->id()])) {
            return $this->cache[$site->id()];
        }
        $settings = $this->services->get('Omeka\Settings\Site');
        $style = $settings->get('vpb_menu_style', null, $site->id());
        $colors = MenuSettings::decode($settings->get('vpb_menu_colors', null, $site->id()));
        $container = null;
        $draft = $this->draft($site);
        if ($draft) {
            $style = $draft['style'];
            $colors = MenuSettings::cleanColors($draft['colors']);
            $container = $this->container($site, $draft['navigation']);
        }
        if ($style !== null && !isset(MenuController::STYLES[$style])) {
            $style = MenuController::DEFAULT_STYLE;
        }
        return $this->cache[$site->id()] = [
            'style' => $style,
            'colors' => $colors,
            'vars' => MenuSettings::cssVars($colors),
            'classes' => MenuSettings::classes($colors),
            'container' => $container,
            'draft' => (bool) $draft,
        ];
    }

    /** The Menu page's unsaved draft for this site, if this request asks for it and may see it. */
    protected function draft(SiteRepresentation $site): ?array
    {
        $request = $this->services->get('Request');
        if (!method_exists($request, 'getQuery') || !$request->getQuery('vpb_menu_draft')) {
            return null;
        }
        try {
            if (!$site->userIsAllowed('update')) {
                return null;
            }
            $session = new Container(self::SESSION);
            $all = $session->offsetGet('drafts');
        } catch (\Exception $e) {
            return null;
        }
        $draft = is_array($all) ? ($all[$site->id()] ?? null) : null;
        return is_array($draft) && isset($draft['style'], $draft['navigation']) ? $draft : null;
    }

    /** A navigation container from a (cleaned) navigation array, as Omeka builds the site's own. */
    protected function container(SiteRepresentation $site, array $navigation)
    {
        $manager = $this->services->get('Omeka\Site\NavigationLinkManager');
        $translator = $this->services->get('Omeka\Site\NavigationTranslator');
        $build = function ($links) use (&$build, $manager, $translator, $site) {
            $out = [];
            foreach ($links as $data) {
                try {
                    $type = $manager->get($data['type']);
                    $page = $type->toZend($data['data'], $site);
                    $page['label'] = $translator->getLinkLabel($type, $data['data'], $site);
                } catch (\Exception $e) {
                    continue;
                }
                if (!empty($data['links'])) {
                    $page['pages'] = $build($data['links']);
                }
                $out[] = $page;
            }
            return $out;
        };
        $pages = $build($navigation);
        if (!$pages) {
            $pages = [['label' => 'Home', 'route' => 'site', 'params' => ['site-slug' => $site->slug()]]];
        }
        $factory = new ConstructedNavigationFactory($pages);
        return $factory($this->services, '');
    }
}
