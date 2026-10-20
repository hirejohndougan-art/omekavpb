<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Service\ViewHelper;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use VisualPageBuilder\View\Helper\VpbMenu;

class VpbMenuFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new VpbMenu($services);
    }
}
