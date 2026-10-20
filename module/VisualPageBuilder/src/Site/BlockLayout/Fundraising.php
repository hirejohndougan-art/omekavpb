<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;

/**
 * A fundraising goal with a progress bar you update by hand.
 */
class Fundraising extends AbstractSchemaBlock
{
    const TEMPLATE = 'fundraising';

    const DESCRIPTION = 'A fundraising goal with a progress bar, like "$12,400 of $20,000 for the depot roof". You update the amount raised.'; // @translate

    const GOOD_FOR = 'Campaigns and capital projects.'; // @translate

    public function getLabel()
    {
        return 'Fundraising progress'; // @translate
    }

    public function schema(): array
    {
        return [
            ['key' => 'title', 'type' => Schema::TYPE_TEXT, 'group' => 'Campaign', 'summary' => true, 'label' => 'Campaign name', 'placeholder' => 'e.g. Save the depot roof'], // @translate
            ['key' => 'goal', 'type' => Schema::TYPE_NUMBER, 'group' => 'Campaign', 'summary' => true, 'default' => 0, 'min' => 0, 'max' => 1000000000, 'label' => 'Goal'], // @translate
            ['key' => 'raised', 'type' => Schema::TYPE_NUMBER, 'group' => 'Campaign', 'summary' => true, 'default' => 0, 'min' => 0, 'max' => 1000000000, 'label' => 'Raised so far'], // @translate
            ['key' => 'currency', 'type' => Schema::TYPE_TEXT, 'group' => 'Campaign', 'default' => '$', 'label' => 'Currency sign'], // @translate
            ['key' => 'deadline', 'type' => Schema::TYPE_DATE, 'group' => 'Campaign', 'label' => 'Ends on (optional)'], // @translate
            ['key' => 'text', 'type' => Schema::TYPE_TEXTAREA, 'group' => 'Campaign', 'label' => 'Why it matters (optional)'], // @translate
            ['key' => 'button_text', 'type' => Schema::TYPE_TEXT, 'group' => 'Button', 'default' => 'Donate', 'label' => 'Button text', 'placeholder' => 'Leave empty for no button'], // @translate
            ['key' => 'button_url', 'type' => Schema::TYPE_URL, 'group' => 'Button', 'label' => 'Button link', 'placeholder' => 'https://…'], // @translate
        ];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $goal = (int) $data['goal'];
        $raised = (int) $data['raised'];
        return [
            'percent' => $goal > 0 ? (int) min(100, floor($raised * 100 / $goal)) : 0,
            'money' => function (int $n) use ($view, $data) {
                return $data['currency'] . $view->specialBlocksNumber($n);
            },
        ];
    }
}
