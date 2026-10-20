<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Stdlib;

use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\AssetRepresentation;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SiteRepresentation;

/**
 * Finds items and assets for the editor's pickers and describes them in the
 * compact shape the editor uses:
 *
 *   item:  {id, title, thumb, isPublic, media: [{id, title, thumb}]}
 *   asset: {id, title, thumb}
 *
 * All reads go through Omeka's API as the current user, so private items and
 * permissions are handled by Omeka.
 */
class ResourceLookup
{
    /** Most results returned by one search. */
    const ITEM_SEARCH_LIMIT = 20;
    const ASSET_SEARCH_LIMIT = 30;

    /** Assets have no text search in the API, so this many newest are scanned by name. */
    const ASSET_SCAN_LIMIT = 500;

    /** Most ids accepted in one lookup. */
    const MAX_IDS = 200;

    /**
     * @var ApiManager
     */
    protected $api;

    public function __construct(ApiManager $api)
    {
        $this->api = $api;
    }

    /**
     * Items matching a full-text search, newest first when the search is empty.
     * A search that is just a number looks up that item id.
     */
    public function searchItems(string $q): array
    {
        $query = ['limit' => self::ITEM_SEARCH_LIMIT, 'sort_by' => 'created', 'sort_order' => 'desc'];
        if ($q !== '') {
            if (ctype_digit($q)) {
                $query['id'] = (int) $q;
            } else {
                $query['fulltext_search'] = $q;
                unset($query['sort_by'], $query['sort_order']);
            }
        }
        $rows = [];
        foreach ($this->api->search('items', $query)->getContent() as $item) {
            $rows[] = $this->itemRow($item);
        }
        return $rows;
    }

    /**
     * Assets whose name contains the search (case-insensitive), newest first.
     */
    public function searchAssets(string $q): array
    {
        $rows = [];
        $assets = $this->api->search('assets', [
            'sort_by' => 'created',
            'sort_order' => 'desc',
            'limit' => self::ASSET_SCAN_LIMIT,
        ])->getContent();
        foreach ($assets as $asset) {
            if ($q === '' || mb_stripos($asset->name(), $q) !== false) {
                $rows[] = $this->assetRow($asset);
                if (count($rows) >= self::ASSET_SEARCH_LIMIT) {
                    break;
                }
            }
        }
        return $rows;
    }

    /**
     * What Omeka's Browse preview block would list for these settings, as
     * {title, thumb} rows. Mirrors Omeka's BrowsePreview::render(): the site
     * is always the page's site, newest first unless the query sorts.
     */
    public function browsePreview(array $data, SiteRepresentation $site): array
    {
        $type = in_array($data['resource_type'] ?? 'items', ['items', 'item_sets', 'media'], true) ? ($data['resource_type'] ?? 'items') : 'items';
        parse_str((string) ($data['query'] ?? ''), $query);
        $query['site_id'] = $site->id();
        $query['limit'] = max(1, min(24, (int) ($data['limit'] ?? 12) ?: 12));
        $query += ['sort_by' => 'created', 'sort_order' => 'desc'];
        $rows = [];
        foreach ($this->api->search($type, $query)->getContent() as $resource) {
            $rows[] = [
                'id' => $resource->id(),
                'title' => (string) $resource->displayTitle(),
                'thumb' => $resource->thumbnailDisplayUrl('square'),
                'description' => (string) $resource->displayDescription(),
            ];
        }
        return $rows;
    }

    /**
     * Rows for these item ids. Ids that don't exist, or that the user may not
     * see, are left out.
     */
    public function items(array $ids): array
    {
        $rows = [];
        foreach (array_slice($ids, 0, self::MAX_IDS) as $id) {
            try {
                $rows[] = $this->itemRow($this->api->read('items', $id)->getContent());
            } catch (NotFoundException $e) {
                // The editor shows it as "not found".
            }
        }
        return $rows;
    }

    /**
     * Rows for these asset ids. Missing ones are left out.
     */
    public function assets(array $ids): array
    {
        $rows = [];
        foreach (array_slice($ids, 0, self::MAX_IDS) as $id) {
            try {
                $rows[] = $this->assetRow($this->api->read('assets', $id)->getContent());
            } catch (NotFoundException $e) {
                // The editor shows it as "not found".
            }
        }
        return $rows;
    }

    /** Most sites and item sets listed in the editor's choosers. */
    const MAX_CHOICES = 1000;

    /**
     * Every site the user can see, for "choose sites" settings.
     *
     * @return array [{id, title}]
     */
    public function sites(): array
    {
        $rows = [];
        foreach ($this->api->search('sites', ['sort_by' => 'title', 'limit' => self::MAX_CHOICES])->getContent() as $site) {
            $rows[] = ['id' => $site->id(), 'title' => $site->title()];
        }
        return $rows;
    }

