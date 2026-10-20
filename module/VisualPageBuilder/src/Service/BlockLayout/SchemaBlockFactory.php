<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use VisualPageBuilder\SpecialBlocks;
use VisualPageBuilder\Schema\FormBuilder;
use VisualPageBuilder\Schema\Normalizer;
use VisualPageBuilder\Stdlib\CalendarFeed;
use VisualPageBuilder\Stdlib\ItemSource;
use VisualPageBuilder\Stdlib\ValueCounts;

/**
 * Builds every Special Blocks layout: looks up the layout's class in
 * SpecialBlocks::LAYOUTS and gives it the three helpers all blocks share.
 */
class SchemaBlockFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        $class = SpecialBlocks::LAYOUTS[$requestedName];
        $block = new $class(
            new Normalizer($services->get('Omeka\HtmlPurifier')),
            new FormBuilder($services->get('FormElementManager')),
            new ItemSource($services->get('Omeka\ApiManager'))
        );
        // "Today" and "now" follow Omeka's time zone setting, not the server's.
        $zone = (string) $services->get('Omeka\Settings')->get('time_zone', 'UTC');
        $block->setTimeZone($zone);
        // Optional helpers, for the blocks that ask for them with a setter.
        if (method_exists($block, 'setCalendarFeed')) { // Upcoming events
            $block->setCalendarFeed(new CalendarFeed($services->get('Omeka\HttpClient'), OMEKA_PATH . '/files/visual-page-builder', $zone));
        }
        if (method_exists($block, 'setValueCounts')) { // Subjects list
            $block->setValueCounts(new ValueCounts($services->get('Omeka\Connection')));
        }
        return $block;
    }
}
