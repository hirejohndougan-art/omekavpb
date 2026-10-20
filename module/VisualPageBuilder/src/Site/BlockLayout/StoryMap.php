<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Stdlib\ImageSource;
use VisualPageBuilder\Stdlib\ItemSource;

/**
 * Story map: a map (OpenStreetMap, the module's own map code) with a timeline under it.
 * Visitors press Play and the map moves from stop to stop in date order,
 * while an information pane shows each stop: a background photo with text
 * over it, an optional video (YouTube, Vimeo, or the linked item's own video
 * file), and a link to the item.
 *
 * Never plays by itself. Pauses at a stop with a video. With reduced motion,
 * the map jumps instead of gliding (special-blocks/js/story-map.js). Without
 * JavaScript every stop's pane is shown in order, so nothing is lost.
 */
class StoryMap extends AbstractSchemaBlock
{
    const TEMPLATE = 'story-map';

    const DESCRIPTION = 'A map with a timeline under it. Visitors press Play and the map moves from place to place in date order, while a pane tells each stop\'s story with a photo, text, and video.'; // @translate

    const GOOD_FOR = 'Town histories, walking tours through time, and exhibits.'; // @translate

    public function getLabel()
    {
        return 'Story map'; // @translate
    }

    public function schema(): array
    {
        return [
            [
                'key' => 'stops', 'type' => Schema::TYPE_LIST, 'group' => 'Stops', 'summary' => true, 'max' => 100,
                'label' => 'Stops', // @translate
                'info' => 'Click the map above to add a stop there. Stops play in date order, whatever order they\'re listed in.', // @translate
                'fields' => [
                    ['key' => 'title', 'type' => Schema::TYPE_TEXT, 'label' => 'Title'], // @translate
                    ['key' => 'date', 'type' => Schema::TYPE_TEXT, 'label' => 'Date', 'placeholder' => 'e.g. 1900-09-08, June 1911, or circa 1920'], // @translate
                    ['key' => 'lat', 'type' => Schema::TYPE_TEXT, 'label' => 'Latitude'], // @translate
                    ['key' => 'lng', 'type' => Schema::TYPE_TEXT, 'label' => 'Longitude'], // @translate
                    ['key' => 'item_id', 'type' => Schema::TYPE_ITEM, 'label' => 'Link an item (optional)'], // @translate
                    [
                        'key' => 'img', 'type' => Schema::TYPE_SELECT, 'default' => 'item', 'label' => 'Pane background photo', // @translate
                        'options' => ['none' => 'None', 'item' => 'The item\'s picture', 'asset' => 'Choose an image'], // @translate
                    ],
                    ['key' => 'asset_id', 'type' => Schema::TYPE_ASSET, 'label' => 'Image', 'show_if' => ['img' => ['asset']]], // @translate
                    ['key' => 'text', 'type' => Schema::TYPE_TEXTAREA, 'label' => 'Text in the pane'], // @translate
                    [
                        'key' => 'video', 'type' => Schema::TYPE_SELECT, 'default' => 'none', 'label' => 'Video (optional)', // @translate
                        'options' => ['none' => 'No video', 'url' => 'YouTube or Vimeo', 'item' => 'The item\'s video'], // @translate
                    ],
                    ['key' => 'video_url', 'type' => Schema::TYPE_URL, 'label' => 'YouTube or Vimeo address', 'placeholder' => 'https://www.youtube.com/watch?v=…', 'show_if' => ['video' => ['url']]], // @translate
                ],
            ],
            ['key' => 'seconds', 'type' => Schema::TYPE_SELECT, 'group' => 'Playback', 'default' => '6', 'label' => 'Seconds at each stop', 'options' => ['4' => '4 seconds', '6' => '6 seconds', '10' => '10 seconds', '15' => '15 seconds']], // @translate
            ['key' => 'loop', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Playback', 'default' => false, 'label' => 'Start over after the last stop'], // @translate
            ['key' => 'pane', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'right', 'label' => 'Information pane', 'options' => ['right' => 'Right of the map', 'left' => 'Left of the map', 'below' => 'Under the map']], // @translate
            ['key' => 'height', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'medium', 'label' => 'Map height', 'options' => ['short' => 'Short', 'medium' => 'Medium', 'tall' => 'Tall']], // @translate
        ];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $stops = [];
        foreach ($data['stops'] as $s) {
            $item = null;
            if ($s['item_id']) {
                $found = $this->itemSource->items(['site_scope' => ItemSource::SCOPE_ALL], $site, ['id' => $s['item_id'], 'limit' => 1]);
                $item = $found[0] ?? null;
            }
            $lat = PlacesMap::coord($s['lat'], 90);
            $lng = PlacesMap::coord($s['lng'], 180);
            $when = $s['date'] !== '' ? $s['date'] : ($item ? (string) $item->value('dcterms:date') : '');
            $date = Timeline::parseDate($when);
            $title = $s['title'] !== '' ? $s['title'] : ($item ? $item->displayTitle() : '');
            if ($lat === null || $lng === null || !$date || $title === '') {
                continue;
            }
            $photo = null;
            if ($s['img'] === 'item' && $item) {
                $photo = $item->thumbnailDisplayUrl('large');
            } elseif ($s['img'] === 'asset' && $s['asset_id']) {
                $photo = ImageSource::url($view, ['asset_id' => $s['asset_id']]);
            }
            $video = null;
            if ($s['video'] === 'url') {
                $embed = Embed::toEmbedUrl($s['video_url']);
                if ($embed && in_array($embed['site'], ['YouTube', 'Vimeo'], true)) {
                    $video = ['kind' => 'embed', 'src' => $embed['src'], 'site' => $embed['site'], 'link' => $s['video_url']];
                }
            } elseif ($s['video'] === 'item' && $item) {
                foreach ($item->media() as $m) {
                    if (strpos((string) $m->mediaType(), 'video/') === 0 && $m->hasOriginal()) {
                        $video = ['kind' => 'file', 'src' => $m->originalUrl(), 'type' => $m->mediaType(), 'link' => $m->originalUrl()];
                        break;
                    }
                }
            }
            $stops[] = [
                'key' => $date['key'], 'year' => $date['year'], 'when' => $when, 'title' => $title, 'text' => $s['text'],
                'lat' => $lat, 'lng' => $lng, 'photo' => $photo, 'video' => $video,
                'url' => $item ? ItemSource::url($item, ['site_scope' => ItemSource::SCOPE_ALL], $site) : null,
                'itemTitle' => $item ? $item->displayTitle() : null,
            ];
        }
        usort($stops, function ($a, $b) {
            return strcmp($a['key'], $b['key']);
        });
        return [
            'stops' => $stops,
            'storyId' => 'vpb-story-' . preg_replace('/[^a-z0-9]/i', '', $blockKey),
            'isPreview' => $blockKey === 'preview',
        ];
    }
}
