<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder;

use Doctrine\DBAL\Connection;
use Omeka\Api\Manager as ApiManager;

/**
 * Drafts (Phase 6, plan decision 1). Omeka items are only public or private, so a draft
 * is a private item with a row in vpb_draft. Publishing makes the item public and
 * removes the row. If VPB is uninstalled, drafts are simply private items.
 */
class Drafts
{
    public const TABLE = 'vpb_draft';

    public static function createTable(Connection $conn): void
    {
        $conn->executeStatement('CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
            item_id INT NOT NULL,
            saved DATETIME NOT NULL,
            PRIMARY KEY (item_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public static function dropTable(Connection $conn): void
    {
        $conn->executeStatement('DROP TABLE IF EXISTS ' . self::TABLE);
    }

    /** Keep an item as a draft: it is made private, and listed as a draft. */
    public static function keep(Connection $conn, ApiManager $api, int $itemId): void
    {
        self::createTable($conn);
        $api->update('items', $itemId, ['o:is_public' => false], [], ['isPartial' => true]);
        $conn->executeStatement(
            'INSERT IGNORE INTO ' . self::TABLE . ' (item_id, saved) VALUES (?, ?)',
            [$itemId, (new \DateTime())->format('Y-m-d H:i:s')]
        );
    }

    /** Publish a draft: the item becomes public and stops being a draft. */
    public static function publish(Connection $conn, ApiManager $api, int $itemId): bool
    {
        self::createTable($conn);
        if (!self::isDraft($conn, $itemId)) {
            return false;
        }
        $api->update('items', $itemId, ['o:is_public' => true], [], ['isPartial' => true]);
        $conn->executeStatement('DELETE FROM ' . self::TABLE . ' WHERE item_id = ?', [$itemId]);
        return true;
    }

    public static function isDraft(Connection $conn, int $itemId): bool
    {
        self::createTable($conn);
        return (bool) $conn->fetchOne('SELECT 1 FROM ' . self::TABLE . ' WHERE item_id = ?', [$itemId]);
    }

    /** How many items are drafts (only items that still exist). */
    public static function count(Connection $conn): int
    {
        self::createTable($conn);
        return (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' d JOIN item i ON i.id = d.item_id'
        );
    }

    /** Forget a draft when its item is deleted. */
    public static function forget(Connection $conn, int $itemId): void
    {
        self::createTable($conn);
        $conn->executeStatement('DELETE FROM ' . self::TABLE . ' WHERE item_id = ?', [$itemId]);
    }
}
