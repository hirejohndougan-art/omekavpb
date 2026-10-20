<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;

/**
 * The item's subjects as tags. Each tag links to every item with that subject.
 */
class ItemSubjects extends AbstractItemField
{
    const DESCRIPTION = 'The item\'s subjects as tags. Each tag links to every item with that subject.'; // @translate

    const GOOD_FOR = 'Item templates.'; // @translate

    const TEMPLATE = 'item-subjects';
    const SAMPLE = 'Subject tags'; // @translate

    public function getLabel()
    {
        return 'Subjects as tags'; // @translate
    }

    public function schema(): array
    {
        return [
            [
                'key' => 'term', 'type' => Schema::TYPE_TEXT, 'group' => 'Field', 'default' => 'dcterms:subject',
                'label' => 'Field (its property term)', // @translate
            ],
        ];
    }

    protected function fieldVars(PhpRenderer $view, array $data, SiteRepresentation $site, $item): array
    {
        $term = trim((string) $data['term']) ?: 'dcterms:subject';
        $tags = [];
        if ($item) {
            $property = null;
            try {
                $found = $view->api()->search('properties', ['term' => $term])->getContent();
                $property = $found ? $found[0]->id() : null;
            } catch (\Exception $e) {
            }
            foreach (self::itemTexts($item, $term) as $text) {
                $url = $property
                    ? $view->url('site/resource', ['site-slug' => $site->slug(), 'controller' => 'item', 'action' => 'browse'], ['query' => ['property' => [['joiner' => 'and', 'property' => $property, 'type' => 'eq', 'text' => $text]]]])
                    : null;
                $tags[] = ['text' => $text, 'url' => $url];
            }
        }
        return ['tags' => $tags];
    }
}
