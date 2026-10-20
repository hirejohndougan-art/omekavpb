<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Search;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;


/**
 * What a visitor asked for on a browse-style page (Browse items, Search
 * results): the query text, page, page size and facet choices from the address
 * bar, and the items that match on this site.
 *
 * Results blocks and Pagination blocks on the same page both need the answer,
 * so it is worked out once per request and remembered here.
 *
 * Address parameters: q, page, per_page (12, 24 or 48), item_set[] and class[].
 */
class BrowseQuery
{
    const PER_PAGE_CHOICES = [12, 24, 48];

    const DEFAULT_PER_PAGE = 24;

    /** @var array<string, array> one answer per distinct request */
    protected static $answers = [];

    /**
     * The visitor's browse state, read from the address bar.
     *
     * @return array{q: string, page: int, per_page: int, facets: array{item_set: int[], class: int[]}}
     */
    public static function fromRequest(PhpRenderer $view): array
    {
        $services = $view->getHelperPluginManager()->getServiceLocator();
        $query = $services->get('Application')->getRequest()->getQuery();

        $perPage = (int) $query->get('per_page', self::DEFAULT_PER_PAGE);
        if (!in_array($perPage, self::PER_PAGE_CHOICES, true)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }
        return [
            'q' => trim((string) $query->get('q', '')),
            'page' => max(1, (int) $query->get('page', 1)),
            'per_page' => $perPage,
            'facets' => [
                'item_set' => array_map('intval', (array) $query->get('item_set', [])),
                'class' => array_map('intval', (array) $query->get('class', [])),
            ],
        ];
    }

    /**
     * The matching items for this request on this site.
     *
     * @return array{items: \Omeka\Api\Representation\ItemRepresentation[], total: int, page: int, per_page: int, pages: int, query: string, suggestion: ?string, mode: string}
     */
    public static function run(PhpRenderer $view, SiteRepresentation $site): array
    {
        $state = self::fromRequest($view);
        $key = $site->id() . '|' . json_encode($state);
        if (isset(self::$answers[$key])) {
            return self::$answers[$key];
        }

        $services = $view->getHelperPluginManager()->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $conn = $services->get('Omeka\Connection');
        $settings = $services->get('Omeka\Settings');
        $siteId = (int) $site->id();

        if (Index::ready($conn, $settings)) {
            $synonyms = Synonyms::fromText((string) $settings->get(Index::SYNONYMS_SETTING, ''));
            $result = Search::run($conn, $state['q'], false, $state['page'], $state['per_page'], $synonyms, $state['facets'], $siteId);
            $items = self::items($api, $result['ids']);
            $answer = [
                'items' => $items,
                'total' => $result['total'],
                'suggestion' => $result['suggestion'],
                'mode' => 'index',
            ];
        } else {
            // The index is missing, empty, or being rebuilt: use Omeka's own search.
            $params = ['page' => $state['page'], 'per_page' => $state['per_page'], 'sort_by' => 'title', 'site_id' => $siteId];
            if ($state['q'] !== '') {
                $params['fulltext_search'] = $state['q'];
            }
            if ($state['facets']['item_set']) {
                $params['item_set_id'] = $state['facets']['item_set'];
            }
            if ($state['facets']['class']) {
                $params['resource_class_id'] = $state['facets']['class'];
            }
            $response = $api->search('items', $params);
            $answer = [
                'items' => $response->getContent(),
                'total' => (int) $response->getTotalResults(),
                'suggestion' => null,
                'mode' => 'fallback',
            ];
        }

        $answer += [
            'page' => $state['page'],
            'per_page' => $state['per_page'],
            'pages' => max(1, (int) ceil($answer['total'] / $state['per_page'])),
            'query' => $state['q'],
            'facets' => $state['facets'],
        ];
        self::$answers[$key] = $answer;
        return $answer;
    }

    /** Full item representations for the ids, in the order given (visitors see only public items). */
    protected static function items($api, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $byId = [];
        foreach ($api->search('items', ['id' => $ids])->getContent() as $item) {
            $byId[$item->id()] = $item;
        }
        $out = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $out[] = $byId[$id];
            }
        }
        return $out;
    }

    /** Forget the remembered answers (tests). */
    public static function forget(): void
    {
        self::$answers = [];
    }
}
