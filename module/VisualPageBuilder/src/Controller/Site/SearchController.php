<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Controller\Site;

use Laminas\Mvc\Controller\AbstractActionController;
use VisualPageBuilder\Search\BrowseQuery;
use VisualPageBuilder\Search\Index;
use VisualPageBuilder\Search\Search;
use VisualPageBuilder\Search\Synonyms;

/**
 * The public search endpoint: GET /s/:site-slug/vpb-search?q=...
 *
 * Returns JSON for the search box's suggestions and the results. It uses the VPB
 * Search index, and falls back to Omeka's own full-text search when the index is
 * missing, empty, or being rebuilt, so visitors always get results.
 *
 * Query parameters: q, page, per_page (12, 24 or 48), item_set[] and class[] (facet values).
 */
class SearchController extends AbstractActionController
{
    public const PER_PAGE_CHOICES = [12, 24, 48];

    public function searchAction()
    {
        $services = $this->getEvent()->getApplication()->getServiceManager();
        $api = $services->get('Omeka\ApiManager');
        $conn = $services->get('Omeka\Connection');
        $settings = $services->get('Omeka\Settings');

        $query = trim((string) $this->params()->fromQuery('q', ''));
        $page = max(1, (int) $this->params()->fromQuery('page', 1));
        $perPage = (int) $this->params()->fromQuery('per_page', 12);
        if (!in_array($perPage, self::PER_PAGE_CHOICES, true)) {
            $perPage = 12;
        }
        $facets = [
            'item_set' => array_map('intval', (array) $this->params()->fromQuery('item_set', [])),
            'class' => array_map('intval', (array) $this->params()->fromQuery('class', [])),
        ];

        // Only this site's items: the site comes from the URL (/s/:site-slug).
        $siteId = (int) $this->currentSite()->id();

        $index = Index::ready($conn, $settings);
        if ($index) {
            $synonyms = Synonyms::fromText((string) $settings->get(Index::SYNONYMS_SETTING, ''));
            $result = Search::run($conn, $query, false, $page, $perPage, $synonyms, $facets, $siteId);
            $items = $this->titles($conn, $result['ids']);
            $counts = Search::facetCounts($conn, $query, false, $synonyms, $facets, $siteId);
            return $this->json([
                'mode' => 'index',
                'query' => $query,
                'total' => $result['total'],
                'page' => $page,
                'per_page' => $perPage,
                'suggestion' => $result['suggestion'],
                'facets' => BrowseQuery::facetLabels($api, $counts),
                'items' => $items,
            ]);
        }

        // Fallback: Omeka's own search. Only public items come back for visitors.
        $params = ['page' => $page, 'per_page' => $perPage, 'sort_by' => 'title', 'site_id' => $siteId];
        if ($query !== '') {
            $params['fulltext_search'] = $query;
        }
        $response = $api->search('items', $params);
        $items = [];
        foreach ($response->getContent() as $item) {
            $items[] = ['id' => (int) $item->id(), 'title' => (string) $item->displayTitle('')];
        }
        return $this->json([
            'mode' => 'fallback',
            'query' => $query,
            'total' => (int) $response->getTotalResults(),
            'page' => $page,
            'per_page' => $perPage,
            'suggestion' => null,
            'items' => $items,
        ]);
    }

    /**
     * Send JSON straight back. Returning a response skips Omeka's site layout,
     * which expects an HTML view.
     */
    private function json(array $data)
    {
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine('Content-Type', 'application/json; charset=utf-8');
        $response->setContent(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response;
    }

    /** @return array<int, array{id: int, title: string}> in the order given. */
    private function titles($conn, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $rows = $conn->fetchAllAssociative(
            'SELECT item_id, title FROM ' . Index::DOC . ' WHERE item_id IN (' . $marks . ')',
            $ids
        );
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row['item_id']] = $row['title'];
        }
        $out = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $out[] = ['id' => $id, 'title' => $byId[$id]];
            }
        }
        return $out;
    }
}
