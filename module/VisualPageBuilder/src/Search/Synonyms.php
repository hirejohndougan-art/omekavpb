<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Search;

/**
 * Local word list for VPB Search (plan section 2): "rr" also searches "railroad".
 *
 * Settings stores one line per rule as "term = alternative, alternative".
 * Matching is one way: a query word expands to its alternatives. Pure.
 */
class Synonyms
{
    /** @var array<string,string[]> term => alternatives */
    private array $map = [];

    /** Parse the Settings text. Blank lines and lines without "=" are ignored. */
    public static function fromText(string $text): self
    {
        $s = new self();
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            if (strpos($line, '=') === false) {
                continue;
            }
            [$left, $right] = explode('=', $line, 2);
            $from = mb_strtolower(trim($left));
            $to = array_filter(array_map(fn ($w) => mb_strtolower(trim($w)), explode(',', $right)));
            if ($from !== '' && $to) {
                $s->map[$from] = array_values(array_unique(array_merge($s->map[$from] ?? [], $to)));
            }
        }
        return $s;
    }

    /** The word plus its alternatives, all lower case, without repeats. */
    public function expand(string $word): array
    {
        $w = mb_strtolower($word);
        return array_values(array_unique(array_merge([$w], $this->map[$w] ?? [])));
    }
}
