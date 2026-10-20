<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\View\Helper;

use Laminas\EventManager\Event;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\View\Helper\PageLayout as OmekaPageLayout;

/**
 * Renders pages saved by the Visual Page Builder.
 *
 * A section is stored as an Omeka block group whose layout data carries
 * "vpb_section" => ["cols" => [["w" => 6, "bg" => null], ...]]. Each block in
 * the group carries "vpb_col" (its column index) plus Omeka's own
 * grid_column_position and grid_column_span. This helper wraps each column's
 * blocks in a div so a column can hold a stack of blocks.
 *
 * Pages without the builder's marker go straight to Omeka's renderer, and if
 * this module is removed Omeka still renders every block in its section at the
 * right position and width, just row by row instead of stacked by column.
 */
class PageLayout extends OmekaPageLayout
{
    /** Anchors::plan() of the page being rendered. */
    protected $anchorPlan = ['named' => [], 'headings' => []];

    /** Next footnote number while rendering the page. */
    protected $nextNote = 1;

    /** One block, with its link name (and, for text, heading ids) added. */
    protected function renderBlock(SitePageBlockRepresentation $block): string
    {
        // Hidden on the public page (a required Theme Builder block the template hides).
        if ($block->layoutDataValue('vpb_hidden')) {
            return '';
        }
        $html = (string) $this->getView()->blockLayout()->render($block);
        // Hidden on some devices only (layout data "vpb_hide": desktop, tablet, phone).
        $html = \VisualPageBuilder\Devices::withClasses($html, \VisualPageBuilder\Devices::fromValue($block->layoutDataValue('vpb_hide', '')));
        if ($block->layout() === 'html' && strpos($html, 'vpb-footnote') !== false) {
            $html = \VisualPageBuilder\Footnotes::numberRefs($html, $this->nextNote);
        }
        if (isset($this->anchorPlan['headings'][$block->id()])) {
            $html = \VisualPageBuilder\Anchors::headingIds($html, $this->anchorPlan['headings'][$block->id()]);
        }
        if (isset($this->anchorPlan['named'][$block->id()])) {
            $html = \VisualPageBuilder\Anchors::withId($html, $this->anchorPlan['named'][$block->id()]['id']);
        }
        return $html;
    }

    public function render(SitePageRepresentation $page)
    {
        $this->anchorPlan = \VisualPageBuilder\Anchors::plan($page);
        $this->nextNote = 1;
        if ('grid' !== $page->layout() || !is_array($page->layoutDataValue('visual_page_builder'))) {
            return $this->addAnchors((string) parent::render($page), $page);
        }

        $view = $this->getView();
        $view->headLink()->appendStylesheet($view->assetUrl('css/page-grid.css', 'Omeka'), 'all');
        $view->headLink()->appendStylesheet($view->assetUrl('css/public.css', 'VisualPageBuilder'), 'all');

        $eventArgs = $this->eventManager->prepareArgs(['classes' => []]);
        $this->eventManager->triggerEvent(new Event('page_layout.classes', $page, $eventArgs));
        $classes = $eventArgs['classes'];
        $eventArgs = $this->eventManager->prepareArgs(['inline_styles' => []]);
        $this->eventManager->triggerEvent(new Event('page_layout.inline_styles', $page, $eventArgs));
        $inlineStyles = $eventArgs['inline_styles'];

        $gridColumns = (int) $page->layoutDataValue('grid_columns') ?: 12;
        $columnGap = (int) $page->layoutDataValue('grid_column_gap', 16);
        $rowGap = (int) $page->layoutDataValue('grid_row_gap', 16);
        $classes[] = 'page-layout-grid';
        $classes[] = sprintf('grid-template-columns-%s', $gridColumns);
        $classes[] = 'vpb-page';
        $inlineStyles[] = sprintf('column-gap: %spx;', $columnGap);
        $inlineStyles[] = sprintf('row-gap: %spx;', $rowGap);
        $inlineStyles[] = sprintf('--vpb-row-gap: %spx;', $rowGap);

        $output = [];
        $output[] = sprintf(
            '<div class="blocks-inner %s" style="%s">',
            $view->escapeHtml(implode(' ', $classes)),
            $view->escapeHtml(implode(' ', $inlineStyles))
        );

        $blocks = $page->blocks();
        $prepared = [];
        $count = count($blocks);
        $i = 0;
        while ($i < $count) {
            $block = $blocks[$i];
            $this->prepare($block, $prepared);
            if ('blockGroup' !== $block->layout()) {
                $output[] = $this->renderBlock($block);
                $i++;
                continue;
            }

            // Collect the group's children, stopping early at another group
            // (Omeka does not allow groups to overlap).
            $span = max(0, (int) $block->dataValue('span'));
            $children = [];
            for ($j = $i + 1; $j < $count && count($children) < $span; $j++) {
                if ('blockGroup' === $blocks[$j]->layout()) {
                    break;
                }
                $this->prepare($blocks[$j], $prepared);
                $children[] = $blocks[$j];
            }

            $section = $block->layoutDataValue('vpb_section');
            if (!$children && is_array($section)) {
                // An empty builder section (saved by older versions): skip it
                // rather than leave a gap.
                $i++;
                continue;
            }
            if (is_array($section) && isset($section['cols']) && is_array($section['cols']) && $section['cols']) {
                $output[] = $this->renderSection($block, $children, $section['cols'], $gridColumns);
            } else {
                $output[] = $this->renderPlainGroup($block, $children, $gridColumns);
            }
            $i += 1 + count($children);
        }

        // Footnotes go at the end unless the page has a Footnotes block.
        $notes = \VisualPageBuilder\Footnotes::plan($page);
        if ($notes && !\VisualPageBuilder\Footnotes::hasListBlock($page)) {
            $output[] = '<div class="vpb-footnotes-auto" style="grid-column: 1 / -1">'
                . \VisualPageBuilder\Footnotes::listHtml($notes, $view->translate('Notes'), [$view, 'escapeHtml'], [$view, 'translate']) . '</div>';
        }
        $output[] = '</div>';
        return implode('', $output);
    }

