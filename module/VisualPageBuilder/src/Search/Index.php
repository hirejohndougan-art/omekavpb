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
 *   vpb_search_facet  one row per item and facet value (item set, class, decade, images, text values)
 *   vpb_search_facet_value  the text of each subject, creator and place value, with an id the facet rows use
 *
 * indexItem() replaces an item's rows, so it is safe to call again after every
 * save. Staff-only items are indexed too; Search keeps them out of public results.
 */
class Index
{
    public const DOC = 'vpb_search_doc';
    public const TERM = 'vpb_search_term';
    public const FACET = 'vpb_search_facet';
    public const FACET_VALUE = 'vpb_search_facet_value';

    /**
     * Facet fields: item set and resource class (ids), decade (the decade's first year),
     * has images (1), and the text fields (see TEXT_FIELDS).
     */
    public const FACET_FIELDS = ['item_set', 'class', 'decade', 'has_images', 'has_description', 'has_transcript', 'subject', 'creator', 'place'];

    /**
     * The property that holds an item's transcript (the has-transcript yes/no facet).
     * Default bibo:content, the property the DHS oral histories use; change it here if the
     * transcripts live elsewhere, then reindex.
     */
    public const TRANSCRIPT_TERM = 'bibo:content';

    /** Text facet fields: the property each one reads. Their values are stored as ids in FACET_VALUE. */
    public const TEXT_FIELDS = [
        'subject' => 'dcterms:subject',
        'creator' => 'dcterms:creator',
        'place' => 'dcterms:spatial',
    ];
    /** Longest text value kept as a facet label (the index column size). */
    public const TEXT_LABEL_MAX = 191;
    /** One row per stem: the word as first written in the catalog, for "Did you mean". */
    public const WORD = 'vpb_search_word';

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
            added DATETIME NULL,
            PRIMARY KEY (item_id),
            INDEX vpb_sd_years (year_start, year_end),
            INDEX vpb_sd_added (added)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        // Tables made before "added" (Recently added sort) existed get the column now.
        if (!self::hasColumn($conn, self::DOC, 'added')) {
            $conn->executeStatement('ALTER TABLE ' . self::DOC . ' ADD COLUMN added DATETIME NULL, ADD INDEX vpb_sd_added (added)');
        }
        $conn->executeStatement('CREATE TABLE IF NOT EXISTS ' . self::TERM . ' (
            term VARCHAR(100) NOT NULL,
            item_id INT NOT NULL,
            weight TINYINT NOT NULL,
            PRIMARY KEY (term, item_id),
            INDEX vpb_st_item (item_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $conn->executeStatement('CREATE TABLE IF NOT EXISTS ' . self::WORD . ' (
            stem VARCHAR(100) NOT NULL,
            display VARCHAR(100) NOT NULL,
            PRIMARY KEY (stem)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $conn->executeStatement('CREATE TABLE IF NOT EXISTS ' . self::FACET . ' (
            item_id INT NOT NULL,
            field VARCHAR(40) NOT NULL,
            value_id INT NOT NULL,
            PRIMARY KEY (item_id, field, value_id),
            INDEX vpb_sf_value (field, value_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $conn->executeStatement('CREATE TABLE IF NOT EXISTS ' . self::FACET_VALUE . ' (
            id INT AUTO_INCREMENT PRIMARY KEY,
            field VARCHAR(40) NOT NULL,
            label VARCHAR(191) NOT NULL,
            UNIQUE KEY vpb_sfv_label (field, label)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public static function hasColumn(Connection $conn, string $table, string $column): bool
    {
        return (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$table, $column]
        ) === 1;
    }

    public static function dropTables(Connection $conn): void
    {
        foreach ([self::WORD, self::FACET, self::FACET_VALUE, self::TERM, self::DOC] as $table) {
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

    /** True when the index exists, has rows, and is not being rebuilt right now. */
    public static function ready(Connection $conn, $settings): bool
    {
        if ($settings->get(ReindexJob::REBUILDING_SETTING, false)) {
            return false;
        }
        if (!self::exists($conn)) {
            return false;
        }
        return (bool) $conn->fetchOne('SELECT 1 FROM ' . self::DOC . ' LIMIT 1');
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
            'added' => $item->created() ? $item->created()->format('Y-m-d H:i:s') : null,
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
                self::rememberWord($conn, $stem, $value, $word);
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
        // Decade: from the item's first year. Undated items have no decade.
        if ($start !== null && $start >= 0) {
            $conn->insert(self::FACET, ['item_id' => $id, 'field' => 'decade', 'value_id' => intdiv($start, 10) * 10]);
        }
        foreach ($item->media() as $media) {
            if (strpos((string) $media->mediaType(), 'image/') === 0) {
                $conn->insert(self::FACET, ['item_id' => $id, 'field' => 'has_images', 'value_id' => 1]);
                break;
            }
        }
        // Yes/no: the item has a description (any non-blank dcterms:description).
        foreach ($item->value('dcterms:description', ['all' => true, 'default' => []]) as $value) {
            if (self::textLabel((string) $value) !== null) {
                $conn->insert(self::FACET, ['item_id' => $id, 'field' => 'has_description', 'value_id' => 1]);
                break;
            }
        }
        // Yes/no: the item has a transcript (any non-blank value of TRANSCRIPT_TERM).
        foreach ($item->value(self::TRANSCRIPT_TERM, ['all' => true, 'default' => []]) as $value) {
            if (self::textLabel((string) $value) !== null) {
                $conn->insert(self::FACET, ['item_id' => $id, 'field' => 'has_transcript', 'value_id' => 1]);
                break;
            }
        }
        // Text facets: each distinct subject, creator and place value, once per item.
        foreach (self::TEXT_FIELDS as $field => $term) {
            $labels = [];
            foreach ($item->value($term, ['all' => true, 'default' => []]) as $value) {
                $label = self::textLabel((string) $value);
                if ($label !== null) {
                    $labels[$label] = true;
                }
            }
            foreach (array_keys($labels) as $label) {
                $conn->insert(self::FACET, [
                    'item_id' => $id,
                    'field' => $field,
                    'value_id' => self::facetValueId($conn, $field, $label),
                ]);
            }
        }
    }

    /** A text facet's label: spaces collapsed and trimmed, or null when nothing is left. */
    public static function textLabel(string $raw): ?string
    {
        $label = trim(preg_replace('/\s+/u', ' ', $raw) ?? '');
        if ($label === '') {
            return null;
        }
        return mb_substr($label, 0, self::TEXT_LABEL_MAX);
    }

    /** The id of a text value, added the first time it is seen. */
    public static function facetValueId(Connection $conn, string $field, string $label): int
    {
        $id = $conn->fetchOne('SELECT id FROM ' . self::FACET_VALUE . ' WHERE field = ? AND label = ?', [$field, $label]);
        if ($id === false) {
            $conn->insert(self::FACET_VALUE, ['field' => $field, 'label' => $label]);
            $id = $conn->lastInsertId();
        }
        return (int) $id;
    }

    /** Labels for text facet ids: [id => label]. */
    public static function valueLabels(Connection $conn, string $field, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $rows = $conn->fetchAllKeyValue(
            'SELECT id, label FROM ' . self::FACET_VALUE . ' WHERE field = ? AND id IN (' . $marks . ')',
            array_merge([$field], $ids)
        );
        return array_map('strval', $rows);
    }

    /**
     * Keep the word as it is spelled in the catalog ("Dickinson", not "dickinson"),
     * the first time a stem is seen. Only used to show "Did you mean" suggestions.
     */
    private static function rememberWord(Connection $conn, string $stem, string $text, string $lowerWord): void
    {
        static $seen = [];
        if (isset($seen[$stem])) {
            return;
        }
        $seen[$stem] = true;
        if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote($lowerWord, '/') . '(?![\p{L}\p{N}])/iu', $text, $m)) {
            $display = mb_substr($m[0], 0, 100);
        } else {
            $display = $lowerWord;
        }
        $conn->executeStatement(
            'INSERT IGNORE INTO ' . self::WORD . ' (stem, display) VALUES (?, ?)',
            [mb_substr($stem, 0, 100), $display]
        );
    }

    /**
     * An item is deleted: remove its rows, and forget the spellings of any word
     * no other item still has, so "Did you mean" never suggests a word the
     * catalog no longer contains. (indexItem() uses removeItem() alone, because
     * an edited item re-adds the same words straight away.)
     */
    public static function forgetItem(Connection $conn, int $itemId): void
    {
        $stems = $conn->fetchFirstColumn('SELECT DISTINCT term FROM ' . self::TERM . ' WHERE item_id = ?', [$itemId]);
        self::removeItem($conn, $itemId);
        foreach ($stems as $stem) {
            if (!$conn->fetchOne('SELECT 1 FROM ' . self::TERM . ' WHERE term = ? LIMIT 1', [$stem])) {
                $conn->delete(self::WORD, ['stem' => $stem]);
            }
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
