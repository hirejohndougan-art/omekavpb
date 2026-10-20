<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Validator\Csrf;
use Laminas\View\Model\ViewModel;
use Omeka\Mvc\Exception\NotFoundException;
use VisualPageBuilder\Drafts;
use VisualPageBuilder\ItemForm;
use VisualPageBuilder\Questions;

/**
 * Add an item (Phase 7): three steps, Files, Describe and Where it shows, then Publish or
 * Keep as draft. The item is created as a draft at the start, so each step saves as it goes.
 * A "classic form" switch sends a person to Omeka's own add-item form, and their choice is
 * remembered (per person) until they switch back.
 *
 * Route: admin/visual-page-builder-add  →  /admin/vpb-add?item=ID&step=N
 */
class AddController extends AbstractActionController
{
    public const CSRF_NAME = 'vpb_add_csrf';
    /** Per-person setting: 'friendly' (default) or 'classic'. */
    public const FORM_SETTING = 'vpb_add_form';
    public const STEPS = [1 => 'Files', 2 => 'Describe', 3 => 'Where it shows'];

    public function indexAction()
    {
        $manager = $this->getEvent()->getApplication()->getServiceManager();
        $userSettings = $manager->get('Omeka\Settings\User');
        $query = $this->params()->fromQuery();

        if (isset($query['form'])) {
            $mode = $query['form'] === 'classic' ? 'classic' : 'friendly';
            $userSettings->set(self::FORM_SETTING, $mode);
            return $mode === 'classic' ? $this->classicRedirect() : $this->redirect()->toRoute('admin/visual-page-builder-add');
        }
        if ($userSettings->get(self::FORM_SETTING, 'friendly') === 'classic') {
            return $this->classicRedirect();
        }

        if ($this->getRequest()->isPost()) {
            return $this->act($manager);
        }

        $api = $manager->get('Omeka\ApiManager');
        $conn = $manager->get('Omeka\Connection');
        $itemId = (int) ($query['item'] ?? 0);
        $step = max(1, min(3, (int) ($query['step'] ?? 1)));
        $item = null;
        if ($itemId) {
            try {
                $item = $api->read('items', $itemId)->getContent();
            } catch (NotFoundException $e) {
                $this->messenger()->addError('That item was not found. Start a new one.'); // @translate
            }
        }

        $view = new ViewModel([
            'csrf' => (new Csrf(['name' => self::CSRF_NAME]))->getHash(),
            'item' => $item,
            'itemId' => $item ? (int) $item->id() : 0,
            'step' => $item ? $step : 0,
            'steps' => self::STEPS,
            'classicUrl' => $this->url()->fromRoute('admin/visual-page-builder-add', [], ['query' => ['form' => 'classic']], true),
        ]);
        if ($item) {
            $view->setVariables($this->summary($api, $conn, $item, $manager->get('Omeka\Settings')));
        }
        $view->setTemplate('visual-page-builder/admin/add/index');
        return $view;
    }

    private function classicRedirect()
    {
        return $this->redirect()->toRoute('admin/default', ['controller' => 'item', 'action' => 'add']);
    }

    /** What the screen shows about the item: the values, the preview, and the checklist. */
    private function summary($api, $conn, $item, $settings): array
    {
        $values = [];
        foreach (ItemForm::FIELDS as $field => $term) {
            $values[$field] = implode("\n", array_map(
                fn ($value) => (string) $value->value(),
                $item->value($term, ['all' => true])
            ));
        }
        $sites = [];
        $chosenSites = array_map(fn ($site) => (int) $site->id(), $item->sites());
        foreach ($api->search('sites', ['per_page' => 100])->getContent() as $site) {
            $sites[] = ['id' => $site->id(), 'title' => (string) $site->title(), 'on' => in_array((int) $site->id(), $chosenSites, true)];
        }
        $files = [];
        foreach ($item->media() as $media) {
            $files[] = (string) $media->displayTitle();
        }
        $title = trim($values['title']);
        $checklist = [
            ['label' => 'A title (needed to publish)', 'done' => $title !== '', 'required' => true],
            ['label' => 'A file', 'done' => $files !== [], 'required' => false],
            ['label' => 'A date', 'done' => trim($values['date']) !== '', 'required' => false],
            ['label' => 'Shows on at least one site', 'done' => (bool) array_filter(array_column($sites, 'on')), 'required' => false],
        ];
        return [
            'values' => $values,
            'labels' => Questions::fieldLabels($api, $settings),
            'sites' => $sites,
            'files' => $files,
            'checklist' => $checklist,
            'canPublish' => $title !== '',
            'draft' => Drafts::isDraft($conn, (int) $item->id()),
        ];
    }

