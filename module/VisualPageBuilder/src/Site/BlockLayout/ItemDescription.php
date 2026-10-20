<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;

/**
 * The item's description.
 */
class ItemDescription extends AbstractItemField
{
    const DESCRIPTION = 'The item\'s description.'; // @translate

    const GOOD_FOR = 'Every item template.'; // @translate

    const TEMPLATE = 'item-description';
    const SAMPLE = 'Description'; // @translate

    public function getLabel()
    {
        return 'Description'; // @translate
    }

    public function schema(): array
    {
        return [];
    }

    protected function fieldVars(PhpRenderer $view, array $data, SiteRepresentation $site, $item): array
    {
        return ['paragraphs' => self::itemTexts($item, 'dcterms:description')];
    }
}
