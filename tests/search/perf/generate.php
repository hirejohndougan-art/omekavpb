<?php
// Synthetic VPB Search index rows for timing (plan Phase 3). Items 9000001 and up, which
// aren't real Omeka items. Remove them afterward with the DELETE statements in perf/README.md.
// Usage: php tests/search/perf/generate.php 10000   (database from VPB_DB, default omeka_test)
$db = getenv('VPB_DB') ?: 'omeka_test';
$pdo = new PDO('mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=' . $db, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$N = (int) ($argv[1] ?? 10000);
mt_srand(42);
$pdo->beginTransaction();
$doc = $pdo->prepare('INSERT INTO vpb_search_doc (item_id,is_public,title,search_text,year_start,year_end,indexed,added) VALUES (?,1,?,?,?,?,NOW(),NOW() - INTERVAL ? DAY)');
$term = $pdo->prepare('INSERT IGNORE INTO vpb_search_term (term,item_id,weight) VALUES (?,?,?)');
$fac = $pdo->prepare('INSERT IGNORE INTO vpb_search_facet (item_id,field,value_id) VALUES (?,?,?)');
$val = $pdo->prepare('INSERT INTO vpb_search_facet_value (field,label) VALUES (?,?) ON DUPLICATE KEY UPDATE id = id');
$ids = [];
$fields = ['item_set' => 20, 'class' => 5, 'decade' => 15, 'has_images' => 2, 'has_description' => 2, 'subject' => 60, 'creator' => 300, 'place' => 40];
foreach ($fields as $f => $n) {
    for ($k = 1; $k <= $n; $k++) { $val->execute([$f, "bench $f $k"]); }
}
$lookup = $pdo->query("SELECT id, field, label FROM vpb_search_facet_value WHERE label LIKE 'bench %'")->fetchAll(PDO::FETCH_ASSOC);
$byKey = [];
foreach ($lookup as $r) { $byKey[$r['field']][$r['label']] = (int) $r['id']; }
for ($i = 0; $i < $N; $i++) {
    $id = 9000001 + $i;
    $y = 1880 + mt_rand(0, 140);
    $words = [];
    for ($w = 0; $w < 25; $w++) { $words[] = 'w' . (int) (pow(mt_rand() / mt_getrandmax(), 3) * 2000); }
    $text = implode(' ', $words);
    $doc->execute([$id, "Bench item $i", $text, $y, $y + mt_rand(0, 5), mt_rand(0, 1500)]);
    foreach (array_unique($words) as $wd) { $term->execute([$wd, $id, mt_rand(1, 3)]); }
    $sets = ['item_set' => 'bench item_set ' . mt_rand(1, 20), 'class' => 'bench class ' . mt_rand(1, 5),
        'decade' => 'bench decade ' . (int) (($y - 1880) / 10 + 1), 'has_images' => 'bench has_images ' . mt_rand(1, 2),
        'has_description' => 'bench has_description ' . mt_rand(1, 2), 'creator' => 'bench creator ' . mt_rand(1, 300),
        'place' => 'bench place ' . mt_rand(1, 40)];
    foreach ($sets as $f => $label) {
        $fac->execute([$id, $f, $byKey[$f][$label]]);
    }
    for ($s = 0; $s < 2; $s++) {
        $fac->execute([$id, 'subject', $byKey['subject']['bench subject ' . (int) (pow(mt_rand() / mt_getrandmax(), 2) * 60 + 1)]]);
    }
}
$pdo->commit();
echo "rows: ", $pdo->query('SELECT COUNT(*) FROM vpb_search_doc WHERE item_id >= 9000001')->fetchColumn(), "\n";
echo "terms: ", $pdo->query('SELECT COUNT(*) FROM vpb_search_term WHERE item_id >= 9000001')->fetchColumn(), "\n";
echo "facets: ", $pdo->query('SELECT COUNT(*) FROM vpb_search_facet WHERE item_id >= 9000001')->fetchColumn(), "\n";
