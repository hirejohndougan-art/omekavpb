<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Stdlib\ItemSource;

/**
 * Decades with item counts, each linking to that decade's items.
 *
 * Reads each item's date (Dublin Core Date by default) and counts dates that
 * start with the decade's first three digits ("191" for the 1910s), so dates
 * must be written YYYY… (1911, 1911-06, 1911-06-14). Links open the site's
 * item browse page with the same search. With several sites chosen, links go
 * to this site's browse page, which lists this site's items.
 */
class BrowseByDecade extends AbstractSchemaBlock
{
    const TEMPLATE = 'browse-by-decade';

    const DESCRIPTION = 'Shows decades, like the 1910s and 1920s, with how many items come from each. Visitors click a decade to see its items. Item dates need to start with the year, like 1911 or 1911-06-14.'; // @translate

    const GOOD_FOR = 'Collection home pages and "explore by era" pages.'; // @translate

    public function getLabel()
    {
        return 'Browse by decade'; // @translate
    }

    public function schema(): array
    {
        return array_merge(ItemSource::fields(true, false), [
            ['key' => 'date_property', 'type' => Schema::TYPE_TEXT, 'group' => 'Source', 'default' => 'dcterms:date', 'label' => 'Date field', 'info' => 'The property holding each item\'s date, as prefix:name.'], // @translate
            ['key' => 'from', 'type' => Schema::TYPE_NUMBER, 'group' => 'Decades', 'summary' => true, 'default' => 1850, 'min' => 1000, 'max' => 2100, 'label' => 'First decade (year)'], // @translate
            ['key' => 'to', 'type' => Schema::TYPE_NUMBER, 'group' => 'Decades', 'summary' => true, 'default' => 2020, 'min' => 1000, 'max' => 2100, 'label' => 'Last decade (year)'], // @translate
            ['key' => 'hide_empty', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Decades', 'default' => true, 'label' => 'Hide decades with no items'], // @translate
            DisplayFields::heading('Explore by decade'),
            ['key' => 'show_count', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Display', 'default' => true, 'label' => 'Show how many items'], // @translate
        ]);
    }

    /** The decades from..to, e.g. 1850, 1860, … (at most 30). */
    public static function decades(int $from, int $to): array
    {
        $first = intdiv(min($from, $to), 10) * 10;
        $last = intdiv(max($from, $to), 10) * 10;
        return range($first, min($last, $first + 290), 10);
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $property = preg_match('/^[a-z0-9_-]+:[A-Za-z0-9_-]+$/i', $data['date_property']) ? $data['date_property'] : 'dcterms:date';
        $browse = $view->url('site/resource', ['site-slug' => $site->slug(), 'controller' => 'item', 'action' => 'browse']);
        $rows = [];
        foreach (self::decades($data['from'], $data['to']) as $decade) {
            $match = ['property' => [['joiner' => 'and', 'property' => $property, 'type' => 'sw', 'text' => substr((string) $decade, 0, 3)]]];
            $n = $this->itemSource->countItems($data, $site, $match);
            if (!$n && $data['hide_empty']) {
                continue;
            }
            $query = $match;
            if (!empty($data['item_set_ids'])) {
                $query['item_set_id'] = $data['item_set_ids'];
            }
            $rows[] = ['label' => sprintf($view->translate('%ss'), $decade), 'count' => $n, 'url' => $browse . '?' . http_build_query($query)];
        }
        return ['decades' => $rows];
    }
}
