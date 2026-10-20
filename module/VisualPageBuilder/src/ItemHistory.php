<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder;

use Doctrine\DBAL\Connection;
use Omeka\Api\Manager as ApiManager;

/**
 * Item history (Phase 9, plan decision 2): a snapshot of an item's values before each
 * change made through the API, the last KEEP snapshots per item. Restore writes a
 * snapshot back through the API, so the state being replaced is itself kept.
 *
 * Media files are not part of a snapshot; restore leaves them as they are.
 */
class ItemHistory
{
    public const TABLE = 'vpb_revision';
    /** Snapshots kept per item. */
    public const KEEP = 20;

    public static function createTable(Connection $conn): void
    {
        $conn->executeStatement('CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
            id INT AUTO_INCREMENT NOT NULL,
            item_id INT NOT NULL,
            saved DATETIME NOT NULL,
            data LONGTEXT NOT NULL,
            PRIMARY KEY (id),
            INDEX IDX_vpb_revision_item (item_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public static function dropTable(Connection $conn): void
    {
        $conn->executeStatement('DROP TABLE IF EXISTS ' . self::TABLE);
    }

    /**
     * Save the item as it is now. Called before an update is applied.
     */
    public static function snapshot(Connection $conn, ApiManager $api, int $itemId): void
    {
        self::createTable($conn);
        $item = $api->read('items', $itemId)->getContent();
        $data = self::valuesOf($item);
        $conn->insert(self::TABLE, [
            'item_id' => $itemId,
            'saved' => (new \DateTime())->format('Y-m-d H:i:s'),
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        // Keep only the newest KEEP snapshots of this item.
        $conn->executeStatement(
            'DELETE FROM ' . self::TABLE . ' WHERE item_id = ? AND id NOT IN ('
            . ' SELECT id FROM (SELECT id FROM ' . self::TABLE . ' WHERE item_id = ? ORDER BY id DESC LIMIT ' . self::KEEP . ') keep)',
            [$itemId, $itemId]
        );
    }

    /**
     * The snapshots of an item, newest first: [id, saved].
     *
     * @return array<int, array{id: int, saved: string}>
     */
    public static function list(Connection $conn, int $itemId): array
    {
        self::createTable($conn);
        $rows = $conn->fetchAllAssociative(
            'SELECT id, saved FROM ' . self::TABLE . ' WHERE item_id = ? ORDER BY id DESC',
            [$itemId]
        );
        return array_map(fn ($r) => ['id' => (int) $r['id'], 'saved' => (string) $r['saved']], $rows);
    }

    /**
     * Write a snapshot back to the item through the API. Returns false when the snapshot
     * is not one of this item's.
     */
    public static function restore(Connection $conn, ApiManager $api, int $itemId, int $revisionId): bool
    {
        self::createTable($conn);
        $json = $conn->fetchOne(
            'SELECT data FROM ' . self::TABLE . ' WHERE id = ? AND item_id = ?',
            [$revisionId, $itemId]
        );
        if ($json === false) {
            return false;
        }
        $data = json_decode((string) $json, true);
        $api->update('items', $itemId, $data);
        return true;
    }

    /**
     * The values a snapshot keeps: every property value, the visibility, the class,
     * the template and the item sets. Values are stored as Omeka sends them.
     *
     * @param \Omeka\Api\Representation\ItemRepresentation $item
     */
    public static function valuesOf($item): array
    {
        $data = [];
        foreach ((array) $item->jsonSerialize() as $key => $value) {
            if (is_string($key) && preg_match('/^[a-z]+:[A-Za-z]+$/', $key) && is_array($value)) {
                $data[$key] = $value;
            }
        }
        $data['o:is_public'] = (bool) $item->isPublic();
        $class = $item->resourceClass();
        $data['o:resource_class'] = $class ? ['o:id' => $class->id()] : null;
        $template = $item->resourceTemplate();
        $data['o:resource_template'] = $template ? ['o:id' => $template->id()] : null;
        $data['o:item_set'] = array_map(fn ($set) => ['o:id' => $set->id()], $item->itemSets());
        return $data;
    }
}
