<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder;

/**
 * Simple or Expert (Phase 9). Everyone starts in Simple, which shows plain words only. Expert
 * adds the Omeka screens to the sidebar, the Omeka ID and the dcterms code beside each question,
 * and a link to the standard Omeka form. The choice is saved per person.
 */
class Mode
{
    public const SETTING = 'vpb_mode';
    public const SIMPLE = 'simple';
    public const EXPERT = 'expert';

    /** The current person's mode, read from their own settings. */
    public static function current($services): string
    {
        $mode = (string) $services->get('Omeka\Settings\User')->get(self::SETTING, self::SIMPLE);
        return $mode === self::EXPERT ? self::EXPERT : self::SIMPLE;
    }

    public static function set($services, string $mode): void
    {
        $services->get('Omeka\Settings\User')->set(self::SETTING, $mode === self::EXPERT ? self::EXPERT : self::SIMPLE);
    }
}
