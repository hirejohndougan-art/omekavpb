<?php
// Item history (Phase 9): a snapshot before each API change, the last 20 kept, and
// restore through the API (which keeps the state it replaces). Needs the Omeka test install
// with Visual Page Builder active. Run on the test install:
//   OMEKA_PATH=/path/to/omeka php tests/revisions/integration.php
// It makes one item and deletes it, with its snapshots.
$omekaPath = rtrim(getenv('OMEKA_PATH') ?: '/home/claude/omeka-test/omeka', '/');
require $omekaPath . '/bootstrap.php';

use Omeka\Entity\User;
use VisualPageBuilder\ItemHistory;

$app = Omeka\Mvc\Application::init(require $omekaPath . '/application/config/application.config.php');
$services = $app->getServiceManager();
$api = $services->get('Omeka\ApiManager');
$conn = $services->get('Omeka\Connection');
$em = $services->get('Omeka\EntityManager');
$services->get('Omeka\AuthenticationService')->getStorage()->write($em->find(User::class, 1));

$pass = 0;
$fail = [];
function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail[] = $name . ($detail !== '' ? " ($detail)" : '');
}
$titleOf = function (int $id) use ($api, $em): string {
    $em->clear();
    return (string) $api->read('items', $id)->getContent()->displayTitle();
};
$title = function (string $t): array {
    return ['dcterms:title' => [['type' => 'literal', 'property_id' => 1, '@value' => $t]]];
};

$item = $api->create('items', $title('VPBTEST history A'))->getContent();
$id = (int) $item->id();
try {
    $api->update('items', $id, $title('VPBTEST history B'));
    $api->update('items', $id, $title('VPBTEST history C'));
    $list = ItemHistory::list($conn, $id);
    check('each change keeps a snapshot', count($list) === 2, (string) count($list));
    $snapshotTitle = function (int $revision) use ($conn): string {
        $data = json_decode((string) $conn->fetchOne('SELECT data FROM ' . ItemHistory::TABLE . ' WHERE id = ?', [$revision]), true);
        return (string) ($data['dcterms:title'][0]['@value'] ?? 'none');
    };
    check('the newest snapshot holds the title before the last change', $snapshotTitle($list[0]['id']) === 'VPBTEST history B', $snapshotTitle($list[0]['id']));
    $oldest = end($list);
    check('the oldest snapshot holds the first title', $snapshotTitle($oldest['id']) === 'VPBTEST history A', $snapshotTitle($oldest['id']));

    check('restore takes a snapshot of the current state too', ItemHistory::restore($conn, $api, $id, $oldest['id']));
    check('restore puts the old title back', $titleOf($id) === 'VPBTEST history A', $titleOf($id));
    check('restore keeps what it replaced', count(ItemHistory::list($conn, $id)) === 3);
    check('a snapshot of another item cannot be restored here', !ItemHistory::restore($conn, $api, $id + 999999, $oldest['id']));

    for ($n = 0; $n < 22; $n++) {
        $api->update('items', $id, $title('VPBTEST history ' . $n));
    }
    check('only the newest 20 snapshots are kept', count(ItemHistory::list($conn, $id)) === ItemHistory::KEEP,
        (string) count(ItemHistory::list($conn, $id)));
} finally {
    $api->delete('items', $id);
    $conn->executeStatement('DELETE FROM ' . ItemHistory::TABLE . ' WHERE item_id = ?', [$id]);
}
check('the test item is gone', $conn->fetchOne('SELECT COUNT(*) FROM item WHERE id = ?', [$id]) == 0);

echo 'item history checks: ' . $pass . ' passed, ' . count($fail) . " failed\n";
foreach ($fail as $f) {
    echo "FAIL $f\n";
}
exit($fail ? 1 : 0);
