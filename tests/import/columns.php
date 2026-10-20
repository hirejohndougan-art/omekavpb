<?php
// Column matching for the import wizard (Phase 8). Pure PHP: no Omeka needed.
//   php tests/import/columns.php
require __DIR__ . '/../../module/VisualPageBuilder/src/Import/ColumnMatch.php';

use VisualPageBuilder\Import\ColumnMatch;

$pass = 0;
$fail = [];
function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail[] = $name . ($detail !== '' ? " ($detail)" : '');
}

$properties = ['dcterms:title' => 'Title', 'dcterms:date' => 'Date', 'dcterms:creator' => 'Creator', 'dcterms:subject' => 'Subject'];
$rows = ColumnMatch::match(['dcterms:title', 'Date', 'Creater', 'Notes', 'subject'], $properties);

check('a term matches', $rows[0]['status'] === 'Matched' && $rows[0]['term'] === 'dcterms:title');
check('a label matches, ignoring case', $rows[1]['status'] === 'Matched' && $rows[1]['term'] === 'dcterms:date', json_encode($rows[1]));
check('a close misspelling is for a person to check, with the suggestion', $rows[2]['status'] === 'Please check' && $rows[2]['suggestion'] === 'Creator', json_encode($rows[2]));
check('an unknown heading is for a person to check, with no suggestion', $rows[3]['status'] === 'Please check' && $rows[3]['suggestion'] === null, json_encode($rows[3]));
check('a lowercase label matches', $rows[4]['status'] === 'Matched' && $rows[4]['term'] === 'dcterms:subject');
check('a punctuated heading matches its label', ColumnMatch::match(['Date (created)'], ['dcterms:created' => 'Date created'])[0]['status'] === 'Matched');
check('an empty heading is not matched', ColumnMatch::match([''], $properties)[0]['status'] === 'Please check');
check('every heading gets a row, in order', count($rows) === 5 && $rows[4]['heading'] === 'subject');

echo 'column checks: ' . $pass . ' passed, ' . count($fail) . " failed\n";
foreach ($fail as $f) {
    echo "FAIL $f\n";
}
exit($fail ? 1 : 0);
