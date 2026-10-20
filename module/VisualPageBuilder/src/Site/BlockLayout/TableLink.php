<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Anchors;
use VisualPageBuilder\Schema\Schema;

/**
 * Table link: a named spot for the Table of contents where there's no
 * heading (above a map or a gallery, say). It takes no space on the site;
 * in the editor it's a thin dashed bar you can drag like any block.
 */
class TableLink extends AbstractSchemaBlock
{
    const TEMPLATE = 'table-link';

    const DESCRIPTION = 'An invisible spot the Table of contents links to, for places with no heading (a map, a gallery). It takes no space on the site.'; // @translate

    const GOOD_FOR = 'Listing a map, gallery, or timeline in the Table of contents.'; // @translate

    const PLACEMENT = 'page';

    public function getLabel()
    {
        return 'Table link'; // @translate
    }

    public function schema(): array
    {
        return [
            ['key' => 'name', 'type' => Schema::TYPE_TEXT, 'default' => '', 'label' => 'Name in the table of contents', 'placeholder' => 'e.g. Where it happened (map)'], // @translate
            [
                'key' => 'level', 'type' => Schema::TYPE_SELECT, 'default' => '1', 'label' => 'Level', // @translate
                'options' => ['1' => 'Section (like Heading 2)', '2' => 'Subsection (like Heading 3)', '3' => 'Sub-subsection (like Heading 4)'], // @translate
            ],
        ];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $page = $this->renderingBlock ? $this->renderingBlock->page() : null;
        $id = $page ? (Anchors::plan($page)['links'][$this->renderingBlock->id()]['id'] ?? '') : '';
        return ['anchorId' => $id];
    }
}
