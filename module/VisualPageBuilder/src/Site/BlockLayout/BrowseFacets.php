<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Search\BrowseQuery;
use VisualPageBuilder\Search\FacetSettings;
use VisualPageBuilder\Search\ItemSetTree;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Templates\CurrentScreen;

/**
 * Filters for the results: collection (item set) and type (resource class), with
 * the number of matching items for each choice, the filters in use, and a clear
 * link. Works on the index screens (Browse items, Search results, an item set's
 * page). Shows nothing on Browse item sets.
 */
class BrowseFacets extends AbstractSchemaBlock
{
    const DESCRIPTION = 'Filters for the results: collection and type, with how many items each choice matches, and the filters in use.'; // @translate

    const GOOD_FOR = 'Browse items and Search results, beside the results.'; // @translate

    const TEMPLATE = 'browse-facets';

    /** Field name => the label shown to visitors. */
    const FIELDS = [
        'item_set' => 'Collection',
        'class' => 'Type',
        'subject' => 'Subject',
        'creator' => 'Creator',
        'place' => 'Place',
        'decade' => 'Date',
        'has_images' => 'Images',
        'has_description' => 'Description',
    ]; // @translate

    /** Text fields list this many values (most items first) plus any chosen ones; the rest sit behind "Show all". */
    const TEXT_SHOWN = FacetSettings::TEXT_SHOWN;

    public function getLabel()
    {
        return 'Filters'; // @translate
    }

