<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

/**
 * Import (Phase 8): the spreadsheet import wizard, which runs CSV Import underneath.
 * Without CSV Import active, the screen says so and links to the Modules page.
 *
 * Route: admin/visual-page-builder-import  →  /admin/vpb-import
 */
class ImportController extends AbstractActionController
{
    /** The module this wizard runs on. */
    public const ENGINE = 'CSVImport';

    public function indexAction()
    {
        $view = new ViewModel([
            'engineActive' => $this->engineActive(),
            'modulesUrl' => $this->url()->fromRoute('admin/default', ['controller' => 'module']),
        ]);
        $view->setTemplate('visual-page-builder/admin/library/import');
        return $view;
    }

    private function engineActive(): bool
    {
        $modules = $this->getEvent()->getApplication()->getServiceManager()->get('Omeka\ModuleManager');
        $module = $modules->getModule(self::ENGINE);
        // getModule() returns false for a module that is not installed.
        return (bool) $module && $module->getState() === \Omeka\Module\Manager::STATE_ACTIVE;
    }
}
