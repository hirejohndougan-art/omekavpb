<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Search;

use Doctrine\DBAL\Connection;
use Omeka\Api\Representation\ItemRepresentation;

/**
 * The VPB Search index (plan section 2): three tables kept in the site's own
 * database, so nothing else needs installing.
 *
 *   vpb_search_doc    one row per item: title, searchable text, year range, public flag
 *   vpb_search_term   one row per stemmed word per item, with a field weight
 *   vpb_search_facet  one row per item and facet value (item set, resource class)
 *
 * indexItem() replaces an item's rows, so it is safe to call again after every
 * save. Staff-only items are indexed too; Search keeps them out of public results.
 */
class Index
{
    public const DOC = 'vpb_search_doc';
    public const TERM = 'vpb_search_term';
    public const FACET = 'vpb_search_facet';

    /** Omeka setting that holds the synonyms list (one "term = alternative" per line). */
    public const SYNONYMS_SETTING = 'vpb_search_synonyms';

    /** How much a word counts, by the property it came from (plan: title highest). */
    public const WEIGHTS = [
        'dcterms:title' => 3,
        'dcterms:subject' => 2,
        'dcterms:creator' => 2,
        'dcterms:contributor' => 2,
        'dcterms:spatial' => 2,
    ];
    public const DEFAULT_WEIGHT = 1;

    public static function createTables(Connection $conn): void
    {
        $conn->executeStatement('CREATE TABLE IF NOT EXISTS ' . self::DOC . ' (
            item_id INT NOT NULL,
            is_public TINYINT(1) NOT NULL DEFAULT 0,
            title VARCHAR(255) NOT NULL DEFAULT \'\',
            search_text LONGTEXT NOT NULL,
            year_start INT NULL,
            year_end INT NULL,
            indexed DATETIME NOT NULL,
            PRIMARY KEY (item_id),
            INDEX vpb_sd_years (year_start, year_end)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $conn->executeStatement('CREATE TABLE IF NOT EXISTS ' . self::TERM . ' (
            term VARCHAR(100) NOT NULL,
            item_id INT NOT NULL,
            weight TINYINT NOT NULL,
            PRIMARY KEY (term, item_id),
            INDEX vpb_st_item (item_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $conn->executeStatement('CREATE TABLE IF NOT EXISTS ' . self::FACET . ' (
            item_id INT NOT NULL,
            field VARCHAR(40) NOT NULL,
            value_id INT NOT NULL,
            PRIMARY KEY (item_id, field, value_id),
            INDEX vpb_sf_value (field, value_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public static function dropTables(Connection $conn): void
    {
        foreach ([self::FACET, self::TERM, self::DOC] as $table) {
            $conn->executeStatement('DROP TABLE IF EXISTS ' . $table);
        }
    }

    public static function exists(Connection $conn): bool
    {
        return (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [self::DOC]
        ) === 1;
    }

    /** Replace one item's rows with a fresh reading of it. */
    public static function indexItem(Connection $conn, ItemRepresentation $item): void
    {
        $id = (int) $item->id();
        $fields = self::fieldText($item);
        $text = mb_strtolower(implode(' ', array_column($fields, 1)));
        [$start, $end] = self::yearRange($item);

        self::removeItem($conn, $id);
        $conn->insert(self::DOC, [
            'item_id' => $id,
            'is_public' => $item->isPublic() ? 1 : 0,
            'title' => mb_substr((string) $item->displayTitle(''), 0, 255),
            'search_text' => $text,
            'year_start' => $start,
            'year_end' => $end,
            'indexed' => (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
        ]);

        // Best weight per stem: a word in the title outranks the same word in a description.
        $weights = [];
        foreach ($fields as $entry) {
            [$property, $value] = $entry;
            $weight = self::WEIGHTS[$property] ?? self::DEFAULT_WEIGHT;
            foreach (self::words($value) as $word) {
                $stem = Stemmer::stem($word);
                $weights[$stem] = max($weights[$stem] ?? 0, $weight);
            }
        }
        foreach ($weights as $term => $weight) {
            $conn->insert(self::TERM, ['term' => mb_substr($term, 0, 100), 'item_id' => $id, 'weight' => $weight]);
        }

        foreach ($item->itemSets() as $set) {
            $conn->insert(self::FACET, ['item_id' => $id, 'field' => 'item_set', 'value_id' => (int) $set->id()]);
        }
        $class = $item->resourceClass();
        if ($class) {
            $conn->insert(self::FACET, ['item_id' => $id, 'field' => 'class', 'value_id' => (int) $class->id()]);
        }
    }

    public static function removeItem(Connection $conn, int $itemId): void
    {
        $conn->delete(self::TERM, ['item_id' => $itemId]);
        $conn->delete(self::FACET, ['item_id' => $itemId]);
        $conn->delete(self::DOC, ['item_id' => $itemId]);
    }

    /** @return array<int, array{0:string,1:string}> [property term, text] for every value. */
    public static function fieldText(ItemRepresentation $item): array
    {
        $out = [];
        foreach ($item->values() as $term => $data) {
            foreach ($data['values'] ?? [] as $value) {
                if ($value->type() === 'resource' || $value->type() === 'resource:item') {
                    $resource = $value->valueResource();
                    $text = $resource ? (string) $resource->displayTitle('') : '';
                } else {
                    $text = (string) $value->value();
                }
                if ($text !== '') {
                    $out[] = [$term, $text];
                }
            }
        }
        return $out;
    }

    /** Lower-case words, without stopwords or one-letter fragments. */
    public static function words(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}&]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_filter(
            $words,
            fn ($w) => mb_strlen($w) > 1 && !in_array($w, QueryParser::STOPWORDS, true)
        ));
    }

    /** The year range of the first dated date value, or [null, null]. */
    public static function yearRange(ItemRepresentation $item): array
    {
        foreach (['dcterms:date', 'dcterms:created', 'dcterms:issued'] as $term) {
            foreach ($item->value($term, ['all' => true]) ?? [] as $value) {
                $range = DateParser::parse((string) $value->value());
                if ($range) {
                    return [$range['start'], $range['end']];
                }
            }
        }
        return [null, null];
    }
}
