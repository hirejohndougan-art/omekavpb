<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Entity\SitePageBlock;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Site\BlockLayout\TemplateableBlockLayoutInterface;
use Omeka\Stdlib\ErrorStore;
use VisualPageBuilder\Schema\FormBuilder;
use VisualPageBuilder\Schema\Normalizer;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Stdlib\ItemSource;

/**
 * Base for every Special Blocks layout.
 *
 * A block subclass declares:
 *   getLabel()   its name in the block menu
 *   DESCRIPTION  what it does, in one or two plain sentences
 *   GOOD_FOR     a short "good for" hint
 *   schema()     its settings (see Schema for the field format)
 *   TEMPLATE     its template under view/common/block-layout/special-blocks/
 * and, if its template needs more than the saved settings, viewVars().
 *
 * Everything else comes from here: the standard editor form, cleaning the
 * data on save, the Visual Page Builder's sidebar form, help text, and live
 * preview, site search text, and rendering through a theme-overridable
 * template.
 */
abstract class AbstractSchemaBlock extends AbstractBlockLayout implements TemplateableBlockLayoutInterface
{
    /** Template name (without .phtml) under common/block-layout/special-blocks/. */
    const TEMPLATE = '';

    /**
     * Where the block is offered in the visual editor's palette:
     * 'page' (pages only), 'region' (the site's header and footer only), or
     * 'both'. See SiteRegions.
     */
    const PLACEMENT = 'page';

    /** What the block does, for editors, in plain language. */
    const DESCRIPTION = '';

    /** What it's good for, e.g. "Home pages that feel fresh on every visit." */
    const GOOD_FOR = '';

    /**
     * @var Normalizer
     */
    protected $normalizer;

    /**
     * @var FormBuilder
     */
    protected $formBuilder;

    /**
     * @var ItemSource
     */
    protected $itemSource;

    /**
     * The time zone set in Omeka's settings. PHP's own default may differ
     * (Omeka only uses its setting for formatting), so anything about "today"
     * or "now" must use this (now(), today()).
     *
     * @var \DateTimeZone
     */
    protected $timeZone;

    /** Set by the block factory from Omeka's "time_zone" setting. */
    public function setTimeZone(string $zone): void
    {
        try {
            $this->timeZone = new \DateTimeZone($zone);
        } catch (\Exception $e) {
            $this->timeZone = new \DateTimeZone('UTC');
        }
    }

    protected function zone(): \DateTimeZone
    {
        return $this->timeZone ?: new \DateTimeZone(date_default_timezone_get());
    }

    /** Now, in the society's time zone. */
    protected function now(): \DateTime
    {
        return new \DateTime('now', $this->zone());
    }

    /** Midnight today, in the society's time zone. */
    protected function today(): \DateTime
    {
        return new \DateTime('today', $this->zone());
    }

    public function __construct(Normalizer $normalizer, FormBuilder $formBuilder, ItemSource $itemSource)
    {
        $this->normalizer = $normalizer;
        $this->formBuilder = $formBuilder;
        $this->itemSource = $itemSource;
    }

