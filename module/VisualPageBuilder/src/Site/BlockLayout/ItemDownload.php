<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;

/**
 * Links to the item's files, so visitors can save them.
 */
class ItemDownload extends AbstractItemField
{
    const DESCRIPTION = 'Links to the item\'s files, so visitors can save them.'; // @translate

    const GOOD_FOR = 'Every item template with files.'; // @translate

    const TEMPLATE = 'item-download';
    const SAMPLE = 'Download links (the item\'s files)'; // @translate

    public function getLabel()
    {
        return 'Download'; // @translate
    }

    public function schema(): array
    {
        return [];
    }

    protected function fieldVars(PhpRenderer $view, array $data, SiteRepresentation $site, $item): array
    {
        $files = [];
        if ($item) {
            foreach ($item->media() as $media) {
                $url = $media->originalUrl();
                if ($url) {
                    $files[] = ['url' => $url, 'type' => (string) $media->mediaType(), 'title' => (string) $media->displayTitle()];
                }
            }
        }
        return ['files' => $files];
    }
}
