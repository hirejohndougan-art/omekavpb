<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Exception\NotFoundException;
use VisualPageBuilder\Schema\Schema;

/**
 * A search field that searches one site's items, optionally only in some
 * item sets.
 *
 * Results open on a site's own item browse page, and Omeka shows one site's
 * items there, so a search box searches one site: this one or another you
 * choose. (To let visitors search several sites, use one box per site.)
 */
class SearchBox extends AbstractSchemaBlock
{
    const DESCRIPTION = 'A search field visitors can use to search one site\'s collection, optionally only some item sets.'; // @translate

    const GOOD_FOR = 'Home pages and collection landing pages.'; // @translate

    /** On pages, and in the site's header and footer. */
    const PLACEMENT = 'both';

    const TEMPLATE = 'search-box';

    public function getLabel()
    {
        return 'Search box'; // @translate
    }

    public function schema(): array
    {
        return [
            [
                'key' => 'target_site', 'type' => Schema::TYPE_SITE, 'group' => 'Search', 'summary' => true,
                'label' => 'Search on site', // @translate
                'info' => 'Leave empty to search this site.', // @translate
                'placeholder' => 'This site', // @translate
            ],
            [
                'key' => 'item_set_ids', 'type' => Schema::TYPE_ITEM_SETS, 'group' => 'Search', 'summary' => true,
                'label' => 'Only in these item sets', // @translate
                'info' => 'Optional.', // @translate
            ],
            DisplayFields::heading(),
            ['key' => 'label', 'type' => Schema::TYPE_TEXT, 'group' => 'Display', 'default' => 'Search the collection', 'label' => 'Field label (read aloud to screen readers)'], // @translate
            ['key' => 'show_label', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Display', 'default' => false, 'label' => 'Show the label on the page'], // @translate
            ['key' => 'placeholder', 'type' => Schema::TYPE_TEXT, 'group' => 'Display', 'default' => 'Search photos, documents, and more', 'label' => 'Placeholder text'], // @translate
            ['key' => 'button_text', 'type' => Schema::TYPE_TEXT, 'group' => 'Display', 'default' => 'Search', 'label' => 'Button text'], // @translate
            [
                'key' => 'size', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'large',
                'label' => 'Size', // @translate
                'options' => ['normal' => 'Normal', 'large' => 'Large'], // @translate
            ],
        ];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        if ($data['target_site'] && $data['target_site'] !== $site->id()) {
            try {
                $site = $view->api()->read('sites', $data['target_site'])->getContent();
            } catch (NotFoundException $e) {
                // Private or deleted: search this site instead.
            }
        }
        return [
            'action' => $view->url('site/resource', ['site-slug' => $site->slug(), 'controller' => 'item', 'action' => 'browse']),
            'inputId' => 'special-search-' . $blockKey,
        ];
    }
}
