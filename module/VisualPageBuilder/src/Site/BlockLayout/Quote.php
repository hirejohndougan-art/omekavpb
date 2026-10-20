<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Exception\NotFoundException;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Stdlib\ItemSource;

/**
 * A pull quote with attribution, optionally linked to the item it came from
 * (an oral history recording, a letter…).
 */
class Quote extends AbstractSchemaBlock
{
    const DESCRIPTION = 'A large quotation with who said it, optionally linked to the item it came from.'; // @translate

    const GOOD_FOR = 'Oral histories and letters.'; // @translate

    const TEMPLATE = 'quote';

    public function getLabel()
    {
        return 'Quote'; // @translate
    }

    public function schema(): array
    {
        return [
            ['key' => 'text', 'type' => Schema::TYPE_TEXTAREA, 'group' => 'Quote', 'summary' => true, 'label' => 'Quote'], // @translate
            ['key' => 'attribution', 'type' => Schema::TYPE_TEXT, 'group' => 'Quote', 'summary' => true, 'label' => 'Who said it'], // @translate
            ['key' => 'context', 'type' => Schema::TYPE_TEXT, 'group' => 'Quote', 'label' => 'Context', 'placeholder' => 'e.g. Oral history interview, 1978'], // @translate
            ['key' => 'item_id', 'type' => Schema::TYPE_ITEM, 'group' => 'Quote', 'label' => 'Source item', 'info' => 'Optional. Links the attribution to this item.'], // @translate
            [
                'key' => 'style', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'large',
                'label' => 'Style', // @translate
                'options' => ['large' => 'Large', 'boxed' => 'In a box', 'plain' => 'Plain'], // @translate
            ],
        ];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $url = null;
        if ($data['item_id']) {
            try {
                $item = $view->api()->read('items', $data['item_id'])->getContent();
                $url = ItemSource::url($item, ['site_scope' => ItemSource::SCOPE_ALL], $site);
            } catch (NotFoundException $e) {
            }
        }
        return ['sourceUrl' => $url];
    }
}
