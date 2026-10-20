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
            'VisualPageBuilder\Controller\Admin\Settings' => Controller\Admin\SettingsController::class,
            'VisualPageBuilder\Controller\Admin\People' => Controller\Admin\PeopleController::class,
            'VisualPageBuilder\Controller\Admin\Library' => Controller\Admin\LibraryController::class,
            'VisualPageBuilder\Controller\Admin\Import' => Controller\Admin\ImportController::class,
            'VisualPageBuilder\Controller\Admin\Home' => Controller\Admin\HomeController::class,
            'VisualPageBuilder\Controller\Admin\Items' => Controller\Admin\ItemsController::class,
            'VisualPageBuilder\Controller\Admin\Item' => Controller\Admin\ItemController::class,
            'VisualPageBuilder\Controller\Admin\Add' => Controller\Admin\AddController::class,
            'VisualPageBuilder\Controller\Admin\Questions' => Controller\Admin\QuestionsController::class,
            'VisualPageBuilder\Controller\Admin\Menu' => Controller\Admin\MenuController::class,
            'VisualPageBuilder\Controller\Admin\Region' => Controller\Admin\RegionController::class,
            'VisualPageBuilder\Controller\Admin\Pages' => Controller\Admin\PagesController::class,
            'VisualPageBuilder\Controller\Admin\Theme' => Controller\Admin\ThemeController::class,
            'VisualPageBuilder\Controller\Admin\Diagnostics' => Controller\Admin\DiagnosticsController::class,
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
            // The Theme Builder template for a page-wide screen, such as Browse items (Companion Theme).
            'vpbScreen' => View\Helper\VpbScreen::class,
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
            // Diagnostics: the read-only report, for a request that carries the diagnostics token.
            'visual-page-builder-diagnostics-report' => [
                'type' => \Laminas\Router\Http\Literal::class,
                'options' => [
                    'route' => '/vpb-diagnostics',
                    'defaults' => [
                        '__NAMESPACE__' => 'VisualPageBuilder\Controller\Admin',
                        'controller' => 'Diagnostics',
                        'action' => 'report',
                    ],
                ],
            ],
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
                    // Simple or Expert mode (Phase 9): /vpb-mode?to=simple or expert.
                    'visual-page-builder-mode' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/vpb-mode',
                            'defaults' => [
                                '__NAMESPACE__' => 'VisualPageBuilder\\Controller\\Admin',
                                'controller' => 'Home',
                                'action' => 'mode',
                            ],
                        ],
                    ],
                    // Edit questions (Phase 7): labels, help text and Needed for the item questions.
                    'visual-page-builder-questions' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/vpb-questions',
                            'defaults' => [
                                '__NAMESPACE__' => 'VisualPageBuilder\\Controller\\Admin',
                                'controller' => 'Questions',
                                'action' => 'index',
                            ],
                        ],
                    ],
                    // Add an item (Phase 7): Files, Describe, Where it shows; or the classic form.
                    'visual-page-builder-add' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/vpb-add',
                            'defaults' => [
                                '__NAMESPACE__' => 'VisualPageBuilder\\Controller\\Admin',
                                'controller' => 'Add',
                                'action' => 'index',
                            ],
                        ],
                    ],
                    // Glossary and help (Phase 6): plain words for the admin screens.
                    'visual-page-builder-glossary' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/vpb-glossary',
                            'defaults' => [
                                '__NAMESPACE__' => 'VisualPageBuilder\Controller\Admin',
                                'controller' => 'Home',
                                'action' => 'glossary',
                            ],
                        ],
                    ],
                    // One item (Phase 6): Details, Where it shows, Files, History, actions.
                    'visual-page-builder-item' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/vpb-item/:item-id',
                            'constraints' => ['item-id' => '[0-9]+'],
                            'defaults' => [
                                '__NAMESPACE__' => 'VisualPageBuilder\Controller\Admin',
                                'controller' => 'Item',
                                'action' => 'index',
                            ],
                        ],
                    ],
                    // Items (Phase 6): search, filters, list or grid, bulk actions.
                    'visual-page-builder-items' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/vpb-items',
                            'defaults' => [
                                '__NAMESPACE__' => 'VisualPageBuilder\Controller\Admin',
                                'controller' => 'Items',
                                'action' => 'index',
                            ],
                        ],
                    ],
                    // Home (Phase 6): greeting, what needs attention, recent changes, counts.
                    'visual-page-builder-home' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/vpb-home',
                            'defaults' => [
                                '__NAMESPACE__' => 'VisualPageBuilder\Controller\Admin',
                                'controller' => 'Home',
                                'action' => 'index',
                            ],
                        ],
                    ],
                    // Import (Phase 8): the spreadsheet wizard, on CSV Import.
                    'visual-page-builder-import' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/vpb-import',
                            'defaults' => [
                                '__NAMESPACE__' => 'VisualPageBuilder\Controller\Admin',
                                'controller' => 'Import',
                                'action' => 'index',
                            ],
                        ],
                    ],
                    // Files and Item sets (Phase 8): every file, and the item sets as cards.
                    'visual-page-builder-files' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/vpb-files',
                            'defaults' => [
                                '__NAMESPACE__' => 'VisualPageBuilder\Controller\Admin',
                                'controller' => 'Library',
                                'action' => 'files',
                            ],
                        ],
                    ],
                    'visual-page-builder-item-sets' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/vpb-item-sets',
                            'defaults' => [
                                '__NAMESPACE__' => 'VisualPageBuilder\Controller\Admin',
                                'controller' => 'Library',
                                'action' => 'item-sets',
                            ],
                        ],
                    ],
                    // People (Phase 9): accounts, roles in plain words, invitations.
                    'visual-page-builder-people' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/vpb-people',
                            'defaults' => [
                                '__NAMESPACE__' => 'VisualPageBuilder\Controller\Admin',
                                'controller' => 'People',
                                'action' => 'index',
                            ],
                        ],
                    ],
                    // Diagnostics: the admin page that creates or turns off the report token.
                    'visual-page-builder-diagnostics' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/vpb-diagnostics',
                            'defaults' => [
                                '__NAMESPACE__' => 'VisualPageBuilder\Controller\Admin',
                                'controller' => 'Diagnostics',
                                'action' => 'index',
                            ],
                        ],
                    ],
                    // Settings (Phase 9): organization, new items, file types, cite and download, items per page.
                    'visual-page-builder-settings' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/vpb-settings',
                            'defaults' => [
                                '__NAMESPACE__' => 'VisualPageBuilder\Controller\Admin',
                                'controller' => 'Settings',
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
                    // Check page: every template's required blocks, before going live.
                    'visual-page-builder-check' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/visual-builder/:site-slug/check',
                            'constraints' => ['site-slug' => '[a-zA-Z0-9_-]+'],
                            'defaults' => [
                                '__NAMESPACE__' => 'VisualPageBuilder\Controller\Admin',
                                'controller' => 'Theme',
                                'action' => 'check',
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
