<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Templates\CurrentRecord;

/**
 * Base for the item field blocks (Theme Builder, plan Phase 2). They show a
 * part of the item a template is being drawn for: its title, image,
 * description, details, and so on.
 *
 * - Outside a template (or on a page), a field has no item and shows nothing.
 * - In the visual editor's preview it shows a sample label, so the block can be
 *   seen while it's built.
 * - They only appear in the palette when editing a Theme Builder template
 *   (placement "item"; see BuilderController and palette.js).
 */
abstract class AbstractItemField extends AbstractSchemaBlock
{
    const PLACEMENT = 'item';

    const GOOD_FOR = ''; // @translate

    /** Label shown in the editor's preview when there's no item. */
    const SAMPLE = 'Shows the item here.'; // @translate

    /** The item being drawn, or null (not in a template). */
    protected function item()
    {
        return CurrentRecord::get();
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $item = $this->item();
        return [
            'item' => $item,
            'site' => $site,
            'isPreview' => $blockKey === 'preview',
            'sample' => static::SAMPLE,
        ] + $this->fieldVars($view, $data, $site, $item);
    }

    /** Extra variables for the field's own template (beyond the item and preview flag). */
    protected function fieldVars(PhpRenderer $view, array $data, SiteRepresentation $site, $item): array
    {
        return [];
    }

    /** The first item values of a property term, as plain text (for templates). */
    protected static function itemTexts($item, string $term): array
    {
        if (!$item) {
            return [];
        }
        $out = [];
        foreach ($item->value($term, ['all' => true]) ?? [] as $value) {
            $text = trim((string) $value->value());
            if ($text !== '') {
                $out[] = $text;
            }
        }
        return $out;
    }
}
