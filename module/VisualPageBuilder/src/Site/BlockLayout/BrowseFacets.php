<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Search\BrowseQuery;
use VisualPageBuilder\Templates\CurrentScreen;

/**
 * Filters for the results: collection (item set) and type (resource class), with
 * the number of matching items for each choice, the filters in use, and a clear
 * link. Works on the index screens (Browse items, Search results, an item set's
 * page). Shows nothing on Browse item sets.
 */
class BrowseFacets extends AbstractSchemaBlock
{
    const DESCRIPTION = 'Filters for the results: collection and type, with how many items each choice matches, and the filters in use.'; // @translate

    const GOOD_FOR = 'Browse items and Search results, beside the results.'; // @translate

    const TEMPLATE = 'browse-facets';

    /** Field name => the label shown to visitors. */
    const FIELDS = ['item_set' => 'Collection', 'class' => 'Type', 'decade' => 'Date', 'has_images' => 'Images']; // @translate

    public function getLabel()
    {
        return 'Filters'; // @translate
    }

    public function schema(): array
    {
        return [];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $result = BrowseQuery::run($view, $site);
        $screen = CurrentScreen::get();
        $api = $view->getHelperPluginManager()->getServiceLocator()->get('Omeka\ApiManager');
        $selected = $result['selected'];
        // On an item set's page the collection is fixed to that set, so it isn't offered as a filter.
        $fields = array_diff_key(self::FIELDS, $screen === 'item-set' ? ['item_set' => 1] : []);

        $groups = [];
        $active = [];
        foreach ($fields as $field => $label) {
            $options = [];
            $options_all = $result['facetOptions'][$field] ?? [];
            $largest = $options_all ? max(array_column($options_all, 'count')) : 1;
            foreach ($options_all as $option) {
                $on = in_array($option['id'], $selected[$field], true);
                $next = $selected;
                $next[$field] = $on
                    ? array_values(array_diff($selected[$field], [$option['id']]))
                    : array_values(array_merge($selected[$field], [$option['id']]));
                $bar = (int) round(100 * $option['count'] / max(1, $largest));
                $options[] = $option + ['active' => $on, 'url' => self::url($view, $next), 'bar' => $bar];
            }
            if ($options) {
                // Dates read as bars: each decade's width is its share of the largest decade.
                $groups[] = ['label' => $view->translate($label), 'options' => $options, 'bars' => $field === 'decade'];
            }
            foreach (BrowseQuery::labelsFor($api, $field, $selected[$field]) as $id => $name) {
                $next = $selected;
                $next[$field] = array_values(array_diff($selected[$field], [$id]));
                $active[] = ['label' => $name, 'url' => self::url($view, $next)];
            }
        }

        return [
            'show' => $result['mode'] === 'index' && $screen !== 'browse-item-sets',
            'panelId' => 'vpb-facets-' . preg_replace('/[^A-Za-z0-9_-]/', '', $blockKey),
            'total' => $result['total'],
            'groups' => $groups,
            'active' => $active,
            'clearUrl' => $active ? self::url($view, []) : null,
            'blockClass' => static::cssClass(),
        ];
    }

    /**
     * The address for these filters: the search, sort and page size stay, the filters
     * are exactly the ones given, and paging starts again at page one.
     */
    public static function url(PhpRenderer $view, array $selected): string
    {
        $query = array_intersect_key($view->params()->fromQuery(), array_flip(['q', 'fulltext_search', 'per_page', 'sort']));
        foreach (BrowseQuery::FILTER_PARAMS as $field) {
            if (!empty($selected[$field])) {
                $query[$field] = array_values($selected[$field]);
            }
        }
        return $view->url(null, [], ['query' => $query], true);
    }
}
