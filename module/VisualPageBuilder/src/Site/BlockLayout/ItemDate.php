<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;

/**
 * The item's date, as written.
 */
class ItemDate extends AbstractItemField
{
    const DESCRIPTION = 'The item\'s date, as written.'; // @translate

    const GOOD_FOR = 'Every item template.'; // @translate

    const TEMPLATE = 'item-date';
    const SAMPLE = 'Date'; // @translate

    public function getLabel()
    {
        return 'Date'; // @translate
    }

    public function schema(): array
    {
        return [];
    }

    protected function fieldVars(PhpRenderer $view, array $data, SiteRepresentation $site, $item): array
    {
        return ['dates' => self::itemTexts($item, 'dcterms:date')];
    }
}