    private function act($manager)
    {
        $post = $this->params()->fromPost();
        $api = $manager->get('Omeka\ApiManager');
        $conn = $manager->get('Omeka\Connection');
        $action = (string) ($post['vpb_add_action'] ?? '');
        $itemId = (int) ($post['item'] ?? 0);
        $back = fn (int $step, int $item) => $this->redirect()->toRoute('admin/visual-page-builder-add', [], ['query' => ['item' => $item, 'step' => $step]], true);

        if (!(new Csrf(['name' => self::CSRF_NAME]))->isValid($post[self::CSRF_NAME] ?? '')) {
            $this->messenger()->addError('That form expired. Please try again.'); // @translate
            return $back(max(1, (int) ($post['step'] ?? 1)), $itemId);
        }

        if ($action === 'start') {
            // A draft from the start: nothing is public until Publish, and each step saves as it goes.
            $id = (int) $api->create('items', ['o:is_public' => false, 'dcterms:title' => []])->getContent()->id();
            Drafts::keep($conn, $api, $id);
            return $back(1, $id);
        }

        $item = $itemId ? $api->read('items', $itemId)->getContent() : null;
        if (!$item) {
            $this->messenger()->addError('That item was not found. Start a new one.'); // @translate
            return $this->redirect()->toRoute('admin/visual-page-builder-add');
        }
        $id = (int) $item->id();

        switch ($action) {
            case 'files':
                $files = $this->params()->fromFiles('upload');
                $added = $failed = 0;
                foreach ($this->fileList($files) as $file) {
                    try {
                        $api->create('media', [
                            'o:ingester' => 'upload',
                            'file_index' => 0,
                            'o:item' => ['o:id' => $id],
                        ], ['file' => [0 => $file]]);
                        $added++;
                    } catch (\Exception $e) {
                        // Omeka refuses a file whose type or size is not allowed; say why and keep going.
                        $failed++;
                        $this->messenger()->addError('“' . $file['name'] . '” was not added: ' . $e->getMessage()); // @translate
                    }
                }
                if ($added) {
                    $this->messenger()->addSuccess(sprintf($added === 1 ? 'Added %d file.' : 'Added %d files.', $added)); // @translate
                }
                return $back(2, $id);

            case 'describe':
                $api->update('items', $id, ItemForm::detailsData($post, $api), [], ['isPartial' => true]);
                $this->messenger()->addSuccess('Saved.'); // @translate
                return $back(3, $id);

            case 'publish':
            case 'keep_draft':
            case 'where':
                $sites = array_map(fn ($site) => ['o:id' => (int) $site], (array) ($post['sites'] ?? []));
                $data = ['o:site' => $sites];
                $titleValue = $item->value('dcterms:title');
                $title = $titleValue ? trim((string) $titleValue->value()) : '';
                if ($action === 'publish' && $title === '') {
                    // A title is required to publish. Save the sites and stay on this step.
                    $api->update('items', $id, $data, [], ['isPartial' => true]);
                    $this->messenger()->addError('Add a title on the Describe step, then publish.'); // @translate
                    return $back(3, $id);
                }
                $api->update('items', $id, $data, [], ['isPartial' => true]);
                if ($action === 'publish') {
                    if (!Drafts::publish($conn, $api, $id)) {
                        $api->update('items', $id, ['o:is_public' => true], [], ['isPartial' => true]);
                    }
                    $this->messenger()->addSuccess('Published.'); // @translate
                } elseif ($action === 'keep_draft') {
                    Drafts::keep($conn, $api, $id);
                    $this->messenger()->addSuccess('Kept as a draft. Only staff can see it.'); // @translate
                } else {
                    $this->messenger()->addSuccess('Where it shows is saved.'); // @translate
                    return $back(3, $id);
                }
                return $this->redirect()->toRoute('admin/visual-page-builder-item', ['item-id' => $id]);

            default:
                $this->messenger()->addError('Choose an action first.'); // @translate
                return $back(1, $id);
        }
    }

    /** The uploaded files as a list, whether one file or several were chosen (name="upload[]"). */
    private function fileList($files): array
    {
        if (empty($files)) {
            return [];
        }
        if (isset($files['name'])) {
            $files = [$files];
        }
        return array_values(array_filter($files, fn ($file) => is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK));
    }
}
