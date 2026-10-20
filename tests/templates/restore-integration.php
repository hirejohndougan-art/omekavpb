<?php
// Integration check for "Put back" in the Theme Builder (plan decision 10): a template that
// lost a required block gets it back, at the end, and a complete template is left alone.
// Needs the Omeka test install with Visual Page Builder active. Run on the test install:
//   OMEKA_PATH=/path/to/omeka php tests/templates/restore-integration.php
// It creates a template for the Item screen, then removes it with SQL.
$omekaPath = rtrim(getenv('OMEKA_PATH') ?: '/home/claude/omeka-test/omeka', '/');
require $omekaPath . '/bootstrap.php';

use Omeka\Entity\User;
use VisualPageBuilder\Templates\Required;
use VisualPageBuilder\Templates\Screens;
use VisualPageBuilder\Templates\Store;

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

$site = $api->search('sites', ['slug' => 'dhs'])->getContent()[0];
$required = Screens::required('item');
check('the Item screen has required blocks to test with', count($required) >= 2, implode(',', $required));

$page = Store::create($api, $site, 'item', 'all', null, 'VPBTEST restore');
$pageId = (int) $page->id();
// Clear Omeka's entity cache first, or a read after a direct SQL change returns the old blocks.
$fresh = function () use ($api, $em, $pageId) {
    $em->clear();
    return $api->read('site_pages', $pageId)->getContent();
};

try {
    check('a new template starts with its required blocks', Required::missing('item', Store::blockTypes($fresh())) === []);
    check('a new template has no page-title block', !in_array('pageTitle', Store::blockTypes($fresh()), true),
        implode(',', Store::blockTypes($fresh())));

    // Remove one required block directly (the editor and API refuse to delete it).
    $lost = $required[0];
    $conn->executeStatement('DELETE FROM site_page_block WHERE page_id = ? AND layout = ?', [$pageId, $lost]);
    $missing = Required::missing('item', Store::blockTypes($fresh()));
    check('removing the block makes it missing', $missing === [$lost], implode(',', $missing));

    $added = Store::restoreRequired($api, $conn, $fresh());
    check('put back adds the missing block', $added === [$lost], implode(',', $added));
    $types = Store::blockTypes($fresh());
    check('the block is back in the template', in_array($lost, $types, true));
    check('the template is complete again', Required::missing('item', $types) === []);
    check('the put back block is last', end($types) === $lost, implode(',', $types));

    check('a complete template is left alone', Store::restoreRequired($api, $conn, $fresh()) === []);
} finally {
    $conn->executeStatement('DELETE FROM site_page_block WHERE page_id = ?', [$pageId]);
    $conn->executeStatement('DELETE FROM site_page WHERE id = ?', [$pageId]);
}

echo "Template integration checks: $pass passed";
if ($fail) {
    echo ", " . count($fail) . " failed:\n - " . implode("\n - ", $fail) . "\n";
    exit(1);
}
echo "\n";
