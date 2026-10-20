<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Search\BrowseQuery;

/**
 * The order of the results: best match, A-Z, oldest first, or newest first.
 */
class BrowseSort extends AbstractSchemaBlock
{
    const DESCRIPTION = 'The order of the results: best match, A to Z, oldest first, or newest first.'; // @translate

    const GOOD_FOR = 'Above the results, beside Results per page.'; // @translate

    const TEMPLATE = 'browse-sort';

    const LABELS = [
        'relevance' => 'Best match',
        'title' => 'A to Z',
        'oldest' => 'Oldest first',
        'newest' => 'Newest first',
    ]; // @translate

    public function getLabel()
    {
        return 'Sort'; // @translate
    }

    public function schema(): array
    {
        return [];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $result = BrowseQuery::run($view, $site);
        // Keep the search, filters and page size; a new order starts at page one.
        $query = array_intersect_key($view->params()->fromQuery(), array_flip(array_merge(['q', 'fulltext_search', 'per_page'], BrowseQuery::FILTER_PARAMS)));
        $choices = [];
        foreach (self::LABELS as $sort => $label) {
            $choices[] = [
                'label' => $view->translate($label),
                'url' => $view->url(null, [], ['query' => $query + ['sort' => $sort]], true),
                'current' => $sort === $result['sort'],
            ];
        }
        return [
            'choices' => $choices,
            'blockClass' => static::cssClass(),
        ];
    }
}
