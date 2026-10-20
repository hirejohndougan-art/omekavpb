<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Stdlib;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Exception\NotFoundException;
use VisualPageBuilder\Schema\Schema;

/**
 * "An image from an item, or an uploaded asset": the pair of fields several
 * Special Blocks use (slides, topic tiles), and the URL they resolve to.
 */
class ImageSource
{
    /**
     * The two schema fields, keyed `<prefix>item_id` and `<prefix>asset_id`.
     */
    public static function fields(string $prefix = '', array $extra = []): array
    {
        return [
            ['key' => $prefix . 'item_id', 'type' => Schema::TYPE_ITEM, 'label' => 'Image from an item', 'info' => 'Uses the item\'s main image.'] + $extra, // @translate
            ['key' => $prefix . 'asset_id', 'type' => Schema::TYPE_ASSET, 'label' => 'Or an uploaded image (asset)'] + $extra, // @translate
        ];
    }

    /**
     * Image URL for a row of data: the item's image if set, else the asset's,
     * else null. `$size` is a thumbnail type for item images.
     */
    public static function url(PhpRenderer $view, array $row, string $size = 'large', string $prefix = ''): ?string
    {
        try {
            if (!empty($row[$prefix . 'item_id'])) {
                return $view->api()->read('items', $row[$prefix . 'item_id'])->getContent()->thumbnailDisplayUrl($size);
            }
            if (!empty($row[$prefix . 'asset_id'])) {
                return $view->api()->read('assets', $row[$prefix . 'asset_id'])->getContent()->assetUrl();
            }
        } catch (NotFoundException $e) {
            // Deleted or private: no image.
        }
        return null;
    }
}
