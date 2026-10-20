<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder;

use Omeka\Api\Representation\SitePageRepresentation;

/**
 * Spots on a page that visitors can jump to (/s/site/page/about#visit-us),
 * and what the Table of contents block lists.
 *
 * Three kinds, in this order of priority for ids (ids never repeat; later
 * ones get -2, -3… when needed):
 * - Named spots (before v1.14.0): a section or block given a "Link name"
 *   (layout data vpb_anchor, label vpb_anchor_label). Kept so old links work.
 * - Table links: the Table link block (specialTableLink), a named, invisible
 *   spot for places with no heading.
 * - Headings: every Heading 2, 3, and 4 typed in a text block gets an id
 *   from its words. The text block's layout data vpb_toc can leave one out of
 *   the table of contents or give it another name there:
 *   [{"i": heading index in the block, "hide": true, "name": "…"}, …].
 *
 * plan() works out every id for a page once, so the page (PageLayout) and the
 * Table of contents block agree.
 */
class Anchors
{
    /** Per page id: the plan (one request renders a page once). */
    protected static $plans = [];

    /** "Visit Us!" → "visit-us". Empty when nothing usable is left. */
    public static function slug(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
        if (function_exists('transliterator_transliterate')) {
            $t = transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
            if (is_string($t)) {
                $text = $t;
            }
        }
        $text = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $text));
        $text = trim(substr($text, 0, 60), '-');
        // An id should start with a letter.
        return $text === '' ? '' : (ctype_digit($text[0]) ? 'n-' . $text : $text);
    }

    /**
     * @return array{named: array, links: array, headings: array, order: array}
     *   named: block id => ['id', 'label', 'blockId'];
     *   links: block id => ['id', 'label', 'level' (1-3)];
     *   headings: block id => list of ['index', 'id', 'text', 'level' (2-4), 'hide', 'name'] in order;
     *   order: the page's blocks in reading order (see readingOrder())
     */
    public static function plan(SitePageRepresentation $page): array
    {
        if (isset(self::$plans[$page->id()])) {
            return self::$plans[$page->id()];
        }
        $blocks = self::readingOrder($page);
        $used = [];
        $unique = function (string $base) use (&$used): string {
            $id = $base;
            for ($n = 2; isset($used[$id]); $n++) {
                $id = $base . '-' . $n;
            }
            $used[$id] = true;
            return $id;
        };
        $named = [];
        foreach ($blocks as $block) {
            $raw = $block->layoutDataValue('vpb_anchor');
            $id = is_string($raw) ? self::slug($raw) : '';
            if ($id === '' || isset($used[$id])) {
                continue;
            }
            $used[$id] = true;
            $label = $block->layoutDataValue('vpb_anchor_label');
            $named[$block->id()] = [
                'id' => $id,
                'label' => is_string($label) && trim($label) !== '' ? trim($label) : ucfirst(str_replace('-', ' ', $id)),
                'blockId' => $block->id(),
            ];
        }
        // Headings that already have their own id keep it; nothing else may take it.
        foreach ($blocks as $block) {
            if ($block->layout() === 'html' && preg_match_all('/<h[2-4]\s[^>]*\bid\s*=\s*["\']?([^"\'\s>]+)/i', (string) $block->dataValue('html'), $m)) {
                foreach ($m[1] as $own) {
                    $used[$own] = true;
                }
            }
        }
        $links = [];
        foreach ($blocks as $block) {
            if ($block->layout() !== 'specialTableLink') {
                continue;
            }
            $label = trim((string) $block->dataValue('name'));
            $base = self::slug($label);
            $links[$block->id()] = [
                'id' => $unique($base === '' ? 'section' : $base),
                'label' => $label,
                'level' => self::level($block->dataValue('level')),
            ];
        }
        $headings = [];
        foreach ($blocks as $block) {
            if ($block->layout() !== 'html') {
                continue;
            }
            $settings = self::tocSettings($block->layoutDataValue('vpb_toc'));
            foreach (self::headingsIn((string) $block->dataValue('html')) as $h) {
                if ($h['text'] === '' && $h['own'] === '') {
                    continue;
                }
                $set = $settings[$h['index']] ?? [];
                $headings[$block->id()][] = [
                    'index' => $h['index'],
                    'id' => $h['own'] !== '' ? $h['own'] : $unique(self::slug($h['text']) ?: 'section'),
                    'own' => $h['own'] !== '',
                    'text' => $h['text'],
                    'level' => $h['level'],
                    'hide' => !empty($set['hide']),
                    'name' => $set['name'] ?? '',
                ];
            }
        }
        $order = array_map(function ($b) {
            return $b->id();
        }, $blocks);
        return self::$plans[$page->id()] = ['named' => $named, 'links' => $links, 'headings' => $headings, 'order' => $order];
    }

    /** A Table link's level: 1 (section), 2 (subsection), or 3 (sub-subsection). */
    public static function level($value): int
    {
        return max(1, min(3, (int) $value ?: 1));
    }

    /**
     * Every Heading 2, 3, and 4 in some HTML, in order:
     * [['index', 'level', 'text', 'own' (its own id, or '')], …]. The index
     * counts every one of them, so it matches headingIds() and the editor.
     */
    public static function headingsIn(string $html): array
    {
        if (!preg_match_all('/<h([234])((?:\s[^>]*)?)>/i', $html, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }
        $out = [];
        foreach ($m as $i => $h) {
            $level = (int) $h[1][0];
            $from = $h[0][1] + strlen($h[0][0]);
            $close = stripos($html, '</h' . $level, $from);
            $inner = substr($html, $from, $close === false ? 0 : $close - $from);
            $own = preg_match('/\sid\s*=\s*["\']?([^"\'\s>]+)/i', $h[2][0], $idm) ? $idm[1] : '';
            $out[] = [
                'index' => $i,
                'level' => $level,
                'text' => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($inner), ENT_QUOTES, 'UTF-8'))),
                'own' => $own,
            ];
        }
        return $out;
    }

    /** A text block's vpb_toc layout data, cleaned: heading index => ['hide' => bool, 'name' => string]. */
    public static function tocSettings($raw): array
    {
        $out = [];
        foreach (is_array($raw) ? $raw : [] as $row) {
            if (!is_array($row) || !isset($row['i']) || !is_numeric($row['i'])) {
                continue;
            }
            $name = isset($row['name']) && is_string($row['name']) ? trim(mb_substr($row['name'], 0, 200)) : '';
            $out[(int) $row['i']] = ['hide' => !empty($row['hide']), 'name' => $name];
        }
        return $out;
    }

    /**
     * The page's blocks in the order visitors read them: section by section,
     * and within a section column by column (the editor stores them row by
     * row so the page still reads sensibly without the module).
     *
     * @return \Omeka\Api\Representation\SitePageBlockRepresentation[]
     */
    public static function readingOrder(SitePageRepresentation $page): array
    {
        $blocks = array_values($page->blocks());
        $out = [];
        $count = count($blocks);
        for ($i = 0; $i < $count;) {
            $block = $blocks[$i];
            $out[] = $block;
            $i++;
            if ($block->layout() !== 'blockGroup') {
                continue;
            }
            $span = max(0, (int) $block->dataValue('span'));
            $kids = [];
            while ($i < $count && count($kids) < $span && $blocks[$i]->layout() !== 'blockGroup') {
                $kids[] = $blocks[$i++];
            }
            if (is_array($block->layoutDataValue('vpb_section'))) {
                $keyed = [];
                foreach ($kids as $n => $kid) {
                    $col = $kid->layoutDataValue('vpb_col');
                    $keyed[] = [is_numeric($col) ? (int) $col : 0, $n, $kid];
                }
                usort($keyed, function ($a, $b) {
                    return [$a[0], $a[1]] <=> [$b[0], $b[1]];
                });
                $kids = array_column($keyed, 2);
            }
            foreach ($kids as $kid) {
                $out[] = $kid;
            }
        }
        return $out;
    }

    /** Add id="…" (and a marker for the jump offset) to the first tag of $html. */
    public static function withId(string $html, string $id): string
    {
        return preg_replace('/^(\s*<[a-z][a-z0-9]*)(?![^>]*\sid=)/i', '$1 id="' . htmlspecialchars($id, ENT_QUOTES) . '" data-vpb-anchor', $html, 1) ?? $html;
    }

    /** Give the Heading 2–4s in a text block's rendered HTML their planned ids (matched by index). */
    public static function headingIds(string $html, array $planned): string
    {
        if (!$planned) {
            return $html;
        }
        $byIndex = [];
        foreach ($planned as $p) {
            if (empty($p['own'])) {
                $byIndex[$p['index']] = $p['id'];
            }
        }
        $i = 0;
        return preg_replace_callback('/<h([234])((?:\s[^>]*)?)>/i', function ($m) use (&$i, $byIndex) {
            $at = $i++;
            if (!isset($byIndex[$at]) || preg_match('/\sid\s*=/i', $m[2])) {
                return $m[0];
            }
            return '<h' . $m[1] . $m[2] . ' id="' . htmlspecialchars($byIndex[$at], ENT_QUOTES) . '" data-vpb-anchor>';
        }, $html) ?? $html;
    }

    /** For tests: forget cached plans. */
    public static function reset(): void
    {
        self::$plans = [];
    }
}
