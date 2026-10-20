<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Form\VisitInfoSettings;
use VisualPageBuilder\Schema\Schema;

/**
 * Hours, address, admission, and contact details: typed into the block, or
 * taken from a site's Visit info settings (entered once, used on any page,
 * see Form\VisitInfoSettings).
 */
class VisitInfo extends AbstractSchemaBlock
{
    const DESCRIPTION = 'Your hours, address, admission, and contact details in one tidy block.'; // @translate

    const GOOD_FOR = 'Visit and contact pages.'; // @translate

    /** On pages, and in the site's header and footer. */
    const PLACEMENT = 'both';

    const TEMPLATE = 'visit-info';

    public function getLabel()
    {
        return 'Visit info'; // @translate
    }

    public function schema(): array
    {
        $own = ['source' => ['block']];
        $fields = [
            DisplayFields::heading('Plan your visit'),
            [
                'key' => 'source', 'type' => Schema::TYPE_SELECT, 'group' => 'Information', 'summary' => true, 'default' => 'block',
                'label' => 'Where the details come from', // @translate
                'info' => 'A site\'s visit info is entered once under Sites › the site › Settings › Visit info, and every block using it stays up to date.', // @translate
                'options' => ['block' => 'Typed into this block', 'site' => 'A site\'s visit info'], // @translate
            ],
            [
                'key' => 'source_site', 'type' => Schema::TYPE_SITE, 'group' => 'Information', 'summary' => true,
                'label' => 'Which site', // @translate
                'placeholder' => 'This site', // @translate
                'show_if' => ['source' => ['site']],
            ],
            [
                'key' => 'hours', 'type' => Schema::TYPE_LIST, 'group' => 'Hours', 'max' => 14,
                'label' => 'Hours', // @translate
                'fields' => [
                    ['key' => 'days', 'type' => Schema::TYPE_TEXT, 'label' => 'Days', 'placeholder' => 'e.g. Tuesday to Saturday'], // @translate
                    ['key' => 'times', 'type' => Schema::TYPE_TEXT, 'label' => 'Times', 'placeholder' => 'e.g. 10 am to 4 pm, or Closed'], // @translate
                ],
            ],
            ['key' => 'hours_note', 'type' => Schema::TYPE_TEXT, 'group' => 'Hours', 'label' => 'Note about hours', 'placeholder' => 'e.g. Closed on major holidays'], // @translate
            ['key' => 'address', 'type' => Schema::TYPE_TEXTAREA, 'group' => 'Location', 'summary' => true, 'label' => 'Address'], // @translate
            ['key' => 'directions_url', 'type' => Schema::TYPE_URL, 'group' => 'Location', 'label' => 'Directions link', 'info' => 'e.g. a map link for the address.', 'placeholder' => 'https://…'], // @translate
            ['key' => 'admission', 'type' => Schema::TYPE_TEXTAREA, 'group' => 'Admission', 'label' => 'Admission'], // @translate
            ['key' => 'phone', 'type' => Schema::TYPE_TEXT, 'group' => 'Contact', 'label' => 'Phone'], // @translate
            ['key' => 'email', 'type' => Schema::TYPE_TEXT, 'group' => 'Contact', 'label' => 'Email'], // @translate
            ['key' => 'notes', 'type' => Schema::TYPE_HTML, 'group' => 'Contact', 'label' => 'More information', 'info' => 'Parking, accessibility, group visits… Basic HTML is allowed.'], // @translate
            [
                'key' => 'layout', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'columns',
                'label' => 'Layout', // @translate
                'options' => ['columns' => 'Side by side', 'stacked' => 'Stacked'], // @translate
            ],
        ];
        // The typed-in fields only apply when the block has its own details.
        foreach ($fields as &$field) {
            if (in_array($field['key'], ['hours', 'hours_note', 'address', 'directions_url', 'admission', 'phone', 'email', 'notes'], true)) {
                $field['show_if'] = $own;
            }
        }
        unset($field);
        return $fields;
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        if ($data['source'] !== 'site') {
            return ['info' => $data];
        }
        return ['info' => VisitInfoSettings::read($view, $data['source_site'] ?: $site->id())];
    }

}
