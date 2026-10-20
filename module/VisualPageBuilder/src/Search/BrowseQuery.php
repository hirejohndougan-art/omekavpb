<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Search;

use Laminas\View\Renderer\PhpRenderer;
use VisualPageBuilder\Templates\CurrentRecord;
use VisualPageBuilder\Templates\CurrentScreen;
use Omeka\Api\Representation\SiteRepresentation;


/**
 * What a visitor asked for on a browse-style page (Browse items, Search
 * results): the query text, page, page size and facet choices from the address
 * bar, and the items that match on this site.
 *
 * Results blocks and Pagination blocks on the same page both need the answer,
 * so it is worked out once per request and remembered here.
 *
 * On the Browse item sets screen the same parameters find item sets (no facets,
 * since the index only covers items).
 *
 * Address parameters: fulltext_search (what the site's search box sends) or q, page,
 * per_page (12, 24 or 48), item_set[] and class[].
 */
class BrowseQuery
{
    const PER_PAGE_CHOICES = [12, 24, 48];

    const DEFAULT_PER_PAGE = 24;

    /** Address parameters that narrow the results (carried by every browse control). */
    const FILTER_PARAMS = ['item_set', 'class', 'decade', 'has_images', 'has_description', 'has_transcript', 'subject', 'creator', 'place'];

    /** Sort choices: relevance (best match), A-Z, oldest, newest first, and recently added. */
    const SORTS = ['relevance', 'title', 'oldest', 'newest', 'recent'];

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

