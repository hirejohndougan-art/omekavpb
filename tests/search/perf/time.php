<?php
// Times VPB Search on the index (synthetic from generate.php, or a copy of the DHS collection).
// Median of 5 runs per query. Usage: OMEKA_PATH=/path/to/omeka php tests/search/perf/time.php
$omekaPath = rtrim(getenv('OMEKA_PATH') ?: '/home/claude/omeka-test/omeka', '/');
require $omekaPath . '/bootstrap.php';
use VisualPageBuilder\Search\Search;
use VisualPageBuilder\Search\Synonyms;
$app = Omeka\Mvc\Application::init(require $omekaPath . '/application/config/application.config.php');
$conn = $app->getServiceManager()->get('Omeka\Connection');
$syn = Synonyms::fromText('rr = railroad');

$idOf = fn (string $field, string $label) => (int) $conn->fetchOne('SELECT id FROM vpb_search_facet_value WHERE field = ? AND label = ?', [$field, $label]);
$f3 = ['item_set' => [$idOf('item_set', 'bench item_set 3')], 'decade' => [$idOf('decade', 'bench decade 5')], 'subject' => [$idOf('subject', 'bench subject 2')]];

function median(array $xs): float { sort($xs); return $xs[intdiv(count($xs), 2)]; }
function timeit(callable $fn): array {
    $ms = []; $last = null;
    for ($i = 0; $i < 5; $i++) { $t = microtime(true); $last = $fn(); $ms[] = (microtime(true) - $t) * 1000; }
    return [round(median($ms), 1), $last];
}

$rows = [];
foreach (['w1', 'w1 w2', 'w5 w9 w12', 'w1' . ' 1910s'] as $q) {
    [$ms, $r] = timeit(fn () => Search::run($conn, $q, false, 1, 24, $syn, []));
    $rows[] = ["search: $q", $ms, $r['total'] . ' results'];
}
foreach (['w1', 'w1 w2'] as $q) {
    [$ms, $r] = timeit(fn () => Search::run($conn, $q, false, 1, 24, $syn, $f3));
    $rows[] = ["search with 3 facets: $q", $ms, $r['total'] . ' results'];
}
foreach (['w1', 'w5 w9'] as $q) {
    [$ms, $c] = timeit(fn () => Search::facetCounts($conn, $q, false, $syn, $f3, null));
    $rows[] = ["facet counts (3 facets selected): $q", $ms, count($c) . ' fields'];
}
foreach ($rows as [$name, $ms, $note]) { printf("%-42s %8.1f ms  %s\n", $name, $ms, $note); }
