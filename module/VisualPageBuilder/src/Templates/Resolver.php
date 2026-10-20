<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Templates;

/**
 * Picks the one template a public screen uses (plan section 3, decisions 5
 * and 7 of theme-builder-decisions.md).
 *
 * Precedence, most specific first:
 *   record          one chosen record (an item, media or item set, by id)
 *   resource-template  the record's resource template
 *   item-set        an item set the record is in
 *   class           the record's resource class
 *   all             every record on the screen
 * The first level with a live match wins. Within a level the template listed
 * first in the Theme Builder (lowest "order") wins.
 *
 * A template is an array:
 *   id, screen (a Screens key), scope (one of the levels above),
 *   value (the record id, resource template id, item set id or class id; null for "all"),
 *   status ("live" or "draft"; only live templates are used), order (int).
 *
 * A record is an array with any of: id, resource_template, item_sets (ids),
 * class. Missing keys just mean that level can't match.
 */
class Resolver
{
    const LEVELS = ['record', 'resource-template', 'item-set', 'class', 'all'];

    /**
     * @param string $screen  A Screens key.
     * @param array $record   The record on the screen (empty for page-wide screens).
     * @param array $templates  Every template of the site.
     * @return array|null  The chosen template, or null (the theme's own layout is used).
     */
    public function choose(string $screen, array $record, array $templates): ?array
    {
        foreach (self::LEVELS as $level) {
            $matches = [];
            foreach ($templates as $template) {
                if (($template['screen'] ?? null) !== $screen) {
                    continue;
                }
                if (($template['status'] ?? 'draft') !== 'live') {
                    continue;
                }
                if (($template['scope'] ?? null) !== $level) {
                    continue;
                }
                if ($this->matches($level, $template['value'] ?? null, $record)) {
                    $matches[] = $template;
                }
            }
            if ($matches) {
                usort($matches, function ($a, $b) {
                    return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
                });
                return $matches[0];
            }
        }
        return null;
    }

    protected function matches(string $level, $value, array $record): bool
    {
        switch ($level) {
            case 'record':
                return $value !== null && (int) $value === (int) ($record['id'] ?? -1);
            case 'resource-template':
                return $value !== null && (int) $value === (int) ($record['resource_template'] ?? -1);
            case 'item-set':
                return $value !== null && in_array((int) $value, array_map('intval', $record['item_sets'] ?? []), true);
            case 'class':
                return $value !== null && (int) $value === (int) ($record['class'] ?? -1);
            case 'all':
                return true;
        }
        return false;
    }
}
