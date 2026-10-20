<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder;

use Omeka\Api\Representation\SitePageRepresentation;

/**
 * Footnotes typed in text blocks. In the editor a footnote is the note's own
 * text inside <sup class="vpb-footnote">…</sup> where the number should go
 * (formatting bar › Footnote). On the page each becomes a numbered link, and
 * the notes are listed in order: in a Footnotes block if the page has one,
 * otherwise at the end of the page.
 */
class Footnotes
{
    const PATTERN = '/<sup\s+class="vpb-footnote"\s*>(.*?)<\/sup>/is';

    protected static $plans = [];

    /** @return array list of ['n' => int, 'html' => string, 'blockId' => int] in page order */
    public static function plan(SitePageRepresentation $page): array
    {
        if (isset(self::$plans[$page->id()])) {
            return self::$plans[$page->id()];
        }
        $notes = [];
        foreach ($page->blocks() as $block) {
            if ($block->layout() !== 'html' || !preg_match_all(self::PATTERN, (string) $block->dataValue('html'), $m)) {
                continue;
            }
            foreach ($m[1] as $html) {
                $notes[] = ['n' => count($notes) + 1, 'html' => trim($html), 'blockId' => $block->id()];
            }
        }
        return self::$plans[$page->id()] = $notes;
    }

    /** Whether the page has a Footnotes block (otherwise notes go at the end). */
    public static function hasListBlock(SitePageRepresentation $page): bool
    {
        foreach ($page->blocks() as $block) {
            if ($block->layout() === 'specialFootnotes') {
                return true;
            }
        }
        return false;
    }

    /** Replace a text block's footnotes with numbered links, continuing from $next. */
    public static function numberRefs(string $html, int &$next): string
    {
        return preg_replace_callback(self::PATTERN, function () use (&$next) {
            $n = $next++;
            return sprintf('<sup class="vpb-fn-ref" id="vpb-fnref-%1$d"><a href="#vpb-fn-%1$d" aria-label="Note %1$d">%1$d</a></sup>', $n);
        }, $html) ?? $html;
    }

    /** The numbered list of notes, with links back to where each was used. */
    public static function listHtml(array $notes, string $heading, callable $escape, callable $translate): string
    {
        if (!$notes) {
            return '';
        }
        $out = '<section class="vpb-footnotes" aria-labelledby="vpb-fn-heading">';
        if ($heading !== '') {
            $out .= '<h2 id="vpb-fn-heading" class="special-blocks-heading">' . $escape($heading) . '</h2>';
        }
        $out .= '<ol>';
        foreach ($notes as $note) {
            $out .= sprintf(
                '<li id="vpb-fn-%1$d">%2$s <a class="vpb-fn-back" href="#vpb-fnref-%1$d" aria-label="%3$s">&#8617;</a></li>',
                $note['n'],
                $note['html'], // purified with the text block it came from
                $escape(sprintf($translate('Back to note %s in the text'), $note['n']))
            );
        }
        return $out . '</ol></section>';
    }

    public static function reset(): void
    {
        self::$plans = [];
    }
}