    public function schema(): array
    {
        return [
            ['key' => 'show_collection', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Fields', 'default' => true, 'label' => 'Collection'], // @translate
            ['key' => 'show_type', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Fields', 'default' => true, 'label' => 'Type'], // @translate
            ['key' => 'show_subject', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Fields', 'default' => true, 'label' => 'Subject'], // @translate
            ['key' => 'show_creator', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Fields', 'default' => true, 'label' => 'Creator'], // @translate
            ['key' => 'show_place', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Fields', 'default' => true, 'label' => 'Place'], // @translate
            ['key' => 'show_date', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Fields', 'default' => true, 'label' => 'Date, with the years'], // @translate
            ['key' => 'show_images', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Fields', 'default' => true, 'label' => 'Images'], // @translate
            ['key' => 'show_description', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Fields', 'default' => true, 'label' => 'Description'], // @translate
            ['key' => 'text_order', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'count', 'label' => 'Subjects, creators and places in',
                'options' => ['count' => 'Most items first', 'az' => 'A to Z']], // @translate
            ['key' => 'text_shown', 'type' => Schema::TYPE_NUMBER, 'group' => 'Display', 'default' => 10, 'min' => 1, 'max' => 100,
                'label' => 'Values listed before "Show all"'], // @translate
            ['key' => 'text_style', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'list',
                'label' => 'Subjects, creators and places as',
                'options' => ['list' => 'A list', 'dropdown' => 'A dropdown (one value at a time)']], // @translate
        ];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $result = BrowseQuery::run($view, $site);
        $screen = CurrentScreen::get();
        $api = $view->getHelperPluginManager()->getServiceLocator()->get('Omeka\ApiManager');
        $conn = $view->getHelperPluginManager()->getServiceLocator()->get('Omeka\Connection');
        $selected = $result['selected'];
        // On an item set's page the collection is fixed to that set, so it isn't offered as a filter.
        $fields = array_diff_key(self::FIELDS, $screen === 'item-set' ? ['item_set' => 1] : []);

        $expanded = self::expandedFields($view);
        $letters = self::activeLetters($view);
        $textShown = FacetSettings::textShown($data);
        $groups = [];
        $active = [];
        foreach ($fields as $field => $label) {
            $options = [];
            $options_all = $result['facetOptions'][$field] ?? [];
            $isText = isset(\VisualPageBuilder\Search\Index::TEXT_FIELDS[$field]);
            $letter = $isText ? ($letters[$field] ?? null) : null;
            $show = FacetSettings::shown($field, $data);
            // The A–Z row lists every letter that starts a value, so it is built before the letter narrows the values.
            $letterLinks = $isText ? self::letterLinks($view, $field, $options_all, $selected, $expanded, $letters) : [];
            if ($letter !== null) {
                $options_all = array_values(array_filter($options_all, fn ($o) => self::initial($o['label']) === $letter));
            }
            if ($isText) {
                $options_all = FacetSettings::ordered($options_all, $data);
            }
            $largest = $options_all ? max(array_column($options_all, 'count')) : 1;
            $showAll = $isText && in_array($field, $expanded, true);
            $shown = 0;
            foreach ($options_all as $option) {
                $on = in_array($option['id'], $selected[$field], true);
                // Text values: the most common ones, and always the chosen ones, unless "Show all" is on.
                if ($isText && !$showAll && !$on && $shown >= $textShown) {
                    continue;
                }
                $shown++;
                $next = $selected;
                $next[$field] = $on
                    ? array_values(array_diff($selected[$field], [$option['id']]))
                    : array_values(array_merge($selected[$field], [$option['id']]));
                $bar = (int) round(100 * $option['count'] / max(1, $largest));
                $options[] = $option + ['active' => $on, 'url' => self::url($view, $next, $expanded, $letters), 'bar' => $bar];
            }
            // A dropdown lists every value not yet in use, and adds the chosen one when submitted.
            $dropdown = null;
            if ($isText && $show && ($data['text_style'] ?? 'list') === 'dropdown') {
                $choices = [];
                foreach ($options_all as $option) {
                    if (!in_array($option['id'], $selected[$field], true)) {
                        $choices[] = ['id' => $option['id'], 'label' => $option['label'], 'count' => $option['count']];
                    }
                }
                if ($choices) {
                    $dropdown = [
                        'action' => $view->url(null, [], [], true),
                        'name' => $field . '[]',
                        'choices' => $choices,
                        // Everything else stays: the search, sort, filters in use, letters and expanded lists.
                        'hidden' => self::hiddenFields($view, $selected, $expanded, $letters),
                    ];
                }
                $options = [];
            }
            // Collections nest: parents come first, children are indented under them.
            if ($field === 'item_set' && $options) {
                $options = ItemSetTree::arrange($api, $options);
            }
            if ($show && ($options || $letter !== null || $dropdown)) {
                // Dates read as bars: each decade's width is its share of the largest decade.
                $toggle = null;
                if ($isText && !$dropdown && count($options_all) > $textShown) {
                    $others = array_values(array_diff($expanded, [$field]));
                    $toggle = $showAll
                        ? ['url' => self::url($view, $selected, $others, $letters), 'label' => $view->translate('Show fewer')]
                        : ['url' => self::url($view, $selected, array_merge($expanded, [$field]), $letters),
                            'label' => sprintf($view->translate('Show all %s'), number_format(count($options_all)))];
                }
                $groups[] = ['label' => $view->translate($label), 'options' => $options, 'bars' => $field === 'decade',
                    'toggle' => $toggle, 'letters' => $letterLinks, 'dropdown' => $dropdown];
            }
            foreach (BrowseQuery::labelsFor($api, $field, $selected[$field], $conn) as $id => $name) {
                $next = $selected;
                $next[$field] = array_values(array_diff($selected[$field], [$id]));
                $active[] = ['label' => $name, 'url' => self::url($view, $next, $expanded, $letters)];
            }
        }

        // The year range: a chip in Filtered by (removing it keeps the rest), and the From and To boxes.
        if (!empty($selected['year_from']) || !empty($selected['year_to'])) {
            $next = $selected;
            unset($next['year_from'], $next['year_to']);
            $active[] = ['label' => self::yearLabel($view, $selected), 'url' => self::url($view, $next, $expanded, $letters)];
        }
        $years = [
            'action' => $view->url(null, [], [], true),
            'from' => $selected['year_from'] ?? '',
            'to' => $selected['year_to'] ?? '',
            'hidden' => self::hiddenFields($view, $selected, $expanded, $letters),
        ];

        return [
            'years' => $years,
            'showYears' => FacetSettings::shown('decade', $data),
            'show' => $result['mode'] === 'index' && $screen !== 'browse-item-sets',
            'panelId' => 'vpb-facets-' . preg_replace('/[^A-Za-z0-9_-]/', '', $blockKey),
            'total' => $result['total'],
            'groups' => $groups,
            'active' => $active,
            'clearUrl' => $active ? self::url($view, [], $expanded) : null,
            'blockClass' => static::cssClass(),
        ];
    }

    /**
     * The address for these filters: the search, sort and page size stay, the filters
     * are exactly the ones given, and paging starts again at page one.
     */
    public static function url(PhpRenderer $view, array $selected, array $expanded = [], array $letters = []): string
    {
        $query = array_intersect_key($view->params()->fromQuery(), array_flip(['q', 'fulltext_search', 'per_page', 'sort']));
        foreach (BrowseQuery::FILTER_PARAMS as $field) {
            if (!empty($selected[$field])) {
                $query[$field] = array_values($selected[$field]);
            }
        }
        if ($expanded) {
            $query['show_all'] = array_values($expanded);
        }
        if ($letters) {
            $query['letter'] = $letters;
        }
        foreach (['year_from', 'year_to'] as $key) {
            if (!empty($selected[$key])) {
                $query[$key] = (int) $selected[$key];
            }
        }
        return $view->url(null, [], ['query' => $query], true);
    }

    /** The year range as words: "1920 to 1930", "from 1920" or "to 1930". */
    public static function yearLabel(PhpRenderer $view, array $selected): string
    {
        $from = $selected['year_from'] ?? null;
        $to = $selected['year_to'] ?? null;
        if ($from && $to) {
            return sprintf($view->translate('%1$s to %2$s'), $from, $to);
        }
        return $from ? sprintf($view->translate('From %s'), $from) : sprintf($view->translate('To %s'), $to);
    }

    /**
     * The fields the Years form must send so that the other settings stay: the search,
     * page size and sort, the filters in use, the expanded lists and the letters.
     *
     * @return array<int, array{0: string, 1: string}> [name, value] pairs
     */
    public static function hiddenFields(PhpRenderer $view, array $selected, array $expanded, array $letters): array
    {
        $fields = [];
        foreach (array_intersect_key($view->params()->fromQuery(), array_flip(['q', 'fulltext_search', 'per_page', 'sort'])) as $name => $value) {
            $fields[] = [$name, (string) $value];
        }
        foreach (BrowseQuery::FILTER_PARAMS as $field) {
            foreach ($selected[$field] ?? [] as $id) {
                $fields[] = [$field . '[]', (string) $id];
            }
        }
        foreach ($expanded as $field) {
            $fields[] = ['show_all[]', $field];
        }
        foreach ($letters as $field => $letter) {
            $fields[] = ['letter[' . $field . ']', $letter];
        }
        return $fields;
    }

    /** The A–Z letter chosen for each text field, from the address bar (letter[subject]=V). */
    public static function activeLetters(PhpRenderer $view): array
    {
        $asked = (array) $view->params()->fromQuery('letter', []);
        $out = [];
        foreach (array_keys(\VisualPageBuilder\Search\Index::TEXT_FIELDS) as $field) {
            $letter = strtoupper((string) ($asked[$field] ?? ''));
            if (preg_match('/^[A-Z]$/', $letter)) {
                $out[$field] = $letter;
            }
        }
        return $out;
    }

    /** The first letter of a value in capitals, or '' when it does not start with a letter. */
    public static function initial(string $label): string
    {
        $first = strtoupper(mb_substr(ltrim($label), 0, 1));
        return preg_match('/^[A-Z]$/', $first) ? $first : '';
    }

    /**
     * The A–Z links for a text field: one for each letter that starts a value, plus
     * the chosen letter (pressing it again turns it off).
     */
    protected static function letterLinks(PhpRenderer $view, string $field, array $options_all, array $selected, array $expanded, array $letters): array
    {
        $present = [];
        foreach ($options_all as $option) {
            $present[self::initial((string) $option['label'])] = true;
        }
        $current = $letters[$field] ?? null;
        $links = [];
        foreach (range('A', 'Z') as $letter) {
            if (!isset($present[$letter]) && $letter !== $current) {
                continue;
            }
            $next = $letters;
            if ($letter === $current) {
                unset($next[$field]);
            } else {
                $next[$field] = $letter;
            }
            $links[] = ['letter' => $letter, 'active' => $letter === $current, 'url' => self::url($view, $selected, $expanded, $next)];
        }
        return $links;
    }

    /** Text fields whose full list is shown ("Show all"), from the address bar. */
    public static function expandedFields(PhpRenderer $view): array
    {
        $asked = (array) $view->params()->fromQuery('show_all', []);
        return array_values(array_intersect(array_keys(\VisualPageBuilder\Search\Index::TEXT_FIELDS), array_map('strval', $asked)));
    }
}
