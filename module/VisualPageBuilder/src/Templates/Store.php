<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Templates;

use Doctrine\DBAL\Connection;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Controller\Admin\PagesController;
use VisualPageBuilder\SiteRegions;

/**
 * Theme Builder templates, stored as hidden site pages with the slug prefix
 * "vpb-template-" (the same way shared sections and the header and footer are
 * stored). A template's settings live in its layout data, under "vpb_template":
 *   screen, scope (a Resolver level), value (a record id, or null for "all"),
 *   status ("live" or "draft"), order (the list position).
 *
 * Public screens don't use these yet: the resolver is wired into the theme in
 * Phase 2. Until then templates can be built, duplicated and checked here.
 */
class Store
{
    const KEY = 'vpb_template';

    /** Every template of a site, in the order the Theme Builder lists them. */
    public static function all(SiteRepresentation $site): array
    {
        $out = [];
        foreach ($site->pages() as $page) {
            if (!self::isTemplate($page)) {
                continue;
            }
            $out[] = self::describe($page);
        }
        usort($out, function ($a, $b) {
            return [$a['screenOrder'], $a['order'], $a['id']] <=> [$b['screenOrder'], $b['order'], $b['id']];
        });
        return $out;
    }

    /** The live templates of a site, in the shape Resolver::choose() takes. */
    public static function live(SiteRepresentation $site): array
    {
        return array_values(array_filter(self::all($site), function ($t) {
            return $t['status'] === 'live';
        }));
    }

    public static function isTemplate(SitePageRepresentation $page): bool
    {
        return strpos((string) $page->slug(), SiteRegions::TEMPLATE_PREFIX) === 0;
    }

    /** A template as a plain array, with its screen label and state. */
    public static function describe(SitePageRepresentation $page): array
    {
        $meta = self::meta($page);
        $screen = $meta['screen'] ?? '';
        return [
            'id' => $page->id(),
            'page' => $page,
            'name' => $page->title(),
            'screen' => $screen,
            'screenLabel' => Screens::label($screen),
            'screenOrder' => array_search($screen, Screens::keys(), true) ?: 0,
            'scope' => $meta['scope'] ?? 'all',
            'value' => $meta['value'] ?? null,
            'status' => ($meta['status'] ?? 'draft') === 'live' ? 'live' : 'draft',
            'order' => (int) ($meta['order'] ?? 0),
            'missing' => Required::missing($screen, self::blockTypes($page)),
        ];
    }

    /** Make a new template (a draft, private to the site's visitors until it is live). */
    public static function create(ApiManager $api, SiteRepresentation $site, string $screen, string $scope, $value, string $name): SitePageRepresentation
    {
        self::assertScreen($screen);
        self::assertScope($scope, $value);
        $count = count(self::all($site));
        // The starter blocks go in with the page. Omeka adds its page-title block (which
        // shows the page's name, here the template's internal name) to any new page
        // created without blocks, so an empty create would bring that block in.
        $blocks = Screens::starterBlocks($screen, $site->slug());
        $page = $api->create('site_pages', [
            'o:site' => ['o:id' => $site->id()],
            'o:title' => mb_substr($name !== '' ? $name : Screens::label($screen), 0, 190),
            'o:slug' => SiteRegions::TEMPLATE_PREFIX . bin2hex(random_bytes(4)),
            'o:is_public' => true,
            'o:layout' => 'grid',
            'o:layout_data' => [
                'grid_columns' => 12,
                'visual_page_builder' => ['version' => 1],
                self::KEY => ['screen' => $screen, 'scope' => $scope, 'value' => $value, 'status' => 'draft', 'order' => $count + 1],
            ],
            'o:block' => $blocks,
        ])->getContent();
        if ($blocks) {
            $page = $api->read('site_pages', $page->id())->getContent();
        }
        return $page;
    }

    /**
     * Copy a template (its blocks and settings). The copy is a draft and goes
     * to the end of the list.
     */
    public static function duplicate(ApiManager $api, SiteRepresentation $site, SitePageRepresentation $source): SitePageRepresentation
    {
        $meta = self::meta($source);
        $meta['status'] = 'draft';
        $meta['order'] = count(self::all($site)) + 1;
        $data = $source->layoutData();
        $data[self::KEY] = $meta;
        return $api->create('site_pages', [
            'o:site' => ['o:id' => $site->id()],
            'o:title' => sprintf('Copy of %s', $source->title()),
            'o:slug' => SiteRegions::TEMPLATE_PREFIX . bin2hex(random_bytes(4)),
            'o:is_public' => true,
            'o:layout' => $source->layout() ?: 'grid',
            'o:layout_data' => $data,
            'o:block' => PagesController::copyBlocks($source),
        ])->getContent();
    }

