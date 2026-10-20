<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Stdlib\ImageSource;

/**
 * The site's logo, linking to the home page. Falls back to the Companion
 * Theme's logo setting, then to the site title as text.
 */
class Logo extends AbstractSchemaBlock
{
    /** Only for the site's header and footer. */
    const PLACEMENT = 'region';

    const TEMPLATE = 'logo';

    const DESCRIPTION = 'Your logo, linking to the home page. Uses the theme\'s logo if you don\'t choose one here, or the site\'s name as text.'; // @translate

    const GOOD_FOR = 'Headers and footers.'; // @translate

    public function getLabel()
    {
        return 'Logo'; // @translate
    }

    public function schema(): array
    {
        return [
            ['key' => 'asset_id', 'type' => Schema::TYPE_ASSET, 'group' => 'Logo', 'summary' => true, 'label' => 'Logo image (optional)'], // @translate
            ['key' => 'height', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'medium', 'label' => 'Size', 'options' => ['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large']], // @translate
            ['key' => 'align', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'left', 'label' => 'Alignment', 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right']], // @translate
        ];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $url = ImageSource::url($view, $data);
        if (!$url) {
            // The Companion Theme's logo setting, if this site uses it.
            $themeSettings = $view->siteSetting('theme_settings_visual-page-builder-companion', [], $site->id());
            if (!empty($themeSettings['logo'])) {
                $url = ImageSource::url($view, ['asset_id' => $themeSettings['logo']]);
            }
        }
        return ['logoUrl' => $url, 'homeUrl' => $site->siteUrl(), 'siteTitle' => $site->title()];
    }
}
