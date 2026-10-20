<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Templates;

/**
 * The public screens a Theme Builder template can design, and the blocks each
 * one must keep (plan section 1, decision 10). Pure data: no Omeka calls, so
 * the lists are tested on their own (tests/php).
 *
 * "omeka" is the Omeka view a screen replaces; the Companion Theme hands it to
 * VPB before drawing its own layout (Phase 2 onward).
 */
class Screens
{
    /** Block types a required block can be. The editor and Check page use these names. */
    const RESULTS = 'results';          // Results grid or list
    const PAGINATION = 'pagination';
    // Item field blocks: their layout names (SpecialBlocks::LAYOUTS).
    const TITLE = 'itemTitle';          // Item title
    const MAIN_IMAGE = 'itemMainImage'; // Item main image (with placeholder)
    const MEDIA_VIEWER = 'media-viewer';

    const SCREENS = [
        'item' => ['label' => 'Item', 'omeka' => 'omeka/site/item/show', 'required' => [self::TITLE, self::MAIN_IMAGE]], // @translate
        'media' => ['label' => 'Media', 'omeka' => 'omeka/site/media/show', 'required' => [self::MEDIA_VIEWER]], // @translate
        'item-set' => ['label' => 'Item set', 'omeka' => 'omeka/site/item-set/show', 'required' => [self::RESULTS, self::PAGINATION]], // @translate
        'browse-items' => ['label' => 'Browse items', 'omeka' => 'omeka/site/item/browse', 'required' => [self::RESULTS, self::PAGINATION]], // @translate
        'browse-item-sets' => ['label' => 'Browse item sets', 'omeka' => 'omeka/site/item-set/browse', 'required' => [self::RESULTS, self::PAGINATION]], // @translate
        'search-results' => ['label' => 'Search results', 'omeka' => 'omeka/site/item/browse', 'required' => [self::RESULTS, self::PAGINATION]], // @translate
        'not-found' => ['label' => 'Page not found', 'omeka' => 'error/404', 'required' => []], // @translate
        'header' => ['label' => 'Header', 'omeka' => null, 'required' => []], // @translate
        'footer' => ['label' => 'Footer', 'omeka' => null, 'required' => []], // @translate
    ];

    /** Screens that take a record (an item, media or item set); the others are page-wide. */
    const RECORD_SCREENS = ['item', 'media', 'item-set'];

    public static function exists(string $screen): bool
    {
        return isset(self::SCREENS[$screen]);
    }

    /** Required block types for a screen (empty when the screen has none). */
    public static function required(string $screen): array
    {
        return self::SCREENS[$screen]['required'] ?? [];
    }

    public static function label(string $screen): string
    {
        return self::SCREENS[$screen]['label'] ?? $screen;
    }

    /** Keys of every screen, in the Theme Builder's order. */
    public static function keys(): array
    {
        return array_keys(self::SCREENS);
    }
}
