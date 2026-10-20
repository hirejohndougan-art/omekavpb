<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder;

/**
 * Block visibility by device. A block's layout data "vpb_hide" lists the
 * devices it is hidden on ("desktop", "tablet", "phone", comma-separated).
 * The public page adds a class per device to the block's wrapper; public.css
 * hides it in that device's width range.
 */
class Devices
{
    const ALL = ['desktop', 'tablet', 'phone'];

    /** The known devices in a stored "vpb_hide" value, in ALL order. */
    public static function fromValue($raw): array
    {
        $listed = array_map('trim', explode(',', (string) $raw));
        return array_values(array_intersect(self::ALL, $listed));
    }

    /**
     * Adds "vpb-hide-<device>" to the block's own wrapper (so its grid placement
     * is unchanged). Falls back to a wrapper div when the block has none.
     */
    public static function withClasses(string $html, array $devices): string
    {
        $devices = array_values(array_intersect(self::ALL, $devices));
        if (!$devices) {
            return $html;
        }
        $classes = implode(' ', array_map(fn ($d) => 'vpb-hide-' . $d, $devices));
        $out = preg_replace('/<div class="block /', '<div class="block ' . $classes . ' ', $html, 1, $count);
        if ($count) {
            return $out;
        }
        return '<div class="' . $classes . '">' . $html . '</div>';
    }
}
