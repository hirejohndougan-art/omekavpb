<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Templates;

/**
 * Display conditions for Theme Builder templates, adapted from Blueprint's
 * template architecture for Omeka's route structure.
 *
 * Each template carries a list of conditions (include/exclude rules). A
 * condition is: {mode: "include"|"exclude", rule: string, value: string|null}.
 *
 * Rules map to Omeka's route structure:
 *   entire_site         every public screen on the site
 *   screen:{name}       a specific screen (item, browse-items, not-found…)
 *   resource_template   records using a specific resource template (by id)
 *   item_set            records in a specific item set (by id)
 *   resource_class      records of a specific class (by id)
 *   page:{id}           a specific site page (by id)
 *   home                the site's landing page
 *
 * Specificity scoring (higher wins):
 *   entire_site       = 1
 *   screen:{name}     = 2   (a whole screen, like "every item" or "every browse")
 *   resource_class    = 3   (records of a class, across screens)
 *   item_set          = 4   (records in an item set)
 *   resource_template = 5   (records using a template)
 *   home              = 5   (the site's front page)
 *   page:{id}         = 6   (one specific page/record)
 *
 * Exclude rules always win over include rules at the same or lower specificity.
 */
class DisplayConditions
{
    /**
     * The specificity score of a rule.
     */
    public static function score(string $rule): int
    {
        if ($rule === 'entire_site') {
            return 1;
        }
        if (strpos($rule, 'screen:') === 0) {
            return 2;
        }
        if ($rule === 'resource_class') {
            return 3;
        }
        if ($rule === 'item_set') {
            return 4;
        }
        if ($rule === 'resource_template') {
            return 5;
        }
        if ($rule === 'home') {
            return 5;
        }
        if (strpos($rule, 'page:') === 0) {
            return 6;
        }
        return 0;
    }

    /**
     * Does a single condition match the current request context?
     *
     * @param array $condition  {rule, value}
     * @param array $context    The current request: screen, record (with id,
     *                          resource_template, item_sets, class), page_id,
     *                          is_home.
     */
    public static function matches(array $condition, array $context): bool
    {
        $rule = $condition['rule'] ?? '';
        $value = $condition['value'] ?? null;
        $screen = $context['screen'] ?? '';
        $record = $context['record'] ?? [];

        switch ($rule) {
            case 'entire_site':
                return true;

            case 'home':
                return !empty($context['is_home']);

            default:
                if (strpos($rule, 'screen:') === 0) {
                    $target = substr($rule, 7);
                    return $screen === $target;
                }
                if ($rule === 'resource_template' && $value !== null) {
                    return (int) $value === (int) ($record['resource_template'] ?? -1);
                }
                if ($rule === 'item_set' && $value !== null) {
                    return in_array((int) $value, array_map('intval', $record['item_sets'] ?? []), true);
                }
                if ($rule === 'resource_class' && $value !== null) {
                    return (int) $value === (int) ($record['class'] ?? -1);
                }
                if (strpos($rule, 'page:') === 0) {
                    $pageId = (int) substr($rule, 5);
                    return $pageId === (int) ($context['page_id'] ?? -1);
                }
                return false;
        }
    }

    /**
     * Score a template against the current context, like Blueprint's rule_score
     * but handling multiple conditions with include/exclude logic.
     *
     * Returns the highest include score, or -1 if excluded or no match.
     *
     * @param array $conditions  The template's display conditions.
     * @param array $context     The current request context.
     * @return int  The match score (0 = no match, -1 = excluded).
     */
    public static function resolve(array $conditions, array $context): int
    {
        if (empty($conditions)) {
            // No conditions means "entire site" (the default).
            return 1;
        }

        $bestInclude = 0;
        $excluded = false;

        foreach ($conditions as $cond) {
            $mode = $cond['mode'] ?? 'include';
            if (!self::matches($cond, $context)) {
                continue;
            }
            $s = self::score($cond['rule'] ?? '');
            if ($mode === 'exclude') {
                $excluded = true;
            } else {
                $bestInclude = max($bestInclude, $s);
            }
        }

        // Exclude always wins (Blueprint pattern).
        if ($excluded) {
            return -1;
        }
        return $bestInclude;
    }

    /**
     * Pick the best template from a list for the current context and location
     * (header, footer, or a body screen).
     *
     * @param array $templates   All templates of a site (from Store::all()).
     * @param string $location   The template type: 'header', 'footer', or a
     *                           screen key like 'item', 'browse-items'.
     * @param array $context     The current request context.
     * @return array|null  The best-matching template, or null.
     */
    public static function best(array $templates, string $location, array $context): ?array
    {
        $best = null;
        $bestScore = 0;

        foreach ($templates as $template) {
            // Only consider templates for this location/screen.
            if (($template['screen'] ?? '') !== $location) {
                continue;
            }
            // Only live templates.
            if (($template['status'] ?? 'draft') !== 'live') {
                continue;
            }

            $conditions = $template['conditions'] ?? [];
            $score = self::resolve($conditions, $context);
            if ($score <= 0) {
                continue;
            }

            // Higher score wins; on a tie, lower order (higher in the list) wins.
            if ($score > $bestScore || ($score === $bestScore && $best !== null && ($template['order'] ?? 0) < ($best['order'] ?? 0))) {
                $best = $template;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * Sanitize a list of conditions from user input.
     */
    public static function sanitize(array $conditions): array
    {
        $out = [];
        foreach ($conditions as $cond) {
            if (!is_array($cond)) {
                continue;
            }
            $mode = ($cond['mode'] ?? '') === 'exclude' ? 'exclude' : 'include';
            $rule = trim((string) ($cond['rule'] ?? ''));
            $value = isset($cond['value']) && $cond['value'] !== '' ? (string) $cond['value'] : null;
            if ($rule === '') {
                continue;
            }
            // Validate the rule format.
            $valid = in_array($rule, ['entire_site', 'home', 'resource_template', 'item_set', 'resource_class'], true)
                || preg_match('/^screen:[a-z][-a-z]*$/', $rule)
                || preg_match('/^page:\d+$/', $rule);
            if (!$valid) {
                continue;
            }
            $out[] = ['mode' => $mode, 'rule' => $rule, 'value' => $value];
        }
        return $out;
    }

    /**
     * A human-readable label for a condition rule.
     */
    public static function ruleLabel(string $rule): string
    {
        if ($rule === 'entire_site') {
            return 'Entire site';
        }
        if ($rule === 'home') {
            return 'Home page';
        }
        if (strpos($rule, 'screen:') === 0) {
            $screen = substr($rule, 7);
            return Screens::exists($screen) ? Screens::label($screen) . ' screen' : $screen;
        }
        if ($rule === 'resource_template') {
            return 'Resource template';
        }
        if ($rule === 'item_set') {
            return 'Item set';
        }
        if ($rule === 'resource_class') {
            return 'Resource class';
        }
        if (strpos($rule, 'page:') === 0) {
            return 'Specific page';
        }
        return $rule;
    }
}
