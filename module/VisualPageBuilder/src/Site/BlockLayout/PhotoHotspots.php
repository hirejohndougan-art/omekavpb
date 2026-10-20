<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Stdlib\ImageSource;

/**
 * "Who's who in this photo": a photo with numbered markers, each naming a
 * person or place. The numbered list under the photo always shows every
 * name (so it works without JavaScript and for screen readers);
 * special-blocks/js/hotspots.js lets visitors tap a marker to see its note.
 *
 * Marker positions are percentages: x from the left edge, y from the top.
 */
class PhotoHotspots extends AbstractSchemaBlock
{
    const TEMPLATE = 'photo-hotspots';

    const DESCRIPTION = 'A photo with numbered markers on people or buildings. Visitors tap a marker to see who or what it is, and a numbered list under the photo names everyone.'; // @translate

    const GOOD_FOR = 'Class photos, team photos, and street scenes.'; // @translate

    public function getLabel()
    {
        return 'Who\'s who in this photo'; // @translate
    }

    public function schema(): array
    {
        return array_merge(
            [DisplayFields::heading()],
            array_map(function ($f) {
                return $f + ['group' => 'Photo'];
            }, ImageSource::fields()),
            [
                ['key' => 'alt', 'type' => Schema::TYPE_TEXT, 'group' => 'Photo', 'label' => 'Photo description', 'info' => 'A short description for people who can\'t see the photo.'], // @translate
                [
                    'key' => 'markers', 'type' => Schema::TYPE_LIST, 'group' => 'Markers', 'summary' => true, 'max' => 60,
                    'label' => 'Markers', // @translate
                    'info' => 'Position each marker with two numbers: how far across (0 = left edge, 100 = right edge) and how far down (0 = top, 100 = bottom). The preview shows where it lands.', // @translate
                    'fields' => [
                        ['key' => 'label', 'type' => Schema::TYPE_TEXT, 'label' => 'Name'], // @translate
                        ['key' => 'note', 'type' => Schema::TYPE_TEXTAREA, 'label' => 'Note (optional)'], // @translate
                        ['key' => 'x', 'type' => Schema::TYPE_NUMBER, 'default' => 50, 'min' => 0, 'max' => 100, 'label' => 'Across (0–100)'], // @translate
                        ['key' => 'y', 'type' => Schema::TYPE_NUMBER, 'default' => 50, 'min' => 0, 'max' => 100, 'label' => 'Down (0–100)'], // @translate
                    ],
                ],
                ['key' => 'caption', 'type' => Schema::TYPE_TEXTAREA, 'group' => 'Display', 'label' => 'Caption (optional)'], // @translate
                ['key' => 'show_list', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Display', 'default' => true, 'label' => 'Show the numbered list of names under the photo'], // @translate
            ]
        );
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        return [
            'imageUrl' => ImageSource::url($view, $data, 'large'),
            'markers' => array_values(array_filter($data['markers'], function ($m) {
                return $m['label'] !== '';
            })),
            'idBase' => 'vpb-who-' . preg_replace('/[^a-z0-9]/i', '', $blockKey),
        ];
    }
}
