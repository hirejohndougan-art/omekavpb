<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;

/**
 * A list of the item's fields, with labels. Choose which fields, and whether empty ones show.
 */
class ItemDetails extends AbstractItemField
{
    const DESCRIPTION = 'A list of the item\'s fields, with labels. Choose which fields, and whether empty ones show.'; // @translate

    const GOOD_FOR = 'Every item template.'; // @translate

    const TEMPLATE = 'item-details';
    const SAMPLE = 'Details: the fields you chose'; // @translate

    public function getLabel()
    {
        return 'Details list'; // @translate
    }

    public function schema(): array
    {
        return [
            [
                'key' => 'terms', 'type' => Schema::TYPE_TEXTAREA, 'group' => 'Fields',
                'default' => "dcterms:creator\ndcterms:date\ndcterms:spatial\ndcterms:subject",
                'label' => 'Fields, one per line', // @translate
                'info' => 'Property terms such as dcterms:creator. Shown in this order.', // @translate
            ],
            [
                'key' => 'labels', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'beside',
                'label' => 'Labels', // @translate
                'options' => ['beside' => 'Beside the value', 'above' => 'Above the value'], // @translate
            ],
            [
                'key' => 'hide_empty', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Display', 'default' => true,
                'label' => 'Hide fields with no value', // @translate
            ],
        ];
    }

    protected function fieldVars(PhpRenderer $view, array $data, SiteRepresentation $site, $item): array
    {
        $terms = array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $data['terms'])));
        return ['rows' => self::rows($view, $item, $terms, (bool) $data['hide_empty']), 'labels' => $data['labels']];
    }

    /** One row per term: its label and its values as text. */
    public static function rows(PhpRenderer $view, $item, array $terms, bool $hideEmpty): array
    {
        $rows = [];
        foreach ($terms as $term) {
            $values = self::itemTexts($item, $term);
            if (!$values && $hideEmpty) {
                continue;
            }
            $rows[] = ['term' => $term, 'label' => self::propertyLabel($view, $term), 'values' => $values];
        }
        return $rows;
    }

    /** A property's label by its term (the term itself if it can't be read). */
    public static function propertyLabel(PhpRenderer $view, string $term): string
    {
        static $cache = [];
        if (!isset($cache[$term])) {
            $cache[$term] = $term;
            try {
                $found = $view->api()->search('properties', ['term' => $term])->getContent();
                if ($found) {
                    $cache[$term] = $found[0]->label();
                }
            } catch (\Exception $e) {
            }
        }
        return $cache[$term];
    }
}