    /**
     * Set a template live or draft. Going live needs its required blocks;
     * returns the missing ones (empty when the change was made).
     */
    /** Remove a template page. Its blocks go with it; the screen falls back to the theme's own layout. */
    public static function delete(ApiManager $api, SitePageRepresentation $page): void
    {
        $api->delete('site_pages', $page->id());
    }

    public static function setStatus(ApiManager $api, SitePageRepresentation $page, string $status): array
    {
        $meta = self::meta($page);
        $screen = $meta['screen'] ?? '';
        if ($status === 'live') {
            $missing = Required::missing($screen, self::blockTypes($page));
            if ($missing) {
                return $missing;
            }
        }
        $meta['status'] = $status === 'live' ? 'live' : 'draft';
        self::saveMeta($api, $page, $meta);
        return [];
    }

    /**
     * Puts back the required blocks a template is missing, at the end of its
     * layout (the person moves them where they want). Returns the layouts added.
     *
     * Omeka numbers the blocks of a partial update from 1, so the existing
     * blocks keep their positions and the new ones tie with them. The positions
     * are set afterwards: existing blocks in their order, then the new ones.
     */
    public static function restoreRequired(ApiManager $api, Connection $conn, SitePageRepresentation $page): array
    {
        $meta = self::meta($page);
        $missing = Required::missing((string) ($meta['screen'] ?? ''), self::blockTypes($page));
        if (!$missing) {
            return [];
        }
        $pageId = (int) $page->id();
        $existing = array_map(fn ($block) => (int) $block->id(), array_values($page->blocks()));
        $slug = $page->site()->slug();
        $api->update('site_pages', $pageId, ['o:block' => array_map(function ($layout) use ($slug) {
            return ['o:layout' => $layout, 'o:data' => Screens::starterData($layout, $slug), 'o:attachment' => []];
        }, $missing)], [], ['isPartial' => true]);

        $all = array_map('intval', $conn->fetchFirstColumn('SELECT id FROM site_page_block WHERE page_id = ?', [$pageId]));
        $added = array_values(array_diff($all, $existing));
        sort($added);
        foreach (array_merge($existing, $added) as $position => $blockId) {
            $conn->executeStatement('UPDATE site_page_block SET position = ? WHERE id = ?', [$position + 1, $blockId]);
        }
        return $missing;
    }

    /** Position in the list (a Theme Builder reorder); other templates keep their order. */
    public static function setOrder(ApiManager $api, SitePageRepresentation $page, int $order): void
    {
        $meta = self::meta($page);
        $meta['order'] = max(1, $order);
        self::saveMeta($api, $page, $meta);
    }

    /** The template's settings from its layout data (empty for other pages). */
    public static function meta(SitePageRepresentation $page): array
    {
        $data = $page->layoutData();
        return is_array($data[self::KEY] ?? null) ? $data[self::KEY] : [];
    }

    /** Block types of a page, as the layout names of its blocks. */
    public static function blockTypes(SitePageRepresentation $page): array
    {
        $types = [];
        foreach ($page->blocks() as $block) {
            $types[] = $block->layout();
        }
        return $types;
    }

    public static function assertScreen(string $screen): void
    {
        if (!Screens::exists($screen)) {
            throw new \InvalidArgumentException('Unknown screen: ' . $screen);
        }
    }

    public static function assertScope(string $scope, $value): void
    {
        if (!in_array($scope, Resolver::LEVELS, true)) {
            throw new \InvalidArgumentException('Unknown scope: ' . $scope);
        }
        if ($scope !== 'all' && ($value === null || $value === '' || (int) $value <= 0)) {
            throw new \InvalidArgumentException('This template needs a choice for "' . $scope . '".');
        }
    }

    protected static function saveMeta(ApiManager $api, SitePageRepresentation $page, array $meta): void
    {
        $data = $page->layoutData();
        $data[self::KEY] = $meta;
        $api->update('site_pages', $page->id(), ['o:layout_data' => $data], [], ['isPartial' => true]);
    }
}
