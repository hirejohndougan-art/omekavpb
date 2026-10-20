<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Stdlib\ImageSource;

/**
 * Cards for staff, board members, or volunteers.
 */
class People extends AbstractSchemaBlock
{
    const TEMPLATE = 'people';

    const DESCRIPTION = 'Cards for your staff, board, or volunteers, each with a photo, name, role, and a few lines about them.'; // @translate

    const GOOD_FOR = 'About and "who we are" pages.'; // @translate

    public function getLabel()
    {
        return 'People'; // @translate
    }

    public function schema(): array
    {
        return [
            DisplayFields::heading('Our people'),
            [
                'key' => 'people', 'type' => Schema::TYPE_LIST, 'group' => 'People', 'summary' => true, 'max' => 60,
                'label' => 'People', // @translate
                'fields' => [
                    ['key' => 'name', 'type' => Schema::TYPE_TEXT, 'label' => 'Name'], // @translate
                    ['key' => 'role', 'type' => Schema::TYPE_TEXT, 'label' => 'Role', 'placeholder' => 'e.g. Board president'], // @translate
                    ['key' => 'asset_id', 'type' => Schema::TYPE_ASSET, 'label' => 'Photo'], // @translate
                    ['key' => 'bio', 'type' => Schema::TYPE_TEXTAREA, 'label' => 'About them (optional)'], // @translate
                    ['key' => 'email', 'type' => Schema::TYPE_TEXT, 'label' => 'Email (optional)'], // @translate
                ],
            ],
            ['key' => 'columns', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'auto', 'label' => 'Columns', 'options' => ['auto' => 'Fit to width', '2' => '2', '3' => '3', '4' => '4']], // @translate
            ['key' => 'photo_shape', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'round', 'label' => 'Photo shape', 'options' => ['round' => 'Round', 'square' => 'Square']], // @translate
        ];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $people = [];
        foreach ($data['people'] as $person) {
            if ($person['name'] === '') {
                continue;
            }
            $people[] = $person + [
                'photo' => ImageSource::url($view, $person, 'square'),
                'email_ok' => filter_var($person['email'], FILTER_VALIDATE_EMAIL) ? $person['email'] : '',
            ];
        }
        return ['people' => $people];
    }
}
