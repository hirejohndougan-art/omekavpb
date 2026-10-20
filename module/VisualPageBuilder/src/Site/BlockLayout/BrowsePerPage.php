<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Search\BrowseQuery;

/**
 * "Show 12, 24, or 48 per page" for the results on a browse screen.
 */
class BrowsePerPage extends AbstractSchemaBlock
{
    const DESCRIPTION = 'Choose how many results show on each page: 12, 24, or 48.'; // @translate

    const GOOD_FOR = 'Above or beside Results on a browse page.'; // @translate

    const TEMPLATE = 'browse-per-page';

    public function getLabel()
    {
        return 'Results per page'; // @translate
    }

    public function schema(): array
    {
        return [];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $result = BrowseQuery::run($view, $site);
        // Keep the search and filters; a new page size starts again at page one.
        $query = array_intersect_key($view->params()->fromQuery(), array_flip(array_merge(['q', 'fulltext_search', 'sort'], BrowseQuery::FILTER_PARAMS)));
        $choices = [];
        foreach (BrowseQuery::PER_PAGE_CHOICES as $number) {
            $choices[] = [
                'number' => $number,
                'url' => $view->url(null, [], ['query' => $query + ['per_page' => $number]], true),
                'current' => $number === $result['per_page'],
            ];
        }
        return [
            'choices' => $choices,
            'blockClass' => static::cssClass(),
        ];
    }
}
