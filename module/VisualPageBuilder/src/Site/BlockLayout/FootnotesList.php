<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Footnotes;
use VisualPageBuilder\Schema\Schema;

/**
 * Footnotes: the page's numbered notes, where you want them. (Without this
 * block the notes are listed at the end of the page.)
 */
class FootnotesList extends AbstractSchemaBlock
{
    const TEMPLATE = 'footnotes';

    const DESCRIPTION = 'Lists the page\'s footnotes, numbered, with links back to the text. Add footnotes while typing (formatting bar › Footnote). Without this block they\'re listed at the end of the page.'; // @translate

    const GOOD_FOR = 'History essays and research pages.'; // @translate

    const PLACEMENT = 'page';

    public function getLabel()
    {
        return 'Footnotes'; // @translate
    }

    public function schema(): array
    {
        return [
            ['key' => 'heading', 'type' => Schema::TYPE_TEXT, 'default' => 'Notes', 'label' => 'Title'], // @translate
        ];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $page = $this->renderingBlock ? $this->renderingBlock->page() : null;
        return ['notes' => $page ? Footnotes::plan($page) : [], 'isPreview' => !$page];
    }
}
