<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Templates;

/**
 * What the Main image block shows when an item has no file (plan decision 12).
 *
 * The block never disappears. Its placeholder is a plain panel with the kind of
 * item ("Oral history", "Document", …), unless the item's media can stand in:
 * an audio file shows the audio player, a PDF shows its first page when Omeka
 * has made a thumbnail. Pure: takes plain values.
 */
class Placeholder
{
    /**
     * The kind of item for the panel: its resource template's label, else its
     * resource class's label, else "Item".
     */
    public static function kindLabel(?string $templateLabel, ?string $classLabel): string
    {
        foreach ([$templateLabel, $classLabel] as $label) {
            if (is_string($label) && trim($label) !== '') {
                return trim($label);
            }
        }
        return 'Item';
    }

    /**
     * How a media file is shown in the main image spot.
     *
     * @return string 'image' (a picture or a thumbnail), 'audio' (the player),
     *   'pdf' (the first page, when there's a thumbnail), 'other' (a plain link),
     *   or 'none' (the placeholder panel).
     */
    public static function mediaKind(?string $mediaType, bool $hasThumbnail): string
    {
        $type = strtolower((string) $mediaType);
        if ($type === '') {
            return 'none';
        }
        if (strpos($type, 'image/') === 0) {
            return 'image';
        }
        if (strpos($type, 'audio/') === 0) {
            return 'audio';
        }
        if ($type === 'application/pdf') {
            return $hasThumbnail ? 'pdf' : 'other';
        }
        return $hasThumbnail ? 'image' : 'other';
    }
}
