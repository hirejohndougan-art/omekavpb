<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Api;

use Doctrine\ORM\QueryBuilder;
use Laminas\EventManager\Event;

/**
 * Lets item and item set searches filter by several sites at once.
 *
 * Omeka's own `site_id` takes a single site. Blocks set
 * `special_blocks_site_ids` => [ids] instead, and this listener (attached to
 * api.search.query in Module.php) keeps only resources on any of those sites.
 * A subquery is used so a resource on two of the sites appears once.
 */
class MultiSiteFilter
{
    const QUERY_KEY = 'special_blocks_site_ids';

    public function __invoke(Event $event)
    {
        $request = $event->getParam('request');
        $query = $request->getContent();
        if (empty($query[self::QUERY_KEY]) || !is_array($query[self::QUERY_KEY])) {
            return;
        }
        $siteIds = array_values(array_filter(array_map('intval', $query[self::QUERY_KEY])));
        if (!$siteIds) {
            return;
        }
        /** @var QueryBuilder $qb */
        $qb = $event->getParam('queryBuilder');
        $adapter = $event->getTarget();
        $resource = $request->getResource();

        $sub = $adapter->createAlias();
        $siteAlias = $adapter->createAlias();
        if ($resource === 'items') {
            $dql = sprintf('SELECT %1$s.id FROM Omeka\Entity\Item %1$s JOIN %1$s.sites %2$s WHERE %2$s.id IN (:%3$s)', $sub, $siteAlias, $sub . '_ids');
        } elseif ($resource === 'item_sets') {
            $link = $adapter->createAlias();
            $dql = sprintf('SELECT IDENTITY(%1$s.itemSet) FROM Omeka\Entity\SiteItemSet %1$s JOIN %1$s.site %2$s WHERE %2$s.id IN (:%3$s)', $link, $siteAlias, $sub . '_ids');
        } else {
            return;
        }
        $qb->andWhere($qb->expr()->in('omeka_root.id', $dql));
        $qb->setParameter($sub . '_ids', $siteIds);
    }
}
