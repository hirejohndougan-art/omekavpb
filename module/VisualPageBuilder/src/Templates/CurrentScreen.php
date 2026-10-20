<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Templates;

/**
 * The page-wide screen a template is being drawn for (such as browse-items or
 * browse-item-sets). The Results and Pagination blocks read it, so one block
 * can list items on one screen and item sets on another.
 *
 * Set and cleared around each render by the vpbScreen view helper.
 */
class CurrentScreen
{
    /** @var string[] */
    protected static $stack = [];

    public static function push(string $screen): void
    {
        self::$stack[] = $screen;
    }

    public static function pop(): void
    {
        array_pop(self::$stack);
    }

    /** The screen being drawn, or null outside a template. */
    public static function get(): ?string
    {
        return self::$stack ? end(self::$stack) : null;
    }
}
