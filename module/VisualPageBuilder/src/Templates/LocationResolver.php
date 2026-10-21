<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Templates;

use Omeka\Api\Representation\SiteRepresentation;

/**
 * Picks the header, footer and body template for the current public request,
 * adapted from Blueprint's Theme_Builder::resolve().
 *
 * Blueprint resolves all locations (header, footer, single, archive, 404) in
 * one pass against the current WordPress request. This class does the same
 * for Omeka's route structure: it reads the request context (which screen,
 * which record, which page) and finds the best-matching live template for
 * each location.
 *
 * Usage (in the Companion Theme's layout or a view helper):
 *
 *   $resolver = new LocationResolver();
 *   $matched = $resolver->resolve($site, $context);
 *   // $matched['header'] is the template array or null
 *   // $matched['footer'] is the template array or null
 *   // $matched[$screen] is the body template or null
 */
class LocationResolver
{
    /** The locations resolved in one pass (like Blueprint's header/footer/single/archive/404). */
    const LOCATIONS = ['header', 'footer'];

    /**
     * Resolve all locations for the current request.
     *
     * @param SiteRepresentation $site  The current site.
     * @param array $context  The request context:
     *   - screen: string (the Screens key, e.g. 'item', 'browse-items')
     *   - record: array (id, resource_template, item_sets, class)
     *   - page_id: int|null (the site page id, for page-specific conditions)
     *   - is_home: bool (true on the site's landing page)
     * @return array  Keyed by location ('header', 'footer', and the screen key).
     */
    public function resolve(SiteRepresentation $site, array $context): array
    {
        $templates = Store::all($site);
        $matched = [];

        // Resolve header and footer (these can vary by screen/record).
        foreach (self::LOCATIONS as $location) {
            $matched[$location] = DisplayConditions::best($templates, $location, $context);
        }

        // Resolve the body template for the current screen.
        $screen = $context['screen'] ?? '';
        if ($screen !== '' && Screens::exists($screen)) {
            // Body templates use the existing Resolver (scope-based precedence)
            // when they have no display conditions, and DisplayConditions when
            // they do. This keeps backward compatibility with templates made
            // before display conditions were added.
            $best = DisplayConditions::best($templates, $screen, $context);
            if ($best === null) {
                // Fall back to the original Resolver for templates without
                // display conditions (the scope/value system).
                $resolver = new Resolver();
                $record = $context['record'] ?? [];
                $best = $resolver->choose($screen, $record, Store::live($site));
            }
            $matched[$screen] = $best;
        }

        return $matched;
    }

    /**
     * Build the request context from Omeka's MVC route match and the current
     * record. Called by the Companion Theme or a view helper.
     *
     * @param string $routeName  The matched route name (e.g. 'site/page').
     * @param array $params  Route params (site-slug, id, page-slug…).
     * @param mixed $record  The current record representation, or null.
     * @param SiteRepresentation $site  The site.
     * @return array  The context array for resolve().
     */
    public static function contextFromRoute(string $routeName, array $params, $record, SiteRepresentation $site): array
    {
        $context = [
            'screen' => '',
            'record' => [],
            'page_id' => null,
            'is_home' => false,
        ];

        // Map Omeka route names to VPB screen keys.
        $routeMap = [
            'site/resource-id' => null, // needs the controller param
            'site/item' => 'item',
            'site/media' => 'media',
            'site/item-set' => 'item-set',
            'site/item-set-item' => 'item',
        ];

        // Determine the screen from the route.
        if (isset($routeMap[$routeName])) {
            $context['screen'] = $routeMap[$routeName] ?? '';
        }

        // Browse routes.
        if ($routeName === 'site' && ($params['controller'] ?? '') === 'item') {
            $context['screen'] = isset($params['id']) ? 'item' : 'browse-items';
        }
        if ($routeName === 'site' && ($params['controller'] ?? '') === 'item-set') {
            $context['screen'] = isset($params['id']) ? 'item-set' : 'browse-item-sets';
        }
        if ($routeName === 'site' && ($params['controller'] ?? '') === 'media') {
            $context['screen'] = isset($params['id']) ? 'media' : '';
        }

        // resource-id route: used for item/show, item-set/show etc.
        if ($routeName === 'site/resource-id') {
            $ctrl = $params['controller'] ?? '';
            if ($ctrl === 'Omeka\Controller\Site\Item' || $ctrl === 'item') {
                $context['screen'] = 'item';
            } elseif ($ctrl === 'Omeka\Controller\Site\ItemSet' || $ctrl === 'item-set') {
                $context['screen'] = 'item-set';
            } elseif ($ctrl === 'Omeka\Controller\Site\Media' || $ctrl === 'media') {
                $context['screen'] = 'media';
            }
        }

        // Page route: the site's own pages.
        if ($routeName === 'site/page') {
            $pageSlug = $params['page-slug'] ?? '';
            // Check if this is the home page.
            foreach ($site->pages() as $page) {
                if ($page->slug() === $pageSlug) {
                    $context['page_id'] = $page->id();
                    break;
                }
            }
        }

        // The site's landing page.
        if ($routeName === 'site' && empty($params['controller'])) {
            $context['is_home'] = true;
            // The landing page is typically a site page.
            $homepage = $site->homepage();
            if ($homepage) {
                $context['page_id'] = $homepage->id();
            }
        }

        // Error/404 page.
        if ($routeName === '' || $routeName === 'error') {
            $context['screen'] = 'not-found';
        }

        // Build the record context (for display condition matching).
        if ($record && is_object($record)) {
            $ctx = ['id' => null, 'resource_template' => null, 'item_sets' => [], 'class' => null];
            if (method_exists($record, 'id')) {
                $ctx['id'] = $record->id();
            }
            if (method_exists($record, 'resourceTemplate')) {
                $rt = $record->resourceTemplate();
                $ctx['resource_template'] = $rt ? $rt->id() : null;
            }
            if (method_exists($record, 'itemSets')) {
                $ctx['item_sets'] = array_map(function ($is) { return $is->id(); }, $record->itemSets());
            }
            if (method_exists($record, 'resourceClass')) {
                $rc = $record->resourceClass();
                $ctx['class'] = $rc ? $rc->id() : null;
            }
            $context['record'] = $ctx;
        }

        return $context;
    }
}
