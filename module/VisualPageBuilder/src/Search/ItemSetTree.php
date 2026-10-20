<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Search;

/**
 * Puts the collection (item set) choices of the Filters block in tree order. A set's
 * parent is the item set it is "part of" (dcterms:isPartOf). A parent with no matching
 * items is still shown, as a plain label, so its children have something above them.
 */
class ItemSetTree
{
    /** How many levels up the parents are followed. */
    const MAX_DEPTH = 6;

    /**
     * @param array $options [{id, label, count, active, url, bar}] from the facet
     * @return array the same choices in tree order, each with a 'depth' (0 at the top)
     */
    public static function arrange($api, array $options): array
    {
        if ($options === []) {
            return [];
        }
        $byId = [];
        foreach ($options as $option) {
            $byId[(int) $option['id']] = $option;
        }

        // Follow the parents upward, one request per level.
        $parents = [];
        $titles = [];
        $queue = array_keys($byId);
        for ($level = 0; $queue && $level < self::MAX_DEPTH; $level++) {
            $queue = array_values(array_unique(array_diff($queue, array_keys($parents))));
            if ($queue === []) {
                break;
            }
            $next = [];
            foreach ($api->search('item_sets', ['id' => $queue])->getContent() as $set) {
                $id = (int) $set->id();
                $titles[$id] = (string) $set->displayTitle('');
                $parent = null;
                foreach ($set->value('dcterms:isPartOf', ['all' => true, 'default' => []]) as $value) {
                    $resource = $value->valueResource();
                    if ($resource) {
                        $parent = (int) $resource->id();
                        break;
                    }
                }
                $parents[$id] = $parent;
                if ($parent !== null) {
                    $next[] = $parent;
                }
            }
            $queue = $next;
        }

        // Every set on the way up gets a node: a choice if it has items, else a plain label.
        $nodes = [];
        foreach (array_keys($parents) as $id) {
            $nodes[$id] = $byId[$id] ?? ['id' => $id, 'label' => $titles[$id] ?? (string) $id, 'count' => 0,
                'active' => false, 'url' => null, 'bar' => 0];
        }

        $children = [];
        $roots = [];
        foreach ($nodes as $id => $node) {
            $parent = $parents[$id] ?? null;
            if ($parent !== null && isset($nodes[$parent])) {
                $children[$parent][] = $id;
            } else {
                $roots[] = $id;
            }
        }

        // Depth-first from the top, in the order the choices already came in.
        $out = [];
        $seen = [];
        $walk = function (int $id, int $depth) use (&$walk, &$out, &$seen, $nodes, $children): void {
            if (isset($seen[$id])) {
                return;
            }
            $seen[$id] = true;
            $out[] = $nodes[$id] + ['depth' => $depth];
            foreach ($children[$id] ?? [] as $child) {
                $walk($child, $depth + 1);
            }
        };
        foreach ($roots as $id) {
            $walk($id, 0);
        }
        // Anything left over (a cycle in the parents) is added at the top, so no choice is lost.
        foreach (array_keys($nodes) as $id) {
            if (!isset($seen[$id])) {
                $walk($id, 0);
            }
        }
        return $out;
    }
}