    /**
     * Every item set the user can see, for "choose item sets" settings.
     *
     * @return array [{id, title}]
     */
    public function itemSets(): array
    {
        $rows = [];
        foreach ($this->api->search('item_sets', ['sort_by' => 'title', 'limit' => self::MAX_CHOICES])->getContent() as $set) {
            $rows[] = ['id' => $set->id(), 'title' => (string) $set->displayTitle()];
        }
        return $rows;
    }

    /**
     * Everything a page's blocks already point at, keyed by id, so the editor
     * can show titles and thumbnails without further requests.
     *
     * $schemas (layout => fields) lets blocks from other modules be scanned
     * too: their `item` and `asset` fields, including inside list rows.
     *
     * @return array ['items' => object, 'assets' => object]
     */
    public function pageReferences(SitePageRepresentation $page, array $schemas = []): array
    {
        $itemIds = [];
        $assetIds = [];
        foreach ($page->blocks() as $block) {
            // Background images of sections, columns, and blocks.
            $bgAsset = $block->layoutDataValue('background_image_asset');
            if (is_numeric($bgAsset)) {
                $assetIds[] = (int) $bgAsset;
            }
            foreach ((array) (($block->layoutDataValue('vpb_section') ?: [])['cols'] ?? []) as $col) {
                if (is_array($col) && isset($col['img']['asset']) && is_numeric($col['img']['asset'])) {
                    $assetIds[] = (int) $col['img']['asset'];
                }
            }
            if (isset($schemas[$block->layout()])) {
                $this->schemaIds($schemas[$block->layout()], (array) $block->data(), $itemIds, $assetIds);
            }
            foreach ($block->attachments() as $attachment) {
                if ($attachment->item()) {
                    $itemIds[] = $attachment->item()->id();
                }
            }
            if ($block->layout() === 'asset') {
                foreach ((array) $block->data() as $entry) {
                    if (is_array($entry) && isset($entry['id']) && is_numeric($entry['id'])) {
                        $assetIds[] = (int) $entry['id'];
                    }
                }
            }
        }
        return [
            // Objects, so they encode as JSON objects even when empty.
            'items' => (object) $this->keyById($this->items(array_values(array_unique($itemIds)))),
            'assets' => (object) $this->keyById($this->assets(array_values(array_unique($assetIds)))),
        ];
    }

    /**
     * Collect item and asset ids from data saved against a schema.
     */
    protected function schemaIds(array $fields, array $data, array &$itemIds, array &$assetIds): void
    {
        foreach ($fields as $field) {
            $value = $data[$field['key']] ?? null;
            if ($field['type'] === 'item' && is_numeric($value)) {
                $itemIds[] = (int) $value;
            } elseif ($field['type'] === 'asset' && is_numeric($value)) {
                $assetIds[] = (int) $value;
            } elseif ($field['type'] === 'list' && is_array($value)) {
                foreach ($value as $row) {
                    if (is_array($row)) {
                        $this->schemaIds($field['fields'] ?? [], $row, $itemIds, $assetIds);
                    }
                }
            }
        }
    }

    /**
     * Parse "1,2,3" into unique positive integers (at most MAX_IDS).
     */
    public static function parseIds($raw): array
    {
        $ids = [];
        foreach (explode(',', (string) $raw) as $part) {
            $part = trim($part);
            if (ctype_digit($part)) {
                $ids[] = (int) $part;
            }
        }
        return array_slice(array_values(array_unique($ids)), 0, self::MAX_IDS);
    }

    protected function itemRow(ItemRepresentation $item): array
    {
        $media = [];
        foreach ($item->media() as $m) {
            $media[] = [
                'id' => $m->id(),
                'title' => (string) $m->displayTitle(),
                'thumb' => $m->thumbnailDisplayUrl('square'),
            ];
        }
        return [
            'id' => $item->id(),
            'title' => (string) $item->displayTitle(),
            'thumb' => $item->thumbnailDisplayUrl('square'),
            // Full-shape image, for editor tools that need the real proportions.
            'large' => $item->thumbnailDisplayUrl('large'),
            'isPublic' => $item->isPublic(),
            'media' => $media,
        ];
    }

    protected function assetRow(AssetRepresentation $asset): array
    {
        return [
            'id' => $asset->id(),
            'title' => (string) $asset->name(),
            'thumb' => $asset->assetUrl(),
        ];
    }

    protected function keyById(array $rows): array
    {
        $keyed = [];
        foreach ($rows as $row) {
            $keyed[$row['id']] = $row;
        }
        return $keyed;
    }
}
