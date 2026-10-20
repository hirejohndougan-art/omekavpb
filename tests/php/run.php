<?php
// Pure PHP tests for the Theme Builder core (module/VisualPageBuilder/src/Templates).
// Run: php tests/php/run.php   (exit code 1 on any failure)
spl_autoload_register(function ($class) {
    $prefix = 'VisualPageBuilder\\';
    if (strpos($class, $prefix) === 0) {
        $file = __DIR__ . '/../../module/VisualPageBuilder/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

use VisualPageBuilder\Templates\Required;
use VisualPageBuilder\Templates\Resolver;
use VisualPageBuilder\Templates\Screens;

$pass = 0;
$fail = [];
function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
    } else {
        $fail[] = $name . ($detail !== '' ? " ($detail)" : '');
    }
}

// --- Screens -------------------------------------------------------------
check('nine screens', count(Screens::keys()) === 9, (string) count(Screens::keys()));
check('item requires title and main image', Screens::required('item') === ['itemTitle', 'itemMainImage']);
check('browse requires results and pagination', Screens::required('browse-items') === ['results', 'pagination']);
check('header has no required blocks', Screens::required('header') === []);
check('page not found has no required blocks', Screens::required('not-found') === []);
check('unknown screen is not valid', !Screens::exists('nope'));
check('unknown screen has no required blocks', Screens::required('nope') === []);
check('record screens are item, media, item-set', Screens::RECORD_SCREENS === ['item', 'media', 'item-set']);

// --- Required blocks -----------------------------------------------------
check('item template with both required is valid', Required::valid('item', ['itemTitle', 'itemMainImage', 'itemDescription']));
check('item missing main image reports it', Required::missing('item', ['itemTitle']) === ['itemMainImage']);
check('item missing both reports both', Required::missing('item', []) === ['itemTitle', 'itemMainImage']);
check('browse missing pagination reports it', Required::missing('browse-items', ['results']) === ['pagination']);
check('header is always valid', Required::valid('header', []));
check('extra blocks do not matter', Required::valid('browse-items', ['results', 'pagination', 'facets', 'sort']));

// --- Resolver: precedence ------------------------------------------------
$r = new Resolver();
$rec = ['id' => 7, 'resource_template' => 3, 'item_sets' => [11, 12], 'class' => 20];
$t = fn ($id, $scope, $value, $order = 0, $status = 'live', $screen = 'item') => ['id' => $id, 'screen' => $screen, 'scope' => $scope, 'value' => $value, 'order' => $order, 'status' => $status];

check('no templates means theme layout (null)', $r->choose('item', $rec, []) === null);
check('all-level template matches any record', ($r->choose('item', $rec, [$t(1, 'all', null)]) ?? [])['id'] === 1);
check('record beats resource template', ($r->choose('item', $rec, [$t(1, 'resource-template', 3), $t(2, 'record', 7)]) ?? [])['id'] === 2);
check('resource template beats item set', ($r->choose('item', $rec, [$t(1, 'item-set', 11), $t(2, 'resource-template', 3)]) ?? [])['id'] === 2);
check('item set beats class', ($r->choose('item', $rec, [$t(1, 'class', 20), $t(2, 'item-set', 12)]) ?? [])['id'] === 2);
check('class beats all', ($r->choose('item', $rec, [$t(1, 'all', null), $t(2, 'class', 20)]) ?? [])['id'] === 2);
check('ties go to lowest order', ($r->choose('item', $rec, [$t(1, 'class', 20, 5), $t(2, 'class', 20, 1)]) ?? [])['id'] === 2);
check('draft templates are ignored', ($r->choose('item', $rec, [$t(1, 'record', 7, 0, 'draft'), $t(2, 'all', null)]) ?? [])['id'] === 2);
check('a non-matching specific template falls through to all', ($r->choose('item', $rec, [$t(1, 'record', 99), $t(2, 'all', null)]) ?? [])['id'] === 2);
check('other screens are ignored', $r->choose('item', $rec, [$t(1, 'all', null, 0, 'live', 'media')]) === null);
check('item set match needs membership', $r->choose('item', ['id' => 1, 'item_sets' => [5]], [$t(1, 'item-set', 11)]) === null);
check('resource template match needs the same template', $r->choose('item', ['id' => 1, 'resource_template' => 4], [$t(1, 'resource-template', 3)]) === null);
check('page-wide screen with no record uses all', ($r->choose('header', [], [$t(1, 'all', null, 0, 'live', 'header')]) ?? [])['id'] === 1);
check('page-wide screen ignores record levels', $r->choose('header', [], [$t(1, 'record', 7, 0, 'live', 'header')]) === null);


// --- Global styles ------------------------------------------------------
use VisualPageBuilder\Templates\GlobalStyles;
check('defaults cover every field', count(GlobalStyles::defaults()) === count(GlobalStyles::FIELDS));
check('clean keeps valid colors, lowercased', GlobalStyles::clean(['accent_color' => '#ABCDEF'])['accent_color'] === '#abcdef');
check('bad color falls back to default', GlobalStyles::clean(['accent_color' => 'red'])['accent_color'] === '#0f5e54');
check('blank link color is allowed', GlobalStyles::clean(['link_color' => ''])['link_color'] === '');
check('bad link color falls back to blank', GlobalStyles::clean(['link_color' => 'nope'])['link_color'] === '');
check('unknown select choice falls back', GlobalStyles::clean(['text_size' => 'huge'])['text_size'] === 'medium');
check('values fill missing settings with defaults', GlobalStyles::values([])['corner_style'] === 'soft');
check('values ignore unknown keys', !isset(GlobalStyles::values(['header_color' => '#000000'])['header_color']));
$css = GlobalStyles::cssVars([]);
check('default css has primary', strpos($css, '--pb-accent:#0f5e54') !== false);
check('default link follows primary', strpos($css, '--pb-link:#0f5e54') !== false);
check('default corners are soft (6px)', strpos($css, '--pb-radius:6px') !== false);
check('custom link color is used', strpos(GlobalStyles::cssVars(['link_color' => '#112233']), '--pb-link:#112233') !== false);

// The module's defaults must match the theme's own (theme.ini), so an upgrade doesn't change the look.
$ini = parse_ini_file(__DIR__ . '/../../theme/visual-page-builder-companion/config/theme.ini', true, INI_SCANNER_RAW);
$themeConfig = $ini['config'] ?? [];
$themeDefault = function (string $name) use ($themeConfig) {
    return $themeConfig['elements.' . $name . '.attributes.value'] ?? $themeConfig['elements.' . $name . '.attributes.value'] ?? null;
};
foreach (['accent_color', 'secondary_color', 'text_color', 'background_color', 'link_color', 'heading_font', 'body_font', 'text_size', 'corner_style'] as $field) {
    $theirs = $themeDefault($field);
    $ours = GlobalStyles::FIELDS[$field][2];
    check("theme.ini default for $field matches GlobalStyles", $theirs === $ours || ($theirs === null && $ours === ''), var_export($theirs, true) . ' vs ' . var_export($ours, true));
}

// --- Placeholder (Main image with no file) ------------------------------
use VisualPageBuilder\Templates\Placeholder;
check('kind is the resource template label', Placeholder::kindLabel('Oral history', 'Text') === 'Oral history');
check('kind falls back to the class label', Placeholder::kindLabel(null, 'Document') === 'Document');
check('kind falls back to Item', Placeholder::kindLabel('  ', '') === 'Item');
check('image file is an image', Placeholder::mediaKind('image/jpeg', false) === 'image');
check('audio plays in the spot', Placeholder::mediaKind('audio/mpeg', false) === 'audio');
check('pdf with a thumbnail shows its first page', Placeholder::mediaKind('application/pdf', true) === 'pdf');
check('pdf without a thumbnail is a link', Placeholder::mediaKind('application/pdf', false) === 'other');
check('no media type gives the placeholder panel', Placeholder::mediaKind(null, false) === 'none');
check('unknown type with a thumbnail uses it', Placeholder::mediaKind('video/mp4', true) === 'image');

// --- Report -------------------------------------------------------------
$total = $pass + count($fail);
echo "PHP tests: $pass of $total passed\n";
foreach ($fail as $f) {
    echo "  FAIL: $f\n";
}
exit($fail ? 1 : 0);
