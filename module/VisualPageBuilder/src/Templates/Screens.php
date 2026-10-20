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

    /**
     * The blocks a new template starts with (plan decision 10): the required ones are
     * always here, so a new template can go live. Layout names, in order.
     */
    const STARTERS = [
        'item' => ['itemTitle', 'itemMainImage'],
        'browse-items' => ['specialSearchBox', 'perPage', 'sort', 'facets', 'results', 'pagination'],
        'search-results' => ['specialSearchBox', 'perPage', 'sort', 'facets', 'results', 'pagination'],
        'browse-item-sets' => ['specialSearchBox', 'results', 'pagination'],
        'item-set' => ['itemSetHeader', 'results', 'pagination'],
        'media' => ['media-viewer'],
        'not-found' => ['specialSearchBox', 'specialButtons'],
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

    /**
     * The settings a starter block begins with. Most start empty; a link home needs
     * the site's address, so it is set here.
     */
    public static function starterData(string $layout, string $siteSlug): array
    {
        if ($layout === 'specialButtons') {
            return ['buttons' => [['text' => 'Back to the home page', 'url' => '/s/' . $siteSlug, 'style' => 'primary']]]; // @translate
        }
        return [];
    }

    /** Screens whose starter puts the filters in a side column, with the results beside them. */
    const SIDE_FILTERS = ['browse-items', 'search-results'];

    /**
     * The blocks a new template starts with, as Omeka block rows. The list screens put
     * the search box across the top, then the filters in a narrow left column and the
     * page size, sort, results and pagination in a wide right column (a block group, the
     * same structure the editor saves its sections in).
     */
    public static function starterBlocks(string $screen, string $siteSlug): array
    {
        if (!in_array($screen, self::SIDE_FILTERS, true)) {
            return array_map(function ($layout) use ($siteSlug) {
                return self::plainBlock($layout, $siteSlug);
            }, self::starters($screen));
        }
        return [
            self::plainBlock('specialSearchBox', $siteSlug),
            [
                'o:layout' => 'blockGroup',
                'o:data' => ['span' => 5],
                'o:layout_data' => ['vpb_section' => ['cols' => [['w' => 3], ['w' => 9]]]],
                'o:attachment' => [],
            ],
            self::inColumn('facets', 0, $siteSlug),
            self::inColumn('perPage', 1, $siteSlug),
            self::inColumn('sort', 1, $siteSlug),
            self::inColumn('results', 1, $siteSlug),
            self::inColumn('pagination', 1, $siteSlug),
        ];
    }

    private static function plainBlock(string $layout, string $siteSlug): array
    {
        return ['o:layout' => $layout, 'o:data' => self::starterData($layout, $siteSlug), 'o:attachment' => []];
    }

    private static function inColumn(string $layout, int $column, string $siteSlug): array
    {
        $block = self::plainBlock($layout, $siteSlug);
        $block['o:layout_data'] = ['vpb_col' => $column];
        return $block;
    }

    /** Layout names a new template for the screen starts with (empty when none). */
    public static function starters(string $screen): array
    {
        return self::STARTERS[$screen] ?? [];
    }

    /** Keys of every screen, in the Theme Builder's order. */
    public static function keys(): array
    {
        return array_keys(self::SCREENS);
    }
}
