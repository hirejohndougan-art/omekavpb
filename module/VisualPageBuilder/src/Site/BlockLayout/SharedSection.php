<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\SiteRegions;

/**
 * Shared section: shows a reusable section (built once in the editor, kept
 * as a hidden page with the slug vpb-shared-…) on this page. Change the
 * shared section once and every page using it shows the change.
 */
class SharedSection extends AbstractSchemaBlock
{
    const TEMPLATE = 'shared-section';

    const DESCRIPTION = 'Shows one of your shared sections, built once and used on many pages. Edit it once (Visual Builder › Shared sections) and every page updates.'; // @translate

    const GOOD_FOR = 'A "Visit us" strip, a donate band, or anything repeated across pages.'; // @translate

    const PLACEMENT = 'both';

    /** Shared sections being drawn right now (stops one from showing itself). */
    protected static $drawing = [];

    public function getLabel()
    {
        return 'Shared section'; // @translate
    }

    public function schema(): array
    {
        return [
            // Chosen from a list in the editor (blocks/shared-section.js); a number in the standard editor.
            ['key' => 'section', 'type' => Schema::TYPE_NUMBER, 'default' => 0, 'min' => 0, 'max' => PHP_INT_MAX, 'label' => 'Shared section (its number)', 'editor_hidden' => true], // @translate
        ];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $id = (int) $data['section'];
        $html = '';
        $missing = false;
        if ($id && !isset(self::$drawing[$id])) {
            try {
                $page = $view->api()->read('site_pages', $id)->getContent();
                if ($page->site()->id() === $site->id() && SiteRegions::isSharedSlug($page->slug())) {
                    self::$drawing[$id] = true;
                    $html = $view->pageLayout()->render($page);
                    unset(self::$drawing[$id]);
                } else {
                    $missing = true;
                }
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                $missing = true;
            }
        }
        return ['html' => $html, 'missing' => $missing || !$id, 'isPreview' => $blockKey === 'preview'];
    }
}
