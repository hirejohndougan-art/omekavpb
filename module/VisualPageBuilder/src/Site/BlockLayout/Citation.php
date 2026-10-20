<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;

/**
 * How to cite this page and how to reuse or request copies of materials.
 *
 * The citation fills in [Page title], [Site title], [Organization], [URL],
 * and [Date accessed] (today, in the site's language) and has a Copy button
 * (special-blocks/js/citation.js).
 */
class Citation extends AbstractSchemaBlock
{
    const TEMPLATE = 'citation';

    const DESCRIPTION = 'Shows how to cite this page, filled in automatically, with a Copy button, plus your rules for reusing images and requesting copies.'; // @translate

    const GOOD_FOR = 'Collection and exhibit pages that researchers use.'; // @translate

    public function getLabel()
    {
        return 'How to cite and reuse'; // @translate
    }

    public function schema(): array
    {
        return [
            DisplayFields::heading('Cite and reuse'),
            ['key' => 'organization', 'type' => Schema::TYPE_TEXT, 'group' => 'Citation', 'summary' => true, 'label' => 'Organization name', 'placeholder' => 'e.g. Dickinson Historical Society'], // @translate
            ['key' => 'format', 'type' => Schema::TYPE_TEXTAREA, 'group' => 'Citation', 'default' => '"[Page title]." [Site title], [Organization]. [URL]. Accessed [Date accessed].', 'label' => 'Citation format', 'info' => 'These are filled in: [Page title], [Site title], [Organization], [URL], [Date accessed].'], // @translate
            ['key' => 'reuse', 'type' => Schema::TYPE_HTML, 'group' => 'Reuse', 'label' => 'Reuse and copies', 'info' => 'Your rules for using images and how to request high-resolution copies. Basic HTML is allowed.'], // @translate
            ['key' => 'contact_text', 'type' => Schema::TYPE_TEXT, 'group' => 'Reuse', 'default' => 'Request a copy', 'label' => 'Contact link text', 'placeholder' => 'Leave empty for no link'], // @translate
            ['key' => 'contact_url', 'type' => Schema::TYPE_URL, 'group' => 'Reuse', 'label' => 'Contact link', 'placeholder' => 'mailto:… or https://…'], // @translate
        ];
    }

    /** Fill the citation's placeholders. */
    public static function fill(string $format, array $values): string
    {
        return strtr($format, [
            '[Page title]' => $values['page'],
            '[Site title]' => $values['site'],
            '[Organization]' => $values['organization'],
            '[URL]' => $values['url'],
            '[Date accessed]' => $values['date'],
        ]);
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        // In the visual editor's preview there's no saved block yet; use the site.
        $page = $this->renderingBlock ? $this->renderingBlock->page() : null;
        $pageTitle = $page ? $page->title() : $site->title();
        $url = $page ? $page->siteUrl(null, true) : $site->siteUrl(null, true);
        return [
            'citation' => self::fill($data['format'], [
                'page' => $pageTitle,
                'site' => $site->title(),
                'organization' => $data['organization'],
                'url' => $url,
                'date' => $view->i18n()->dateFormat(new \DateTime('today'), 'long'),
            ]),
        ];
    }
}
