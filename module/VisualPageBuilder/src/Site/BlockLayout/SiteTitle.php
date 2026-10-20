<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;

/**
 * The site's name as text, with an optional tagline, linking home.
 */
class SiteTitle extends AbstractSchemaBlock
{
    /** Only for the site's header and footer. */
    const PLACEMENT = 'region';

    const TEMPLATE = 'site-title';

    const DESCRIPTION = 'Your site\'s name in large text, with an optional line under it, linking to the home page.'; // @translate

    const GOOD_FOR = 'Headers without a logo image.'; // @translate

    public function getLabel()
    {
        return 'Site title'; // @translate
    }

    public function schema(): array
    {
        return [
            ['key' => 'text', 'type' => Schema::TYPE_TEXT, 'group' => 'Title', 'summary' => true, 'label' => 'Title', 'placeholder' => 'Leave empty to use the site\'s name'], // @translate
            ['key' => 'tagline', 'type' => Schema::TYPE_TEXT, 'group' => 'Title', 'label' => 'Line under it (optional)', 'placeholder' => 'e.g. Preserving local history since [year]'], // @translate
            ['key' => 'size', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'medium', 'label' => 'Size', 'options' => ['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large']], // @translate
            ['key' => 'align', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'left', 'label' => 'Alignment', 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right']], // @translate
        ];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        return ['title' => $data['text'] !== '' ? $data['text'] : $site->title(), 'homeUrl' => $site->siteUrl()];
    }
}
