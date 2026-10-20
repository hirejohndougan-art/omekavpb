<?php
// Integration check for deleting a template in the Theme Builder: the page is removed, it is
// no longer listed for its screen, and the other templates stay. Needs the Omeka test install
// with Visual Page Builder active. Run on the test install:
//   OMEKA_PATH=/path/to/omeka php tests/templates/delete-integration.php
$omekaPath = rtrim(getenv('OMEKA_PATH') ?: '/home/claude/omeka-test/omeka', '/');
require $omekaPath . '/bootstrap.php';

use Omeka\Entity\User;
use Omeka\Api\Exception\NotFoundException;
use VisualPageBuilder\Templates\Store;

$app = Omeka\Mvc\Application::init(require $omekaPath . '/application/config/application.config.php');
$services = $app->getServiceManager();
$api = $services->get('Omeka\ApiManager');
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
$before = count(Store::all($site));
$page = Store::create($api, $site, 'media', 'all', null, 'VPBTEST delete');
$pageId = (int) $page->id();
// The site keeps its page list from when it was read, so clear the entity cache and read it again.
$em->clear();
$site = $api->read('sites', $site->id())->getContent();
check('the template is listed before delete', count(array_filter(Store::all($site), fn ($t) => (int) $t["id"] === $pageId)) === 1);

Store::delete($api, $page);
$em->clear();
$site = $api->read('sites', $site->id())->getContent();
$gone = false;
try {
    $api->read('site_pages', $pageId);
} catch (NotFoundException $e) {
    $gone = true;
}
check('the page is gone from the API', $gone);
check('it is no longer listed', count(array_filter(Store::all($site), fn ($t) => (int) $t["id"] === $pageId)) === 0);
check('the other templates are unchanged', count(Store::all($site)) === $before);

echo "delete checks: $pass passed, " . count($fail) . " failed\n";
foreach ($fail as $f) {
    echo "  FAIL: $f\n";
}
exit($fail ? 1 : 0);
