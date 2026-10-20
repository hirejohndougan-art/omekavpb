<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Stdlib\ImageSource;

/**
 * A vertical timeline of milestones you type ("1896 – the depot opens"),
 * in the order entered. Each can have a photo from an item or asset.
 */
class Milestones extends AbstractSchemaBlock
{
    const TEMPLATE = 'milestones';

    const DESCRIPTION = 'A timeline of key moments you type in, like "1896 – The depot opens", each with an optional photo.'; // @translate

    const GOOD_FOR = 'About and history pages.'; // @translate

    public function getLabel()
    {
        return 'Milestones'; // @translate
    }

    public function schema(): array
    {
        return [
            DisplayFields::heading('Our history'),
            [
                'key' => 'milestones', 'type' => Schema::TYPE_LIST, 'group' => 'Milestones', 'summary' => true, 'max' => 80,
                'label' => 'Milestones', // @translate
                'info' => 'Shown in the order you list them.', // @translate
                'fields' => array_merge([
                    ['key' => 'when', 'type' => Schema::TYPE_TEXT, 'label' => 'When', 'placeholder' => 'e.g. 1896, or Spring 1900'], // @translate
                    ['key' => 'title', 'type' => Schema::TYPE_TEXT, 'label' => 'What happened'], // @translate
                    ['key' => 'text', 'type' => Schema::TYPE_TEXTAREA, 'label' => 'More detail (optional)'], // @translate
                ], ImageSource::fields()),
            ],
        ];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $rows = [];
        foreach ($data['milestones'] as $m) {
            if ($m['when'] === '' && $m['title'] === '') {
                continue;
            }
            $rows[] = $m + ['image' => ImageSource::url($view, $m, 'medium')];
        }
        return ['milestones' => $rows];
    }
}