    /**
     * A page from Omeka's standard editor, rendered by Omeka: give its
     * headings and older link names their ids too, so a Table of contents
     * on it works. Each block's wrapper (class "block block-<layout>") is
     * found in page order; if they don't line up with the page's blocks,
     * the page is left exactly as Omeka rendered it.
     */
    protected function addAnchors(string $html, SitePageRepresentation $page): string
    {
        $plan = $this->anchorPlan;
        if (!$plan['named'] && !array_filter($plan['headings'], function ($hs) {
            return (bool) array_filter($hs, function ($h) {
                return empty($h['own']);
            });
        })) {
            return $html;
        }
        $blocks = array_values($page->blocks());
        if (!preg_match_all('/<div class="(?:[^"]*\s)?block block-([A-Za-z0-9_.:-]+)[\s"]/', $html, $m, PREG_OFFSET_CAPTURE)
            || count($m[0]) !== count($blocks)
        ) {
            return $html;
        }
        foreach ($blocks as $i => $block) {
            if ($m[1][$i][0] !== $block->layout()) {
                return $html;
            }
        }
        $out = substr($html, 0, $m[0][0][1]);
        foreach ($blocks as $i => $block) {
            $from = $m[0][$i][1];
            $to = isset($m[0][$i + 1]) ? $m[0][$i + 1][1] : strlen($html);
            $part = substr($html, $from, $to - $from);
            if (isset($plan['headings'][$block->id()])) {
                $part = \VisualPageBuilder\Anchors::headingIds($part, $plan['headings'][$block->id()]);
            }
            if (isset($plan['named'][$block->id()])) {
                $part = \VisualPageBuilder\Anchors::withId($part, $plan['named'][$block->id()]['id']);
            }
            $out .= $part;
        }
        return $out;
    }

    protected function prepare(SitePageBlockRepresentation $block, array &$prepared)
    {
        if (!array_key_exists($block->layout(), $prepared)) {
            $prepared[$block->layout()] = true;
            $this->getView()->blockLayout()->prepareRender($block->layout());
        }
    }

    protected function openGroup(SitePageBlockRepresentation $group, $gridColumns, array $extraClasses = [])
    {
        $view = $this->getView();
        $classes = array_merge($this->getBlockClasses($group), [
            'block-group-grid',
            'grid-position-1',
            sprintf('grid-span-%s', $gridColumns),
        ], $extraClasses);
        $anchor = $this->anchorPlan['named'][$group->id()]['id'] ?? null;
        return sprintf(
            '<div class="%s" style="%s"%s>',
            $view->escapeHtml(implode(' ', $classes)),
            $view->escapeHtml(implode('; ', $this->getBlockInlineStyles($group))),
            $anchor ? ' id="' . $view->escapeHtml($anchor) . '" data-vpb-anchor' : ''
        );
    }

    /**
     * A block group the builder did not create: render it the way Omeka does.
     */
    protected function renderPlainGroup(SitePageBlockRepresentation $group, array $children, $gridColumns)
    {
        $out = [$this->openGroup($group, $gridColumns)];
        foreach ($children as $child) {
            $out[] = $this->renderBlock($child);
        }
        $out[] = '</div>';
        return implode('', $out);
    }

