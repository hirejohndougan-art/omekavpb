<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Schema;

use Laminas\Form\Element;
use Laminas\Form\FormElementManager;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Form\Element\Asset as AssetElement;
use Omeka\Form\Element\ItemSetSelect;
use Omeka\Form\Element\SitePageSelect;
use Omeka\Form\Element\SiteSelect;

/**
 * Builds a block's form for Omeka's standard page editor from its schema.
 *
 * Field names follow Omeka's block form convention,
 * o:block[__blockIndex__][o:data][<key>], so Omeka saves them without any
 * extra code. List fields show their saved rows plus a few blank ones; blank
 * rows are dropped when saving (see Normalizer). Fields with `show_if` carry
 * data attributes that asset/js/admin-form.js uses to hide them when they
 * don't apply.
 */
class FormBuilder
{
    /** Blank rows offered under a list field's saved rows. */
    const SPARE_ROWS = 2;

    /**
     * @var FormElementManager
     */
    protected $formElements;

    public function __construct(FormElementManager $formElements)
    {
        $this->formElements = $formElements;
    }

    public function render(PhpRenderer $view, array $schema, array $data, SiteRepresentation $site): string
    {
        $html = '<div class="special-blocks-form">';
        $group = null;
        foreach ($schema as $field) {
            if (($field['group'] ?? null) !== $group) {
                $group = $field['group'] ?? null;
                if ($group !== null) {
                    $html .= '<h4 class="special-blocks-form-group">' . $view->escapeHtml($view->translate($group)) . '</h4>';
                }
            }
            $html .= $this->wrap($view, $field, $this->field($view, $field, $data[$field['key']] ?? null, $site, $this->name($field['key'])));
        }
        return $html . '</div>';
    }

    protected function name(string $key, ?string $parent = null, ?int $row = null): string
    {
        return $parent === null
            ? sprintf('o:block[__blockIndex__][o:data][%s]', $key)
            : sprintf('o:block[__blockIndex__][o:data][%s][%d][%s]', $parent, $row, $key);
    }

    /** Wrap a field so admin-form.js can show or hide it. */
    protected function wrap(PhpRenderer $view, array $field, string $inner): string
    {
        $attrs = '';
        if (!empty($field['show_if'])) {
            $attrs = sprintf(' data-show-if="%s"', $view->escapeHtmlAttr(json_encode($field['show_if'])));
        }
        return sprintf('<div class="special-blocks-field" data-key="%s"%s>%s</div>', $view->escapeHtmlAttr($field['key']), $attrs, $inner);
    }

    /**
     * The form markup for one field.
     */
    protected function field(PhpRenderer $view, array $field, $value, SiteRepresentation $site, string $name): string
    {
        if ($field['type'] === Schema::TYPE_LIST) {
            return $this->listField($view, $field, is_array($value) ? $value : [], $site, $name);
        }
        $element = $this->element($field, $value, $site, $name);
        if ($field['type'] === Schema::TYPE_ASSET) {
            return '<div class="field"><div class="field-meta"><label>' . $view->escapeHtml($view->translate($field['label'])) . '</label></div><div class="inputs">' . $view->formAsset($element) . '</div></div>';
        }
        return $view->formRow($element);
    }

    protected function element(array $field, $value, SiteRepresentation $site, string $name): Element
    {
        $options = ['label' => $field['label']];
        if (!empty($field['info'])) {
            $options['info'] = $field['info'];
        }
        $attributes = ['id' => null];
        if (isset($field['placeholder']) && !in_array($field['type'], [Schema::TYPE_SITE, Schema::TYPE_PAGE], true)) {
            $attributes['placeholder'] = $field['placeholder'];
        }

        switch ($field['type']) {
            case Schema::TYPE_TEXTAREA:
            case Schema::TYPE_HTML:
                $element = new Element\Textarea($name);
                $attributes['rows'] = $field['type'] === Schema::TYPE_HTML ? 5 : 3;
                break;
            case Schema::TYPE_NUMBER:
                $element = new Element\Number($name);
                foreach (['min', 'max'] as $bound) {
                    if (isset($field[$bound])) {
                        $attributes[$bound] = $field[$bound];
                    }
                }
                break;
            case Schema::TYPE_SELECT:
                $element = new Element\Select($name);
                $options['value_options'] = $field['options'];
                break;
            case Schema::TYPE_CHECKBOX:
                $element = new Element\Checkbox($name);
                $value = $value ? '1' : '0';
                break;
            case Schema::TYPE_URL:
                $element = new Element\Text($name);
                $attributes['type'] = 'text'; // Allows site-relative "/…" links too.
                break;
            case Schema::TYPE_DATE:
                $element = new Element\Date($name);
                break;
            case Schema::TYPE_SITE:
            case Schema::TYPE_SITES:
                $element = $this->formElements->get(SiteSelect::class);
                $element->setName($name);
                $options['empty_option'] = $field['placeholder'] ?? '';
                if ($field['type'] === Schema::TYPE_SITES) {
                    $attributes['multiple'] = true;
                }
                $attributes['class'] = 'chosen-select';
                $attributes['data-placeholder'] = 'Select sites…'; // @translate
                break;
            case Schema::TYPE_ITEM_SETS:
                $element = $this->formElements->get(ItemSetSelect::class);
                $element->setName($name);
                $attributes['multiple'] = true;
                $attributes['class'] = 'chosen-select';
                $attributes['data-placeholder'] = 'Select item sets…'; // @translate
                break;
            case Schema::TYPE_PAGE:
                $element = $this->formElements->get(SitePageSelect::class);
                $element->setName($name);
                $options['empty_option'] = '';
                break;
            case Schema::TYPE_ASSET:
                $element = new AssetElement($name);
                break;
            case Schema::TYPE_ITEM:
                // The standard editor has no item picker for plain fields; the
                // visual page builder shows a search picker instead.
                $element = new Element\Number($name);
                $attributes['min'] = 1;
                $options['info'] = trim(($field['info'] ?? '') . ' Enter the item\'s ID number, shown on its admin page.'); // @translate
                break;
            default:
                $element = new Element\Text($name);
        }
        $element->setOptions($options);
        $element->setAttributes(array_filter($attributes, function ($v) {
            return $v !== null;
        }));
        $element->setValue($value);
        return $element;
    }

    /**
     * A list field: a fieldset per row (saved rows, then spare blank rows).
     */
    protected function listField(PhpRenderer $view, array $field, array $rows, SiteRepresentation $site, string $name): string
    {
        $html = sprintf('<fieldset class="special-blocks-list"><legend>%s</legend>', $view->escapeHtml($view->translate($field['label'])));
        if (!empty($field['info'])) {
            $html .= '<p class="field-description">' . $view->escapeHtml($view->translate($field['info'])) . '</p>';
        }
        $spare = self::SPARE_ROWS;
        if (isset($field['max'])) {
            $spare = max(0, min($spare, $field['max'] - count($rows)));
        }
        $total = count($rows) + $spare;
        for ($i = 0; $i < $total; $i++) {
            $row = $rows[$i] ?? Schema::defaults($field['fields']);
            $html .= sprintf('<div class="special-blocks-row"><span class="special-blocks-row-number">%d</span>', $i + 1);
            foreach ($field['fields'] as $sub) {
                $html .= $this->field($view, $sub, $row[$sub['key']] ?? null, $site, $this->name($sub['key'], $field['key'], $i));
            }
            $html .= '</div>';
        }
        $html .= '<p class="field-description">' . $view->escapeHtml($view->translate('Leave a row empty to remove it. Save the page to get more blank rows.')) . '</p>';
        return $html . '</fieldset>';
    }
}
