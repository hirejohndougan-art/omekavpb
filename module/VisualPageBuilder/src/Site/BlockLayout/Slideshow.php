<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Stdlib\ImageSource;

/**
 * Large rotating photos with captions and links (special-blocks/js/slideshow.js).
 * Visitors can pause it, use the arrows or dots, or the keyboard. It doesn't
 * move by itself for people who ask their device for reduced motion. Without
 * JavaScript, the slides show one after another.
 */
class Slideshow extends AbstractSchemaBlock
{
    const TEMPLATE = 'slideshow';

    const DESCRIPTION = 'Large photos that take turns across the page, each with a title, a line of text, and an optional button. Visitors can pause it or click through.'; // @translate

    const GOOD_FOR = 'The top of the home page.'; // @translate

    public function getLabel()
    {
        return 'Featured slideshow'; // @translate
    }

    public function schema(): array
    {
        return [
            [
                'key' => 'slides', 'type' => Schema::TYPE_LIST, 'group' => 'Slides', 'summary' => true, 'max' => 12,
                'label' => 'Slides', // @translate
                'fields' => array_merge(
                    [['key' => 'title', 'type' => Schema::TYPE_TEXT, 'label' => 'Title']], // @translate
                    ImageSource::fields(),
                    [
                        ['key' => 'alt', 'type' => Schema::TYPE_TEXT, 'label' => 'Image description', 'info' => 'For people who can\'t see the photo.'], // @translate
                        ['key' => 'text', 'type' => Schema::TYPE_TEXTAREA, 'label' => 'Text (optional)'], // @translate
                        ['key' => 'button_text', 'type' => Schema::TYPE_TEXT, 'label' => 'Button text (optional)'], // @translate
                        ['key' => 'page_id', 'type' => Schema::TYPE_PAGE, 'label' => 'Button links to a page on this site'], // @translate
                        ['key' => 'url', 'type' => Schema::TYPE_URL, 'label' => 'Or to a web address', 'placeholder' => 'https://… or /s/…'], // @translate
                    ]
                ),
            ],
            ['key' => 'seconds', 'type' => Schema::TYPE_NUMBER, 'group' => 'Display', 'default' => 7, 'min' => 0, 'max' => 60, 'label' => 'Seconds per slide (0 = only change when clicked)'], // @translate
            ['key' => 'height', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'medium', 'label' => 'Height', 'options' => ['small' => 'Short', 'medium' => 'Medium', 'large' => 'Tall']], // @translate
        ];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $pages = [];
        foreach ($site->pages() as $page) {
            $pages[$page->id()] = $page;
        }
        $slides = [];
        foreach ($data['slides'] as $slide) {
            $image = ImageSource::url($view, $slide, 'large');
            if (!$image) {
                continue;
            }
            $href = $slide['page_id'] && isset($pages[$slide['page_id']]) ? $pages[$slide['page_id']]->siteUrl() : $slide['url'];
            $slides[] = $slide + ['image' => $image, 'href' => $href ?: null];
        }
        return ['slides' => $slides, 'blockKey' => $blockKey, 'needsImages' => !$slides && $data['slides']];
    }
}
