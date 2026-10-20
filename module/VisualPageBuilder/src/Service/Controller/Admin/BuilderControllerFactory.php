<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Service\Controller\Admin;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use VisualPageBuilder\Controller\Admin\BuilderController;

class BuilderControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        $config = $services->get('Config');
        $thumbnailTypes = array_keys($config['thumbnails']['types'] ?? []);
        return new BuilderController(
            $services->get('VisualPageBuilder\ResourceLookup'),
            $services->get('Omeka\BlockLayoutManager'),
            $thumbnailTypes ?: ['large', 'medium', 'square']
        );
    }
}
