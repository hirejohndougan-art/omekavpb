<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Service\Stdlib;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use VisualPageBuilder\Stdlib\ResourceLookup;

class ResourceLookupFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new ResourceLookup($services->get('Omeka\ApiManager'));
    }
}
