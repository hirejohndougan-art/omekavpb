<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Stdlib;

use Doctrine\DBAL\Connection;

/**
 * Counts how many public items use each value of a property (for example how
 * many items have each Subject), optionally only on some sites or in some
 * item sets. Omeka's API can't group by value, so this reads the database.
 *
 * Only public items and public values are counted, so private material never
 * shows on the public site, whoever is viewing. Only plain-text values
 * (not links to other resources) are counted.
 */
class ValueCounts
{
    /**
     * @var Connection
     */
    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param int $propertyId
     * @param int[]|null $siteIds null = items on any site
     * @param int[] $itemSetIds empty = any
     * @param string $sort "count" (most used first) or "alpha"
     * @return array [['value' => string, 'count' => int], …]
     */
    public function count(int $propertyId, ?array $siteIds, array $itemSetIds, int $limit, string $sort = 'count'): array
    {
        $sql = 'SELECT v.value AS value, COUNT(DISTINCT v.resource_id) AS n
            FROM value v
            JOIN resource r ON r.id = v.resource_id AND r.resource_type = :type AND r.is_public = 1
            WHERE v.property_id = :property AND v.is_public = 1 AND v.value IS NOT NULL AND v.value <> \'\'';
        $params = ['type' => 'Omeka\Entity\Item', 'property' => $propertyId];
        $types = [];
        if ($siteIds === null) {
            $sql .= ' AND EXISTS (SELECT 1 FROM item_site s WHERE s.item_id = v.resource_id)';
        } else {
            $sql .= ' AND EXISTS (SELECT 1 FROM item_site s WHERE s.item_id = v.resource_id AND s.site_id IN (:sites))';
            $params['sites'] = $siteIds ?: [0];
            $types['sites'] = Connection::PARAM_INT_ARRAY;
        }
        if ($itemSetIds) {
            $sql .= ' AND EXISTS (SELECT 1 FROM item_item_set i JOIN resource sr ON sr.id = i.item_set_id AND sr.is_public = 1 WHERE i.item_id = v.resource_id AND i.item_set_id IN (:sets))';
            $params['sets'] = $itemSetIds;
            $types['sets'] = Connection::PARAM_INT_ARRAY;
        }
        $sql .= ' GROUP BY v.value ORDER BY ' . ($sort === 'alpha' ? 'v.value ASC' : 'n DESC, v.value ASC') . ' LIMIT ' . max(1, min(500, $limit));
        $rows = [];
        foreach ($this->connection->executeQuery($sql, $params, $types)->fetchAllAssociative() as $row) {
            $rows[] = ['value' => (string) $row['value'], 'count' => (int) $row['n']];
        }
        return $rows;
    }
}
