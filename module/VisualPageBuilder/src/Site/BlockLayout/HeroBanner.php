<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Exception\NotFoundException;
use VisualPageBuilder\Schema\Schema;

/**
 * A wide image with a title, subtitle, and button laid over it.
 */
class HeroBanner extends AbstractSchemaBlock
{
    const DESCRIPTION = 'A wide photo across the page with a large title, short text, and a button on top of it.'; // @translate

    const GOOD_FOR = 'The top of the home page or an exhibit.'; // @translate

    const TEMPLATE = 'hero-banner';

    public function getLabel()
    {
        return 'Hero banner'; // @translate
    }

    public function schema(): array
    {
        return [
            ['key' => 'asset_id', 'type' => Schema::TYPE_ASSET, 'group' => 'Image', 'summary' => true, 'label' => 'Background image'], // @translate
            ['key' => 'alt', 'type' => Schema::TYPE_TEXT, 'group' => 'Image', 'label' => 'Image description', 'info' => 'Describe the photo for people who can\'t see it. Leave empty if it\'s only decoration.'], // @translate
            [
                'key' => 'focus', 'type' => Schema::TYPE_SELECT, 'group' => 'Image', 'default' => 'center',
                'label' => 'Keep in view when cropped', // @translate
                'options' => ['top' => 'Top', 'center' => 'Center', 'bottom' => 'Bottom'], // @translate
            ],
            ['key' => 'title', 'type' => Schema::TYPE_TEXT, 'group' => 'Text', 'summary' => true, 'label' => 'Title'], // @translate
            ['key' => 'subtitle', 'type' => Schema::TYPE_TEXTAREA, 'group' => 'Text', 'label' => 'Subtitle'], // @translate
            ['key' => 'button_text', 'type' => Schema::TYPE_TEXT, 'group' => 'Button', 'label' => 'Button text', 'placeholder' => 'Leave empty for no button'], // @translate
            ['key' => 'button_url', 'type' => Schema::TYPE_URL, 'group' => 'Button', 'label' => 'Button link', 'placeholder' => 'https://… or /s/…'], // @translate
            [
                'key' => 'height', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'medium',
                'label' => 'Height', // @translate
                'options' => ['small' => 'Short', 'medium' => 'Medium', 'large' => 'Tall', 'screen' => 'Full screen'], // @translate
            ],
            [
                'key' => 'text_position', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'bottom-left',
                'label' => 'Text position', // @translate
                'options' => ['bottom-left' => 'Bottom left', 'center' => 'Center', 'top-left' => 'Top left'], // @translate
            ],
            [
                'key' => 'shade', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'dark',
                'label' => 'Shade behind the text', // @translate
                'info' => 'Keeps white text readable on light photos.', // @translate
                'options' => ['none' => 'None', 'light' => 'Light', 'dark' => 'Dark'], // @translate
            ],
        ];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $asset = null;
        if ($data['asset_id']) {
            try {
                $asset = $view->api()->read('assets', $data['asset_id'])->getContent();
            } catch (NotFoundException $e) {
            }
        }
        return ['imageUrl' => $asset ? $asset->assetUrl() : null];
    }
}
