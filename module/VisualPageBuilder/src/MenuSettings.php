<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder;

/**
 * The site menu's look, chosen on the Menu page (Visual Builder › Menu):
 * its style (site setting vpb_menu_style) and its colors (site setting
 * vpb_menu_colors). The Companion Theme and the Site menu block both draw
 * the menu from these, through the vpbMenu view helper.
 *
 * Colors are five hex values, each optional (empty = the theme's own):
 *   bar       the menu's background
 *   link      its links
 *   hover     a link you point at, and the current page
 *   dropBg    drop-downs and panels
 *   dropLink  links in drop-downs and panels
 * They reach the theme's stylesheet as CSS custom properties on the menu
 * (--pb-nav-bg, --pb-nav-link, --pb-nav-hover, --pb-nav-drop-bg,
 * --pb-nav-drop-link).
 */
class MenuSettings
{
    const COLOR_KEYS = ['bar' => '--pb-nav-bg', 'link' => '--pb-nav-link', 'hover' => '--pb-nav-hover', 'dropBg' => '--pb-nav-drop-bg', 'dropLink' => '--pb-nav-drop-link'];

    /** Ready-made color schemes: name => [label, colors]. "theme" = no colors of its own. */
    const SCHEMES = [
        'theme' => ['Theme colors', []], // @translate
        'light' => ['Light', ['bar' => '#ffffff', 'link' => '#1f2933', 'hover' => '#0f5e54', 'dropBg' => '#ffffff', 'dropLink' => '#1f2933']], // @translate
        'cream' => ['Cream and ink', ['bar' => '#f5efe1', 'link' => '#2b2118', 'hover' => '#8a3b12', 'dropBg' => '#fbf8f1', 'dropLink' => '#2b2118']], // @translate
        'dark' => ['Charcoal', ['bar' => '#22262b', 'link' => '#f4f4f2', 'hover' => '#f2c14e', 'dropBg' => '#2f343a', 'dropLink' => '#f4f4f2']], // @translate
        'navy' => ['Navy and gold', ['bar' => '#1b2a49', 'link' => '#ffffff', 'hover' => '#f2c14e', 'dropBg' => '#ffffff', 'dropLink' => '#1b2a49']], // @translate
        'bayou' => ['Bayou green', ['bar' => '#0f5e54', 'link' => '#ffffff', 'hover' => '#d9f2ec', 'dropBg' => '#ffffff', 'dropLink' => '#0f3d37']], // @translate
        'brick' => ['Brick', ['bar' => '#8a2f1d', 'link' => '#ffffff', 'hover' => '#ffe2c2', 'dropBg' => '#fff8f2', 'dropLink' => '#5c1e12']], // @translate
        'sky' => ['Sky', ['bar' => '#e7f0fa', 'link' => '#16365c', 'hover' => '#1d5fa8', 'dropBg' => '#ffffff', 'dropLink' => '#16365c']], // @translate
    ];

    /** Only valid hex colors, under known keys. */
    public static function cleanColors($colors): array
    {
        $out = [];
        foreach (array_keys(self::COLOR_KEYS) as $key) {
            $v = is_array($colors) ? ($colors[$key] ?? '') : '';
            if (is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v)) {
                $out[$key] = strtolower($v);
            }
        }
        return $out;
    }

    /** The colors as CSS custom properties for a style="" attribute ("" when none). */
    public static function cssVars(array $colors): string
    {
        $out = [];
        foreach (self::cleanColors($colors) as $key => $hex) {
            $out[] = self::COLOR_KEYS[$key] . ':' . $hex;
        }
        return implode(';', $out);
    }

    /** Extra classes for the menu: which colors are set (the stylesheet styles only those). */
    public static function classes(array $colors): string
    {
        $colors = self::cleanColors($colors);
        $out = $colors ? ['pb-nav-colored'] : [];
        if (isset($colors['bar'])) {
            $out[] = 'pb-nav-has-bg';
        }
        if (isset($colors['dropBg'])) {
            $out[] = 'pb-nav-has-drop';
        }
        return implode(' ', $out);
    }

    /** The saved colors of a site setting value (stored as a JSON string or an array). */
    public static function decode($stored): array
    {
        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }
        return self::cleanColors(is_array($stored) ? $stored : []);
    }
}
