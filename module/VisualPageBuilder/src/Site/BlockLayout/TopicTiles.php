<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Stdlib\ImageSource;

/**
 * Picture tiles you set up yourself, each linking to a page, a search, or any
 * address ("Railroad", "Schools", "The 1900 Storm").
 */
class TopicTiles extends AbstractSchemaBlock
{
    const TEMPLATE = 'topic-tiles';

    const DESCRIPTION = 'Picture tiles you choose, each with a title and a link: to a page, a search, a collection, or anywhere.'; // @translate

    const GOOD_FOR = 'Home pages and "explore by topic" pages.'; // @translate

    public function getLabel()
    {
        return 'Explore by topic'; // @translate
    }

    public function schema(): array
    {
        return [
            DisplayFields::heading('Explore by topic'),
            [
                'key' => 'tiles', 'type' => Schema::TYPE_LIST, 'group' => 'Tiles', 'summary' => true, 'max' => 24,
                'label' => 'Tiles', // @translate
                'fields' => array_merge(
                    [['key' => 'title', 'type' => Schema::TYPE_TEXT, 'label' => 'Title']], // @translate
                    ImageSource::fields(),
                    [
                        ['key' => 'text', 'type' => Schema::TYPE_TEXTAREA, 'label' => 'Short description (optional)'], // @translate
                        ['key' => 'page_id', 'type' => Schema::TYPE_PAGE, 'label' => 'Link to a page on this site'], // @translate
                        ['key' => 'url', 'type' => Schema::TYPE_URL, 'label' => 'Or a web address', 'info' => 'For a search, run it on your site and paste its address.', 'placeholder' => 'https://… or /s/…'], // @translate
                    ]
                ),
            ],
            ['key' => 'columns', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'auto', 'label' => 'Columns', 'options' => ['auto' => 'Fit to width', '2' => '2', '3' => '3', '4' => '4']], // @translate
            ['key' => 'style', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'overlay', 'label' => 'Style', 'options' => ['overlay' => 'Title on the picture', 'below' => 'Title under the picture']], // @translate
        ];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $pages = [];
        foreach ($site->pages() as $page) {
            $pages[$page->id()] = $page;
        }
        $tiles = [];
        foreach ($data['tiles'] as $tile) {
            if ($tile['title'] === '') {
                continue;
            }
            $href = $tile['page_id'] && isset($pages[$tile['page_id']]) ? $pages[$tile['page_id']]->siteUrl() : $tile['url'];
            $tiles[] = $tile + ['href' => $href ?: null, 'image' => ImageSource::url($view, $tile, 'large')];
        }
        return ['tiles' => $tiles];
    }
}
