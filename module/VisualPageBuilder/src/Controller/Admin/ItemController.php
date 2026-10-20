<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Validator\Csrf;
use Laminas\View\Model\ViewModel;
use Omeka\Mvc\Exception\NotFoundException;
use VisualPageBuilder\Drafts;
use VisualPageBuilder\ItemHistory;

/**
 * One item (Phase 6): Details, Where it shows, Files and History tabs, and the actions
 * Keep as draft, Publish, Duplicate and Delete. Every save goes through Omeka's API
 * (so Omeka's permissions apply), and each change keeps a history snapshot.
 *
 * Route: admin/visual-page-builder-item  →  /admin/vpb-item/:item-id
 */
class ItemController extends AbstractActionController
{
    public const CSRF_NAME = 'vpb_item_csrf';

    /** The Details fields (see ItemForm). */
    public const FIELDS = \VisualPageBuilder\ItemForm::FIELDS;

    public const TABS = ['details', 'where', 'files', 'history'];

    public function indexAction()
    {
        $manager = $this->getEvent()->getApplication()->getServiceManager();
        $api = $manager->get('Omeka\ApiManager');
        $conn = $manager->get('Omeka\Connection');
        $id = (int) $this->params('item-id');
        if ($this->getRequest()->isPost()) {
            return $this->act($id, $manager);
        }
        try {
            $item = $api->read('items', $id)->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            throw new NotFoundException('That item was not found.'); // @translate
        }
        $tab = in_array($this->params()->fromQuery('tab'), self::TABS, true) ? $this->params()->fromQuery('tab') : 'details';

        $values = [];
        foreach (self::FIELDS as $field => $term) {
            $lines = [];
            foreach ($item->value($term, ['all' => true, 'default' => []]) as $value) {
                $lines[] = (string) $value->value();
            }
            $values[$field] = implode("\n", $lines);
        }
        $sets = [];
        $chosenSets = array_map(fn ($set) => (int) $set->id(), $item->itemSets());
        foreach ($api->search('item_sets', ['sort_by' => 'title', 'per_page' => 200])->getContent() as $set) {
            $sets[] = ['id' => $set->id(), 'title' => (string) $set->displayTitle(), 'on' => in_array((int) $set->id(), $chosenSets, true)];
        }
        $sites = [];
        $chosenSites = array_map(fn ($site) => (int) $site->id(), $item->sites());
        foreach ($api->search('sites', ['per_page' => 100])->getContent() as $site) {
            $sites[] = ['id' => $site->id(), 'title' => (string) $site->title(), 'on' => in_array((int) $site->id(), $chosenSites, true)];
        }
        $files = [];
        $mainId = $item->primaryMedia() ? (int) $item->primaryMedia()->id() : 0;
        foreach ($item->media() as $media) {
            $files[] = ['id' => $media->id(), 'title' => (string) $media->displayTitle(), 'type' => (string) $media->mediaType(), 'main' => (int) $media->id() === $mainId];
        }
        $snapshots = [];
        foreach (ItemHistory::list($conn, $id) as $snapshot) {
            $snapshots[] = $snapshot + ['when' => (new \DateTime($snapshot['saved']))->format('M j, Y g:i a')];
        }

        $view = new ViewModel([
            'item' => $item,
            'id' => $id,
            'tab' => $tab,
            'title' => (string) $item->displayTitle(),
            'public' => (bool) $item->isPublic(),
            'draft' => Drafts::isDraft($conn, $id),
            'values' => $values,
            'labels' => \VisualPageBuilder\Questions::fieldLabels($api, $manager->get('Omeka\Settings')),
            'expert' => \VisualPageBuilder\Mode::current($manager) === \VisualPageBuilder\Mode::EXPERT,
            'standardUrl' => $this->url()->fromRoute('admin/id', ['controller' => 'item', 'action' => 'edit', 'id' => $id], [], true),
            'sets' => $sets,
            'sites' => $sites,
            'files' => $files,
            'snapshots' => $snapshots,
            'csrf' => (new Csrf(['name' => self::CSRF_NAME]))->getHash(),
        ]);
        $view->setTemplate('visual-page-builder/admin/item/index');
        return $view;
    }

