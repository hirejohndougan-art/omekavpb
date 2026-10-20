<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;

/**
 * A copyright or credit line. [year] and [site] are filled in.
 */
class Copyright extends AbstractSchemaBlock
{
    /** Only for the site's header and footer. */
    const PLACEMENT = 'region';

    const TEMPLATE = 'copyright';

    const DESCRIPTION = 'A small line like "© 2026 Dickinson Historical Society". The year updates itself.'; // @translate

    const GOOD_FOR = 'The bottom of the footer.'; // @translate

    public function getLabel()
    {
        return 'Copyright line'; // @translate
    }

    public function schema(): array
    {
        return [
            ['key' => 'text', 'type' => Schema::TYPE_TEXT, 'group' => 'Text', 'summary' => true, 'default' => '© [year] [site]', 'label' => 'Text', 'info' => '[year] becomes this year and [site] the site\'s name.'], // @translate
            ['key' => 'align', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'center', 'label' => 'Alignment', 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right']], // @translate
        ];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        return ['line' => strtr($data['text'], ['[year]' => $this->now()->format('Y'), '[site]' => $site->title()])];
    }
}
