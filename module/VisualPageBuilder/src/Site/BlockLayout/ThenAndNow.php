<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Exception\NotFoundException;
use VisualPageBuilder\Schema\Schema;

/**
 * Two photos of the same place, compared with a slider.
 *
 * Each photo comes from an item (its main file) or from an uploaded asset.
 * Without JavaScript, or for screen readers, both photos show side by side
 * with their labels. asset/js/then-and-now.js adds the slider.
 */
class ThenAndNow extends AbstractSchemaBlock
{
    const DESCRIPTION = 'Two photos of the same place. Visitors drag a slider across to compare them.'; // @translate

    const GOOD_FOR = 'Showing how a street or building has changed.'; // @translate

    const TEMPLATE = 'then-and-now';

    public function getLabel()
    {
        return 'Then and now'; // @translate
    }

    public function schema(): array
    {
        $side = function ($prefix, $group, $label) {
            return [
                ['key' => $prefix . '_item_id', 'type' => Schema::TYPE_ITEM, 'group' => $group, 'summary' => true, 'label' => 'Photo from an item', 'info' => 'Uses the item\'s main image.'], // @translate
                ['key' => $prefix . '_asset_id', 'type' => Schema::TYPE_ASSET, 'group' => $group, 'label' => 'Or an uploaded image (asset)'], // @translate
                ['key' => $prefix . '_label', 'type' => Schema::TYPE_TEXT, 'group' => $group, 'default' => $label, 'label' => 'Label'], // @translate
                ['key' => $prefix . '_alt', 'type' => Schema::TYPE_TEXT, 'group' => $group, 'label' => 'Image description', 'info' => 'Describe the photo for people who can\'t see it.'], // @translate
            ];
        };
        return array_merge(
            $side('before', 'Then', 'Then'),
            $side('after', 'Now', 'Now'),
            [
                ['key' => 'caption', 'type' => Schema::TYPE_TEXTAREA, 'group' => 'Display', 'label' => 'Caption'], // @translate
                ['key' => 'start', 'type' => Schema::TYPE_NUMBER, 'group' => 'Display', 'default' => 50, 'min' => 0, 'max' => 100, 'label' => 'Slider starts at (%)'], // @translate
                [
                    'key' => 'thumbnail_type', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'large',
                    'label' => 'Image size for item photos', // @translate
                    'options' => ['medium' => 'Medium', 'large' => 'Large'], // @translate
                ],
            ]
        );
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        return [
            'before' => $this->image($view, $data, 'before'),
            'after' => $this->image($view, $data, 'after'),
            'sliderId' => 'special-then-now-' . $blockKey,
        ];
    }

    /** URL, label, and description of one side; the item wins over the asset. */
    protected function image(PhpRenderer $view, array $data, string $prefix): array
    {
        $url = null;
        try {
            if ($data[$prefix . '_item_id']) {
                $url = $view->api()->read('items', $data[$prefix . '_item_id'])->getContent()->thumbnailDisplayUrl($data['thumbnail_type']);
            } elseif ($data[$prefix . '_asset_id']) {
                $url = $view->api()->read('assets', $data[$prefix . '_asset_id'])->getContent()->assetUrl();
            }
        } catch (NotFoundException $e) {
            $url = null;
        }
        return ['url' => $url, 'label' => $data[$prefix . '_label'], 'alt' => $data[$prefix . '_alt']];
    }
}
