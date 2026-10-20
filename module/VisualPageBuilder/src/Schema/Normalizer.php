<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Schema;

use Omeka\Stdlib\HtmlPurifier;

/**
 * Cleans block data against its schema before Omeka saves it.
 *
 * Both editors send form-ish values ("1", "12", ["3","4"]); this turns them
 * into tidy, typed data (true, 12, [3, 4]), applies defaults and bounds,
 * removes empty list rows and keys the schema doesn't know, and runs rich text
 * through Omeka's HTML purifier (which follows the "Use HTMLPurifier" setting).
 */
class Normalizer
{
    /**
     * @var HtmlPurifier
     */
    protected $purifier;

    public function __construct(HtmlPurifier $purifier)
    {
        $this->purifier = $purifier;
    }

    public function normalize(array $schema, array $data): array
    {
        $out = [];
        foreach ($schema as $field) {
            $key = $field['key'];
            $out[$key] = array_key_exists($key, $data)
                ? $this->value($field, $data[$key])
                : Schema::defaultValue($field);
        }
        return $out;
    }

    /**
     * One field's value.
     */
    public function value(array $field, $raw)
    {
        switch ($field['type']) {
            case Schema::TYPE_TEXT:
            case Schema::TYPE_TEXTAREA:
                return is_scalar($raw) ? trim((string) $raw) : '';

            case Schema::TYPE_HTML:
                return is_scalar($raw) ? trim($this->purifier->purify((string) $raw)) : '';

            case Schema::TYPE_NUMBER:
                if (!is_numeric($raw)) {
                    return Schema::defaultValue($field);
                }
                $n = (int) $raw;
                if (isset($field['min'])) {
                    $n = max($field['min'], $n);
                }
                if (isset($field['max'])) {
                    $n = min($field['max'], $n);
                }
                return $n;

            case Schema::TYPE_SELECT:
                $raw = is_scalar($raw) ? (string) $raw : '';
                return array_key_exists($raw, $field['options']) ? $raw : Schema::defaultValue($field);

            case Schema::TYPE_CHECKBOX:
                return in_array($raw, [true, 1, '1', 'true', 'on'], true);

            case Schema::TYPE_URL:
                return self::safeUrl($raw);

            case Schema::TYPE_DATE:
                $raw = is_scalar($raw) ? trim((string) $raw) : '';
                return preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) && checkdate((int) substr($raw, 5, 2), (int) substr($raw, 8, 2), (int) substr($raw, 0, 4)) ? $raw : '';

            case Schema::TYPE_SITE:
            case Schema::TYPE_ASSET:
            case Schema::TYPE_ITEM:
            case Schema::TYPE_PAGE:
                return is_numeric($raw) && (int) $raw > 0 ? (int) $raw : null;

            case Schema::TYPE_SITES:
            case Schema::TYPE_ITEM_SETS:
                return self::idList($raw);

            case Schema::TYPE_LIST:
                return $this->rows($field, $raw);
        }
        return null;
    }

    /**
     * Rows of a list field. Rows where every sub-field is empty are dropped,
     * so the standard editor's spare blank rows don't get saved.
     */
    protected function rows(array $field, $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $rows = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $clean = $this->normalize($field['fields'], $row);
            $hasContent = false;
            foreach ($field['fields'] as $sub) {
                $v = $clean[$sub['key']];
                if ($sub['type'] !== Schema::TYPE_CHECKBOX && $sub['type'] !== Schema::TYPE_SELECT && $v !== '' && $v !== null && $v !== []) {
                    $hasContent = true;
                }
            }
            if ($hasContent) {
                $rows[] = $clean;
            }
        }
        return isset($field['max']) ? array_slice($rows, 0, $field['max']) : $rows;
    }

    /** Unique positive integer ids from "3", ["3", "4"], or "3,4". */
    public static function idList($raw): array
    {
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }
        if (!is_array($raw)) {
            return [];
        }
        $ids = [];
        foreach ($raw as $v) {
            if (is_numeric($v) && (int) $v > 0) {
                $ids[] = (int) $v;
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * A link address safe to print in an href, or '' if it isn't one:
     * http(s)://, mailto:, tel:, #…, or a site path starting with one "/".
     */
    public static function safeUrl($raw): string
    {
        $url = is_scalar($raw) ? trim((string) $raw) : '';
        if ($url === '') {
            return '';
        }
        // Also "#…" (a spot on this page; templates use "#" until a link is chosen).
        if (preg_match('~^(https?://|mailto:|tel:|#)~i', $url) || (substr($url, 0, 1) === '/' && substr($url, 0, 2) !== '//')) {
            return $url;
        }
        return '';
    }
}
