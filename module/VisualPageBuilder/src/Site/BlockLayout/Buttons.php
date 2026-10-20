<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;

/**
 * One or more buttons: Visit, Donate, Become a member… Each links to a page
 * on this site or to any web address.
 */
class Buttons extends AbstractSchemaBlock
{
    const DESCRIPTION = 'One or more buttons, like Visit, Donate, or Become a member, that link anywhere.'; // @translate

    const GOOD_FOR = 'Calls to action.'; // @translate

    /** On pages, and in the site's header and footer. */
    const PLACEMENT = 'both';

    const TEMPLATE = 'buttons';

    public function getLabel()
    {
        return 'Buttons'; // @translate
    }

    public function schema(): array
    {
        return [
            [
                'key' => 'buttons', 'type' => Schema::TYPE_LIST, 'group' => 'Buttons', 'summary' => true, 'max' => 6,
                'label' => 'Buttons', // @translate
                'fields' => [
                    ['key' => 'text', 'type' => Schema::TYPE_TEXT, 'label' => 'Text'], // @translate
                    ['key' => 'page_id', 'type' => Schema::TYPE_PAGE, 'label' => 'Link to a page on this site'], // @translate
                    ['key' => 'url', 'type' => Schema::TYPE_URL, 'label' => 'Or a web address', 'placeholder' => 'https://…'], // @translate
                    [
                        'key' => 'style', 'type' => Schema::TYPE_SELECT, 'default' => 'primary',
                        'label' => 'Style', // @translate
                        'options' => ['primary' => 'Solid', 'secondary' => 'Outline', 'link' => 'Text link'], // @translate
                    ],
                    ['key' => 'new_tab', 'type' => Schema::TYPE_CHECKBOX, 'default' => false, 'label' => 'Open in a new tab'], // @translate
                ],
            ],
            [
                'key' => 'align', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'left',
                'label' => 'Alignment', // @translate
                'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], // @translate
            ],
            [
                'key' => 'size', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'normal',
                'label' => 'Size', // @translate
                'options' => ['normal' => 'Normal', 'large' => 'Large'], // @translate
            ],
        ];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $pages = [];
        foreach ($site->pages() as $page) {
            $pages[$page->id()] = $page;
        }
        $buttons = [];
        foreach ($data['buttons'] as $button) {
            $url = $button['url'];
            if ($button['page_id'] && isset($pages[$button['page_id']])) {
                $url = $pages[$button['page_id']]->siteUrl();
            }
            if ($button['text'] === '' || !$url) {
                continue;
            }
            $buttons[] = $button + ['href' => $url];
        }
        return ['buttons' => $buttons];
    }
}
