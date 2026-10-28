<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Stdlib\ImageSource;
use VisualPageBuilder\Stdlib\ItemSource;
use VisualPageBuilder\Stdlib\TimelineDate;

/**
 * Story timeline, in the manner of Knight Lab's TimelineJS: one large slide
 * at a time (a photo, video, or recording beside its story), with a time bar
 * under it where every event sits at its true place in time. Visitors move
 * with the arrows (which name the next and previous events), by clicking the
 * time bar, with the keyboard, or by swiping, and can zoom the time bar in
 * and out. Events with an end date show as a bar; eras shade the time bar;
 * groups give events their own labeled rows. An optional title slide opens it.
 *
 * Events are typed in, picked from the archive, or added automatically from
 * dated items. The time bar is laid out here for the first paint (and for the
 * editor's preview, which runs no scripts); special-blocks/js/story-timeline.js
 * makes it live and re-stacks it as it zooms. Without JavaScript every slide
 * shows in date order, so nothing is lost.
 */
class StoryTimeline extends AbstractSchemaBlock
{
    const TEMPLATE = 'story-timeline';

    const DESCRIPTION = 'Tells a story one event at a time: a big photo, video, or recording beside the text, with a time bar underneath where each event sits at its place in time. Visitors click through, zoom the time bar, or jump to any event.'; // @translate

    const GOOD_FOR = 'Exhibits, anniversaries, and "how it happened" stories with pictures.'; // @translate

    /** Width the first-paint layout assumes for the time bar, in pixels. */
    const LAYOUT_WIDTH = 900;

    /** Narrowest marker on the time bar, in pixels (a short title fits). */
    const MARKER_WIDTH = 140;

    /** Most rows the time bar stacks per group before markers overlap. */
    const MAX_ROWS = 4;

    public function getLabel()
    {
        return 'Story timeline'; // @translate
    }

