<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Templates;

/**
 * The record a template is being drawn for (an item, on the public item
 * screen). The item field blocks read it while their template renders, and
 * see nothing when a block is drawn outside a template (the editor's preview).
 *
 * Set and cleared around each template render by the vpbTemplate view helper.
 */
class CurrentRecord
{
    /** @var array|null */
    protected static $stack = [];

    public static function push($record): void
    {
        self::$stack[] = $record;
    }

    public static function pop(): void
    {
        array_pop(self::$stack);
    }

    /** The record being drawn, or null outside a template. */
    public static function get()
    {
        return self::$stack ? end(self::$stack) : null;
    }
}
