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
// The preview's font stacks must be the theme's own (layout.phtml).
$themeLayout = file_get_contents(__DIR__ . '/../../theme/visual-page-builder-companion/view/layout/layout.phtml');
$themeFontsMatch = true;
foreach (GlobalStyles::FONTS as $key => $stack) {
    $themeFontsMatch = $themeFontsMatch && strpos($themeLayout, "'$key' => '$stack'") !== false;
}
check('preview font stacks match the theme layout', $themeFontsMatch);
check('preview style carries the link color, falling back to primary', strpos(GlobalStyles::previewStyle(['accent_color' => '#123456']), '--pb-link:#123456') !== false);
check('preview style carries the font stacks', strpos(GlobalStyles::previewStyle([]), '--pb-body-font:system-ui') !== false);
// Starter for the list screens: the filters sit in a left column, the results in the right.
$side = \VisualPageBuilder\Templates\Screens::starterBlocks('search-results', 'dhs');
$layouts = array_column($side, 'o:layout');
$group = $side[1] ?? [];
check('the list starter has a two-column block group', ($group['o:layout'] ?? '') === 'blockGroup' && count($group['o:layout_data']['vpb_section']['cols'] ?? []) === 2);
check('the filters are in the left column (0), the results in the right (1)', ($side[2]['o:layout_data']['vpb_col'] ?? null) === 0 && $side[2]['o:layout'] === 'facets' && ($side[5]['o:layout_data']['vpb_col'] ?? null) === 1);
check('the list starter keeps every required block', \VisualPageBuilder\Templates\Required::missing('search-results', $layouts) === []);
check('other starters stay flat', array_column(\VisualPageBuilder\Templates\Screens::starterBlocks('item', 'dhs'), 'o:layout') === ['itemTitle', 'itemMainImage']);
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

// --- Block visibility by device -----------------------------------------
use VisualPageBuilder\Devices;

check('no stored value hides nothing', Devices::fromValue('') === []);
check('devices read in desktop, tablet, phone order', Devices::fromValue('phone,tablet') === ['tablet', 'phone']);
check('unknown device names are ignored', Devices::fromValue('tablet,watch') === ['tablet']);
check('spaces around names are ignored', Devices::fromValue(' phone ') === ['phone']);
$wrapped = Devices::decorate('<div class="block block-html" style="grid-column: 1 / 7"><p>x</p></div>', ['tablet', 'phone']);
check('device classes go on the block wrapper', $wrapped === '<div class="block vpb-hide-tablet vpb-hide-phone block-html" style="grid-column: 1 / 7"><p>x</p></div>', $wrapped);
check('wrapper keeps its own inline style', strpos($wrapped, 'grid-column: 1 / 7') !== false);
check('a block with no settings is unchanged', Devices::decorate('<div class="block x">a</div>', []) === '<div class="block x">a</div>');
check('a block without a wrapper gets one', Devices::decorate('<p>a</p>', ['desktop']) === '<div class="vpb-hide-desktop"><p>a</p></div>');

// Padding on tablet and phone
check('padding is a number of px from 0 to 64', Devices::padValue('12') === 12 && Devices::padValue(8) === 8);
check('padding above 64 is capped', Devices::padValue('99') === 64);
check('blank or negative padding means the theme default', Devices::padValue('') === null && Devices::padValue('-4') === null && Devices::padValue(null) === null);
check('non-numeric padding is ignored', Devices::padValue('12px') === null);
$padded = Devices::decorate('<div class="block block-html"><p>x</p></div>', [], ['tablet' => '12', 'phone' => null]);
check('tablet padding marks the wrapper and sets its custom property', $padded === '<div class="block vpb-has-pad-tablet block-html" style="--vpb-pad-tablet:12px"><p>x</p></div>', $padded);
$both = Devices::decorate('<div class="block block-html" style="color:red">x</div>', ['desktop'], ['phone' => 6]);
check('hidden and padded together, with the existing style kept', $both === '<div class="block vpb-hide-desktop vpb-has-pad-phone block-html" style="--vpb-pad-phone:6px;color:red">x</div>', $both);
check('padding with no wrapper gets a wrapper with the custom property', Devices::decorate('<p>a</p>', [], ['tablet' => 4]) === '<div class="vpb-has-pad-tablet" style="--vpb-pad-tablet:4px"><p>a</p></div>');

// Text size on tablet and phone
check('text size is a percent kept between 60 and 150', Devices::textValue('90') === 90 && Devices::textValue('40') === 60 && Devices::textValue('200') === 150);
check('blank text size means the theme default', Devices::textValue('') === null && Devices::textValue(null) === null);
$sized = Devices::decorate('<div class="block block-html"><p>x</p></div>', [], [], ['phone' => '80']);
check('phone text size marks the wrapper and sets its custom property', $sized === '<div class="block vpb-has-text-phone block-html" style="--vpb-text-phone:80"><p>x</p></div>', $sized);
$all = Devices::decorate('<div class="block block-html" style="color:red">x</div>', ['tablet'], ['phone' => 6], ['tablet' => 120]);
check('hidden, padded and sized together', $all === '<div class="block vpb-hide-tablet vpb-has-text-tablet vpb-has-pad-phone block-html" style="--vpb-text-tablet:120;--vpb-pad-phone:6px;color:red">x</div>', $all);

// --- Filters block settings ---------------------------------------------
use VisualPageBuilder\Search\FacetSettings;
check('a group with no setting in the data is shown', FacetSettings::shown('subject', []));
check('a group switched off is hidden', !FacetSettings::shown('subject', ['show_subject' => '']));
check('a group switched on is shown', FacetSettings::shown('subject', ['show_subject' => '1']));
check('the date setting controls the decade group', !FacetSettings::shown('decade', ['show_date' => '']));
check('a field with no setting (the expanded list keys) is shown', FacetSettings::shown('unknown', []));
check('text values list ten by default', FacetSettings::textShown([]) === 10);
check('text values list between 1 and 100', FacetSettings::textShown(['text_shown' => 0]) === 1 && FacetSettings::textShown(['text_shown' => 500]) === 100);
check('text values list the number given', FacetSettings::textShown(['text_shown' => '25']) === 25);
$opts = [['label' => 'Schools', 'count' => 9], ['label' => 'Farms', 'count' => 4], ['label' => 'depots', 'count' => 1]];
check('count order keeps the order it came in', array_column(FacetSettings::ordered($opts, []), 'label') === ['Schools', 'Farms', 'depots']);
check('A to Z order sorts without regard to case', array_column(FacetSettings::ordered($opts, ['text_order' => 'az']), 'label') === ['depots', 'Farms', 'Schools']);

// --- Report -------------------------------------------------------------
$total = $pass + count($fail);
echo "PHP tests: $pass of $total passed\n";
foreach ($fail as $f) {
    echo "  FAIL: $f\n";
}
exit($fail ? 1 : 0);