    protected function renderSection(SitePageBlockRepresentation $group, array $children, array $cols, $gridColumns)
    {
        $view = $this->getView();

        // Sanitize the stored column list and work out where each column starts.
        $columns = [];
        $start = 1;
        foreach ($cols as $col) {
            if ($start > $gridColumns) {
                break;
            }
            $width = isset($col['w']) ? (int) $col['w'] : 0;
            $width = max(1, min($gridColumns - $start + 1, $width));
            $bg = isset($col['bg']) && is_string($col['bg']) && preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $col['bg'])
                ? $col['bg'] : null;
            $img = isset($col['img']) && is_array($col['img']) && isset($col['img']['asset']) && is_numeric($col['img']['asset']) ? $col['img'] : null;
            $columns[] = ['start' => $start, 'w' => $width, 'bg' => $bg, 'img' => $img, 'html' => []];
            $start += $width;
        }
        if (!$columns) {
            return $this->renderPlainGroup($group, $children, $gridColumns);
        }

        foreach ($children as $child) {
            $index = $this->columnIndexFor($child, $columns);
            $columns[$index]['html'][] = $this->renderBlock($child);
        }

        // How the columns arrange on tablets and phones (see public.css).
        $resp = (array) ($group->layoutDataValue('vpb_section')['resp'] ?? []);
        $tablet = in_array($resp['tablet'] ?? '', ['two', 'same'], true) ? $resp['tablet'] : 'stack';
        $phone = ($resp['phone'] ?? '') === 'two' ? 'two' : 'stack';
        $out = [$this->openGroup($group, $gridColumns, ['vpb-section', 'vpb-tablet-' . $tablet, 'vpb-phone-' . $phone])];
        foreach ($columns as $column) {
            $classes = [
                'vpb-column',
                sprintf('grid-position-%s', $column['start']),
                sprintf('grid-span-%s', $column['w']),
            ];
            $style = '';
            if ($column['bg']) {
                $classes[] = 'has-background';
                if (self::isDark($column['bg'])) {
                    $classes[] = 'vpb-on-dark'; // White text (public.css).
                }
                $style = sprintf('background-color: %s;', $column['bg']);
            }
            if ($column['img']) {
                // Background image (the same choices Omeka offers for sections and blocks).
                try {
                    $asset = $view->api()->read('assets', (int) $column['img']['asset'])->getContent();
                    $classes[] = 'has-background';
                    $style .= sprintf(
                        'background-image: url("%s"); background-size: %s; background-position: center %s; background-repeat: no-repeat;',
                        $view->escapeCss($asset->assetUrl()),
                        ($column['img']['size'] ?? '') === 'contain' ? 'contain' : 'cover',
                        in_array($column['img']['pos'] ?? '', ['top', 'bottom'], true) ? $column['img']['pos'] : 'center'
                    );
                    if (!empty($column['img']['overlay'])) {
                        $classes[] = 'vpb-overlay';
                        $classes[] = 'vpb-on-dark';
                    }
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    // A deleted asset: no image.
                }
            }
            $out[] = sprintf(
                '<div class="%s" style="%s">%s</div>',
                $view->escapeHtml(implode(' ', $classes)),
                $view->escapeHtml($style),
                implode('', $column['html'])
            );
        }
        $out[] = '</div>';
        return implode('', $out);
    }

    /**
     * Which column a block belongs to: its stored column index if valid,
     * otherwise the column its grid position falls in, otherwise the first.
     */
    protected function columnIndexFor(SitePageBlockRepresentation $block, array $columns)
    {
        $index = $block->layoutDataValue('vpb_col');
        if (is_numeric($index) && isset($columns[(int) $index])) {
            return (int) $index;
        }
        $position = $block->layoutDataValue('grid_column_position');
        if (is_numeric($position)) {
            foreach ($columns as $i => $column) {
                if ((int) $position >= $column['start'] && (int) $position < $column['start'] + $column['w']) {
                    return $i;
                }
            }
        }
        return 0;
    }

    /** Whether a background is dark enough to need white text (as VPB.isDark in the editor). */
    public static function isDark(string $hex): bool
    {
        $h = ltrim($hex, '#');
        if (strlen($h) === 3) {
            $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
        }
        if (!preg_match('/^[A-Fa-f0-9]{6}$/', $h)) {
            return false;
        }
        $c = array_map(function ($i) use ($h) {
            $v = hexdec(substr($h, $i, 2)) / 255;
            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, [0, 2, 4]);
        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2] < 0.18;
    }
}
