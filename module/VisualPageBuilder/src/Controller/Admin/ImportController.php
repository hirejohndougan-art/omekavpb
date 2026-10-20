<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Validator\Csrf;
use Laminas\View\Model\ViewModel;
use VisualPageBuilder\Import\Wizard;

/**
 * Import (Phase 8): the spreadsheet import wizard. It checks the columns, shows a preview, then
 * starts CSV Import's own job, so the items are made the same way CSV Import makes them.
 * Without CSV Import active, the screen says so and links to the Modules page.
 *
 * Steps (one form each): 1 choose a file, 2 match the columns and see a preview, 3 start.
 * Route: admin/visual-page-builder-import  →  /admin/vpb-import
 */
class ImportController extends AbstractActionController
{
    /** The module this wizard runs on. */
    public const ENGINE = 'CSVImport';
    public const CSRF_NAME = 'vpb_import_csrf';
    /** Prefix of the temporary file, so only files this wizard made can be read back. */
    public const TEMP_PREFIX = 'vpbimport';

    public function indexAction()
    {
        $this->requireEditor();
        if (!$this->engineActive()) {
            return $this->view('engine-off', ['engineActive' => false]);
        }
        if ($this->getRequest()->isPost()) {
            return $this->act();
        }
        return $this->view('choose');
    }

    private function act()
    {
        $post = $this->params()->fromPost();
        $csrf = new Csrf(['name' => self::CSRF_NAME]);
        if (!$csrf->isValid($post[self::CSRF_NAME] ?? '')) {
            $this->messenger()->addError('That form expired. Please try again.'); // @translate
            return $this->redirect()->toRoute('admin/visual-page-builder-import');
        }
        $action = (string) ($post['vpb_import_action'] ?? '');
        if ($action === 'upload') {
            return $this->upload();
        }
        if ($action === 'map') {
            return $this->mapping($post, false);
        }
        if ($action === 'start') {
            return $this->mapping($post, true);
        }
        return $this->redirect()->toRoute('admin/visual-page-builder-import');
    }

