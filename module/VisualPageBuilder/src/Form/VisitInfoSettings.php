<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Form;

use Laminas\EventManager\Event;
use Laminas\View\Renderer\PhpRenderer;
use VisualPageBuilder\Schema\Normalizer;

/**
 * A site's visit information (hours, address, contact), entered once under
 * Sites › (site) › Settings › Visit info, and shown by any Visit info block
 * set to "The site's visit info". Each site has its own.
 *
 * Module.php attaches addElements() and addInputFilters() to Omeka's site
 * settings form. Omeka saves each element as a site setting under its name.
 */
class VisitInfoSettings
{
    const GROUP = 'visual_page_builder_visit_info';

    /** Site setting name => [element type, label, info]. */
    const FIELDS = [
        'vpb_visit_hours' => ['textarea', 'Hours', 'One line per row, with a | between the days and the times. For example: Tuesday to Saturday | 10 am to 4 pm'],
        'vpb_visit_hours_note' => ['text', 'Note about hours', 'For example: Closed on major holidays'],
        'vpb_visit_closures' => ['textarea', 'Closed dates', 'One per line, the date then | and the reason. For example: 2026-11-26 | Thanksgiving. Used by Open now blocks.'],
        'vpb_visit_address' => ['textarea', 'Address', ''],
        'vpb_visit_directions_url' => ['text', 'Directions link', 'A web address, for example a map link for the address.'],
        'vpb_visit_admission' => ['textarea', 'Admission', ''],
        'vpb_visit_phone' => ['text', 'Phone', ''],
        'vpb_visit_email' => ['text', 'Email', ''],
        'vpb_visit_notes' => ['textarea', 'More information', 'Parking, accessibility, group visits… Plain text.'],
    ];

    /** Adds the "Visit info" group to the site settings form. */
    public static function addElements(Event $event): void
    {
        $form = $event->getTarget();
        $settings = $form->getSiteSettings();
        $groups = $form->getOption('element_groups') ?: [];
        $groups[self::GROUP] = 'Visit info'; // @translate
        $form->setOption('element_groups', $groups);
        foreach (self::FIELDS as $name => [$type, $label, $info]) {
            $form->add([
                'name' => $name,
                'type' => $type === 'textarea' ? 'Laminas\Form\Element\Textarea' : 'Laminas\Form\Element\Text',
                'options' => ['element_group' => self::GROUP, 'label' => $label, 'info' => $info],
                'attributes' => ['id' => $name, 'value' => $settings->get($name, '')] + ($type === 'textarea' ? ['rows' => 4] : []),
            ]);
        }
    }

    public static function addInputFilters(Event $event): void
    {
        $filter = $event->getParam('inputFilter');
        foreach (array_keys(self::FIELDS) as $name) {
            $filter->add(['name' => $name, 'required' => false, 'allow_empty' => true]);
        }
    }

    /**
     * A site's visit info in the same shape the Visit info block stores, so
     * the template doesn't care where it came from. Unsafe links are dropped.
     */
    public static function read(PhpRenderer $view, int $siteId): array
    {
        $get = function ($name) use ($view, $siteId) {
            $value = $view->siteSetting($name, '', $siteId);
            return is_string($value) ? trim($value) : '';
        };
        $hours = [];
        foreach (preg_split('/\R/', $get('vpb_visit_hours')) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line, 2));
            $hours[] = ['days' => $parts[0], 'times' => $parts[1] ?? ''];
        }
        $closures = [];
        foreach (preg_split('/\R/', $get('vpb_visit_closures')) as $line) {
            $parts = array_map('trim', explode('|', $line, 2));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $parts[0])) {
                $closures[] = ['date' => $parts[0], 'note' => $parts[1] ?? ''];
            }
        }
        return [
            'hours' => $hours,
            'closures' => $closures,
            'hours_note' => $get('vpb_visit_hours_note'),
            'address' => $get('vpb_visit_address'),
            'directions_url' => Normalizer::safeUrl($get('vpb_visit_directions_url')),
            'admission' => $get('vpb_visit_admission'),
            'phone' => $get('vpb_visit_phone'),
            'email' => $get('vpb_visit_email'),
            // Plain text here (site settings aren't purified), shown with line breaks.
            'notes' => nl2br($view->escapeHtml($get('vpb_visit_notes'))),
        ];
    }
}
