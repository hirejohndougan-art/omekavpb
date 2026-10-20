<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Search\BrowseQuery;

/**
 * Previous and next links, and the page numbers, for the Results on a browse
 * screen. Required on the browse screens, next to Results.
 */
class BrowsePagination extends AbstractSchemaBlock
{
    const DESCRIPTION = 'Page numbers and previous and next links for the results on a browse page.'; // @translate

    const GOOD_FOR = 'Under Results on every browse page.'; // @translate

    const TEMPLATE = 'browse-pagination';

    public function getLabel()
    {
        return 'Pagination'; // @translate
    }

    public function schema(): array
    {
        return [];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $result = BrowseQuery::run($view, $site);
        $page = $result['page'];
        $pages = $result['pages'];
        // Only the browse state (q, per_page, facets) carries over; other address parameters are left out.
        $query = array_intersect_key($view->params()->fromQuery(), array_flip(['q', 'per_page', 'item_set', 'class']));

        // A link to the same screen with one parameter changed.
        $link = function (int $to) use ($view, $query): string {
            $query['page'] = $to;
            return $view->url(null, [], ['query' => $query], true);
        };

        $numbers = [];
        if ($pages > 1) {
            // First, last, and a window of two either side of this page; gaps shown as null.
            $shown = array_unique(array_filter(array_merge([1, $pages], range(max(1, $page - 2), min($pages, $page + 2)))));
            sort($shown);
            $previous = null;
            foreach ($shown as $number) {
                if ($previous !== null && $number - $previous > 1) {
                    $numbers[] = null;
                }
                $numbers[] = ['number' => $number, 'url' => $link($number), 'current' => $number === $page];
                $previous = $number;
            }
        }

        return [
            'page' => $page,
            'pages' => $pages,
            'previousUrl' => $page > 1 ? $link($page - 1) : null,
            'nextUrl' => $page < $pages ? $link($page + 1) : null,
            'numbers' => $numbers,
            'blockClass' => static::cssClass(),
        ];
    }
}
