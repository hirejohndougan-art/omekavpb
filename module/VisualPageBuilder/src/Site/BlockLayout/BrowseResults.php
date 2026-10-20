<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Search\BrowseQuery;
use VisualPageBuilder\Site\BlockLayout\BrowseFacets;

/**
 * The items that match the visitor's search or filters, as a grid or a list,
 * with a line saying how many there are. Required on the browse screens.
 */
class BrowseResults extends AbstractSchemaBlock
{
    const DESCRIPTION = 'The items a visitor is browsing or searching for, as a grid or a list, with the number of matches. Use it on Browse items, Search results and Item sets.'; // @translate

    const GOOD_FOR = 'Every browse page. Keep it on the page: the Check page asks for it.'; // @translate

    const TEMPLATE = 'browse-results';

    public function getLabel()
    {
        return 'Results'; // @translate
    }

    public function schema(): array
    {
        return [
            [
                'key' => 'layout', 'type' => Schema::TYPE_SELECT, 'group' => 'Display',
                'label' => 'Show as', // @translate
                'options' => ['grid' => 'Grid', 'list' => 'List'], // @translate
                'default' => 'grid',
            ],
            [
                'key' => 'show_description', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Display',
                'label' => 'Show each item\'s description', // @translate
                'default' => false,
            ],
        ];
    }

    /**
     * The no-results helper: the names of the filters in use, and the link that
     * removes the narrowest one. Null when there is nothing to suggest.
     */
    protected static function helper(PhpRenderer $view, array $result): ?array
    {
        if (empty($result['helper'])) {
            return null;
        }
        $api = $view->getHelperPluginManager()->getServiceLocator()->get('Omeka\ApiManager');
        $names = [];
        foreach (['item_set', 'class'] as $field) {
            $names = array_merge($names, array_values(BrowseQuery::labelsFor($api, $field, $result['selected'][$field])));
        }
        $removed = BrowseQuery::labelsFor($api, $result['helper']['field'], [$result['helper']['id']]);
        return [
            'names' => $names,
            'removed' => $removed[$result['helper']['id']] ?? '',
            'count' => $result['helper']['count'],
            'url' => BrowseFacets::url($view, $result['helper']['facets']),
        ];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $result = BrowseQuery::run($view, $site);
        $cards = [];
        foreach ($result['items'] as $item) {
            $cards[] = [
                'title' => $item->displayTitle(),
                'url' => $item->siteUrl($site->slug()),
                'thumb' => $item->thumbnailDisplayUrl('square'),
                'description' => $data['show_description'] ? $item->displayDescription() : null,
            ];
        }
        return [
            'cards' => $cards,
            'total' => $result['total'],
            'noun' => $result['noun'],
            'query' => $result['query'],
            'suggestion' => $result['suggestion'],
            'suggestionUrl' => $result['suggestion'] !== null ? $view->url(null, [], ['query' => [$result['param'] => $result['suggestion']]], true) : null,
            'helper' => self::helper($view, $result),
            'layout' => $data['layout'] === 'list' ? 'list' : 'grid',
            'blockClass' => static::cssClass(),
        ];
    }
}
