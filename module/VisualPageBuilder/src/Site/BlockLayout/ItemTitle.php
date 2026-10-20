<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;

/**
 * The item's title, as the page heading.
 */
class ItemTitle extends AbstractItemField
{
    const DESCRIPTION = 'The item\'s title, as the page heading.'; // @translate

    const GOOD_FOR = 'Every item template.'; // @translate

    const TEMPLATE = 'item-title';
    const SAMPLE = 'Item title'; // @translate

    public function getLabel()
    {
        return 'Item title'; // @translate
    }

    public function schema(): array
    {
        return [
            [
                'key' => 'tag', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'h1',
                'label' => 'Heading level', // @translate
                'options' => ['h1' => 'Heading 1 (the item title)', 'h2' => 'Heading 2', 'h3' => 'Heading 3'], // @translate
            ],
        ];
    }

    protected function fieldVars(PhpRenderer $view, array $data, SiteRepresentation $site, $item): array
    {
        return ['title' => $item ? (string) $item->displayTitle() : ''];
    }
}
