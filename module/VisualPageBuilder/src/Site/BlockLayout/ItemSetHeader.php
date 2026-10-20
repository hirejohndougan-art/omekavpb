<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Templates\CurrentRecord;

/**
 * The name and description of the item set a page is about (Item set screen).
 * Outside that screen it shows nothing; in the visual editor it shows a sample.
 */
class ItemSetHeader extends AbstractSchemaBlock
{
    const DESCRIPTION = 'The name and description of the item set the page is showing.'; // @translate

    const GOOD_FOR = 'The top of an Item set page.'; // @translate

    const TEMPLATE = 'item-set-header';

    /** Label shown in the editor's preview when there's no item set. */
    const SAMPLE = 'The item set\'s name and description appear here.'; // @translate

    public function getLabel()
    {
        return 'Item set name and description'; // @translate
    }

    public function schema(): array
    {
        return [
            [
                'key' => 'show_description', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Display',
                'label' => 'Show the description', // @translate
                'default' => true,
            ],
        ];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $set = CurrentRecord::get();
        return [
            'title' => $set ? $set->displayTitle() : null,
            'description' => ($set && $data['show_description']) ? $set->displayDescription() : null,
            'isPreview' => $blockKey === 'preview',
            'sample' => static::SAMPLE,
            'blockClass' => static::cssClass(),
        ];
    }
}
