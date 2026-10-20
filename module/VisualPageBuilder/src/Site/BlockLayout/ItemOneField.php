<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;

/**
 * A single field of the item, such as its place or its rights, with or without its name.
 */
class ItemOneField extends AbstractItemField
{
    const DESCRIPTION = 'A single field of the item, such as its place or its rights, with or without its name.'; // @translate

    const GOOD_FOR = 'Anywhere one field belongs in the layout.'; // @translate

    const TEMPLATE = 'item-one-field';
    const SAMPLE = 'One field (the property you chose)'; // @translate

    public function getLabel()
    {
        return 'One field'; // @translate
    }

    public function schema(): array
    {
        return [
            [
                'key' => 'term', 'type' => Schema::TYPE_TEXT, 'group' => 'Field', 'default' => 'dcterms:spatial',
                'label' => 'Field (its property term, such as dcterms:spatial)', // @translate
            ],
            [
                'key' => 'show_label', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Display', 'default' => true,
                'label' => 'Show the field name', // @translate
            ],
        ];
    }

    protected function fieldVars(PhpRenderer $view, array $data, SiteRepresentation $site, $item): array
    {
        $term = trim((string) $data['term']);
        return [
            'label' => $term !== '' ? self::propertyLabel($view, $term) : '',
            'values' => $term !== '' ? self::itemTexts($item, $term) : [],
        ];
    }
}
