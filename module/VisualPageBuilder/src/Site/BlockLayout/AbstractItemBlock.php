<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Stdlib\ItemSource;

/**
 * Base for blocks that show a grid of items (random, recent, on this day).
 * Subclasses say which items to fetch in findItems(); the grid template and
 * the "see more" link are handled here.
 */
abstract class AbstractItemBlock extends AbstractSchemaBlock
{
    const TEMPLATE = 'item-grid';

    /**
     * The items to show.
     */
    abstract protected function findItems(array $data, SiteRepresentation $site): array;

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        return [
            'cards' => self::cards($this->findItems($data, $site), $data, $site),
            'moreUrl' => $this->moreUrl($view, $data, $site),
            'emptyText' => $data['empty_text'] ?? '',
            'blockClass' => static::cssClass(),
        ];
    }

    /**
     * Template-ready entries: title, url, thumb, description.
     *
     * @param AbstractResourceEntityRepresentation[] $resources
     */
    public static function cards(array $resources, array $data, SiteRepresentation $site): array
    {
        $cards = [];
        foreach ($resources as $resource) {
            $cards[] = [
                'title' => $resource->displayTitle(),
                'url' => ItemSource::url($resource, $data, $site),
                'thumb' => $resource->thumbnailDisplayUrl($data['thumbnail_type'] ?? 'square'),
                'description' => $resource->displayDescription(),
                'resource' => $resource,
            ];
        }
        return $cards;
    }

    /** Where the "see more" link goes: the chosen address, else this site's item browse. */
    protected function moreUrl(PhpRenderer $view, array $data, SiteRepresentation $site): ?string
    {
        if (($data['link_text'] ?? '') === '') {
            return null;
        }
        if (!empty($data['link_url'])) {
            return $data['link_url'];
        }
        return $view->url('site/resource', ['site-slug' => $site->slug(), 'controller' => 'item', 'action' => 'browse']);
    }
}
