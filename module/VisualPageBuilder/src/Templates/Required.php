<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Templates;

/**
 * Checks a template's blocks against its screen's required blocks (plan
 * decision 10). A required block can be hidden but not deleted, so it is
 * always present in the layout. Pure: takes the block types of a layout.
 */
class Required
{
    /**
     * @param string $screen  A Screens key.
     * @param string[] $blockTypes  The block types in the template's layout.
     * @return string[]  Required types missing from the layout (empty when it is valid).
     */
    public static function missing(string $screen, array $blockTypes): array
    {
        return array_values(array_diff(Screens::required($screen), $blockTypes));
    }

    /** Whether a template layout can be published. */
    public static function valid(string $screen, array $blockTypes): bool
    {
        return self::missing($screen, $blockTypes) === [];
    }
}
