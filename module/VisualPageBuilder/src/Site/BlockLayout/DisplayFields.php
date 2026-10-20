<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use VisualPageBuilder\Schema\Schema;

/**
 * Display settings shared by the blocks that show a grid of items or item sets.
 * The grid itself is common/special-blocks/item-grid.phtml.
 */
class DisplayFields
{
    public static function heading(string $default = ''): array
    {
        return [
            'key' => 'heading', 'type' => Schema::TYPE_TEXT, 'group' => 'Display', 'summary' => true, 'default' => $default,
            'label' => 'Heading', // @translate
            'placeholder' => 'Optional', // @translate
        ];
    }

    /**
     * How many to show, columns, thumbnail size, titles, and an optional link.
     */
    public static function grid(int $count = 6, int $maxCount = 24): array
    {
        return [
            [
                'key' => 'count', 'type' => Schema::TYPE_NUMBER, 'group' => 'Display', 'summary' => true,
                'label' => 'How many to show', // @translate
                'default' => $count, 'min' => 1, 'max' => $maxCount,
            ],
            [
                'key' => 'columns', 'type' => Schema::TYPE_SELECT, 'group' => 'Display',
                'label' => 'Columns', // @translate
                'options' => ['auto' => 'Fit to width', '2' => '2', '3' => '3', '4' => '4', '6' => '6'], // @translate
                'default' => 'auto',
            ],
            [
                'key' => 'thumbnail_type', 'type' => Schema::TYPE_SELECT, 'group' => 'Display',
                'label' => 'Image size', // @translate
                'options' => ['square' => 'Square', 'medium' => 'Medium', 'large' => 'Large'], // @translate
                'default' => 'square',
            ],
            [
                'key' => 'show_title', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Display', 'default' => true,
                'label' => 'Show titles', // @translate
            ],
            [
                'key' => 'show_description', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Display', 'default' => false,
                'label' => 'Show descriptions', // @translate
            ],
        ];
    }

    /** "See more" link under a block. */
    public static function link(string $defaultText = ''): array
    {
        return [
            [
                'key' => 'link_text', 'type' => Schema::TYPE_TEXT, 'group' => 'Link', 'default' => $defaultText,
                'label' => 'Link text', // @translate
                'placeholder' => 'Leave empty for no link', // @translate
            ],
            [
                'key' => 'link_url', 'type' => Schema::TYPE_URL, 'group' => 'Link',
                'label' => 'Link to', // @translate
                'info' => 'A web address or a path on this site, like /s/dhs/item. Leave empty to link to this site\'s item browse page.', // @translate
                'placeholder' => 'https://… or /s/…',
            ],
        ];
    }
}
