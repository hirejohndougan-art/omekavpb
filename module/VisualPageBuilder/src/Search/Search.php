<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Search;

use Doctrine\DBAL\Connection;

/**
 * Runs a visitor's search against the VPB Search index (plan section 2).
 *
 * Ranking: words in the title count most (Index::WEIGHTS), and matches with more
 * weight come first, then title A-Z. Public visitors never see staff-only items.
 * Pure SQL over the three index tables; no Omeka API calls.
 */
class Search
{
    /**
     * @param array{item_set?: int[], class?: int[]} $facets selected facet values, each field OR-ed, fields AND-ed
     * @return array{total: int, ids: int[], suggestion: ?string, parsed: array}
     */
    public static function run(
        Connection $conn,
        string $raw,
        bool $staff = false,
        int $page = 1,
        int $perPage = 12,
        ?Synonyms $synonyms = null,
        array $facets = []
    ): array {
        $parsed = QueryParser::parse($raw, $synonyms);
        [$where, $params] = self::conditions($parsed, $staff, $facets);

        $total = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM ' . Index::DOC . ' d WHERE ' . $where,
            $params
        );

        $ids = [];
        if ($total > 0) {
            // Best match first when there are words; a year-only or phrase-only search sorts by title.
            $score = self::scoreSql($parsed);
            $order = $score === null ? 'd.title ASC' : $score['sql'] . ' DESC, d.title ASC';
            $sql = 'SELECT d.item_id FROM ' . Index::DOC . ' d WHERE ' . $where
                . ' ORDER BY ' . $order
                . ' LIMIT ' . max(1, $perPage) . ' OFFSET ' . max(0, ($page - 1) * $perPage);
            $ids = array_map('intval', $conn->fetchFirstColumn($sql, array_merge($params, $score['params'] ?? [])));
        }

        $suggestion = null;
        if ($total === 0 && $parsed['words'] !== []) {
            $suggestion = self::suggestion($conn, $parsed);
        }

        return ['total' => $total, 'ids' => $ids, 'suggestion' => $suggestion, 'parsed' => $parsed];
    }

    /**
     * The WHERE clause and its parameters.
     *
     * @return array{0: string, 1: array}
     */
    public static function conditions(array $parsed, bool $staff, array $facets = []): array
    {
        $clauses = [$staff ? '1 = 1' : 'd.is_public = 1'];
        $params = [];

        foreach ($parsed['groups'] as $group) {
            $variantClauses = [];
            foreach ($group['variants'] as $stems) {
                // Every stem of a variant must be in the item (multi-word synonyms).
                $parts = [];
                foreach ($stems as $stem) {
                    if ($group['prefix'] && count($stems) === 1) {
                        $parts[] = 'EXISTS (SELECT 1 FROM ' . Index::TERM . ' t WHERE t.item_id = d.item_id AND t.term LIKE ?)';
                        $params[] = self::likeEscape($stem) . '%';
                    } else {
                        $parts[] = 'EXISTS (SELECT 1 FROM ' . Index::TERM . ' t WHERE t.item_id = d.item_id AND t.term = ?)';
                        $params[] = $stem;
                    }
                }
                $variantClauses[] = '(' . implode(' AND ', $parts) . ')';
            }
            if ($variantClauses) {
                $clauses[] = '(' . implode(' OR ', $variantClauses) . ')';
            }
        }

        foreach ($parsed['phrases'] as $phrase) {
            $clauses[] = 'd.search_text LIKE ?';
            $params[] = '%' . self::likeEscape($phrase) . '%';
        }

        if ($parsed['years']) {
            $clauses[] = '(d.year_start <= ? AND d.year_end >= ?)';
            $params[] = $parsed['years']['end'];
            $params[] = $parsed['years']['start'];
        }

        foreach (['item_set', 'class'] as $field) {
            $ids = array_values(array_filter(array_map('intval', $facets[$field] ?? [])));
            if ($ids) {
                $marks = implode(',', array_fill(0, count($ids), '?'));
                $clauses[] = 'EXISTS (SELECT 1 FROM ' . Index::FACET . ' f WHERE f.item_id = d.item_id'
                    . ' AND f.field = ? AND f.value_id IN (' . $marks . '))';
                $params[] = $field;
                array_push($params, ...$ids);
            }
        }

        return [implode(' AND ', $clauses), $params];
    }

    /**
     * Relevance: the sum of the weights of the matching words (exact stems only;
     * prefix matches count the same as their stem).
     *
     * @return ?array{sql: string, params: array}
     */
    private static function scoreSql(array $parsed): ?array
    {
        $stems = $parsed['terms'];
        if ($stems === []) {
            return null;
        }
        $marks = implode(',', array_fill(0, count($stems), '?'));
        return [
            'sql' => '(SELECT COALESCE(SUM(t.weight), 0) FROM ' . Index::TERM . ' t'
                . ' WHERE t.item_id = d.item_id AND t.term IN (' . $marks . '))',
            'params' => $stems,
        ];
    }

    /**
     * "Did you mean": the first search word that matches no indexed word, replaced by
     * the closest indexed word. Returns the corrected search, or null.
     */
    private static function suggestion(Connection $conn, array $parsed): ?string
    {
        $vocabulary = [];
        foreach ($conn->fetchAllAssociative(
            'SELECT term, COUNT(*) AS c FROM ' . Index::TERM . ' GROUP BY term LIMIT 50000'
        ) as $row) {
            $vocabulary[(string) $row['term']] = (int) $row['c'];
        }

        $corrected = [];
        $changed = false;
        foreach ($parsed['words'] as $word) {
            $stem = Stemmer::stem($word);
            if (!isset($vocabulary[$stem])) {
                $better = Typo::suggest($stem, $vocabulary);
                if ($better !== null) {
                    $corrected[] = $better;
                    $changed = true;
                    continue;
                }
            }
            $corrected[] = $word;
        }
        return $changed ? implode(' ', $corrected) : null;
    }

    private static function likeEscape(string $s): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
    }
}
