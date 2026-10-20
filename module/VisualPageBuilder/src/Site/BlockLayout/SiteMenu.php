<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Controller\Admin\MenuController;
use VisualPageBuilder\Schema\Schema;

/**
 * The site menu (arranged in the menu builder), in a header or footer.
 * Uses the same markup and classes as the Companion Theme's own menu, so the
 * theme's styles and script make it work (drop-downs, phone "Menu" button).
 * "Plain list" draws every link as a simple list, for footer site maps.
 */
class SiteMenu extends AbstractSchemaBlock
{
    /** Only for the site's header and footer. */
    const PLACEMENT = 'region';

    const TEMPLATE = 'site-menu';

    const DESCRIPTION = 'Your site\'s menu, as arranged in the menu builder. In a header it drops down or opens panels; in a footer it can be a simple list of links.'; // @translate

    const GOOD_FOR = 'Every header, and footer site maps.'; // @translate

    public function getLabel()
    {
        return 'Site menu'; // @translate
    }

    public function schema(): array
    {
        return [
            [
                'key' => 'style', 'type' => Schema::TYPE_SELECT, 'group' => 'Menu', 'summary' => true, 'default' => 'site', 'label' => 'Shown as', // @translate
                'options' => [
                    'site' => 'The menu style and colors set on the Menu page', // @translate
                    'list' => 'Plain list of links (for footers)', // @translate
                ],
                'info' => 'A header\'s menu always follows the Menu page (Visual Builder › Menu), where you choose its style and colors with a preview.', // @translate
            ],
            ['key' => 'align', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'right', 'label' => 'Alignment', 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right']], // @translate
            ['key' => 'heading', 'type' => Schema::TYPE_TEXT, 'group' => 'Display', 'label' => 'Heading above it (optional)', 'placeholder' => 'e.g. Explore'], // @translate
        ];
    }

    /**
     * Before v1.16.0 a Site menu block could pick its own style; now the Menu
     * page decides, so any saved style other than "list" means "site".
     */
    protected function data(?\Omeka\Api\Representation\SitePageBlockRepresentation $block): array
    {
        $saved = $block ? $block->data() : [];
        $saved = is_array($saved) ? $saved : [];
        if (isset($saved['style']) && $saved['style'] !== 'list') {
            $saved['style'] = 'site';
        }
        return $this->normalizer->normalize($this->fields(), $saved);
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $menu = $view->vpbMenu($site);
        $style = $data['style'] === 'list' ? 'list' : ($menu['style'] ?: MenuController::DEFAULT_STYLE);
        if ($style !== 'list' && !isset(MenuController::STYLES[$style])) {
            $style = MenuController::DEFAULT_STYLE;
        }
        return [
            'style' => $style,
            'container' => $menu['container'] ?: $site->publicNav()->getContainer(),
            'navVars' => $style === 'list' ? '' : $menu['vars'],
            'navClasses' => $style === 'list' ? '' : $menu['classes'],
            'menuId' => 'vpb-menu-' . preg_replace('/[^a-z0-9]/i', '', $blockKey),
        ];
    }
}
