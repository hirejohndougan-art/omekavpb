<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Schema;

/**
 * Field types used in block schemas, and helpers for reading a schema.
 *
 * A block's schema is a list of fields. Each field is an array:
 *
 *   key         string   o:data key the value is stored under (required)
 *   type        string   one of the TYPE_* constants (required)
 *   label       string   shown in both editors (required)
 *   info        string   help text under the field
 *   default     mixed    value used when the key is missing
 *   options     array    [value => label] for TYPE_SELECT
 *   min, max    int      bounds for TYPE_NUMBER; max rows for TYPE_LIST
 *   placeholder string
 *   show_if     array    [otherKey => [values…]]: only relevant when another
 *                        field has one of these values (both editors hide it)
 *   fields      array    sub-fields of each row, for TYPE_LIST
 *   summary     bool     show this value on the visual editor's block card
 *   group       string   heading the field appears under ("Items", "Display"…)
 *
 * The same schema drives the standard editor form (FormBuilder), the visual
 * page builder's sidebar (visualPageBuilderSchema()), and how saved data is
 * cleaned (Normalizer), so a field is defined once.
 */
class Schema
{
    const TYPE_TEXT = 'text';
    const TYPE_TEXTAREA = 'textarea';
    /** Rich text: stored as HTML, cleaned by Omeka's HTML purifier on save. */
    const TYPE_HTML = 'html';
    const TYPE_NUMBER = 'number';
    const TYPE_SELECT = 'select';
    const TYPE_CHECKBOX = 'checkbox';
    /** A web address: http(s), site-relative "/…", mailto: or tel: only. */
    const TYPE_URL = 'url';
    /** A calendar date, stored as YYYY-MM-DD. */
    const TYPE_DATE = 'date';
    /** One site id. */
    const TYPE_SITE = 'site';
    /** A list of site ids. */
    const TYPE_SITES = 'sites';
    /** A list of item set ids. */
    const TYPE_ITEM_SETS = 'item_sets';
    /** One asset id (an image uploaded under Assets). */
    const TYPE_ASSET = 'asset';
    /** One item id. */
    const TYPE_ITEM = 'item';
    /** One page id in the current site. */
    const TYPE_PAGE = 'page';
    /** Repeating rows of sub-fields (accordion panels, events…). */
    const TYPE_LIST = 'list';

    /** Index a schema by key. */
    public static function byKey(array $schema): array
    {
        $out = [];
        foreach ($schema as $field) {
            $out[$field['key']] = $field;
        }
        return $out;
    }

    /** Default value of each field, keyed by field key. */
    public static function defaults(array $schema): array
    {
        $out = [];
        foreach ($schema as $field) {
            $out[$field['key']] = self::defaultValue($field);
        }
        return $out;
    }

    /**
     * A field's value when nothing was saved: its `default`, else the first
     * option of a select, else the empty value for its type.
     */
    public static function defaultValue(array $field)
    {
        if (array_key_exists('default', $field)) {
            return $field['default'];
        }
        if ($field['type'] === self::TYPE_SELECT && !empty($field['options'])) {
            return (string) array_key_first($field['options']);
        }
        return self::emptyValue($field['type']);
    }

    /** The empty value for a field type. */
    public static function emptyValue(string $type)
    {
        switch ($type) {
            case self::TYPE_CHECKBOX:
                return false;
            case self::TYPE_SITES:
            case self::TYPE_ITEM_SETS:
            case self::TYPE_LIST:
                return [];
            case self::TYPE_SITE:
            case self::TYPE_ASSET:
            case self::TYPE_ITEM:
            case self::TYPE_PAGE:
            case self::TYPE_NUMBER:
                return null;
            default:
                return '';
        }
    }
}
