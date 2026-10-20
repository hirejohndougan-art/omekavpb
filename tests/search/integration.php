<?php
// Integration checks for VPB Search against a real Omeka S install (plan Phase 3).
// Needs an Omeka S 4.x install with Visual Page Builder active. Not part of `npm test`
// (which has no Omeka); run it on the test install:
//   OMEKA_PATH=/path/to/omeka php tests/search/integration.php
// It creates a few items, checks the index and search results, then deletes them.
$omekaPath = rtrim(getenv('OMEKA_PATH') ?: '/home/claude/omeka-test/omeka', '/');
require $omekaPath . '/bootstrap.php';

use Omeka\Entity\User;
use VisualPageBuilder\Search\Index;
use VisualPageBuilder\Search\Search;
use VisualPageBuilder\Search\Synonyms;

$app = Omeka\Mvc\Application::init(require $omekaPath . '/application/config/application.config.php');
$services = $app->getServiceManager();
$api = $services->get('Omeka\ApiManager');
$conn = $services->get('Omeka\Connection');
$em = $services->get('Omeka\EntityManager');

// Act as the first admin user, since API writes need an identity.
$admin = $em->find(User::class, 1);
$services->get('Omeka\AuthenticationService')->getStorage()->write($admin);

$pass = 0;
$fail = [];
function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail[] = $name . ($detail !== '' ? " ($detail)" : '');
}

function propertyId(object $api, string $term): int
{
    return (int) $api->search('properties', ['term' => $term])->getContent()[0]->id();
}

function lit(int $propertyId, string $text): array
{
    return ['property_id' => $propertyId, 'type' => 'literal', '@value' => $text];
}

Index::createTables($conn);
$title = propertyId($api, 'dcterms:title');
$desc = propertyId($api, 'dcterms:description');
$subject = propertyId($api, 'dcterms:subject');
$date = propertyId($api, 'dcterms:date');
$spatial = propertyId($api, 'dcterms:spatial');

// Clean up from an earlier run, by title prefix.
foreach ($api->search('items', ['property' => [['property' => $title, 'type' => 'in', 'text' => 'VPBTEST']]])->getContent() as $old) {
    $api->delete('items', $old->id());
}

$set = $api->create('item_sets', [
    'o:is_public' => true,
    'dcterms:title' => [lit($title, 'VPBTEST Railroad set')],
])->getContent();

$make = function (array $data) use ($api) {
    return $api->create('items', $data)->getContent();
};

$depot = $make([
    'o:is_public' => true,
    'o:item_set' => [['o:id' => $set->id()]],
    'dcterms:title' => [lit($title, 'VPBTEST Railroad depot at Dickinson')],
    'dcterms:description' => [lit($desc, 'Photograph of the railroad depot')],
    'dcterms:subject' => [lit($subject, 'Railroad')],
    'dcterms:spatial' => [lit($spatial, 'Dickinson, Texas')],
    'dcterms:date' => [lit($date, '1912')],
]);
$interior = $make([
    'o:is_public' => true,
    'dcterms:title' => [lit($title, 'VPBTEST Depot interior')],
    'dcterms:date' => [lit($date, 'about 1910')],
]);
$street = $make([
    'o:is_public' => true,
    'dcterms:title' => [lit($title, 'VPBTEST Galveston street scene')],
    'dcterms:date' => [lit($date, '1920s')],
]);
$staff = $make([
    'o:is_public' => false,
    'dcterms:title' => [lit($title, 'VPBTEST Staff note about depot')],
    'dcterms:date' => [lit($date, '1915')],
]);
$oral = $make([
    'o:is_public' => true,
    'dcterms:title' => [lit($title, 'VPBTEST Oral history of John Smith')],
    'dcterms:description' => [lit($desc, 'Interview about the rail yard')],
    'dcterms:date' => [lit($date, '[year]')],
]);

$syn = Synonyms::fromText("rr = railroad\nstation = depot");

$public = fn (string $q, array $facets = []) => Search::run($conn, $q, false, 1, 50, $syn, $facets);
$staffRun = fn (string $q) => Search::run($conn, $q, true, 1, 50, $syn);

// Items are indexed as they are saved (listeners).
$indexed = (int) $conn->fetchOne('SELECT COUNT(*) FROM ' . Index::DOC . ' WHERE item_id IN (?,?,?,?,?)',
    [$depot->id(), $interior->id(), $street->id(), $staff->id(), $oral->id()]);
check('all five new items are indexed on save', $indexed === 5, (string) $indexed);
check('staff-only item is marked private', (int) $conn->fetchOne(
    'SELECT is_public FROM ' . Index::DOC . ' WHERE item_id = ?', [$staff->id()]) === 0);