    public function schema(): array
    {
        $media = [
            'none' => 'Nothing', // @translate
            'item' => 'The item\'s picture, video, or recording', // @translate
            'asset' => 'Choose an image', // @translate
            'url' => 'A video or map from the web', // @translate
        ];
        $backgrounds = [
            'none' => 'Plain', // @translate
            'tint' => 'Light tint', // @translate
            'dark' => 'Dark', // @translate
            'accent' => 'Accent color', // @translate
            'photo' => 'A photo, darkened', // @translate
        ];
        return array_merge([
            // Title slide.
            ['key' => 'intro_headline', 'type' => Schema::TYPE_TEXT, 'group' => 'Title slide', 'label' => 'Headline', 'info' => 'Optional. Fill in a headline or text to open the timeline with a title slide.'], // @translate
            ['key' => 'intro_text', 'type' => Schema::TYPE_HTML, 'group' => 'Title slide', 'label' => 'Introduction', 'info' => 'Basic HTML is allowed: paragraphs, links, bold, italics.'], // @translate
            ['key' => 'intro_media', 'type' => Schema::TYPE_SELECT, 'group' => 'Title slide', 'default' => 'none', 'label' => 'Picture or video', 'options' => $media], // @translate
            ['key' => 'intro_item_id', 'type' => Schema::TYPE_ITEM, 'group' => 'Title slide', 'label' => 'Item', 'show_if' => ['intro_media' => ['item']]], // @translate
            ['key' => 'intro_asset_id', 'type' => Schema::TYPE_ASSET, 'group' => 'Title slide', 'label' => 'Image', 'show_if' => ['intro_media' => ['asset']]], // @translate
            ['key' => 'intro_url', 'type' => Schema::TYPE_URL, 'group' => 'Title slide', 'label' => 'Address', 'placeholder' => 'YouTube, Vimeo, Internet Archive, or Google Maps', 'show_if' => ['intro_media' => ['url']]], // @translate
            ['key' => 'intro_bg', 'type' => Schema::TYPE_SELECT, 'group' => 'Title slide', 'default' => 'none', 'label' => 'Background', 'options' => $backgrounds], // @translate
            ['key' => 'intro_bg_asset_id', 'type' => Schema::TYPE_ASSET, 'group' => 'Title slide', 'label' => 'Background photo', 'show_if' => ['intro_bg' => ['photo']]], // @translate
            // Events.
            [
                'key' => 'events', 'type' => Schema::TYPE_LIST, 'group' => 'Events', 'summary' => true, 'max' => 200,
                'label' => 'Events', // @translate
                'info' => 'They show in date order, whatever order they\'re listed in. Linking an item fills in an empty title, date, and text.', // @translate
                'fields' => [
                    ['key' => 'title', 'type' => Schema::TYPE_TEXT, 'label' => 'Headline', 'placeholder' => 'What happened'], // @translate
                    ['key' => 'date', 'type' => Schema::TYPE_TEXT, 'label' => 'Date', 'placeholder' => 'e.g. 1900-09-08, June 1911, circa 1920, or 1920s'], // @translate
                    ['key' => 'end_date', 'type' => Schema::TYPE_TEXT, 'label' => 'End date (optional)', 'placeholder' => 'For something that lasted, e.g. 1918'], // @translate
                    ['key' => 'display_date', 'type' => Schema::TYPE_TEXT, 'label' => 'Date as shown (optional)', 'placeholder' => 'e.g. Summer 1925'], // @translate
                    ['key' => 'item_id', 'type' => Schema::TYPE_ITEM, 'label' => 'Link an item from the archive (optional)'], // @translate
                    ['key' => 'media', 'type' => Schema::TYPE_SELECT, 'default' => 'item', 'label' => 'Picture or video', 'options' => $media], // @translate
                    ['key' => 'asset_id', 'type' => Schema::TYPE_ASSET, 'label' => 'Image', 'show_if' => ['media' => ['asset']]], // @translate
                    ['key' => 'media_url', 'type' => Schema::TYPE_URL, 'label' => 'Address', 'placeholder' => 'YouTube, Vimeo, Internet Archive, or Google Maps', 'show_if' => ['media' => ['url']]], // @translate
                    ['key' => 'caption', 'type' => Schema::TYPE_TEXT, 'label' => 'Caption (optional)'], // @translate
                    ['key' => 'credit', 'type' => Schema::TYPE_TEXT, 'label' => 'Credit (optional)', 'placeholder' => 'e.g. Courtesy of the Galveston County Daily News'], // @translate
                    ['key' => 'text', 'type' => Schema::TYPE_HTML, 'label' => 'Story', 'info' => 'Basic HTML is allowed.'], // @translate
                    ['key' => 'group', 'type' => Schema::TYPE_TEXT, 'label' => 'Row on the time bar (optional)', 'placeholder' => 'e.g. Railroad, Schools', 'info' => 'Events with the same row name share a labeled row.'], // @translate
                    ['key' => 'bg', 'type' => Schema::TYPE_SELECT, 'default' => 'none', 'label' => 'Background', 'options' => $backgrounds], // @translate
                    ['key' => 'bg_asset_id', 'type' => Schema::TYPE_ASSET, 'label' => 'Background photo', 'show_if' => ['bg' => ['photo']]], // @translate
                ],
            ],
            // Eras.
            [
                'key' => 'eras', 'type' => Schema::TYPE_LIST, 'group' => 'Eras', 'max' => 20,
                'label' => 'Eras', // @translate
                'info' => 'Optional. Named stretches of time, shaded on the time bar (e.g. "The railroad years").', // @translate
                'fields' => [
                    ['key' => 'title', 'type' => Schema::TYPE_TEXT, 'label' => 'Name'], // @translate
                    ['key' => 'start', 'type' => Schema::TYPE_TEXT, 'label' => 'From', 'placeholder' => 'e.g. 1890'], // @translate
                    ['key' => 'end', 'type' => Schema::TYPE_TEXT, 'label' => 'To', 'placeholder' => 'e.g. 1935'], // @translate
                ],
            ],
        ], ItemSource::fields(true, false), [
            ['key' => 'use_items', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Source', 'default' => false, 'label' => 'Also add dated items from the archive automatically'], // @translate
            ['key' => 'date_property', 'type' => Schema::TYPE_TEXT, 'group' => 'Source', 'default' => 'dcterms:date', 'label' => 'Date field', 'info' => 'The property holding each item\'s date, as prefix:name.', 'show_if' => ['use_items' => [true, '1', 1]]], // @translate
            ['key' => 'count', 'type' => Schema::TYPE_NUMBER, 'group' => 'Source', 'default' => 40, 'min' => 1, 'max' => 200, 'label' => 'Most items', 'show_if' => ['use_items' => [true, '1', 1]]], // @translate
            [
                'key' => 'item_rows', 'type' => Schema::TYPE_SELECT, 'group' => 'Source', 'default' => 'none', 'label' => 'Rows for these items', // @translate
                'options' => ['none' => 'No separate row', 'item_set' => 'A row for each item set', 'class' => 'A row for each kind of item'], // @translate
                'show_if' => ['use_items' => [true, '1', 1]],
            ],
            // Display.
            ['key' => 'height', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'medium', 'label' => 'Slide height', 'options' => ['short' => 'Short', 'medium' => 'Medium', 'tall' => 'Tall']], // @translate
            ['key' => 'media_side', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'left', 'label' => 'Picture side', 'options' => ['left' => 'Left of the text', 'right' => 'Right of the text', 'top' => 'Above the text']], // @translate
            ['key' => 'nav_position', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'bottom', 'label' => 'Time bar', 'options' => ['bottom' => 'Under the slides', 'top' => 'Above the slides']], // @translate
            ['key' => 'zoom', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => '1', 'label' => 'Time bar starts', 'options' => ['1' => 'Showing the whole timeline', '2' => 'Zoomed in', '4' => 'Zoomed in close']], // @translate
            ['key' => 'start_at', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'first', 'label' => 'Opens at', 'options' => ['first' => 'The beginning', 'last' => 'The most recent event']], // @translate
            ['key' => 'links', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Display', 'default' => true, 'label' => 'Give each event its own link (to share or bookmark)'], // @translate
        ]);
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $slides = [];
        $linked = [];
        foreach ($data['events'] as $e) {
            $item = $this->oneItem((int) $e['item_id'], $site);
            $when = $e['date'] !== '' ? $e['date'] : ($item ? (string) $item->value('dcterms:date') : '');
            $date = TimelineDate::parse($when);
            $title = $e['title'] !== '' ? $e['title'] : ($item ? (string) $item->displayTitle() : '');
            if (!$date || $title === '') {
                continue;
            }
            if ($item) {
                $linked[$item->id()] = true;
            }
            $end = $e['end_date'] !== '' ? TimelineDate::parse($e['end_date']) : null;
            $text = $e['text'];
            if ($text === '' && $item && ($desc = (string) $item->value('dcterms:description')) !== '') {
                $text = '<p>' . $view->escapeHtml(self::shorten($desc, 600)) . '</p>';
            }
            $slides[] = $this->slide($view, $site, $date, $end, $when, $e['display_date'], $title, $text, $item, $e['media'], (int) $e['asset_id'], $e['media_url'], $e['caption'], $e['credit'], trim($e['group']), $e['bg'], (int) $e['bg_asset_id']);
        }
        if ($data['use_items']) {
            $term = preg_match('/^[a-z0-9_-]+:[A-Za-z0-9_-]+$/i', $data['date_property']) ? $data['date_property'] : 'dcterms:date';
            $query = ['property' => [['joiner' => 'and', 'property' => $term, 'type' => 'ex']], 'sort_by' => $term, 'sort_order' => 'asc', 'limit' => $data['count']];
            foreach ($this->itemSource->items($data, $site, $query) as $item) {
                if (isset($linked[$item->id()])) {
                    continue;
                }
                $raw = (string) $item->value($term);
                $date = TimelineDate::parse($raw);
                if (!$date) {
                    continue;
                }
                $desc = (string) $item->value('dcterms:description');
                $text = $desc !== '' ? '<p>' . $view->escapeHtml(self::shorten($desc, 600)) . '</p>' : '';
                $slides[] = $this->slide($view, $site, $date, null, $raw, '', (string) $item->displayTitle(), $text, $item, 'item', 0, '', '', '', $this->itemRow($item, $data['item_rows']), 'none', 0, $data);
            }
        }
        usort($slides, function ($a, $b) {
            return strcmp($a['sort'], $b['sort']) ?: strcmp($a['title'], $b['title']);
        });

        $intro = null;
        if ($data['intro_headline'] !== '' || $data['intro_text'] !== '') {
            $item = $data['intro_media'] === 'item' ? $this->oneItem((int) $data['intro_item_id'], $site) : null;
            $intro = [
                'title' => $data['intro_headline'], 'text' => $data['intro_text'],
                'media' => $this->media($view, $site, $data['intro_media'], $item, (int) $data['intro_asset_id'], $data['intro_url'], '', '', $data['intro_headline']),
                'bg' => $this->background($view, $data['intro_bg'], (int) $data['intro_bg_asset_id']),
            ];
        }

        $eras = [];
        foreach ($data['eras'] as $era) {
            $from = TimelineDate::parse($era['start']);
            $to = $era['end'] !== '' ? TimelineDate::parse($era['end']) : null;
            if ($era['title'] === '' || !$from) {
                continue;
            }
            $endPos = $to ? TimelineDate::endPos($to['end'] ?? $to) : TimelineDate::endPos($from['end'] ?? $from);
            if ($endPos > $from['pos']) {
                $eras[] = ['title' => $era['title'], 'pos' => $from['pos'], 'end' => $endPos];
            }
        }

        $id = 'vpb-stl-' . preg_replace('/[^a-z0-9]/i', '', $blockKey);
        foreach ($slides as $i => &$s) {
            $s['anchor'] = $id . '-' . ($i + 1) . '-' . self::slug($s['title']);
        }
        unset($s);
        return [
            'slides' => $slides,
            'intro' => $intro,
            'axis' => $slides ? self::layout($slides, $eras, (float) $data['zoom']) : null,
            'storyId' => $id,
            'isPreview' => $blockKey === 'preview',
        ];
    }

    /** One event's slide. */
    protected function slide(PhpRenderer $view, SiteRepresentation $site, array $date, ?array $end, string $when, string $display, string $title, string $text, ?ItemRepresentation $item, string $mediaKind, int $assetId, string $url, string $caption, string $credit, string $group, string $bg, int $bgAssetId, ?array $itemData = null): array
    {
        // The end: a typed end date, else a span written in the date itself ("1912-1918", "1920s").
        $endPos = null;
        $endDate = $end ? ($end['end'] ?? $end) : ($date['end'] ?? null);
        if ($endDate) {
            $endPos = TimelineDate::endPos($endDate);
            if ($endPos <= $date['pos']) {
                $endPos = null;
            }
        }
        if ($display !== '') {
            $label = $display;
        } elseif (!empty($date['loose'])) {
            $label = trim($when);
        } else {
            $label = TimelineDate::label($date);
            if ($end) {
                $label = TimelineDate::label(array_merge($date, ['end' => null])) . ' – ' . TimelineDate::label(array_merge($end, ['end' => null]));
            }
        }
        $scope = $itemData ?: ['site_scope' => ItemSource::SCOPE_ALL];
        return [
            'sort' => TimelineDate::sortKey($date),
            'pos' => $date['pos'],
            'end' => $endPos,
            'year' => $date['y'] < 0 ? abs($date['y']) . ' BC' : (string) $date['y'],
            'when' => $label,
            'title' => $title,
            'text' => $text,
            'media' => $this->media($view, $site, $mediaKind, $item, $assetId, $url, $caption, $credit, $title),
            'group' => $group,
            'bg' => $this->background($view, $bg, $bgAssetId),
            'url' => $item ? ItemSource::url($item, $scope, $site) : null,
            'itemTitle' => $item ? (string) $item->displayTitle() : null,
        ];
    }

    /**
     * What a slide shows beside its text: an image, a video or audio file,
     * or a web embed (YouTube, Vimeo, Internet Archive, Google Maps), with a
     * caption and credit. Null for none.
     */
    protected function media(PhpRenderer $view, SiteRepresentation $site, string $kind, ?ItemRepresentation $item, int $assetId, string $url, string $caption, string $credit, string $alt): ?array
    {
        $out = null;
        if ($kind === 'item' && $item) {
            foreach ($item->media() as $m) {
                $type = (string) $m->mediaType();
                if ($m->hasOriginal() && (strpos($type, 'video/') === 0 || strpos($type, 'audio/') === 0)) {
                    $out = ['kind' => strpos($type, 'video/') === 0 ? 'video' : 'audio', 'src' => $m->originalUrl(), 'type' => $type, 'poster' => $item->thumbnailDisplayUrl('large')];
                    break;
                }
            }
            if (!$out && ($src = $item->thumbnailDisplayUrl('large'))) {
                $out = ['kind' => 'image', 'src' => $src, 'alt' => (string) $item->displayTitle()];
            }
            if ($out && $caption === '') {
                $caption = (string) $item->displayTitle();
            }
            if ($out && $credit === '') {
                $credit = (string) ($item->value('dcterms:rights') ?: $item->value('dcterms:creator') ?: '');
            }
        } elseif ($kind === 'asset' && $assetId) {
            $src = ImageSource::url($view, ['asset_id' => $assetId]);
            if ($src) {
                $out = ['kind' => 'image', 'src' => $src, 'alt' => $caption !== '' ? $caption : $alt];
            }
        } elseif ($kind === 'url' && $url !== '') {
            $embed = Embed::toEmbedUrl($url);
            if ($embed && in_array($embed['site'], ['YouTube', 'Vimeo', 'Internet Archive', 'Google Maps'], true)) {
                $out = ['kind' => 'embed', 'src' => $embed['src'], 'site' => $embed['site'], 'link' => $url];
            }
        }
        if ($out) {
            $out['caption'] = $caption;
            $out['credit'] = $credit;
        }
        return $out;
    }

    /** A slide background: ['kind' => tint|dark|accent|photo, 'src' => …] or null. */
    protected function background(PhpRenderer $view, string $kind, int $assetId): ?array
    {
        if ($kind === 'photo') {
            $src = $assetId ? ImageSource::url($view, ['asset_id' => $assetId]) : null;
            return $src ? ['kind' => 'photo', 'src' => $src] : ['kind' => 'dark', 'src' => null];
        }
        return in_array($kind, ['tint', 'dark', 'accent'], true) ? ['kind' => $kind, 'src' => null] : null;
    }

    /** The time bar row an item from the archive goes in. */
    protected function itemRow(ItemRepresentation $item, string $rows): string
    {
        if ($rows === 'item_set') {
            $sets = $item->itemSets();
            return $sets ? (string) reset($sets)->displayTitle() : '';
        }
        if ($rows === 'class') {
            $class = $item->resourceClass();
            return $class ? (string) $class->label() : '';
        }
        return '';
    }

    protected function oneItem(int $id, SiteRepresentation $site): ?ItemRepresentation
    {
        if (!$id) {
            return null;
        }
        $found = $this->itemSource->items(['site_scope' => ItemSource::SCOPE_ALL], $site, ['id' => $id, 'limit' => 1]);
        return $found[0] ?? null;
    }

    /**
     * Lays out the time bar for the first paint and the editor's preview:
     * where it starts and ends, each marker's place and row, the eras, the
     * groups, and the tick marks. All places are percentages, so the script
     * can widen the bar to zoom without moving anything; it only re-stacks
     * the rows and picks which ticks to label.
     *
     * Each tick carries the largest step it falls on ("100" for 1900, "1"
     * for 1911, "0.0833" for a month); the bar labels the ticks whose step is
     * at least the step that fits.
     */
    public static function layout(array $slides, array $eras, float $zoom = 1.0): array
    {
        $min = INF;
        $max = -INF;
        foreach ($slides as $s) {
            $min = min($min, $s['pos']);
            $max = max($max, $s['end'] ?? $s['pos'] + 0.0001);
        }
        foreach ($eras as $e) {
            $min = min($min, $e['pos']);
            $max = max($max, $e['end']);
        }
        $span = max($max - $min, 1.0);
        $pad = max($span * 0.04, 0.25);
        $min -= $pad;
        $max += $pad + $span * 0.08; // Room for the last marker's label.
        $span = $max - $min;
        $pct = function (float $pos) use ($min, $span) {
            return round(($pos - $min) / $span * 100, 4);
        };

        // Groups, in the order they first appear; ungrouped events come first.
        $groups = [];
        foreach ($slides as $s) {
            $groups[$s['group']] = true;
        }
        $names = array_keys($groups);
        usort($names, function ($a, $b) {
            return ($a === '' ? 0 : 1) <=> ($b === '' ? 0 : 1);
        });

        $zoom = in_array($zoom, [1.0, 2.0, 4.0], true) ? $zoom : 1.0;
        $width = self::LAYOUT_WIDTH * $zoom;
        $markers = [];
        $bands = [];
        $offset = 0;
        foreach ($names as $g => $name) {
            $rowEnds = [];
            foreach ($slides as $i => $s) {
                if ($s['group'] !== $name) {
                    continue;
                }
                $left = ($s['pos'] - $min) / $span * $width;
                $endPx = $s['end'] !== null ? ($s['end'] - $min) / $span * $width : $left;
                // Near the right end, a marker hangs to the left of its line instead.
                $flip = $s['end'] === null && $left + self::MARKER_WIDTH > $width;
                $right = $flip ? $left : max($left + self::MARKER_WIDTH, $endPx);
                $left = $flip ? $left - self::MARKER_WIDTH : $left;
                $row = 0;
                while ($row < self::MAX_ROWS - 1 && isset($rowEnds[$row]) && $rowEnds[$row] > $left) {
                    $row++;
                }
                $rowEnds[$row] = max($rowEnds[$row] ?? -INF, $right + 6);
                $markers[$i] = [
                    'left' => $pct($s['pos']),
                    'width' => $s['end'] !== null ? $pct($s['end']) - $pct($s['pos']) : 0,
                    'group' => $g,
                    'row' => $offset + $row,
                    'flip' => $flip,
                ];
            }
            $rows = max(1, count($rowEnds));
            $bands[] = ['name' => $name, 'top' => $offset, 'rows' => $rows];
            $offset += $rows;
        }

        // Ticks: the finest step that keeps the count reasonable.
        $steps = [1000, 500, 100, 50, 25, 10, 5, 2, 1, 0.25, 1 / 12];
        $finest = 1000;
        foreach ($steps as $step) {
            if ($span / $step > 600) {
                break;
            }
            $finest = $step;
        }
        $ticks = [];
        $start = floor($min / $finest) * $finest;
        for ($t = $start; $t <= $max + 1e-9; $t += $finest) {
            $v = round($t, 5);
            if ($v < $min) {
                continue;
            }
            $big = $finest;
            foreach ($steps as $step) {
                if ($step >= $finest && self::onStep($v, $step)) {
                    $big = $step;
                    break;
                }
            }
            $ticks[] = ['pos' => $v, 'left' => $pct($v), 'step' => round($big, 4), 'label' => self::tickLabel($v, $big)];
        }
        // The step labeled at first: the smallest that leaves 80px between labels.
        $pxPerYear = $width / $span;
        $shown = 1000;
        foreach (array_reverse($steps) as $step) {
            if ($step >= $finest && $step * $pxPerYear >= 80) {
                $shown = $step;
                break;
            }
        }

        foreach ($ticks as &$tick) {
            $tick['labeled'] = self::onStep($tick['pos'], $shown);
        }
        unset($tick);
        return [
            'min' => round($min, 5), 'max' => round($max, 5),
            'markers' => $markers,
            'bands' => $bands,
            'rows' => $offset,
            'eras' => array_map(function ($e) use ($pct) {
                return ['title' => $e['title'], 'left' => $pct($e['pos']), 'width' => $pct($e['end']) - $pct($e['pos'])];
            }, $eras),
            'ticks' => $ticks,
            'shownStep' => round($shown, 4),
            'zoom' => $zoom,
        ];
    }

    /** Whether a decimal year falls on a step ("1930" on 10, not "1925"). */
    public static function onStep(float $pos, float $step): bool
    {
        return abs($pos / $step - round($pos / $step)) < 1e-4;
    }

    protected static function tickLabel(float $v, float $step): string
    {
        $year = (int) floor($v + 1e-6);
        if ($step >= 1) {
            return $year < 0 ? abs($year) . ' BC' : (string) $year;
        }
        $month = (int) round(($v - $year) * 12) + 1;
        if ($month === 1) {
            return (string) $year;
        }
        return substr(TimelineDate::MONTH_NAMES[min(12, max(1, $month))], 0, 3);
    }

    /** Text cut at a word, with an ellipsis, for item descriptions. */
    public static function shorten(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $cut = mb_substr($text, 0, $max);
        $space = mb_strrpos($cut, ' ');
        return rtrim($space > $max * 0.6 ? mb_substr($cut, 0, $space) : $cut, ' ,;:.') . '…';
    }

    protected static function slug(string $text): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $text), '-'));
        return substr($slug, 0, 40) ?: 'event';
    }
}
