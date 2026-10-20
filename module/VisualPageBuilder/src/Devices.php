<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder;

/**
 * Block settings by device. A block can be hidden on some devices (layout data
 * "vpb_hide", comma-separated: desktop, tablet, phone) and can have its own
 * padding on tablet and phone ("vpb_pad_tablet", "vpb_pad_phone", in px).
 *
 * The public page marks the block's own wrapper; public.css does the rest.
 * Ranges: phone up to 600px, tablet 601-896px, desktop from 897px.
 */
class Devices
{
    const ALL = ['desktop', 'tablet', 'phone'];

    /** Devices that can have their own padding (desktop uses the theme default). */
    const PAD_DEVICES = ['tablet', 'phone'];

    const PAD_MAX = 64;

    /** The known devices in a stored "vpb_hide" value, in ALL order. */
    public static function fromValue($raw): array
    {
        $listed = array_map('trim', explode(',', (string) $raw));
        return array_values(array_intersect(self::ALL, $listed));
    }

    /** A padding value in px (0 to 64), or null when the device uses the theme default. */
    public static function padValue($raw): ?int
    {
        if ((is_int($raw) || (is_string($raw) && ctype_digit(trim($raw))))
            && (int) $raw >= 0) {
            return min(self::PAD_MAX, (int) $raw);
        }
        return null;
    }

    /**
     * Marks the block's own wrapper: "vpb-hide-<device>" classes, "vpb-has-pad-<device>"
     * classes with "--vpb-pad-<device>" custom properties (for the padding overrides).
     * Falls back to a wrapper div when the block has none.
     *
     * @param string[] $devices hidden devices
     * @param array<string, ?int> $pads padding per device (null: theme default)
     */
    public static function decorate(string $html, array $devices, array $pads = []): string
    {
        $devices = array_values(array_intersect(self::ALL, $devices));
        $padded = [];
        foreach (self::PAD_DEVICES as $dev) {
            if (isset($pads[$dev]) && self::padValue($pads[$dev]) !== null) {
                $padded[$dev] = self::padValue($pads[$dev]);
            }
        }
        if (!$devices && !$padded) {
            return $html;
        }

        $classes = array_merge(
            array_map(fn ($d) => 'vpb-hide-' . $d, $devices),
            array_map(fn ($d) => 'vpb-has-pad-' . $d, array_keys($padded))
        );
        $vars = array_map(fn ($d, $px) => '--vpb-pad-' . $d . ':' . $px . 'px', array_keys($padded), $padded);

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
