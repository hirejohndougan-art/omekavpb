<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use VisualPageBuilder\Schema\Schema;

/**
 * Collapsible sections, for FAQs or long text. Uses the browser's own
 * <details> element, so it needs no JavaScript and works with keyboards and
 * screen readers.
 */
class Accordion extends AbstractSchemaBlock
{
    const DESCRIPTION = 'Headings that open to show more text when clicked.'; // @translate

    const GOOD_FOR = 'Frequently asked questions and long details.'; // @translate

    const TEMPLATE = 'accordion';

    public function getLabel()
    {
        return 'Accordion'; // @translate
    }

    public function schema(): array
    {
        return [
            DisplayFields::heading(),
            [
                'key' => 'panels', 'type' => Schema::TYPE_LIST, 'group' => 'Sections', 'summary' => true, 'max' => 40,
                'label' => 'Sections', // @translate
                'fields' => [
                    ['key' => 'title', 'type' => Schema::TYPE_TEXT, 'label' => 'Title'], // @translate
                    ['key' => 'body', 'type' => Schema::TYPE_HTML, 'label' => 'Text', 'info' => 'Basic HTML is allowed: paragraphs, links, lists, bold, italics.'], // @translate
                ],
            ],
            ['key' => 'open_first', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Display', 'default' => false, 'label' => 'Open the first section'], // @translate
            ['key' => 'one_at_a_time', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Display', 'default' => false, 'label' => 'Only one section open at a time'], // @translate
        ];
    }
}
