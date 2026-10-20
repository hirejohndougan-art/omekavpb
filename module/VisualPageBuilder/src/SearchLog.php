<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder;

use Doctrine\DBAL\Connection;

/**
 * What visitors searched for (Phase 6, the Home search report). One row per search
 * with a term. Searches that found nothing are kept apart so staff can see what to catalog.
 */
class SearchLog
{
    public const TABLE = 'vpb_search_log';
    /** Keep a year of searches; older rows are pruned when new ones are logged. */
    public const KEEP_DAYS = 365;

    public static function createTable(Connection $conn): void
    {
        $conn->executeStatement('CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
            id INT AUTO_INCREMENT NOT NULL,
            term VARCHAR(255) NOT NULL,
            total INT NOT NULL,
            searched DATETIME NOT NULL,
            PRIMARY KEY (id),
            INDEX vpb_search_log_searched (searched)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public static function dropTable(Connection $conn): void
    {
        $conn->executeStatement('DROP TABLE IF EXISTS ' . self::TABLE);
    }

    public static function record(Connection $conn, string $term, int $total): void
    {
        $term = mb_substr(mb_strtolower(trim($term)), 0, 255);
        if ($term === '') {
            return;
        }
        $row = [
            'term' => $term,
            'total' => max(0, $total),
            'searched' => (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
        ];
        try {
            $conn->insert(self::TABLE, $row);
        } catch (\Exception $e) {
            // An install that was updated without its install step has no table yet: make it, then log.
            self::createTable($conn);
            $conn->insert(self::TABLE, $row);
        }
    }

    /**
     * The most common terms since $since: those that found results, and those that found nothing.
     *
     * @return array{found: array<int, array{term: string, count: int}>, empty: array<int, array{term: string, count: int}>}
     */
    public static function report(Connection $conn, \DateTimeInterface $since, int $limit = 10): array
    {
        $from = $since->format('Y-m-d H:i:s');
        $rows = fn (string $where) => array_map(
            fn ($row) => ['term' => (string) $row['term'], 'count' => (int) $row['n']],
            $conn->fetchAllAssociative(
                'SELECT term, COUNT(*) AS n FROM ' . self::TABLE . ' WHERE searched >= ? AND ' . $where
                . ' GROUP BY term ORDER BY n DESC, term ASC LIMIT ' . (int) $limit,
                [$from]
            )
        );
        return ['found' => $rows('total > 0'), 'empty' => $rows('total = 0')];
    }

    /** Drop searches older than KEEP_DAYS. Called from time to time, not on every search. */
    public static function prune(Connection $conn): void
    {
        $cutoff = (new \DateTime('now', new \DateTimeZone('UTC')))->modify('-' . self::KEEP_DAYS . ' days')->format('Y-m-d H:i:s');
        $conn->executeStatement('DELETE FROM ' . self::TABLE . ' WHERE searched < ?', [$cutoff]);
    }
}
