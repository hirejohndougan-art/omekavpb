<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

/**
 * Home (Phase 6): the first screen people see. A greeting and quick actions; what
 * needs attention, with live counts; the items changed most recently; and the size
 * of the collection. Counts come straight from the database, so they are current.
 *
 * Route: admin/visual-page-builder-home  →  /admin/vpb-home
 */
class HomeController extends AbstractActionController
{
    public function indexAction()
    {
        $manager = $this->getEvent()->getApplication()->getServiceManager();
        $conn = $manager->get('Omeka\Connection');
        $api = $manager->get('Omeka\ApiManager');
        require_once __DIR__ . '/../../Drafts.php';

        $dateId = (int) $conn->fetchOne(
            'SELECT p.id FROM property p JOIN vocabulary v ON v.id = p.vocabulary_id WHERE v.prefix = ? AND p.local_name = ?',
            ['dcterms', 'date']
        );
        $counts = [
            'items' => (int) $conn->fetchOne('SELECT COUNT(*) FROM item'),
            'itemSets' => (int) $conn->fetchOne('SELECT COUNT(*) FROM item_set'),
            'files' => (int) $conn->fetchOne('SELECT COUNT(*) FROM media'),
            'pages' => (int) $conn->fetchOne('SELECT COUNT(*) FROM site_page'),
            'private' => (int) $conn->fetchOne('SELECT COUNT(*) FROM item i JOIN resource r ON r.id = i.id WHERE r.is_public = 0'),
        ];
        // Needs attention: an item with no file, and an item with no date.
        $attention = [
            'noFile' => (int) $conn->fetchOne('SELECT COUNT(*) FROM item i WHERE NOT EXISTS (SELECT 1 FROM media m WHERE m.item_id = i.id)'),
            'drafts' => \VisualPageBuilder\Drafts::count($conn),
            'noDate' => (int) $conn->fetchOne(
                'SELECT COUNT(*) FROM item i WHERE NOT EXISTS (SELECT 1 FROM value v WHERE v.resource_id = i.id AND v.property_id = ? AND v.value <> \'\')',
                [$dateId]
            ),
        ];
        $recent = [];
        foreach ($api->search('items', ['sort_by' => 'modified', 'sort_order' => 'desc', 'per_page' => 8])->getContent() as $item) {
            $recent[] = [
                'id' => $item->id(),
                'title' => (string) $item->displayTitle(),
                'modified' => $item->modified() ? $item->modified()->format('M j, Y') : '',
                'url' => $item->adminUrl(),
            ];
        }
        $user = $this->identity();
        $hour = (int) (new \DateTimeImmutable('now', new \DateTimeZone('America/Chicago')))->format('G');
        $view = new ViewModel([
            'greeting' => self::greeting($hour),
            'name' => $user ? (string) $user->getName() : '',
            'counts' => $counts,
            'attention' => $attention,
            'recent' => $recent,
        ]);
        $view->setTemplate('visual-page-builder/admin/home/index');
        return $view;
    }

    /** "Good morning" and so on, from the hour (0 to 23). */
    public static function greeting(int $hour): string
    {
        if ($hour < 12) {
            return 'Good morning';
        }
        return $hour < 18 ? 'Good afternoon' : 'Good evening';
    }
}
