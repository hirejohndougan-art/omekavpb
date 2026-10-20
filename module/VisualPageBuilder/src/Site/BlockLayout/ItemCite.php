<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;

/**
 * A citation for the item, with its link, for visitors who want to reference it.
 */
class ItemCite extends AbstractItemField
{
    const DESCRIPTION = 'A citation for the item, with its link, for visitors who want to reference it.'; // @translate

    const GOOD_FOR = 'Every item template.'; // @translate

    const TEMPLATE = 'item-cite';
    const SAMPLE = 'Cite this item'; // @translate

    public function getLabel()
    {
        return 'Cite this item'; // @translate
    }

    public function schema(): array
    {
        return [];
    }

    protected function fieldVars(PhpRenderer $view, array $data, SiteRepresentation $site, $item): array
    {
        if (!$item) {
            return ['citation' => ''];
        }
        $parts = array_filter([
            implode('; ', self::itemTexts($item, 'dcterms:creator')),
            '"' . (string) $item->displayTitle() . '"',
            implode(', ', self::itemTexts($item, 'dcterms:date')),
            $site->title(),
            $item->siteUrl($site->slug()),
        ], 'strlen');
        return ['citation' => implode('. ', $parts)];
    }
}
