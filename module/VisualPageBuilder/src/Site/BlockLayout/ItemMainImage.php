<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;

/**
 * The item's picture or file. With no file, a placeholder shows the kind of item.
 */
class ItemMainImage extends AbstractItemField
{
    const DESCRIPTION = 'The item\'s picture or file. With no file, a placeholder shows the kind of item.'; // @translate

    const GOOD_FOR = 'Every item template. Required.'; // @translate

    const TEMPLATE = 'item-main-image';
    const SAMPLE = 'Main image (or the placeholder for this kind of item)'; // @translate

    public function getLabel()
    {
        return 'Main image'; // @translate
    }

    public function schema(): array
    {
        return [
            [
                'key' => 'size', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'large',
                'label' => 'Size', // @translate
                'options' => ['large' => 'Large', 'medium' => 'Medium'], // @translate
            ],
        ];
    }

    protected function fieldVars(PhpRenderer $view, array $data, SiteRepresentation $site, $item): array
    {
        $kind = 'none';
        $media = null;
        $thumb = null;
        $original = null;
        $label = '';
        if ($item) {
            $media = $item->primaryMedia();
            if ($media) {
                $thumb = $media->thumbnailUrl('large');
                $original = $media->originalUrl();
                $kind = \VisualPageBuilder\Templates\Placeholder::mediaKind($media->mediaType(), (bool) $thumb);
            }
            $label = \VisualPageBuilder\Templates\Placeholder::kindLabel(
                $item->resourceTemplate() ? $item->resourceTemplate()->label() : null,
                $item->resourceClass() ? $item->resourceClass()->label() : null
            );
        }
        return ['kind' => $kind, 'media' => $media, 'thumb' => $thumb, 'original' => $original, 'kindLabel' => $label];
    }
}