        // The site's search box sends fulltext_search; q is the VPB Search name for it.
        $fulltext = trim((string) $query->get('fulltext_search', ''));
        $perPage = (int) $query->get('per_page', self::DEFAULT_PER_PAGE);
        if (!in_array($perPage, self::PER_PAGE_CHOICES, true)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }
        return [
            'q' => $fulltext !== '' ? $fulltext : trim((string) $query->get('q', '')),
            'param' => $fulltext !== '' ? 'fulltext_search' : 'q',
            'page' => max(1, (int) $query->get('page', 1)),
            'per_page' => $perPage,
            'sort' => in_array((string) $query->get('sort', ''), self::SORTS, true) ? (string) $query->get('sort') : 'relevance',
            'facets' => [
                'item_set' => array_map('intval', (array) $query->get('item_set', [])),
                'class' => array_map('intval', (array) $query->get('class', [])),
                'decade' => array_map('intval', (array) $query->get('decade', [])),
                'has_images' => array_map('intval', (array) $query->get('has_images', [])),
                'has_description' => array_map('intval', (array) $query->get('has_description', [])),
                'has_transcript' => array_map('intval', (array) $query->get('has_transcript', [])),
                'subject' => array_map('intval', (array) $query->get('subject', [])),
                'creator' => array_map('intval', (array) $query->get('creator', [])),
                'place' => array_map('intval', (array) $query->get('place', [])),
                // A year range: items whose date overlaps it. Null when not set.
                'year_from' => self::year($query->get('year_from')),
                'year_to' => self::year($query->get('year_to')),
            ],
        ];
    }

    /** A year from the address bar (1 to 4 digits), or null. */
    public static function year($value): ?int
    {
        $value = trim((string) $value);
        return preg_match('/^\d{1,4}$/', $value) ? (int) $value : null;
    }

    /**
     * The matching items (or item sets, on the item set screen) for this request on this site.
     *
     * @return array{items: array, total: int, page: int, per_page: int, pages: int, query: string, suggestion: ?string, mode: string, noun: string}
     */
    public static function run(PhpRenderer $view, SiteRepresentation $site): array
    {
        $state = self::fromRequest($view);
        $screen = CurrentScreen::get() ?? 'browse-items';
        // The item set screen lists only the set it is drawn for.
        $set = $screen === 'item-set' ? CurrentRecord::get() : null;
        if ($set) {
            $state['facets']['item_set'] = [(int) $set->id()];
        }
        $key = $site->id() . '|' . $screen . '|' . json_encode($state);
        if (isset(self::$answers[$key])) {
            return self::$answers[$key];
        }

        $services = $view->getHelperPluginManager()->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $conn = $services->get('Omeka\Connection');
        $settings = $services->get('Omeka\Settings');
        $siteId = (int) $site->id();

        if ($screen === 'browse-item-sets') {
            $params = ['page' => $state['page'], 'per_page' => $state['per_page'], 'sort_by' => 'title', 'site_id' => $siteId];
            if ($state['q'] !== '') {
                $params['fulltext_search'] = $state['q'];
            }
            $response = $api->search('item_sets', $params);
            $answer = [
                'items' => $response->getContent(),
                'total' => (int) $response->getTotalResults(),
                'suggestion' => null,
                'mode' => 'api',
                'noun' => 'item sets',
            ];
        } elseif (Index::ready($conn, $settings)) {
            $synonyms = Synonyms::fromText((string) $settings->get(Index::SYNONYMS_SETTING, ''));
            $result = Search::run($conn, $state['q'], false, $state['page'], $state['per_page'], $synonyms, $state['facets'], $siteId, $state['sort']);
            $items = self::items($api, $result['ids']);
            $answer = [
                'items' => $items,
                'total' => $result['total'],
                'suggestion' => $result['suggestion'],
                'mode' => 'index',
                'noun' => 'items',
                'facetOptions' => self::facetLabels($api, $conn, Search::facetCounts($conn, $state['q'], false, $synonyms, $state['facets'], $siteId)),
                'helper' => null,
            ];
            if ($result['total'] === 0 && array_filter($state['facets'])) {
                $answer['helper'] = self::noResultsHelper($api, $conn, $state, $synonyms, $siteId);
            }
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
            // Decades and images need the index; the fallback can't count or filter by them.
            $response = $api->search('items', $params);
            $answer = [
                'items' => $response->getContent(),
                'total' => (int) $response->getTotalResults(),
                'suggestion' => null,
                'mode' => 'fallback',
                'noun' => 'items',
            ];
        }

        $answer += [
            'page' => $state['page'],
            'per_page' => $state['per_page'],
            'pages' => max(1, (int) ceil($answer['total'] / $state['per_page'])),
            'query' => $state['q'],
            'param' => $state['param'],
            'sort' => $state['sort'],
            'selected' => $state['facets'],
            'facetOptions' => [],
        ];
        self::$answers[$key] = $answer;
        return $answer;
    }

    /**
     * When filters find nothing: the filter whose removal gives the most results
     * (so "No results with Schools + 1890s. Remove 1890s?"), or null when removing
     * any one filter still finds nothing.
     *
     * @return array{field: string, id: int, count: int, facets: array}|null
     */
    protected static function noResultsHelper($api, $conn, array $state, ?Synonyms $synonyms, int $siteId): ?array
    {
        $best = null;
        foreach (Index::FACET_FIELDS as $field) {
            foreach ($state['facets'][$field] ?? [] as $id) {
                $without = $state['facets'];
                $without[$field] = array_values(array_diff($without[$field], [$id]));
                $count = Search::run($conn, $state['q'], false, 1, 1, $synonyms, $without, $siteId)['total'];
                if ($count > 0 && ($best === null || $count > $best['count'])) {
                    $best = ['field' => $field, 'id' => (int) $id, 'count' => $count, 'facets' => $without];
                }
            }
        }
        return $best;
    }

    /**
     * Turns facet counts into lists per field: {id, label, count}, most items first.
     *
     * @param array<string, array<int, int>> $counts [field => [value_id => count]]
     * @return array<string, array<int, array{id: int, label: string, count: int}>>
     */
    public static function facetLabels($api, $conn, array $counts): array
    {
        $out = [];
        foreach ($counts as $field => $values) {
            $out[$field] = [];
            if ($values === []) {
                continue;
            }
            $labels = self::labelsFor($api, $field, array_keys($values), $conn);
            foreach ($labels as $id => $label) {
                $out[$field][] = ['id' => $id, 'label' => $label, 'count' => $values[$id]];
            }
            if ($field === 'decade') {
                // Decades read in order, earliest first.
                usort($out[$field], fn ($a, $b) => $a['id'] <=> $b['id']);
            } else {
                usort($out[$field], fn ($a, $b) => $b['count'] <=> $a['count'] ?: strcmp($a['label'], $b['label']));
            }
        }
        return $out;
    }

    /**
     * Display names for facet values: [id => label]. Field is 'item_set', 'class',
     * 'decade', 'has_images', or a text field (subject, creator, place), which
     * needs the database connection for its labels.
     *
     * @return array<int, string>
     */
    public static function labelsFor($api, string $field, array $ids, $conn = null): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }
        if (isset(Index::TEXT_FIELDS[$field])) {
            return $conn ? Index::valueLabels($conn, $field, $ids) : [];
        }
        if ($field === 'decade') {
            return array_combine($ids, array_map(fn ($decade) => $decade . 's', $ids));
        }
        if ($field === 'has_images') {
            return array_intersect_key([1 => 'Has images'], array_flip($ids)); // @translate
        }
        if ($field === 'has_description') {
            return array_intersect_key([1 => 'Has a description'], array_flip($ids)); // @translate
        }
        if ($field === 'has_transcript') {
            return array_intersect_key([1 => 'Has a transcript'], array_flip($ids)); // @translate
        }
        $resource = $field === 'item_set' ? 'item_sets' : 'resource_classes';
        $labels = [];
        foreach ($api->search($resource, ['id' => $ids])->getContent() as $found) {
            // Item sets have a title; resource classes (Type) have a translated label.
            $labels[(int) $found->id()] = $field === 'item_set' ? (string) $found->displayTitle('') : (string) $found->label();
        }
        return $labels;
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
