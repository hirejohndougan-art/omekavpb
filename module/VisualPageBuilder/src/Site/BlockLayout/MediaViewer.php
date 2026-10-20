<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Templates\CurrentRecord;

/**
 * The file itself (an image, audio, video, PDF, or text), drawn the way Omeka draws
 * it. Required on the Media screen: a media template must show its file.
 */
class MediaViewer extends AbstractSchemaBlock
{
    const DESCRIPTION = 'The media file itself: an image, audio, video, PDF, or text, drawn the way Omeka draws it.'; // @translate

    const GOOD_FOR = 'The Media screen. It is required there.'; // @translate

    const TEMPLATE = 'media-viewer';

    /** Label shown in the editor's preview when there's no media. */
    const SAMPLE = 'The media file appears here.'; // @translate

    public function getLabel()
    {
        return 'Media viewer'; // @translate
    }

    public function schema(): array
    {
        return [];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $media = CurrentRecord::get();
        return [
            'html' => $media ? (string) $media->render() : null,
            'isPreview' => $blockKey === 'preview',
            'sample' => static::SAMPLE,
            'blockClass' => static::cssClass(),
        ];
    }
}
