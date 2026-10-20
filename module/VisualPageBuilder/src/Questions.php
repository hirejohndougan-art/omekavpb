<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder;

/**
 * The questions Add an item and One item ask (Phase 7, Edit questions). Each question is a
 * property of the chosen resource template: its label and help text are that template's
 * alternate label and comment, and "Needed" is its required flag. Changes are saved through
 * the API, so they are ordinary resource template changes in Omeka.
 */
class Questions
{
    public const SETTING = 'vpb_question_template';

    /** The labels and help text for each Details question, from the chosen template. */
    public static function fieldLabels($api, $settings): array
    {
        $defaults = [];
        foreach (ItemForm::FIELDS as $field => $term) {
            $defaults[$field] = ['label' => self::defaultLabel($field), 'comment' => '', 'needed' => $field === 'title'];
        }
        $templateId = (int) $settings->get(self::SETTING, 0);
        if (!$templateId) {
            return $defaults;
        }
        try {
            $template = $api->read('resource_templates', $templateId)->getContent();
        } catch (\Exception $e) {
            return $defaults;
        }
        foreach ($template->resourceTemplateProperties() as $prop) {
            $term = $prop->property()->term();
            $field = array_search($term, ItemForm::FIELDS, true);
            if ($field === false) {
                continue;
            }
            $defaults[$field] = [
                'label' => (string) ($prop->alternateLabel() ?: self::defaultLabel($field)),
                'comment' => (string) ($prop->alternateComment() ?: ''),
                'needed' => (bool) $prop->isRequired() || $field === 'title',
            ];
        }
        return $defaults;
    }

    public static function defaultLabel(string $field): string
    {
        return [
            'title' => 'Title', 'description' => 'Description', 'creator' => 'Creator',
            'date' => 'Date', 'subject' => 'Subjects', 'place' => 'Places',
        ][$field] ?? ucfirst($field);
    }
}
