<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use DateTime;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;

/**
 * One short fact from a list you write: a different one each day (the same
 * for every visitor that day), or a random one on each visit.
 */
class DidYouKnow extends AbstractSchemaBlock
{
    const TEMPLATE = 'did-you-know';

    const DESCRIPTION = 'Shows one short fact from a list you write, a different one each day.'; // @translate

    const GOOD_FOR = 'Home pages and sidebars.'; // @translate

    public function getLabel()
    {
        return 'Did you know?'; // @translate
    }

    public function schema(): array
    {
        return [
            DisplayFields::heading('Did you know?'),
            [
                'key' => 'facts', 'type' => Schema::TYPE_LIST, 'group' => 'Facts', 'summary' => true, 'max' => 365,
                'label' => 'Facts', // @translate
                'fields' => [
                    ['key' => 'text', 'type' => Schema::TYPE_TEXTAREA, 'label' => 'Fact'], // @translate
                    ['key' => 'link_text', 'type' => Schema::TYPE_TEXT, 'label' => 'Link text (optional)', 'placeholder' => 'e.g. See the photo'], // @translate
                    ['key' => 'link_url', 'type' => Schema::TYPE_URL, 'label' => 'Link to', 'placeholder' => 'https://… or /s/…'], // @translate
                ],
            ],
            ['key' => 'mode', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'daily', 'label' => 'Which fact', 'options' => ['daily' => 'A new one each day', 'random' => 'A random one on each visit']], // @translate
            ['key' => 'style', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'card', 'label' => 'Style', 'options' => ['card' => 'Card', 'plain' => 'Plain']], // @translate
        ];
    }

    /** The fact to show: by day (stable for everyone that day) or random. */
    public static function pick(array $facts, string $mode, string $key, ?DateTime $day = null): ?array
    {
        $facts = array_values(array_filter($facts, function ($f) {
            return trim($f['text'] ?? '') !== '';
        }));
        if (!$facts) {
            return null;
        }
        if ($mode === 'random') {
            return $facts[array_rand($facts)];
        }
        $day = $day ?: new DateTime('today');
        return $facts[crc32($day->format('Y-m-d') . '#' . $key) % count($facts)];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        return ['fact' => self::pick($data['facts'], $data['mode'], $blockKey, $this->today())];
    }
}
