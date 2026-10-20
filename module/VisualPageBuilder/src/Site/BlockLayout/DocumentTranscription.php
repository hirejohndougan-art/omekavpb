<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Omeka\Api\Representation\ItemRepresentation;

/**
 * A scanned page beside its typed transcription.
 */
class DocumentTranscription extends AbstractTranscriptBlock
{
    const DESCRIPTION = 'Shows a scanned page from an item beside its typed transcription, so visitors can read handwriting easily.'; // @translate

    const GOOD_FOR = 'Letters, diaries, ledgers, and other handwritten records.'; // @translate

    public function getLabel()
    {
        return 'Document and transcription'; // @translate
    }

    public function schema(): array
    {
        return $this->commonFields('Which page (1 = the first image)'); // @translate
    }

    protected function pickMedia(ItemRepresentation $item, array $data)
    {
        $images = array_values(array_filter($item->media(), function ($m) {
            return $m->hasThumbnails();
        }));
        return $images[$data['media_number'] - 1] ?? ($images[0] ?? null);
    }
}
