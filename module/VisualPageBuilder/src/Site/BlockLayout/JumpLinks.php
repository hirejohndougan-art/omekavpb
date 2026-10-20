<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Anchors;
use VisualPageBuilder\Schema\Schema;

/**
 * Table of contents ("On this page") that builds itself from the page's
 * headings, like a word processor: Heading 2 is a section, Heading 3 a
 * subsection, Heading 4 a sub-subsection. A heading can be left out or given
 * a shorter name (text block layout data vpb_toc; see Anchors). Table link
 * blocks add spots that have no heading. Older Link names (before v1.14.0)
 * are still listed so pages that used them keep working.
 *
 * The layout name stays specialJumpLinks (it's stored in pages).
 */
class JumpLinks extends AbstractSchemaBlock
{
    const TEMPLATE = 'jump-links';

    const DESCRIPTION = 'An "On this page" list that builds itself from your headings: Heading 2 is a section, Heading 3 a subsection, Heading 4 a sub-subsection. Visitors click to jump down the page.'; // @translate

    const GOOD_FOR = 'Long history pages, guides, and FAQ pages.'; // @translate

    const PLACEMENT = 'page';

    /** "Headings to include" => the deepest level listed (0: no headings, only Table links). */
    const DEPTH = ['h2' => 1, 'h3' => 2, 'h4' => 3, 'none' => 0];

    public function getLabel()
    {
        return 'Table of contents (this page)'; // @translate
    }

    public function schema(): array
    {
        return [
            ['key' => 'heading', 'type' => Schema::TYPE_TEXT, 'default' => 'On this page', 'label' => 'Title'], // @translate
            [
                'key' => 'levels', 'type' => Schema::TYPE_SELECT, 'group' => 'Contents', 'default' => 'h3', 'label' => 'Headings to include', // @translate
                'options' => [
                    'h2' => 'Heading 2 only (sections)', // @translate
                    'h3' => 'Down to Heading 3 (subsections)', // @translate
                    'h4' => 'Down to Heading 4 (sub-subsections)', // @translate
                    'none' => 'No headings, only Table links', // @translate
                ],
                'info' => 'For a spot with no heading (a map, a gallery), drag in a Table link.', // @translate
            ],
            ['key' => 'style', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'list', 'label' => 'Style', 'options' => ['list' => 'A list', 'row' => 'A row of links', 'box' => 'A boxed list']], // @translate
            ['key' => 'numbered', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Display', 'default' => false, 'label' => 'Number the entries (1, 1.1, 2…)'], // @translate
        ];
    }

    /**
     * Settings saved before v1.14.0 ("List": named spots / headings, and
     * "Main headings and subheadings") in today's terms.
     */
    public static function upgradeData(array $raw): array
    {
        if (isset($raw['source']) && is_string($raw['source'])) {
            if ($raw['source'] === 'named') {
                $raw['levels'] = 'none';
            } else {
                $raw['levels'] = ($raw['levels'] ?? '') === 'h2h3' ? 'h3' : 'h2';
            }
        } elseif (($raw['levels'] ?? '') === 'h2h3') {
            $raw['levels'] = 'h3';
        }
        unset($raw['source']);
        return $raw;
    }

    protected function data(?SitePageBlockRepresentation $block): array
    {
        $saved = $block ? $block->data() : [];
        return $this->normalizer->normalize($this->fields(), self::upgradeData(is_array($saved) ? $saved : []));
    }

    /**
     * The entries for a page, in reading order:
     * [['id', 'label', 'level' (1-3 as listed, never skipping one), 'num' ("1.2")], …].
     */
    public static function entries(array $plan, string $levels): array
    {
        $depth = self::DEPTH[$levels] ?? 2;
        $raw = [];
        foreach ($plan['order'] as $blockId) {
            if (isset($plan['named'][$blockId])) {
                $raw[] = ['id' => $plan['named'][$blockId]['id'], 'label' => $plan['named'][$blockId]['label'], 'level' => 1];
            }
            if (isset($plan['links'][$blockId])) {
                $link = $plan['links'][$blockId];
                if ($link['label'] !== '' && ($depth === 0 || $link['level'] <= $depth)) {
                    $raw[] = ['id' => $link['id'], 'label' => $link['label'], 'level' => $link['level']];
                }
            }
            foreach ($plan['headings'][$blockId] ?? [] as $h) {
                $level = $h['level'] - 1;
                if ($h['hide'] || $level > $depth || ($h['name'] === '' && $h['text'] === '')) {
                    continue;
                }
                $raw[] = ['id' => $h['id'], 'label' => $h['name'] !== '' ? $h['name'] : $h['text'], 'level' => $level];
            }
        }
        // Nest without skipping a level (a Heading 4 right after a Heading 2
        // sits one step in), and number like 1, 1.1, 1.2, 2.
        $out = [];
        $prev = 0;
        $counters = [0, 0, 0, 0];
        foreach ($raw as $e) {
            $level = min($e['level'], $prev + 1);
            $counters[$level]++;
            for ($k = $level + 1; $k <= 3; $k++) {
                $counters[$k] = 0;
            }
            $e['level'] = $level;
            $e['num'] = implode('.', array_slice($counters, 1, $level));
            $out[] = $e;
            $prev = $level;
        }
        return $out;
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $page = $this->renderingBlock ? $this->renderingBlock->page() : null;
        if (!$page) {
            return ['entries' => [], 'isPreview' => true];
        }
        return ['entries' => self::entries(Anchors::plan($page), (string) $data['levels']), 'isPreview' => false];
    }
}
