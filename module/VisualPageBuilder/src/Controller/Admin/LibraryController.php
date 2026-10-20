<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Validator\Csrf;
use Laminas\View\Model\ViewModel;

/**
 * Files and Item sets (Phase 8): every file in the installation, filtered by kind,
 * with a link to its item; and item sets as cards with counts and a sample image,
 * with a form to make a new set. Reads and writes go through Omeka's API, so
 * Omeka's own permissions decide what each person sees and can change.
 *
 * Routes: admin/visual-page-builder-files  →  /admin/vpb-files
 *         admin/visual-page-builder-item-sets  →  /admin/vpb-item-sets
 */
class LibraryController extends AbstractActionController
{
    public const CSRF_NAME = 'vpb_library_csrf';
    public const PER_PAGE = 24;

    /** Kinds of file, by media type pattern (SQL LIKE). "other" is everything else. */
    public const KINDS = [
        'all' => ['label' => 'All files', 'like' => null],
        'images' => ['label' => 'Images', 'like' => 'image/%'],
        'pdf' => ['label' => 'PDF', 'like' => 'application/pdf'],
        'audio' => ['label' => 'Audio', 'like' => 'audio/%'],
        'video' => ['label' => 'Video', 'like' => 'video/%'],
        'other' => ['label' => 'Other', 'like' => null],
    ];

    public function filesAction()
    {
        $kind = (string) $this->params()->fromQuery('kind', 'all');
        if (!isset(self::KINDS[$kind])) {
            $kind = 'all';
        }
        $page = max(1, (int) $this->params()->fromQuery('page', 1));
        $conn = $this->connection();
        [$where, $params] = $this->kindWhere($kind);
        $total = (int) $conn->fetchOne('SELECT COUNT(*) FROM media' . $where, $params);
        $ids = $conn->fetchFirstColumn(
            'SELECT id FROM media' . $where . ' ORDER BY id DESC LIMIT ' . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE),
            $params
        );
        $files = [];
        foreach ($ids as $id) {
            $media = $this->api()->read('media', (int) $id)->getContent();
            $item = $media->item();
            $files[] = [
                'id' => $media->id(),
                'title' => (string) $media->displayTitle(),
                'type' => (string) $media->mediaType(),
                'thumbnail' => $media->thumbnailUrl('square'),
                'itemId' => $item ? $item->id() : null,
                'itemTitle' => $item ? (string) $item->displayTitle() : '',
                'itemUrl' => $item ? $item->adminUrl() : null,
            ];
        }
        return new ViewModel([
            'files' => $files,
            'kinds' => self::KINDS,
            'kind' => $kind,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / self::PER_PAGE)),
            'total' => $total,
        ]);
    }

    public function itemSetsAction()
    {
        if ($this->getRequest()->isPost()) {
            return $this->createSet();
        }
        $sets = [];
        foreach ($this->api()->search('item_sets', ['sort_by' => 'title', 'per_page' => 200])->getContent() as $set) {
            $items = $this->api()->search('items', ['item_set_id' => $set->id(), 'per_page' => 1, 'sort_by' => 'created', 'sort_order' => 'desc'])->getContent();
            $sample = $items ? $items[0]->primaryMedia() : null;
            $sets[] = [
                'id' => $set->id(),
                'title' => (string) $set->displayTitle(),
                'count' => $this->api()->search('items', ['item_set_id' => $set->id(), 'per_page' => 1])->getTotalResults(),
                'sample' => $sample ? $sample->thumbnailUrl('medium') : null,
                'itemsUrl' => $this->url()->fromRoute('admin/default', ['controller' => 'item', 'action' => 'browse'], ['query' => ['item_set_id' => $set->id()]], true),
            ];
        }
        return new ViewModel([
            'sets' => $sets,
            'csrf' => (new Csrf(['name' => self::CSRF_NAME]))->getHash(),
        ]);
    }

    private function createSet()
    {
        $post = $this->params()->fromPost();
        if (!(new Csrf(['name' => self::CSRF_NAME]))->isValid($post[self::CSRF_NAME] ?? '')) {
            $this->messenger()->addError('That form expired. Please try again.'); // @translate
            return $this->redirect()->toRoute('admin/visual-page-builder-item-sets');
        }
        $title = trim((string) ($post['title'] ?? ''));
        if ($title === '') {
            $this->messenger()->addError('Give the item set a name.'); // @translate
            return $this->redirect()->toRoute('admin/visual-page-builder-item-sets');
        }
        $this->api()->create('item_sets', [
            'dcterms:title' => [['type' => 'literal', 'property_id' => $this->propertyId('dcterms:title'), '@value' => $title]],
            'dcterms:description' => trim((string) ($post['description'] ?? '')) === '' ? [] : [[
                'type' => 'literal', 'property_id' => $this->propertyId('dcterms:description'),
                '@value' => trim((string) $post['description']),
            ]],
            'o:is_public' => !empty($post['is_public']),
        ]);
        $this->messenger()->addSuccess(sprintf($this->translate('Made the item set "%s".'), $title)); // @translate
        return $this->redirect()->toRoute('admin/visual-page-builder-item-sets');
    }

    /** SQL condition for a kind of file. */
    private function kindWhere(string $kind): array
    {
        if ($kind === 'all') {
            return ['', []];
        }
        if ($kind === 'other') {
            return [' WHERE (media_type IS NULL OR (media_type NOT LIKE ? AND media_type NOT LIKE ? AND media_type NOT LIKE ? AND media_type NOT LIKE ?))',
                ['image/%', 'application/pdf', 'audio/%', 'video/%']];
        }
        return [' WHERE media_type LIKE ?', [self::KINDS[$kind]['like']]];
    }

    private function propertyId(string $term): int
    {
        return (int) $this->api()->search('properties', ['term' => $term])->getContent()[0]->id();
    }

    private function connection()
    {
        return $this->getEvent()->getApplication()->getServiceManager()->get('Omeka\Connection');
    }
}
