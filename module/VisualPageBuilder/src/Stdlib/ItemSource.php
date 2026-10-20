<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Stdlib;

use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\ItemSetRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Api\MultiSiteFilter;
use VisualPageBuilder\Schema\Schema;

/**
 * Where a block's items come from, and where their links go.
 *
 * Every block that lists items or item sets shares the same "source" fields
 * (see fields()): this site, chosen sites, or all sites, optionally narrowed to
 * item sets and to items that have files. This class turns those settings into
 * an Omeka API query and picks the right site for each link.
 */
class ItemSource
{
    const SCOPE_CURRENT = 'current';
    const SCOPE_SELECTED = 'selected';
    const SCOPE_ALL = 'all';

    /**
     * @var ApiManager
     */
    protected $api;

    public function __construct(ApiManager $api)
    {
        $this->api = $api;
    }

    /**
     * Schema fields shared by item blocks. `$withItemSets`/`$withMedia` drop
     * the filters a block doesn't use (collection cards list item sets, so they
     * can't be filtered by item set).
     */
    public static function fields(bool $withItemSets = true, bool $withMedia = true): array
    {
        $fields = [
            [
                'key' => 'site_scope', 'type' => Schema::TYPE_SELECT, 'group' => 'Source', 'summary' => true,
                'label' => 'Show from', // @translate
                'options' => [
                    self::SCOPE_CURRENT => 'This site', // @translate
                    self::SCOPE_SELECTED => 'Chosen sites', // @translate
                    self::SCOPE_ALL => 'All sites', // @translate
                ],
                'default' => self::SCOPE_CURRENT,
            ],
            [
                'key' => 'site_ids', 'type' => Schema::TYPE_SITES, 'group' => 'Source', 'summary' => true,
                'label' => 'Sites', // @translate
                'info' => 'Pick one or more sites.', // @translate
                'show_if' => ['site_scope' => [self::SCOPE_SELECTED]],
            ],
        ];
        if ($withItemSets) {
            $fields[] = [
                'key' => 'item_set_ids', 'type' => Schema::TYPE_ITEM_SETS, 'group' => 'Source', 'summary' => true,
                'label' => 'Only from these item sets', // @translate
                'info' => 'Optional. Leave empty to use every item set.', // @translate
            ];
        }
        if ($withMedia) {
            $fields[] = [
                'key' => 'has_media', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Source', 'default' => true,
                'label' => 'Only items that have images or files', // @translate
            ];
        }
        return $fields;
    }

    /**
     * The site ids a block draws from: [id…], or null for all sites.
     */
    public static function siteIds(array $data, SiteRepresentation $currentSite): ?array
    {
        $scope = $data['site_scope'] ?? self::SCOPE_CURRENT;
        if ($scope === self::SCOPE_ALL) {
            return null;
        }
        if ($scope === self::SCOPE_SELECTED && !empty($data['site_ids'])) {
            return array_values($data['site_ids']);
        }
        return [$currentSite->id()];
    }

    /**
     * API query terms for the block's source. Merge with sorting and limits.
     */
    public static function query(array $data, SiteRepresentation $currentSite, string $resource = 'items'): array
    {
        $query = [];
        $siteIds = self::siteIds($data, $currentSite);
        if ($siteIds === null) {
            // "All sites" still means items published on some site: an item
            // that is on no site has no public page to link to.
            $query['in_sites'] = true;
        } elseif (count($siteIds) === 1) {
            $query['site_id'] = $siteIds[0];
        } else {
            $query[MultiSiteFilter::QUERY_KEY] = $siteIds;
        }
        if ($resource === 'items') {
            if (!empty($data['item_set_ids'])) {
                $query['item_set_id'] = array_values($data['item_set_ids']);
            }
            if (!empty($data['has_media'])) {
                $query['has_media'] = true;
            }
        }
        return $query;
    }

    /**
     * Items for a block.
     *
     * @return ItemRepresentation[]
     */
    public function items(array $data, SiteRepresentation $currentSite, array $extra = []): array
    {
        return $this->api->search('items', self::query($data, $currentSite) + $extra)->getContent();
    }

    /** How many items match a block's source (plus any extra terms). */
    public function countItems(array $data, SiteRepresentation $currentSite, array $extra = []): int
    {
        return $this->api->search('items', self::query($data, $currentSite) + $extra + ['limit' => 0])->getTotalResults();
    }

    /**
     * The site whose pages an item or item set should link to: the current
     * site if it's on it, else the first of the block's sites it's on, else
     * any site it's on. Null if it's on no site the visitor can see.
     *
     * @param ItemRepresentation|ItemSetRepresentation $resource
     */
    public static function linkSite($resource, array $data, SiteRepresentation $currentSite): ?SiteRepresentation
    {
        $sites = $resource->sites();
        if (isset($sites[$currentSite->id()])) {
            return $currentSite;
        }
        foreach (self::siteIds($data, $currentSite) ?? [] as $id) {
            if (isset($sites[$id])) {
                return $sites[$id];
            }
        }
        return $sites ? reset($sites) : null;
    }

    /**
     * Public URL of an item or item set, on the site picked by linkSite().
     *
     * @param ItemRepresentation|ItemSetRepresentation $resource
     */
    public static function url($resource, array $data, SiteRepresentation $currentSite): ?string
    {
        $site = self::linkSite($resource, $data, $currentSite);
        return $site ? $resource->siteUrl($site->slug()) : null;
    }
}