// Plurals: "depots" finds "depot" items, and never the staff-only one for the public.
$r = $public('depots');
check('depots finds the two public depot items', $r['total'] === 2, (string) $r['total']);
check('depots does not show the staff note to visitors', !in_array($staff->id(), $r['ids'], true));
check('staff search does show the staff note', in_array($staff->id(), $staffRun('depots')['ids'], true));

// Partial word while typing: "rail" finds railroad and rail yard.
$r = $public('rail');
check('partial word rail finds railroad and rail yard', $r['total'] === 2, (string) $r['total']);

// Synonyms: "rr" finds railroad items.
$r = $public('rr');
check('synonym rr finds railroad items', in_array($depot->id(), $r['ids'], true));

// Fuzzy dates: "1910s" finds 1912, about 1910 (1905-1915); not 1920s; not staff 1915 for public.
$r = $public('1910s');
check('1910s finds the 1912 and about-1910 items', $r['total'] === 2, (string) $r['total']);
check('1910s does not find the 1920s item', !in_array($street->id(), $r['ids'], true));

// Did you mean.
$r = $public('Dickenson');
check('Dickenson finds nothing', $r['total'] === 0, (string) $r['total']);
check('Dickenson suggests Dickinson (catalog spelling)', $r['suggestion'] === 'Dickinson', (string) $r['suggestion']);
$r = $public('zzzzzzzz');
check('nonsense has no suggestion', $r['suggestion'] === null);

// Live update: an edit shows up in search right away.
$api->update('items', $interior->id(), ['dcterms:title' => [lit($title, 'VPBTEST Trestle bridge')]], [], ['isPartial' => true]);
check('an edit is searchable immediately', $public('trestle')['total'] === 1);

// Delete removes the item from the index.
$api->delete('items', $street->id());
check('a deleted item leaves the index', (int) $conn->fetchOne(
    'SELECT COUNT(*) FROM ' . Index::DOC . ' WHERE item_id = ?', [$street->id()]) === 0);

// Facet by item set.
$r = $public('depot', ['item_set' => [$set->id()]]);
check('item set facet narrows to the set', $r['total'] === 1 && $r['ids'] === [$depot->id()], (string) $r['total']);

// Text facets: subject and place values are counted and filter the results.
$valueId = fn (string $field, string $label) => (int) $conn->fetchOne(
    'SELECT id FROM ' . Index::FACET_VALUE . ' WHERE field = ? AND label = ?', [$field, $label]);
$railroad = $valueId('subject', 'Railroad');
$counts = Search::facetCounts($conn, 'depot', false, $syn, [], null);
check('a subject value is counted for the matching items', $railroad > 0 && ($counts['subject'][$railroad] ?? 0) === 1,
    (string) ($counts['subject'][$railroad] ?? 'none'));
$r = $public('depot', ['subject' => [$railroad]]);
check('subject facet narrows to the item with that subject', $r['total'] === 1 && $r['ids'] === [$depot->id()], (string) $r['total']);
$place = $valueId('place', 'Dickinson, Texas');
$r = $public('depot', ['place' => [$place]]);
check('place facet narrows to the item with that place', $place > 0 && $r['total'] === 1 && $r['ids'] === [$depot->id()], (string) $r['total']);
check('text facet labels come from the value table', Index::valueLabels($conn, 'subject', [$railroad]) === [$railroad => 'Railroad']);
check('a text value is not offered under another field', Index::valueLabels($conn, 'place', [$railroad]) === []);

// Yes/no facet: has a description. The depot has one; the interior photo doesn't.
$counts = Search::facetCounts($conn, 'depot', false, $syn, [], null);
check('the yes/no facet counts items with a description', ($counts['has_description'][1] ?? 0) === 1, (string) ($counts['has_description'][1] ?? 'none'));
$r = $public('depot', ['has_description' => [1]]);
check('the yes/no facet narrows to items with a description', $r['total'] === 1 && $r['ids'] === [$depot->id()], (string) $r['total']);
check('the yes/no facet is labelled', \VisualPageBuilder\Search\BrowseQuery::labelsFor($api, 'has_description', [1]) === [1 => 'Has a description']);

// Clean up.
foreach ([$depot, $interior, $staff, $oral] as $item) {
    $api->delete('items', $item->id());
}
$api->delete('item_sets', $set->id());

echo "Search integration checks: $pass passed";
if ($fail) {
    echo ", " . count($fail) . " failed:\n - " . implode("\n - ", $fail) . "\n";
    exit(1);
}
echo "\n";
