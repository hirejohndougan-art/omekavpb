<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Stdlib\ItemSource;

/**
 * One featured item, large, with its description.
 *
 * "Item of the day" picks the same item all day (for every visitor) and a
 * new one the next day, so the page stays stable and caches well.
 */
class ItemSpotlight extends AbstractSchemaBlock
{
    const DESCRIPTION = 'Features one item, large, with its description. It can change every day, change on every visit, or stay on an item you pick.'; // @translate

    const GOOD_FOR = 'An item of the day on the home page.'; // @translate

    const TEMPLATE = 'item-spotlight';

    public function getLabel()
    {
        return 'Item spotlight'; // @translate
    }

    public function schema(): array
    {
        return array_merge(
            [[
                'key' => 'mode', 'type' => Schema::TYPE_SELECT, 'group' => 'Item', 'summary' => true, 'default' => 'daily',
                'label' => 'Which item', // @translate
                'options' => ['daily' => 'Item of the day', 'random' => 'Random on each visit', 'fixed' => 'A specific item'], // @translate
            ], [
                'key' => 'item_id', 'type' => Schema::TYPE_ITEM, 'group' => 'Item', 'summary' => true,
                'label' => 'Item', // @translate
                'show_if' => ['mode' => ['fixed']],
            ]],
            array_map(function ($f) {
                $f['show_if'] = ['mode' => ['daily', 'random']];
                return $f;
            }, ItemSource::fields()),
            [
                DisplayFields::heading('Featured item'),
                [
                    'key' => 'image_position', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'left',
                    'label' => 'Image position', // @translate
                    'options' => ['left' => 'Left', 'right' => 'Right', 'top' => 'Above the text'], // @translate
                ],
                [
                    'key' => 'thumbnail_type', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'large',
                    'label' => 'Image size', // @translate
                    'options' => ['medium' => 'Medium', 'large' => 'Large'], // @translate
                ],
                [
                    'key' => 'show_description', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Display', 'default' => true,
                    'label' => 'Show the item\'s description', // @translate
                ],
                [
                    'key' => 'link_text', 'type' => Schema::TYPE_TEXT, 'group' => 'Display', 'default' => 'View this item',
                    'label' => 'Link text', // @translate
                    'placeholder' => 'Leave empty for no link', // @translate
                ],
            ]
        );
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $item = $this->pick($data, $site, $blockKey);
        return [
            'item' => $item,
            'url' => $item ? ItemSource::url($item, $data, $site) : null,
        ];
    }

    protected function pick(array $data, SiteRepresentation $site, $blockId)
    {
        if ($data['mode'] === 'fixed') {
            if (!$data['item_id']) {
                return null;
            }
            try {
                return $this->itemSource->items(['site_scope' => ItemSource::SCOPE_ALL], $site, ['id' => $data['item_id'], 'limit' => 1])[0] ?? null;
            } catch (NotFoundException $e) {
                return null;
            }
        }
        if ($data['mode'] === 'random') {
            return $this->itemSource->items($data, $site, ['sort_by' => 'random', 'limit' => 1])[0] ?? null;
        }
        // Item of the day: a stable position in a stable order, chosen from
        // today's date and this block's id.
        $total = $this->itemSource->countItems($data, $site);
        if (!$total) {
            return null;
        }
        $offset = crc32($this->today()->format('Y-m-d') . '#' . $blockId) % $total;
        return $this->itemSource->items($data, $site, ['sort_by' => 'id', 'sort_order' => 'asc', 'offset' => $offset, 'limit' => 1])[0] ?? null;
    }
}
