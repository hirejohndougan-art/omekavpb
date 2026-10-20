<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Stdlib\ItemSource;

/**
 * A map (OpenStreetMap, drawn with the module's own map, asset/map) with pinned
 * places, each optionally linked to an item from the archive. As a tour, the
 * pins are numbered and joined in order (a walking tour).
 *
 * Places are placed by clicking the map in the editor
 * (asset/js/editor/blocks/places-map.js) or by typing coordinates. A list of
 * the places is always printed under the map, so the content works without
 * JavaScript and for screen readers. Map pictures come from OpenStreetMap's
 * tile servers (credited on the map).
 */
class PlacesMap extends AbstractSchemaBlock
{
    const TEMPLATE = 'places-map';

    const DESCRIPTION = 'A map with pins on the places you choose. Each pin can link to an item from the archive. Turn on "tour" to number the stops and join them in order.'; // @translate

    const GOOD_FOR = 'Historic sites, walking tours, and "where was this taken?" pages.'; // @translate

    public function getLabel()
    {
        return 'Map of places'; // @translate
    }

    public function schema(): array
    {
        return [
            DisplayFields::heading(),
            [
                'key' => 'places', 'type' => Schema::TYPE_LIST, 'group' => 'Places', 'summary' => true, 'max' => 200,
                'label' => 'Places', // @translate
                'info' => 'Click the map above to add a place, or type its latitude and longitude (from Google Maps: right-click a spot and click the numbers).', // @translate
                'fields' => [
                    ['key' => 'name', 'type' => Schema::TYPE_TEXT, 'label' => 'Name'], // @translate
                    ['key' => 'lat', 'type' => Schema::TYPE_TEXT, 'label' => 'Latitude', 'placeholder' => 'e.g. 29.4608'], // @translate
                    ['key' => 'lng', 'type' => Schema::TYPE_TEXT, 'label' => 'Longitude', 'placeholder' => 'e.g. -95.0513'], // @translate
                    ['key' => 'note', 'type' => Schema::TYPE_TEXTAREA, 'label' => 'Note (optional)'], // @translate
                    ['key' => 'item_id', 'type' => Schema::TYPE_ITEM, 'label' => 'Link to an item (optional)'], // @translate
                    [
                        'key' => 'img', 'type' => Schema::TYPE_SELECT, 'default' => 'item', 'label' => 'Picture', // @translate
                        'options' => ['none' => 'No picture', 'item' => 'The item\'s picture', 'asset' => 'Choose an image'], // @translate
                    ],
                    ['key' => 'asset_id', 'type' => Schema::TYPE_ASSET, 'label' => 'Image', 'show_if' => ['img' => ['asset']]], // @translate
                ],
            ],
            ['key' => 'tour', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Display', 'default' => false, 'label' => 'Show as a tour (numbered stops joined in order)'], // @translate
            ['key' => 'height', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'medium', 'label' => 'Map height', 'options' => ['short' => 'Short', 'medium' => 'Medium', 'tall' => 'Tall']], // @translate
            ['key' => 'show_list', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Display', 'default' => true, 'label' => 'Show the list of places under the map'], // @translate
        ];
    }

    /** A coordinate from text, or null if it isn't one. */
    public static function coord($value, float $limit): ?float
    {
        $value = str_replace([' ', '°'], '', (string) $value);
        if (!preg_match('/^-?\d{1,3}(\.\d+)?$/', $value)) {
            return null;
        }
        $f = (float) $value;
        return abs($f) <= $limit ? round($f, 6) : null;
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $places = [];
        foreach ($data['places'] as $p) {
            $lat = self::coord($p['lat'], 90);
            $lng = self::coord($p['lng'], 180);
            if ($lat === null || $lng === null) {
                continue;
            }
            $item = null;
            if ($p['item_id']) {
                $found = $this->itemSource->items(['site_scope' => ItemSource::SCOPE_ALL], $site, ['id' => $p['item_id'], 'limit' => 1]);
                $item = $found[0] ?? null;
            }
            $places[] = [
                'name' => $p['name'] !== '' ? $p['name'] : ($item ? $item->displayTitle() : sprintf('%s, %s', $lat, $lng)),
                'lat' => $lat,
                'lng' => $lng,
                'note' => $p['note'],
                'url' => $item ? ItemSource::url($item, ['site_scope' => ItemSource::SCOPE_ALL], $site) : null,
                'thumb' => ($p['img'] ?? 'item') === 'asset' && !empty($p['asset_id'])
                    ? \VisualPageBuilder\Stdlib\ImageSource::url($view, ['asset_id' => $p['asset_id']])
                    : (($p['img'] ?? 'item') === 'item' && $item ? $item->thumbnailDisplayUrl('square') : null),
                'itemTitle' => $item ? $item->displayTitle() : null,
            ];
        }
        return ['places' => $places, 'mapId' => 'vpb-map-' . preg_replace('/[^a-z0-9]/i', '', $blockKey), 'isPreview' => $blockKey === 'preview'];
    }
}
