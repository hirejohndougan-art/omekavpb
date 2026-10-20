<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Stdlib\ItemSource;
use VisualPageBuilder\Stdlib\ValueCounts;

/**
 * The most-used values of a property (Subject by default) with item counts,
 * each linking to a search for it. See Stdlib\ValueCounts for what's counted.
 */
class SubjectsList extends AbstractSchemaBlock
{
    const TEMPLATE = 'subjects-list';

    const DESCRIPTION = 'Lists the subjects (or places, or any field) your items use most, with how many items have each. Visitors click one to see those items.'; // @translate

    const GOOD_FOR = 'Helping visitors find a way into the collection.'; // @translate

    /**
     * @var ValueCounts|null
     */
    protected $valueCounts;

    /** Set by the block factory. */
    public function setValueCounts(ValueCounts $counts): void
    {
        $this->valueCounts = $counts;
    }

    public function getLabel()
    {
        return 'Subjects list'; // @translate
    }

    public function schema(): array
    {
        return array_merge(ItemSource::fields(true, false), [
            ['key' => 'property', 'type' => Schema::TYPE_TEXT, 'group' => 'Source', 'summary' => true, 'default' => 'dcterms:subject', 'label' => 'Field to list', 'info' => 'As prefix:name, e.g. dcterms:subject for subjects or dcterms:spatial for places.'], // @translate
            DisplayFields::heading('Explore by subject'),
            ['key' => 'count', 'type' => Schema::TYPE_NUMBER, 'group' => 'Display', 'default' => 20, 'min' => 1, 'max' => 200, 'label' => 'How many to show'], // @translate
            ['key' => 'sort', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'count', 'label' => 'Order', 'options' => ['count' => 'Most used first', 'alpha' => 'Alphabetical']], // @translate
            ['key' => 'style', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'pills', 'label' => 'Style', 'options' => ['pills' => 'Buttons', 'list' => 'List', 'cloud' => 'Word cloud (bigger = more items)']], // @translate
            ['key' => 'show_count', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Display', 'default' => true, 'label' => 'Show how many items'], // @translate
        ]);
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $term = preg_match('/^[a-z0-9_-]+:[A-Za-z0-9_-]+$/i', $data['property']) ? $data['property'] : 'dcterms:subject';
        $properties = $view->api()->search('properties', ['term' => $term])->getContent();
        if (!$properties || !$this->valueCounts) {
            return ['values' => []];
        }
        $rows = $this->valueCounts->count($properties[0]->id(), ItemSource::siteIds($data, $site), $data['item_set_ids'], $data['count'], $data['sort']);
        $browse = $view->url('site/resource', ['site-slug' => $site->slug(), 'controller' => 'item', 'action' => 'browse']);
        $max = $rows ? max(array_column($rows, 'count')) : 1;
        foreach ($rows as &$row) {
            $query = ['property' => [['joiner' => 'and', 'property' => $term, 'type' => 'eq', 'text' => $row['value']]]];
            if ($data['item_set_ids']) {
                $query['item_set_id'] = $data['item_set_ids'];
            }
            $row['url'] = $browse . '?' . http_build_query($query);
            $row['weight'] = 1 + (int) round(4 * $row['count'] / $max); // 1–5, for the word cloud
        }
        unset($row);
        return ['values' => $rows];
    }
}
