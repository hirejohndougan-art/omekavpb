<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Stdlib\ItemSource;

/**
 * A photo grid that opens each photo large, with arrows between them
 * (special-blocks/js/gallery.js). Photos come from chosen items, or from the
 * usual item source (sites, item sets). Without JavaScript, each photo links
 * to its item page.
 */
class Gallery extends AbstractSchemaBlock
{
    const TEMPLATE = 'gallery';

    const DESCRIPTION = 'A grid of photos from items you choose or from a collection. Clicking a photo opens it large, with arrows to move through them.'; // @translate

    const GOOD_FOR = 'Showing off a set of photographs.'; // @translate

    public function getLabel()
    {
        return 'Gallery'; // @translate
    }

    public function schema(): array
    {
        $fromSource = ['mode' => ['source']];
        return array_merge(
            [[
                'key' => 'mode', 'type' => Schema::TYPE_SELECT, 'group' => 'Photos', 'summary' => true, 'default' => 'source',
                'label' => 'Which photos', // @translate
                'options' => ['source' => 'From sites and item sets', 'chosen' => 'Items I choose'], // @translate
            ], [
                'key' => 'items', 'type' => Schema::TYPE_LIST, 'group' => 'Photos', 'summary' => true, 'max' => 60,
                'label' => 'Items', // @translate
                'show_if' => ['mode' => ['chosen']],
                'fields' => [
                    ['key' => 'item_id', 'type' => Schema::TYPE_ITEM, 'label' => 'Item'], // @translate
                    ['key' => 'caption', 'type' => Schema::TYPE_TEXT, 'label' => 'Caption (optional; the item title is used otherwise)'], // @translate
                ],
            ]],
            array_map(function ($f) use ($fromSource) {
                $f['show_if'] = ($f['show_if'] ?? []) + $fromSource;
                return $f;
            }, ItemSource::fields(true, false)),
            [
                ['key' => 'order', 'type' => Schema::TYPE_SELECT, 'group' => 'Source', 'default' => 'random', 'label' => 'Order', 'show_if' => $fromSource, 'options' => ['random' => 'Random', 'created' => 'Newest first', 'title' => 'By title']], // @translate
                DisplayFields::heading(),
                ['key' => 'count', 'type' => Schema::TYPE_NUMBER, 'group' => 'Display', 'default' => 12, 'min' => 1, 'max' => 60, 'label' => 'How many to show', 'show_if' => $fromSource], // @translate
                ['key' => 'columns', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'auto', 'label' => 'Columns', 'options' => ['auto' => 'Fit to width', '3' => '3', '4' => '4', '6' => '6']], // @translate
                ['key' => 'show_captions', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Display', 'default' => false, 'label' => 'Show captions under the photos'], // @translate
            ]
        );
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $photos = [];
        if ($data['mode'] === 'chosen') {
            foreach ($data['items'] as $row) {
                if (!$row['item_id']) {
                    continue;
                }
                $found = $this->itemSource->items(['site_scope' => ItemSource::SCOPE_ALL], $site, ['id' => $row['item_id'], 'has_media' => true, 'limit' => 1]);
                if ($found) {
                    $photos[] = ['item' => $found[0], 'caption' => $row['caption'] !== '' ? $row['caption'] : $found[0]->displayTitle()];
                }
            }
        } else {
            $sort = $data['order'] === 'title' ? ['sort_by' => 'title', 'sort_order' => 'asc'] : ($data['order'] === 'created' ? ['sort_by' => 'created', 'sort_order' => 'desc'] : ['sort_by' => 'random']);
            foreach ($this->itemSource->items($data + ['has_media' => true], $site, $sort + ['limit' => $data['count']]) as $item) {
                $photos[] = ['item' => $item, 'caption' => $item->displayTitle()];
            }
        }
        foreach ($photos as &$p) {
            $p['thumb'] = $p['item']->thumbnailDisplayUrl('square');
            $p['large'] = $p['item']->thumbnailDisplayUrl('large');
            $p['url'] = ItemSource::url($p['item'], $data, $site);
        }
        unset($p);
        return ['photos' => array_values(array_filter($photos, function ($p) {
            return $p['thumb'];
        }))];
    }
}
