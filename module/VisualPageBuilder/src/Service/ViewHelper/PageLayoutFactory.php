<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use VisualPageBuilder\View\Helper\PageLayout;

class PageLayoutFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new PageLayout($services->get('EventManager'));
    }
}
