<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Templates\CurrentRecord;
use VisualPageBuilder\Templates\CurrentScreen;
use VisualPageBuilder\Templates\LocationResolver;

/**
 * Resolve and render a template for a location (header, footer, or body screen)
 * using display conditions, adapted from Blueprint's bpb_location() / bpt_location().
 *
 * Usage in the Companion Theme:
 *   <?php if ($html = $this->vpbLocation($site, 'header', $context)): ?>
 *       <header class="pb-header pb-header-built"><?php echo $html; ?></header>
 *   <?php else: ?>
 *       <!-- theme's own built-in header -->
 *   <?php endif; ?>
 *
 * Like Blueprint's bpt_location(), it returns the rendered HTML or null when no
 * template matches (the theme then shows its own built-in header/footer/body).
 */
class VpbLocation extends AbstractHelper
{
    /**
     * Render the best matching template for a location.
     *
     * @param SiteRepresentation $site  The current site.
     * @param string $location  'header', 'footer', or a screen key.
     * @param array $context  The request context from LocationResolver::contextFromRoute().
     * @return string|null  The rendered HTML, or null if no template matches.
     */
    public function __invoke(SiteRepresentation $site, string $location, array $context): ?string
    {
        $resolver = new LocationResolver();
        $matched = $resolver->resolve($site, $context);

        $template = $matched[$location] ?? null;
        if (!$template) {
            return null;
        }

        $page = $template['page'] ?? null;
        if (!$page || !$page->blocks()) {
            return null;
        }

        // Set the current screen and record context for blocks that need it
        // (like Blueprint's Renderer setting up the rendering context).
        $screen = $context['screen'] ?? '';
        $record = $context['record_object'] ?? null;

        if ($screen !== '') {
            CurrentScreen::push($screen);
        }
        if ($record) {
            CurrentRecord::push($record);
        }

        try {
            return (string) $this->getView()->pageLayout()->render($page);
        } finally {
            if ($record) {
                CurrentRecord::pop();
            }
            if ($screen !== '') {
                CurrentScreen::pop();
            }
        }
    }
}
