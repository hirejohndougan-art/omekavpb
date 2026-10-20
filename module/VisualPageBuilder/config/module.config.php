<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder;

return [
    'controllers' => [
        'factories' => [
            'VisualPageBuilder\Controller\Admin\Builder' => Service\Controller\Admin\BuilderControllerFactory::class,
        ],
        'invokables' => [
            'VisualPageBuilder\Controller\Site\Search' => Controller\Site\SearchController::class,
            'VisualPageBuilder\Controller\Admin\Search' => Controller\Admin\SearchController::class,
            'VisualPageBuilder\Controller\Admin\Menu' => Controller\Admin\MenuController::class,
            'VisualPageBuilder\Controller\Admin\Region' => Controller\Admin\RegionController::class,
            'VisualPageBuilder\Controller\Admin\Pages' => Controller\Admin\PagesController::class,
            'VisualPageBuilder\Controller\Admin\Theme' => Controller\Admin\ThemeController::class,
        ],
    ],
    'service_manager' => [
        'factories' => [
            'VisualPageBuilder\ResourceLookup' => Service\Stdlib\ResourceLookupFactory::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    // Special Blocks: every layout is built by the same factory, which looks
    // up the layout's class in SpecialBlocks::LAYOUTS.
    'block_layouts' => [
        'factories' => array_fill_keys(array_keys(SpecialBlocks::LAYOUTS), Service\BlockLayout\SchemaBlockFactory::class),
    ],
    'view_helpers' => [
        'invokables' => [
            // Formats counts for the site's language (Special Blocks templates).
            'specialBlocksNumber' => View\Helper\SpecialBlocksNumber::class,
            // A site's built header or footer (Companion Theme).
            'vpbSiteRegion' => View\Helper\VpbSiteRegion::class,
            // The Theme Builder template for an item page (Companion Theme).
            'vpbTemplate' => View\Helper\VpbTemplate::class,
        ],
        // Replaces Omeka's page layout helper so sections can show true
        // columns. Pages this module never touched are passed straight
        // through to Omeka's own renderer.
        'factories' => [
            'pageLayout' => Service\ViewHelper\PageLayoutFactory::class,
            // The site menu's style, colors, and links (Menu page; Companion
            // Theme and the Site menu block).
            'vpbMenu' => Service\ViewHelper\VpbMenuFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            // Public: the search endpoint under each site (VPB Search).
            'site' => [
                'child_routes' => [
                    'vpb-search' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/vpb-search',
                            'defaults' => [
                                '__NAMESPACE__' => 'VisualPageBuilder\Controller\Site',
                                'controller' => 'Search',
                                'action' => 'search',
                            ],
                        ],
                    ],
                ],
            ],
            'admin' => [
                'child_routes' => [
                    // The menu builder. The page editor's route below needs a
                    // numeric page id, so the two don't collide.
                    'visual-page-builder-menu' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/visual-page-builder/menu[/:site-slug[/:action]]',
                            'constraints' => [
                                'site-slug' => '[a-zA-Z0-9_-]+',
                                'action' => 'edit|save|draft',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'VisualPageBuilder\Controller\Admin',
                                'controller' => 'Menu',
                                'action' => 'index',
                            ],
                        ],
                    ],
                    // Search settings (VPB Search synonyms), for the whole installation.
                    'visual-page-builder-search' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/vpb-search-settings',
                            'defaults' => [
                                '__NAMESPACE__' => 'VisualPageBuilder\Controller\Admin',
                                'controller' => 'Search',
                                'action' => 'index',
                            ],
                        ],
                    ],
                    // Theme Builder (Templates and Global styles) for one site.
                    'visual-page-builder-theme' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/visual-builder/:site-slug/theme',
                            'constraints' => ['site-slug' => '[a-zA-Z0-9_-]+'],
                            'defaults' => [
                                '__NAMESPACE__' => 'VisualPageBuilder\Controller\Admin',
                                'controller' => 'Theme',
                                'action' => 'index',
                            ],
                        ],
                    ],
                    // Visual Builder: one page per site with its pages, header,
                    // footer, and menu (Controller\Admin\PagesController).
                    'visual-page-builder-pages' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/visual-builder[/:site-slug]',
                            'constraints' => ['site-slug' => '[a-zA-Z0-9_-]+'],
                            'defaults' => [
                                '__NAMESPACE__' => 'VisualPageBuilder\Controller\Admin',
                                'controller' => 'Pages',
                                'action' => 'index',
                            ],
                        ],
                    ],
                    // Header & footer: open a site's header or footer in the editor.
                    'visual-page-builder-region' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/visual-page-builder/region[/:site-slug/:region[/:action]]',
                            'constraints' => [
                                'site-slug' => '[a-zA-Z0-9_-]+',
                                'region' => 'header|footer',
                                'action' => 'edit',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'VisualPageBuilder\Controller\Admin',
                                'controller' => 'Region',
                                'action' => 'index',
                            ],
                        ],
                    ],
                    'visual-page-builder' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/visual-page-builder/:site-slug/:page-id[/:action]',
                            'constraints' => [
                                'site-slug' => '[a-zA-Z0-9_-]+',
                                'page-id' => '\d+',
                                'action' => 'edit|save|lookup|preview|theme-preview|make-shared|history',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'VisualPageBuilder\Controller\Admin',
                                'controller' => 'Builder',
                                'action' => 'edit',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    // One "Visual Builder" entry under Modules in Omeka's admin sidebar: pages,
    // header, footer, and menu for a site, all on one page.
    'navigation' => [
        'AdminModule' => [
            [
                'label' => 'Visual Builder', // @translate
                'route' => 'admin/visual-page-builder-pages',
                'resource' => 'VisualPageBuilder\Controller\Admin\Pages',
            ],
        ],
    ],
];
