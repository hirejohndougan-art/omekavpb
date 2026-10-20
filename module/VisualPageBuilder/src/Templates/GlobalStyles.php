<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Templates;

/**
 * The Theme Builder's Global styles: the settings that every page and
 * template shares, saved as the Companion Theme's own settings (per site).
 * Blocks refer to them by name through the theme's CSS variables.
 *
 * The defaults here are the Companion Theme 2.0's defaults (its theme.ini),
 * so a site that upgrades looks the same until someone changes a setting.
 * tests/php checks that the two agree.
 */
class GlobalStyles
{
    /** Name of the theme setting group (the Companion Theme's settings key on each site). */
    const THEME_SETTINGS_KEY = 'theme_settings_visual-page-builder-companion';

    /** name => [label, type, default, options (for selects)] */
    const FIELDS = [
        'accent_color' => ['Primary color', 'color', '#0f5e54', null], // @translate
        'secondary_color' => ['Secondary color', 'color', '#5b5348', null], // @translate
        'text_color' => ['Text color', 'color', '#1f1b16', null], // @translate
        'background_color' => ['Background color', 'color', '#fbf8f1', null], // @translate
        'link_color' => ['Link color (blank: the primary color)', 'color', '', null], // @translate
        'heading_font' => ['Heading font', 'select', 'serif', ['serif' => 'Classic serif', 'sans' => 'Modern sans-serif', 'humanist' => 'Friendly sans-serif', 'slab' => 'Slab serif']], // @translate
        'body_font' => ['Text font', 'select', 'sans', ['sans' => 'Modern sans-serif', 'serif' => 'Classic serif', 'humanist' => 'Friendly sans-serif']], // @translate
        'text_size' => ['Text size', 'select', 'medium', ['small' => 'Smaller', 'medium' => 'Standard', 'large' => 'Larger']], // @translate
        'corner_style' => ['Corners', 'select', 'soft', ['square' => 'Square', 'soft' => 'Soft', 'round' => 'Round']], // @translate
    ];

    /** The values the theme's CSS variables take for each choice. */
    const SIZES = ['small' => '0.95rem', 'medium' => '1.0625rem', 'large' => '1.15rem'];
    const CORNERS = ['square' => '0', 'soft' => '6px', 'round' => '14px'];

    /** The defaults, by name. */
    public static function defaults(): array
    {
        $out = [];
        foreach (self::FIELDS as $name => $field) {
            $out[$name] = $field[2];
        }
        return $out;
    }

    /**
     * Clean posted values: colors must be hex (or blank for links), selects
     * must be a listed choice. Anything else becomes the default.
     */
    public static function clean(array $input): array
    {
        $out = [];
        foreach (self::FIELDS as $name => [$label, $type, $default, $options]) {
            $v = isset($input[$name]) ? trim((string) $input[$name]) : '';
            if ($type === 'color') {
                $ok = $v === '' ? $name === 'link_color' : (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $v);
                $out[$name] = $ok ? strtolower($v) : $default;
            } else {
                $out[$name] = array_key_exists($v, $options) ? $v : $default;
            }
        }
        return $out;
    }

    /** Saved values merged over the defaults (a site that never saved gets the defaults). */
    public static function values(array $saved): array
    {
        return self::clean(array_merge(self::defaults(), array_intersect_key($saved, self::FIELDS)));
    }

    /** The CSS custom properties for these values, as one :root rule. */
    public static function cssVars(array $values): string
    {
        $v = self::values($values);
        $link = $v['link_color'] !== '' ? $v['link_color'] : $v['accent_color'];
        return sprintf(
            ':root{--pb-accent:%s;--pb-secondary:%s;--pb-ink:%s;--pb-bg:%s;--pb-link:%s;--pb-text-size:%s;--pb-radius:%s}',
            $v['accent_color'],
            $v['secondary_color'],
            $v['text_color'],
            $v['background_color'],
            $link,
            self::SIZES[$v['text_size']],
            self::CORNERS[$v['corner_style']]
        );
    }
}