    /**
     * CSS class for the block's wrapper, from its class name:
     * RandomItems → "special-blocks-random-items".
     */
    public static function cssClass(): string
    {
        $short = substr(strrchr(static::class, '\\'), 1);
        return 'special-blocks-' . strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $short));
    }

    /**
     * The block's settings. See Schema for the field format.
     */
    abstract public function schema(): array;

    /**
     * Extra template variables, beyond `data`, `site`, and `blockKey`.
     * `$blockKey` is unique per block on a page (the block id, or "preview").
     */
    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        return [];
    }

    public function prepareForm(PhpRenderer $view)
    {
        $view->headScript()->appendFile($view->assetUrl('js/asset-form.js', 'Omeka'));
        $view->headScript()->appendFile($view->assetUrl('special-blocks/js/admin-form.js', 'VisualPageBuilder'));
        $view->headLink()->appendStylesheet($view->assetUrl('special-blocks/css/admin-form.css', 'VisualPageBuilder'));
    }

    public function prepareRender(PhpRenderer $view)
    {
        $view->headLink()->appendStylesheet($view->assetUrl('special-blocks/css/special-blocks.css', 'VisualPageBuilder'), 'all');
    }

    /**
     * Clean the data before Omeka saves it (both editors go through here).
     */
    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore)
    {
        $data = $block->getData();
        $block->setData($this->normalizer->normalize($this->fields(), is_array($data) ? $data : []));
    }

    public function form(PhpRenderer $view, SiteRepresentation $site, ?SitePageRepresentation $page = null, ?SitePageBlockRepresentation $block = null)
    {
        $intro = sprintf('<p class="special-blocks-form-about">%s</p>', $view->escapeHtml($view->translate(static::DESCRIPTION)));
        return $intro . $this->formBuilder->render($view, $this->fields(), $this->data($block), $site);
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block, $templateViewScript = null)
    {
        return $this->renderData($view, $this->data($block), $block->page()->site(), (string) $block->id(), $templateViewScript, $block);
    }

    /**
     * Render from settings alone. Used for pages and for the visual editor's
     * live preview, where the block may not be saved yet.
     */
    /**
     * The saved block being rendered on a page, for viewVars() that need its
     * page (null in the visual editor's preview).
     *
     * @var SitePageBlockRepresentation|null
     */
    protected $renderingBlock;

    /**
     * Blocks whose title IS their content don't get a separate title setting.
     */
    const NO_TITLE_SETTING = ['specialLogo', 'specialSiteTitle', 'specialCopyright', 'specialBreadcrumbs', 'specialAnnouncement', 'specialTableLink', 'itemTitle', 'itemMainImage', 'itemDownload', 'itemPrevNext', 'itemCite'];

    /**
     * The block's settings: schema() plus, for every Special Block, a title
     * ("heading") and "Show the title" so titles can be changed or hidden.
     * Blocks whose schema already has a heading keep it (and its default);
     * the others get an empty one, drawn above the block when filled in.
     */
    protected function fields(): array
    {
        $fields = $this->schema();
        $keys = array_column($fields, 'key');
        if (in_array(static::class, array_map(function ($l) {
            return \VisualPageBuilder\SpecialBlocks::LAYOUTS[$l] ?? null;
        }, self::NO_TITLE_SETTING), true)) {
            return $fields;
        }
        $show = ['key' => 'show_heading', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Title', 'default' => true, 'label' => 'Show the title']; // @translate
        // Title settings go first, in their own "Title" group.
        $at = array_search('heading', $keys, true);
        $heading = $at === false ? DisplayFields::heading() : $fields[$at];
        if ($at !== false) {
            array_splice($fields, $at, 1);
        }
        $heading['group'] = 'Title';
        $heading['label'] = 'Title'; // @translate
        array_unshift($fields, $heading, $show);
        return $fields;
    }

    /** Whether this block's own template draws the heading (schema() has one). */
    protected function drawsOwnHeading(): bool
    {
        return in_array('heading', array_column($this->schema(), 'key'), true);
    }

    protected function renderData(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey, $templateViewScript = null, ?SitePageBlockRepresentation $block = null): string
    {
        $this->renderingBlock = $block;
        // "Show the title" off: the block is drawn without its title.
        if (array_key_exists('show_heading', $data) && !$data['show_heading']) {
            $data['heading'] = '';
        }
        $vars = ['block' => $block, 'data' => $data, 'site' => $site, 'blockKey' => $blockKey]
            + $this->viewVars($view, $data, $site, $blockKey);
        $html = $view->partial($templateViewScript ?: 'common/block-layout/special-blocks/' . static::TEMPLATE, $vars);
        // Blocks that didn't have a title before get it drawn above them.
        if (!$this->drawsOwnHeading() && ($data['heading'] ?? '') !== '' && trim($html) !== '') {
            $html = $view->partial('common/special-blocks/heading', ['heading' => $data['heading']]) . $html;
        }
        return $html;
    }

    /**
     * The block's typed text, so Omeka's site search finds pages by it.
     */
    public function getFulltextText(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        return trim(implode(' ', $this->texts($this->fields(), $this->data($block))));
    }

    /**
     * What the Visual Page Builder module needs to edit this block: its
     * settings form, help text, and the stylesheet its preview uses. That
     * module looks for this method on any block layout; see its README.
     */
    public function visualPageBuilderSchema(): array
    {
        return [
            'fields' => $this->fields(),
            'description' => static::DESCRIPTION,
            'goodFor' => static::GOOD_FOR,
            'stylesheets' => [['module' => 'VisualPageBuilder', 'file' => 'special-blocks/css/special-blocks.css']],
            'preview' => true,
            // Its own palette group, right after Sections and Text.
            'paletteGroup' => 'Special Blocks', // @translate
            'paletteFeatured' => true,
            // A smaller group inside Special Blocks, and blocks it works well with.
            'paletteSubgroup' => \VisualPageBuilder\SpecialBlocks::groupOf(static::class),
            'worksWith' => \VisualPageBuilder\SpecialBlocks::WORKS_WITH[array_search(static::class, \VisualPageBuilder\SpecialBlocks::LAYOUTS, true) ?: ''] ?? '',
            'placement' => static::PLACEMENT,
        ];
    }

    /**
     * The block's HTML for the Visual Page Builder's live preview, from
     * unsaved settings. Rendered with the real template, so it matches the
     * public page (apart from the theme's own styles).
     */
    public function visualPageBuilderPreview(PhpRenderer $view, array $data, SiteRepresentation $site): string
    {
        return $this->renderData($view, $this->normalizer->normalize($this->fields(), $data), $site, 'preview');
    }

    /**
     * Saved settings with defaults filled in. Blocks saved by an older version
     * (or created through the API without some keys) still get every key.
     */
    protected function data(?SitePageBlockRepresentation $block): array
    {
        $saved = $block ? $block->data() : [];
        return $this->normalizer->normalize($this->fields(), is_array($saved) ? $saved : []);
    }

    protected function texts(array $schema, array $data): array
    {
        $out = [];
        foreach ($schema as $field) {
            $value = $data[$field['key']] ?? null;
            if (in_array($field['type'], [Schema::TYPE_TEXT, Schema::TYPE_TEXTAREA, Schema::TYPE_HTML], true) && is_string($value)) {
                $out[] = strip_tags($value);
            } elseif ($field['type'] === Schema::TYPE_LIST && is_array($value)) {
                foreach ($value as $row) {
                    $out = array_merge($out, $this->texts($field['fields'], $row));
                }
            }
        }
        return array_filter($out, 'strlen');
    }
}
