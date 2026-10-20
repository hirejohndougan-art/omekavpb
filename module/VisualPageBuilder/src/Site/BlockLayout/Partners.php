<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Stdlib\ImageSource;

/**
 * A row of partner or sponsor logos, each linking to their site.
 */
class Partners extends AbstractSchemaBlock
{
    /** On pages, and in the site's header and footer. */
    const PLACEMENT = 'both';

    const TEMPLATE = 'partners';

    const DESCRIPTION = 'A row of partner or sponsor logos, each linking to their website.'; // @translate

    const GOOD_FOR = 'Thanking sponsors and showing partnerships.'; // @translate

    public function getLabel()
    {
        return 'Partners and sponsors'; // @translate
    }

    public function schema(): array
    {
        return [
            DisplayFields::heading('Our partners'),
            [
                'key' => 'partners', 'type' => Schema::TYPE_LIST, 'group' => 'Partners', 'summary' => true, 'max' => 40,
                'label' => 'Partners', // @translate
                'fields' => [
                    ['key' => 'name', 'type' => Schema::TYPE_TEXT, 'label' => 'Name'], // @translate
                    ['key' => 'asset_id', 'type' => Schema::TYPE_ASSET, 'label' => 'Logo'], // @translate
                    ['key' => 'url', 'type' => Schema::TYPE_URL, 'label' => 'Website', 'placeholder' => 'https://…'], // @translate
                ],
            ],
            ['key' => 'logo_height', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'medium', 'label' => 'Logo size', 'options' => ['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large']], // @translate
            ['key' => 'grayscale', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Display', 'default' => false, 'label' => 'Show logos in gray until pointed at'], // @translate
        ];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $partners = [];
        foreach ($data['partners'] as $partner) {
            $logo = ImageSource::url($view, $partner);
            if ($partner['name'] === '' && !$logo) {
                continue;
            }
            $partners[] = $partner + ['logo' => $logo];
        }
        return ['partners' => $partners];
    }
}
