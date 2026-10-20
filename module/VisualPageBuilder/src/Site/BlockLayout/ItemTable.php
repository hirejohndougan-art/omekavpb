<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Stdlib\ItemSource;

/**
 * Items as a table researchers can scan and sort (special-blocks/js/item-table.js
 * adds sorting by clicking a column heading). Columns are fields you choose.
 */
class ItemTable extends AbstractSchemaBlock
{
    const TEMPLATE = 'item-table';

    const DESCRIPTION = 'Lists items in a table with the columns you choose, such as title, date, and identifier. Visitors click a column heading to sort by it.'; // @translate

    const GOOD_FOR = 'Collection guides and pages for researchers.'; // @translate

    /** Column choices: field => heading. */
    const COLUMNS = [
        'dcterms:date' => 'Date', 'dcterms:identifier' => 'Identifier', 'dcterms:creator' => 'Creator',
        'dcterms:subject' => 'Subject', 'dcterms:type' => 'Type', 'dcterms:spatial' => 'Place', 'dcterms:description' => 'Description',
    ];

    public function getLabel()
    {
        return 'Item table'; // @translate
    }

    public function schema(): array
    {
        $fields = array_merge(ItemSource::fields(true, false), [DisplayFields::heading()]);
        foreach (self::COLUMNS as $term => $label) {
            $fields[] = ['key' => 'col_' . str_replace(':', '_', $term), 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Columns', 'default' => in_array($term, ['dcterms:date', 'dcterms:identifier'], true), 'label' => $label];
        }
        $fields[] = ['key' => 'sort', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'title', 'label' => 'Starting order', 'options' => ['title' => 'Title', 'dcterms:date' => 'Date', 'dcterms:identifier' => 'Identifier', 'created' => 'Newest added']]; // @translate
        $fields[] = ['key' => 'count', 'type' => Schema::TYPE_NUMBER, 'group' => 'Display', 'default' => 100, 'min' => 1, 'max' => 500, 'label' => 'Most rows'];  // @translate
        return $fields;
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $columns = [];
        foreach (self::COLUMNS as $term => $label) {
            if (!empty($data['col_' . str_replace(':', '_', $term)])) {
                $columns[$term] = $view->translate($label);
            }
        }
        $sort = $data['sort'] === 'created' ? ['sort_by' => 'created', 'sort_order' => 'desc'] : ['sort_by' => $data['sort'], 'sort_order' => 'asc'];
        $rows = [];
        foreach ($this->itemSource->items($data, $site, $sort + ['limit' => $data['count']]) as $item) {
            $cells = [];
            foreach (array_keys($columns) as $term) {
                $values = array_map('strval', $item->value($term, ['all' => true]) ?: []);
                $cells[] = implode('; ', $values);
            }
            $rows[] = ['title' => $item->displayTitle(), 'url' => ItemSource::url($item, $data, $site), 'cells' => $cells];
        }
        return ['columns' => $columns, 'rows' => $rows];
    }
}
