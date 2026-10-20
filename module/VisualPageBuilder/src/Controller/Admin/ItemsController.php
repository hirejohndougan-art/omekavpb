<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Validator\Csrf;
use Laminas\View\Model\ViewModel;
use VisualPageBuilder\Drafts;

/**
 * Items (Phase 6): search, an item set filter, visibility and quick filters (no file,
 * no date, drafts), a list or a grid, and bulk actions: make public, make private,
 * add to an item set, delete (after a confirmation tick). Reads and writes go through
 * Omeka's API, so Omeka's permissions decide what each person sees and can change.
 *
 * Route: admin/visual-page-builder-items  →  /admin/vpb-items
 */
class ItemsController extends AbstractActionController
{
    public const CSRF_NAME = 'vpb_items_csrf';

    public const QUICK = [
        'nofile' => 'No file',
        'nodate' => 'No date',
        'drafts' => 'Drafts',
    ];

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            return $this->bulk();
        }
        $manager = $this->getEvent()->getApplication()->getServiceManager();
        $api = $manager->get('Omeka\ApiManager');
        $conn = $manager->get('Omeka\Connection');
        $query = $this->params()->fromQuery();

        $q = trim((string) ($query['q'] ?? ''));
        $setId = (int) ($query['item_set_id'] ?? 0);
        $visibility = in_array($query['visibility'] ?? '', ['public', 'private'], true) ? $query['visibility'] : 'all';
        $quick = isset(self::QUICK[$query['quick'] ?? '']) ? $query['quick'] : '';
        $view = ($query['view'] ?? '') === 'grid' ? 'grid' : 'list';
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = max(5, min(100, (int) $manager->get('Omeka\Settings')->get('vpb_items_per_page', 25)));

        $params = ['page' => $page, 'per_page' => $perPage, 'sort_by' => 'modified', 'sort_order' => 'desc'];
        // A search goes through the VPB Search index when it is ready (staff see every item, drafts
        // included), in best-match order. Otherwise, or with a quick filter, Omeka's own search runs.
        $indexOrder = null;
        if ($q !== '' && $quick === '' && \VisualPageBuilder\Search\Index::ready($conn, $manager->get('Omeka\Settings'))) {
            $synonyms = \VisualPageBuilder\Search\Synonyms::fromText((string) $manager->get('Omeka\Settings')->get(\VisualPageBuilder\Search\Index::SYNONYMS_SETTING, ''));
            $found = \VisualPageBuilder\Search\Search::run($conn, $q, true, $page, $perPage, $synonyms);
            $indexOrder = array_flip($found['ids']);
            $params = ['page' => 1, 'per_page' => max(1, count($found['ids'])), 'id' => $found['ids'] ?: [0]];
            $indexTotal = (int) $found['total'];
        } elseif ($q !== '') {
            $params['fulltext_search'] = $q;
        }
        if ($setId) {
            $params['item_set_id'] = $setId;
        }
        if ($visibility !== 'all') {
            $params['is_public'] = $visibility === 'public';
        }
        if ($quick === 'nofile') {
            $params['has_media'] = false;
        } elseif ($quick === 'nodate' || $quick === 'drafts') {
            // Ids that match, found in the database (there is no API filter for them).
            if ($quick === 'drafts') {
                $ids = $conn->fetchFirstColumn('SELECT item_id FROM ' . Drafts::TABLE);
            } else {
                $dateId = (int) $conn->fetchOne(
                    'SELECT p.id FROM property p JOIN vocabulary v ON v.id = p.vocabulary_id WHERE v.prefix = ? AND p.local_name = ?',
                    ['dcterms', 'date']
                );
                $ids = $conn->fetchFirstColumn(
                    'SELECT i.id FROM item i WHERE NOT EXISTS (SELECT 1 FROM value v WHERE v.resource_id = i.id AND v.property_id = ? AND v.value <> \'\')',
                    [$dateId]
                );
            }
            // An empty list means nothing matches: ask for an id that cannot exist.
            $params['id'] = $ids ?: [0];
        }

        $response = $api->search('items', $params);
        $content = $response->getContent();
        if ($indexOrder !== null) {
            // Keep the index's order (best match first) rather than the API's.
            usort($content, fn ($a, $b) => ($indexOrder[$a->id()] ?? 0) <=> ($indexOrder[$b->id()] ?? 0));
        }
        $items = [];
        foreach ($content as $item) {
            $media = $item->primaryMedia();
            $items[] = [
                'id' => $item->id(),
                'title' => (string) $item->displayTitle(),
                'public' => (bool) $item->isPublic(),
                'modified' => $item->modified() ? $item->modified()->format('M j, Y') : '',
                'thumbnail' => $media ? $media->thumbnailUrl('square') : null,
                'url' => $this->url()->fromRoute('admin/visual-page-builder-item', ['item-id' => $item->id()], [], true),
                'draft' => Drafts::isDraft($conn, (int) $item->id()),
            ];
        }
        $total = $indexOrder !== null ? $indexTotal : (int) $response->getTotalResults();
        $sets = [];
        foreach ($api->search('item_sets', ['sort_by' => 'title', 'per_page' => 200])->getContent() as $set) {
            $sets[] = ['id' => $set->id(), 'title' => (string) $set->displayTitle()];
        }

        $view = new ViewModel([
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $perPage)),
            'filters' => ['q' => $q, 'item_set_id' => $setId, 'visibility' => $visibility, 'quick' => $quick, 'view' => $view],
            'quick' => self::QUICK,
            'sets' => $sets,
            'expert' => \VisualPageBuilder\Mode::current($manager) === \VisualPageBuilder\Mode::EXPERT,
            'classicForm' => $manager->get('Omeka\Settings\User')->get(\VisualPageBuilder\Controller\Admin\AddController::FORM_SETTING, 'friendly') === 'classic',
            'csrf' => (new Csrf(['name' => self::CSRF_NAME]))->getHash(),
        ]);
        $view->setTemplate('visual-page-builder/admin/items/index');
        return $view;
    }

    private function bulk()
    {
        $post = $this->params()->fromPost();
        $back = $this->redirect()->toRoute('admin/visual-page-builder-items', [], ['query' => $this->params()->fromQuery()], true);
        if (!(new Csrf(['name' => self::CSRF_NAME]))->isValid($post[self::CSRF_NAME] ?? '')) {
            $this->messenger()->addError('That form expired. Please try again.'); // @translate
            return $back;
        }
        $ids = array_values(array_filter(array_map('intval', (array) ($post['ids'] ?? []))));
        if (!$ids) {
            $this->messenger()->addError('Choose at least one item first.'); // @translate
            return $back;
        }
        $manager = $this->getEvent()->getApplication()->getServiceManager();
        $api = $manager->get('Omeka\ApiManager');
        $conn = $manager->get('Omeka\Connection');
        $action = (string) ($post['vpb_action'] ?? '');
        switch ($action) {
            case 'public':
                $api->batchUpdate('items', $ids, ['o:is_public' => true], ['isPartial' => true]);
                $this->messenger()->addSuccess(sprintf($this->translate('%d items are now public.'), count($ids))); // @translate
                break;
            case 'private':
                $api->batchUpdate('items', $ids, ['o:is_public' => false], ['isPartial' => true]);
                $this->messenger()->addSuccess(sprintf($this->translate('%d items are now staff only.'), count($ids))); // @translate
                break;
            case 'add_set':
                $setId = (int) ($post['item_set_id'] ?? 0);
                if (!$setId) {
                    $this->messenger()->addError('Choose an item set.'); // @translate
                    break;
                }
                $api->batchUpdate('items', $ids, ['o:item_set' => [['o:id' => $setId]]],
                    ['isPartial' => true, 'collectionAction' => 'append']);
                $this->messenger()->addSuccess(sprintf($this->translate('%d items were added to the item set.'), count($ids))); // @translate
                break;
            case 'delete':
                if (empty($post['confirm_delete'])) {
                    $this->messenger()->addError('Tick “I understand” to delete. Nothing was deleted.'); // @translate
                    break;
                }
                foreach ($ids as $id) {
                    $api->delete('items', $id);
                }
                $this->messenger()->addSuccess(sprintf($this->translate('%d items were deleted.'), count($ids))); // @translate
                break;
            default:
                $this->messenger()->addError('Choose an action first.'); // @translate
        }
        return $back;
    }
}
