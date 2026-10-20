<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder;

/**
 * Block settings by device. A block can be hidden on some devices (layout data
 * "vpb_hide", comma-separated: desktop, tablet, phone), and can have its own
 * padding ("vpb_pad_tablet", "vpb_pad_phone", px) and text size ("vpb_text_tablet",
 * "vpb_text_phone", percent of the theme's text size) on tablet and phone.
 *
 * The public page marks the block's own wrapper; public.css does the rest.
 * Ranges: phone up to 600px, tablet 601-896px, desktop from 897px.
 */
class Devices
{
    const ALL = ['desktop', 'tablet', 'phone'];

    /** Devices that can have their own padding and text size (desktop uses the theme's). */
    const OWN_DEVICES = ['tablet', 'phone'];

    const PAD_MAX = 64;
    const TEXT_MIN = 60;
    const TEXT_MAX = 150;

    /** The known devices in a stored "vpb_hide" value, in ALL order. */
    public static function fromValue($raw): array
    {
        $listed = array_map('trim', explode(',', (string) $raw));
        return array_values(array_intersect(self::ALL, $listed));
    }

    /** A padding value in px (0 to 64), or null when the device uses the theme default. */
    public static function padValue($raw): ?int
    {
        if ((is_int($raw) || (is_string($raw) && ctype_digit(trim($raw)))) && (int) $raw >= 0) {
            return min(self::PAD_MAX, (int) $raw);
        }
        return null;
    }

    /** A text size in percent (60 to 150), or null when the device uses the theme's size. */
    public static function textValue($raw): ?int
    {
        if ((is_int($raw) || (is_string($raw) && ctype_digit(trim($raw))))) {
            return max(self::TEXT_MIN, min(self::TEXT_MAX, (int) $raw));
        }
        return null;
    }

    /**
     * Marks the block's own wrapper: "vpb-hide-<device>" classes; for padding and
     * text size, "vpb-has-pad-<device>" / "vpb-has-text-<device>" classes with the
     * value in a custom property ("--vpb-pad-<device>", "--vpb-text-<device>").
     * Falls back to a wrapper div when the block has none.
     *
     * @param string[] $devices hidden devices
     * @param array<string, mixed> $pads padding per device (null: theme default)
     * @param array<string, mixed> $texts text size per device (null: theme default)
     */
    public static function decorate(string $html, array $devices, array $pads = [], array $texts = []): string
    {
        $devices = array_values(array_intersect(self::ALL, $devices));
        $classes = array_map(fn ($d) => 'vpb-hide-' . $d, $devices);
        $vars = [];
        foreach (self::OWN_DEVICES as $dev) {
            if (isset($pads[$dev]) && self::padValue($pads[$dev]) !== null) {
                $classes[] = 'vpb-has-pad-' . $dev;
                $vars[] = '--vpb-pad-' . $dev . ':' . self::padValue($pads[$dev]) . 'px';
            }
            if (isset($texts[$dev]) && self::textValue($texts[$dev]) !== null) {
                $classes[] = 'vpb-has-text-' . $dev;
                $vars[] = '--vpb-text-' . $dev . ':' . self::textValue($texts[$dev]);
            }
        }
        if (!$classes) {
            return $html;
        }

        if (!preg_match('/<div class="block [^>]*>/', $html, $m)) {
            $style = $vars ? ' style="' . implode(';', $vars) . '"' : '';
            return '<div class="' . implode(' ', $classes) . '"' . $style . '>' . $html . '</div>';
        }
        $tag = $m[0];
        $new = str_replace('<div class="block ', '<div class="block ' . implode(' ', $classes) . ' ', $tag);
        if ($vars) {
            if (preg_match('/ style="/', $new)) {
                $new = preg_replace('/ style="/', ' style="' . implode(';', $vars) . ';', $new, 1);
            } else {
                $new = substr($new, 0, -1) . ' style="' . implode(';', $vars) . '">';
            }
        }
        return substr_replace($html, $new, strpos($html, $tag), strlen($tag));
    }
}
