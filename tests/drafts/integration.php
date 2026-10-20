<?php
// Drafts (Phase 6, plan decision 1): a draft is a private item with a row in vpb_draft.
// Publishing makes it public and removes the row; deleting the item removes the row.
// Needs the Omeka test install with Visual Page Builder active. Run on the test install:
//   OMEKA_PATH=/path/to/omeka php tests/drafts/integration.php
// It makes one item and deletes it.
$omekaPath = rtrim(getenv('OMEKA_PATH') ?: '/home/claude/omeka-test/omeka', '/');
require $omekaPath . '/bootstrap.php';

use Omeka\Entity\User;
use VisualPageBuilder\Drafts;

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
$isPublic = function (int $id) use ($conn): bool {
    return (bool) $conn->fetchOne('SELECT is_public FROM resource WHERE id = ?', [$id]);
};

$item = $api->create('items', ['dcterms:title' => [['type' => 'literal', 'property_id' => 1, '@value' => 'VPBTEST draft']], 'o:is_public' => true])->getContent();
$id = (int) $item->id();
try {
    $before = Drafts::count($conn);
    Drafts::keep($conn, $api, $id);
    check('keeping an item as a draft makes it private', !$isPublic($id));
    check('the draft is listed', Drafts::isDraft($conn, $id));
    check('the draft count goes up by one', Drafts::count($conn) === $before + 1, (string) Drafts::count($conn));

    check('publishing a draft makes it public', Drafts::publish($conn, $api, $id) && $isPublic($id));
    check('publishing removes the draft', !Drafts::isDraft($conn, $id));
    check('publishing something that is not a draft does nothing', !Drafts::publish($conn, $api, $id));

    Drafts::keep($conn, $api, $id);
    $api->delete('items', $id);
    check('deleting the item removes its draft row',
        (int) $conn->fetchOne('SELECT COUNT(*) FROM vpb_draft WHERE item_id = ?', [$id]) === 0);
} finally {
    $conn->executeStatement('DELETE FROM vpb_draft WHERE item_id = ?', [$id]);
    $conn->executeStatement('DELETE FROM item WHERE id = ?', [$id]);
}

echo 'draft checks: ' . $pass . ' passed, ' . count($fail) . " failed\n";
foreach ($fail as $f) {
    echo "FAIL $f\n";
}
exit($fail ? 1 : 0);