    /** Step 1: store the file where CSV Import keeps its own temporary files, then read its columns. */
    private function upload()
    {
        $files = $this->params()->fromFiles('source');
        $file = is_array($files) ? $files : null;
        if (!$file || empty($file['tmp_name']) || ($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
            $this->messenger()->addError('Choose a file to import.'); // @translate
            return $this->redirect()->toRoute('admin/visual-page-builder-import');
        }
        $delimiter = Wizard::delimiter((string) $file['name']);
        if ($delimiter === null) {
            $this->messenger()->addError('Only CSV and TSV files can be imported here. Save the spreadsheet as CSV or TSV.'); // @translate
            return $this->redirect()->toRoute('admin/visual-page-builder-import');
        }
        $path = tempnam($this->tempDir(), self::TEMP_PREFIX);
        if (!$path || !move_uploaded_file($file['tmp_name'], $path)) {
            $this->messenger()->addError('The file could not be saved. Try again.'); // @translate
            return $this->redirect()->toRoute('admin/visual-page-builder-import');
        }
        $preview = Wizard::readPreview($path, $delimiter);
        if (!$preview['headers']) {
            @unlink($path);
            $this->messenger()->addError('The file has no column names in its first row.'); // @translate
            return $this->redirect()->toRoute('admin/visual-page-builder-import');
        }
        return $this->view('map', [
            'token' => basename($path),
            'filename' => (string) $file['name'],
            'delimiter' => $delimiter,
            'columns' => Wizard::matchColumns($preview['headers']),
            'rows' => $preview['rows'],
            'count' => $preview['count'],
        ]);
    }

    /**
     * Steps 2 and 3. $start false: check the mapping and show the preview again. $start true:
     * check it once more and start the job.
     */
    private function mapping(array $post, bool $start)
    {
        $path = $this->tempPath((string) ($post['file'] ?? ''));
        // The temporary file has no extension, so the delimiter comes from the name the file was uploaded with.
        $delimiter = $path ? Wizard::delimiter((string) ($post['filename'] ?? '')) : null;
        if (!$path || $delimiter === null) {
            $this->messenger()->addError('That file is no longer here. Choose it again.'); // @translate
            return $this->redirect()->toRoute('admin/visual-page-builder-import');
        }
        $preview = Wizard::readPreview($path, $delimiter);
        $columns = Wizard::matchColumns($preview['headers']);
        $chosen = [];
        foreach (array_keys($columns) as $index) {
            $field = (string) ($post['column'][$index] ?? '');
            $chosen[$index] = array_key_exists($field, Wizard::fields()) ? $field : null;
            $columns[$index]['field'] = $chosen[$index];
            $columns[$index]['status'] = $chosen[$index] === null ? 'Please check' : 'Matched';
        }
        $duplicate = (string) ($post['duplicate'] ?? 'add');
        $itemSet = (int) ($post['item_set'] ?? 0);
        $public = !empty($post['public']);

        $problems = [];
        if (!in_array('title', $chosen, true)) {
            $problems[] = 'Choose the column for the title. Every item needs one.'; // @translate
        }
        if ($duplicate !== 'add' && !in_array(Wizard::CATALOG, $chosen, true)) {
            $problems[] = 'To update items that already exist, choose the column for the catalog number.'; // @translate
        }
        if ($problems) {
            foreach ($problems as $problem) {
                $this->messenger()->addError($problem);
            }
            return $this->view('map', [
                'token' => basename($path), 'filename' => (string) ($post['filename'] ?? ''),
                'delimiter' => $delimiter, 'columns' => $columns,
                'rows' => $preview['rows'], 'count' => $preview['count'],
                'duplicate' => $duplicate, 'itemSet' => $itemSet, 'public' => $public,
            ]);
        }

        if (!$start) {
            return $this->view('preview', [
                'token' => basename($path), 'filename' => (string) ($post['filename'] ?? ''),
                'delimiter' => $delimiter, 'columns' => $columns, 'chosen' => $chosen,
                'rows' => $preview['rows'], 'count' => $preview['count'],
                'duplicate' => $duplicate, 'itemSet' => $itemSet, 'public' => $public,
                'itemSetTitle' => $itemSet ? $this->itemSetTitle($itemSet) : null,
            ]);
        }

        $args = Wizard::jobArgs([
            'filepath' => $path,
            'delimiter' => $delimiter,
            'mediaType' => Wizard::mediaType($delimiter),
            'columns' => $chosen,
            'duplicate' => $duplicate,
            'public' => $public,
            'itemSet' => $itemSet ?: null,
        ], $this->propertyIds());
        $job = $this->jobDispatcher()->dispatch('CSVImport\Job\Import', $args);
        $this->messenger()->addSuccess('The import has started in the background. The items appear as it runs.'); // @translate
        return $this->view('started', ['jobId' => $job->getId()]);
    }

    private function view(string $step, array $vars = [])
    {
        $view = new ViewModel(array_merge([
            'step' => $step,
            'engineActive' => true,
            'modulesUrl' => $this->url()->fromRoute('admin/default', ['controller' => 'module']),
            'csrf' => (new Csrf(['name' => self::CSRF_NAME]))->getHash(),
            'csrfName' => self::CSRF_NAME,
            'itemSets' => $this->itemSets(),
            'fields' => Wizard::fields(),
            'engineHelp' => $this->url()->fromRoute('admin/default', ['controller' => 'module']),
            'pastImports' => $this->url()->fromRoute('admin/csvimport/past-imports', ['action' => 'browse'], [], true),
            'items' => $this->url()->fromRoute('admin/visual-page-builder-items'),
        ], $vars));
        $view->setTemplate('visual-page-builder/admin/library/import');
        return $view;
    }

    private function requireEditor(): void
    {
        $user = $this->identity();
        if (!$user || !in_array($user->getRole(), ['global_admin', 'site_admin', 'editor'], true)) {
            throw new \Omeka\Mvc\Exception\PermissionDeniedException('You need an editor account to import.'); // @translate
        }
    }

    private function engineActive(): bool
    {
        $modules = $this->getEvent()->getApplication()->getServiceManager()->get('Omeka\ModuleManager');
        $module = $modules->getModule(self::ENGINE);
        // getModule() returns false for a module that is not installed.
        return (bool) $module && $module->getState() === \Omeka\Module\Manager::STATE_ACTIVE;
    }

    private function tempDir(): string
    {
        // The same folder CSV Import uses; without a configured temp_dir, the system temp folder.
        $configured = $this->getEvent()->getApplication()->getServiceManager()->get('Config')['temp_dir'] ?? '';
        return rtrim($configured !== '' ? (string) $configured : sys_get_temp_dir(), '/');
    }

    /** The full path of a temporary file this wizard made, or null. The token is only a file name. */
    private function tempPath(string $token): ?string
    {
        $name = basename($token);
        if (!str_starts_with($name, self::TEMP_PREFIX)) {
            return null;
        }
        $path = $this->tempDir() . '/' . $name;
        return is_file($path) ? $path : null;
    }

    /** Property ids for the fields the wizard can fill, by Omeka term. */
    private function propertyIds(): array
    {
        $api = $this->api();
        $ids = [];
        foreach (Wizard::fields() as $term) {
            $found = $api->search('properties', ['term' => $term])->getContent();
            if ($found) {
                $ids[$term] = $found[0]->id();
            }
        }
        return $ids;
    }

    private function itemSets(): array
    {
        $out = [];
        foreach ($this->api()->search('item_sets', ['sort_by' => 'title', 'per_page' => 500])->getContent() as $set) {
            $out[$set->id()] = $set->displayTitle();
        }
        return $out;
    }

    private function itemSetTitle(int $id): ?string
    {
        try {
            return $this->api()->read('item_sets', $id)->getContent()->displayTitle();
        } catch (\Exception $e) {
            return null;
        }
    }
}
