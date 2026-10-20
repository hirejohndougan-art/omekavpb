<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder;

use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SiteRepresentation;

/**
 * A site's header and footer, built in the visual editor.
 *
 * Each is stored as a site page with a reserved slug, so it gets everything
 * pages have (the editor, blocks, undo, permissions) for free. These pages:
 * - are public (visitors' browsers need to see their blocks) but can't be
 *   opened at their own address (Module::hideRegionPages() answers 404),
 * - are left out of page lists in the editor and menu builder,
 * - are shown by the Companion Theme in place of its built-in header and
 *   footer (view helper vpbSiteRegion). Other themes ignore them.
 */
class SiteRegions
{
    const REGIONS = [
        'header' => ['slug' => 'vpb-site-header', 'title' => 'Site header'],
        'footer' => ['slug' => 'vpb-site-footer', 'title' => 'Site footer'],
    ];

    /** Slug prefix of the private copies used by "Preview on site". */
    const PREVIEW_PREFIX = 'vpb-preview-';

    /** Slug prefix of shared sections (reusable sections shown on many pages). */
    const SHARED_PREFIX = 'vpb-shared-';

    /** Theme Builder templates (hidden pages, src/Templates/Store.php). */
    const TEMPLATE_PREFIX = 'vpb-template-';

    public static function isSharedSlug(?string $slug): bool
    {
        return strpos((string) $slug, self::SHARED_PREFIX) === 0;
    }

    /**
     * Pages that aren't ordinary pages: the header, the footer, and
     * "Preview on site" copies. Left out of page lists.
     */
    public static function isHiddenSlug(?string $slug): bool
    {
        return self::isRegionSlug($slug) || self::isSharedSlug($slug) || strpos((string) $slug, self::TEMPLATE_PREFIX) === 0 || strpos((string) $slug, self::PREVIEW_PREFIX) === 0;
    }

    public static function isRegionSlug(?string $slug): bool
    {
        return in_array($slug, array_column(self::REGIONS, 'slug'), true);
    }

    /** 'header', 'footer', or null for an ordinary page. */
    public static function regionOf(SitePageRepresentation $page): ?string
    {
        foreach (self::REGIONS as $region => $info) {
            if ($page->slug() === $info['slug']) {
                return $region;
            }
        }
        return null;
    }

    /** The site's header or footer page, or null if it hasn't been made. */
    public static function find(SiteRepresentation $site, string $region): ?SitePageRepresentation
    {
        $slug = self::REGIONS[$region]['slug'] ?? null;
        foreach ($site->pages() as $page) {
            if ($page->slug() === $slug) {
                return $page;
            }
        }
        return null;
    }

    /** The header or footer page, made (empty) if it doesn't exist yet. */
    public static function ensure(ApiManager $api, SiteRepresentation $site, string $region): SitePageRepresentation
    {
        $page = self::find($site, $region);
        if ($page) {
            return $page;
        }
        return $api->create('site_pages', [
            'o:site' => ['o:id' => $site->id()],
            'o:title' => self::REGIONS[$region]['title'],
            'o:slug' => self::REGIONS[$region]['slug'],
            'o:is_public' => true,
            'o:block' => [],
        ])->getContent();
    }
}
