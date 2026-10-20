<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder;

use Doctrine\DBAL\Connection;
use Omeka\Api\Representation\SitePageRepresentation;

/**
 * Page history: each save in the visual editor first keeps a copy of the page
 * as it was (title, layout, layout data, blocks), in the module's own table
 * vpb_page_revision. The newest KEEP copies per page are kept. Restoring loads
 * a copy into the editor as unsaved changes; nothing changes until you save.
 */
class PageHistory
{
    const TABLE = 'vpb_page_revision';

    const KEEP = 50;

    public static function createTable(Connection $conn): void
    {
        $conn->executeStatement('CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
            id INT AUTO_INCREMENT NOT NULL,
            page_id INT NOT NULL,
            created DATETIME NOT NULL,
            user_name VARCHAR(190) NOT NULL DEFAULT \'\',
            title VARCHAR(255) NOT NULL DEFAULT \'\',
            data LONGTEXT NOT NULL,
            INDEX vpb_rev_page (page_id, id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    /** Make sure the table exists (also covers a skipped upgrade); once per request. */
    protected static $ready = false;

    public static function ensure(Connection $conn): void
    {
        if (!self::$ready) {
            self::createTable($conn);
            self::$ready = true;
        }
    }

    public static function dropTable(Connection $conn): void
    {
        $conn->executeStatement('DROP TABLE IF EXISTS ' . self::TABLE);
    }

    /** Keep a copy of the page as it is now. */
    public static function keep(Connection $conn, SitePageRepresentation $page, string $userName): void
    {
        self::ensure($conn);
        $data = [
            'o:title' => $page->title(),
            'o:layout' => $page->layout(),
            'o:layout_data' => $page->layoutData(),
            'o:block' => Controller\Admin\PagesController::copyBlocks($page),
        ];
        $conn->insert(self::TABLE, [
            'page_id' => $page->id(),
            'created' => (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
            'user_name' => mb_substr($userName, 0, 190),
            'title' => mb_substr($page->title(), 0, 255),
            'data' => json_encode($data),
        ]);
        // Only the newest KEEP per page.
        $ids = $conn->fetchFirstColumn('SELECT id FROM ' . self::TABLE . ' WHERE page_id = ? ORDER BY id DESC LIMIT 1000 OFFSET ' . self::KEEP, [$page->id()]);
        if ($ids) {
            $conn->executeStatement('DELETE FROM ' . self::TABLE . ' WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')');
        }
    }

    /** [{id, created (ISO, UTC), user, title}] newest first. */
    public static function listFor(Connection $conn, int $pageId): array
    {
        self::ensure($conn);
        $rows = $conn->fetchAllAssociative('SELECT id, created, user_name, title FROM ' . self::TABLE . ' WHERE page_id = ? ORDER BY id DESC', [$pageId]);
        return array_map(function ($r) {
            return ['id' => (int) $r['id'], 'created' => str_replace(' ', 'T', $r['created']) . 'Z', 'user' => $r['user_name'], 'title' => $r['title']];
        }, $rows);
    }

    /** One kept copy of the page (or null). */
    public static function get(Connection $conn, int $pageId, int $id): ?array
    {
        self::ensure($conn);
        $data = $conn->fetchOne('SELECT data FROM ' . self::TABLE . ' WHERE page_id = ? AND id = ?', [$pageId, $id]);
        $decoded = is_string($data) ? json_decode($data, true) : null;
        return is_array($decoded) ? $decoded : null;
    }

    public static function forget(Connection $conn, int $pageId): void
    {
        $conn->delete(self::TABLE, ['page_id' => $pageId]);
    }
}
