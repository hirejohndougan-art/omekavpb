<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;

/**
 * Buttons to copy the page's link, email it, print it, or share it on
 * Facebook, X, or LinkedIn. Plain links: no tracking scripts are loaded.
 * Copy and Print need JavaScript (special-blocks/js/share.js) and stay
 * hidden without it.
 */
class SharePage extends AbstractSchemaBlock
{
    const TEMPLATE = 'share-page';

    const DESCRIPTION = 'Buttons to copy this page\'s link, email it, print it, or share it on Facebook and other sites. No tracking.'; // @translate

    const GOOD_FOR = 'Stories, exhibits, and event pages.'; // @translate

    const NETWORKS = ['copy' => 'Copy link', 'email' => 'Email', 'facebook' => 'Facebook', 'x' => 'X', 'linkedin' => 'LinkedIn', 'print' => 'Print'];

    public function getLabel()
    {
        return 'Share this page'; // @translate
    }

    public function schema(): array
    {
        $fields = [['key' => 'heading', 'type' => Schema::TYPE_TEXT, 'group' => 'Display', 'default' => 'Share this page', 'label' => 'Heading', 'placeholder' => 'Optional']]; // @translate
        foreach (self::NETWORKS as $key => $label) {
            $fields[] = ['key' => 'show_' . $key, 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Buttons', 'default' => in_array($key, ['copy', 'email', 'facebook'], true), 'label' => $label];
        }
        $fields[] = ['key' => 'align', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'left', 'label' => 'Alignment', 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right']]; // @translate
        return $fields;
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $page = $this->renderingBlock ? $this->renderingBlock->page() : null;
        // In a header or footer, share the page being viewed, not the footer.
        $current = $view->serverUrl(true);
        $url = $page && !\VisualPageBuilder\SiteRegions::regionOf($page) ? $page->siteUrl(null, true) : $current;
        $title = $page && !\VisualPageBuilder\SiteRegions::regionOf($page) ? $page->title() : $site->title();
        $e = 'rawurlencode';
        $links = [];
        foreach (self::NETWORKS as $key => $label) {
            if (empty($data['show_' . $key])) {
                continue;
            }
            $href = [
                'email' => 'mailto:?subject=' . $e($title) . '&body=' . $e($url),
                'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . $e($url),
                'x' => 'https://x.com/intent/post?url=' . $e($url) . '&text=' . $e($title),
                'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $e($url),
            ][$key] ?? null;
            $links[] = ['key' => $key, 'label' => $view->translate($label), 'href' => $href];
        }
        return ['links' => $links, 'url' => $url];
    }
}