    private function act(int $id, $manager)
    {
        $post = $this->params()->fromPost();
        $tab = in_array($post['tab'] ?? '', self::TABS, true) ? $post['tab'] : 'details';
        $back = $this->redirect()->toRoute('admin/visual-page-builder-item', ['item-id' => $id], ['query' => ['tab' => $tab]], true);
        if (!(new Csrf(['name' => self::CSRF_NAME]))->isValid($post[self::CSRF_NAME] ?? '')) {
            $this->messenger()->addError('That form expired. Please try again.'); // @translate
            return $back;
        }
        $api = $manager->get('Omeka\ApiManager');
        $conn = $manager->get('Omeka\Connection');
        $action = (string) ($post['vpb_action'] ?? '');
        switch ($action) {
            case 'details':
                $api->update('items', $id, $this->detailsData($post, $manager), [], ['isPartial' => true]);
                $this->messenger()->addSuccess('Details saved.'); // @translate
                break;
            case 'where':
                $data = [
                    'o:is_public' => !empty($post['is_public']),
                    'o:item_set' => array_map(fn ($set) => ['o:id' => (int) $set], (array) ($post['item_sets'] ?? [])),
                    'o:site' => array_map(fn ($site) => ['o:id' => (int) $site], (array) ($post['sites'] ?? [])),
                ];
                $api->update('items', $id, $data, [], ['isPartial' => true]);
                $this->messenger()->addSuccess('Where it shows is saved.'); // @translate
                break;
            case 'remove_file':
                $mediaId = (int) ($post['media_id'] ?? 0);
                $api->delete('media', $mediaId);
                $this->messenger()->addSuccess('File removed.'); // @translate
                $tab = 'files';
                break;
            case 'upload':
                $file = $this->params()->fromFiles('upload');
                if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    $this->messenger()->addError('Choose a file to add first.'); // @translate
                    break;
                }
                try {
                    $api->create('media', [
                        'o:ingester' => 'upload',
                        'file_index' => 0,
                        'o:item' => ['o:id' => $id],
                    ], ['file' => [0 => $file]]);
                    $this->messenger()->addSuccess('File added.'); // @translate
                } catch (\Exception $e) {
                    // Omeka refuses files whose type is not allowed (Settings > Files); say why.
                    $this->messenger()->addError('That file was not added: ' . $e->getMessage()); // @translate
                }
                $tab = 'files';
                break;
            case 'set_main':
                $mediaId = (int) ($post['media_id'] ?? 0);
                $owned = false;
                foreach ($api->read('items', $id)->getContent()->media() as $media) {
                    $owned = $owned || (int) $media->id() === $mediaId;
                }
                if ($owned) {
                    $api->update('items', $id, ['o:primary_media' => ['o:id' => $mediaId]], [], ['isPartial' => true]);
                    $this->messenger()->addSuccess('Main image set.'); // @translate
                } else {
                    $this->messenger()->addError('That file is not on this item.'); // @translate
                }
                $tab = 'files';
                break;
            case 'keep_draft':
                Drafts::keep($conn, $api, $id);
                $this->messenger()->addSuccess('Kept as a draft. Only staff can see it.'); // @translate
                break;
            case 'publish':
                // A draft is made public and stops being a draft; an item that is not a draft is made public.
                if (!Drafts::publish($conn, $api, $id)) {
                    $api->update('items', $id, ['o:is_public' => true], [], ['isPartial' => true]);
                }
                $this->messenger()->addSuccess('Published.'); // @translate
                break;
            case 'restore':
                if (ItemHistory::restore($conn, $api, $id, (int) ($post['revision_id'] ?? 0))) {
                    $this->messenger()->addSuccess('The earlier version is back. The version it replaced is kept in History.'); // @translate
                } else {
                    $this->messenger()->addError('That version was not found.'); // @translate
                }
                $tab = 'history';
                break;
            case 'duplicate':
                $copy = $this->duplicate($api, $conn, $id);
                $this->messenger()->addSuccess('Made a copy. It is a draft, kept staff only.'); // @translate
                return $this->redirect()->toRoute('admin/visual-page-builder-item', ['item-id' => $copy]);
            case 'delete':
                if (empty($post['confirm_delete'])) {
                    $this->messenger()->addError('Tick “I understand” to delete. Nothing was deleted.'); // @translate
                    break;
                }
                $api->delete('items', $id);
                $this->messenger()->addSuccess('Item deleted.'); // @translate
                return $this->redirect()->toRoute('admin/visual-page-builder-items');
            default:
                $this->messenger()->addError('Choose an action first.'); // @translate
        }
        return $this->redirect()->toRoute('admin/visual-page-builder-item', ['item-id' => $id], ['query' => ['tab' => $tab]], true);
    }

    /** The Details form as Omeka values: one literal per non-blank line, the others emptied. */
    private function detailsData(array $post, $manager): array
    {
        return \VisualPageBuilder\ItemForm::detailsData($post, $manager->get('Omeka\ApiManager'));
    }

    /** A copy of the item: its values, sites and sets; a draft, so it is staff only. */
    private function duplicate($api, $conn, int $id): int
    {
        $item = $api->read('items', $id)->getContent();
        $data = ItemHistory::valuesOf($item);
        $data['o:is_public'] = false;
        $data['o:site'] = array_map(fn ($site) => ['o:id' => $site->id()], $item->sites());
        $copy = $api->create('items', $data)->getContent();
        Drafts::keep($conn, $api, (int) $copy->id());
        return (int) $copy->id();
    }
}
