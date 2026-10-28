<?php
/**
 * Unit checks for Special Blocks' PHP helpers that don't need a web request:
 * the Normalizer, OnThisDay's date matching, and UpcomingEvents' filtering.
 *
 * Run through tests/special/php-unit.test.js (npm test), or directly:
 *   OMEKA_PATH=/path/to/omeka-s php tests/special/php/unit.php
 */
$omeka = getenv('OMEKA_PATH') ?: '/home/claude/omeka-s';
require $omeka . '/vendor/autoload.php';
spl_autoload_register(function ($class) {
    if (strpos($class, 'VisualPageBuilder\\') === 0) {
        $file = __DIR__ . '/../../../module/VisualPageBuilder/src/' . str_replace('\\', '/', substr($class, strlen('VisualPageBuilder\\'))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

use VisualPageBuilder\Schema\Normalizer;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Site\BlockLayout\OnThisDay;
use VisualPageBuilder\Site\BlockLayout\UpcomingEvents;
use VisualPageBuilder\Stdlib\CalendarFeed;
use VisualPageBuilder\Site\BlockLayout\Announcement;
use VisualPageBuilder\Site\BlockLayout\BrowseByDecade;
use VisualPageBuilder\Site\BlockLayout\Citation;
use VisualPageBuilder\Site\BlockLayout\DidYouKnow;
use VisualPageBuilder\Site\BlockLayout\Embed;
use VisualPageBuilder\Site\BlockLayout\OpenNow;
use VisualPageBuilder\Site\BlockLayout\OralHistory;
use VisualPageBuilder\Site\BlockLayout\PlacesMap;
use VisualPageBuilder\Site\BlockLayout\Timeline;
use VisualPageBuilder\Site\BlockLayout\StoryTimeline;
use VisualPageBuilder\Stdlib\TimelineDate;

$pass = 0;
$fail = 0;
function check($cond, $msg)
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
    } else {
        $fail++;
        echo "  FAIL [php unit] $msg\n";
    }
}

// A purifier stand-in that marks what it was given, so we can see it was used.
$purifier = new class extends \Omeka\Stdlib\HtmlPurifier {
    public function __construct()
    {
    }
    public function purify($html)
    {
        return '[p]' . $html;
    }
};
$n = new Normalizer($purifier);

$schema = [
    ['key' => 'title', 'type' => Schema::TYPE_TEXT, 'label' => 'T'],
    ['key' => 'count', 'type' => Schema::TYPE_NUMBER, 'label' => 'C', 'default' => 6, 'min' => 1, 'max' => 24],
    ['key' => 'mode', 'type' => Schema::TYPE_SELECT, 'label' => 'M', 'options' => ['a' => 'A', 'b' => 'B'], 'default' => 'b'],
    ['key' => 'on', 'type' => Schema::TYPE_CHECKBOX, 'label' => 'O', 'default' => true],
    ['key' => 'link', 'type' => Schema::TYPE_URL, 'label' => 'L'],
    ['key' => 'day', 'type' => Schema::TYPE_DATE, 'label' => 'D'],
    ['key' => 'sites', 'type' => Schema::TYPE_SITES, 'label' => 'S'],
    ['key' => 'item', 'type' => Schema::TYPE_ITEM, 'label' => 'I'],
    ['key' => 'body', 'type' => Schema::TYPE_HTML, 'label' => 'B'],
    ['key' => 'rows', 'type' => Schema::TYPE_LIST, 'label' => 'R', 'max' => 2, 'fields' => [
        ['key' => 'name', 'type' => Schema::TYPE_TEXT, 'label' => 'N'],
        ['key' => 'kind', 'type' => Schema::TYPE_SELECT, 'label' => 'K', 'options' => ['x' => 'X']],
    ]],
];

$d = $n->normalize($schema, []);
check($d === ['title' => '', 'count' => 6, 'mode' => 'b', 'on' => true, 'link' => '', 'day' => '', 'sites' => [], 'item' => null, 'body' => '', 'rows' => []], 'missing keys get defaults: ' . json_encode($d));

$d = $n->normalize($schema, [
    'title' => '  Hi  ', 'count' => '99', 'mode' => 'zzz', 'on' => '0', 'link' => 'javascript:alert(1)', 'day' => '2024-02-30',
    'sites' => ['3', '3', 'x', '-1', 4], 'item' => 'abc', 'body' => '<b>x</b>', 'extra' => 'dropped',
    'rows' => [['name' => 'a', 'kind' => 'x'], ['name' => '', 'kind' => 'x'], ['name' => 'b'], ['name' => 'c'], 'junk'],
]);
check($d['title'] === 'Hi', 'text trimmed');
check($d['count'] === 24, 'number clamped to max');
check($d['mode'] === 'b', 'unknown select value falls back to default');
check($d['on'] === false, 'checkbox "0" is false');
check($d['link'] === '', 'javascript: link removed');
check($d['day'] === '', 'impossible date removed');
check($d['sites'] === [3, 4], 'id list deduplicated and cleaned');
check($d['item'] === null, 'non-numeric id is null');
check($d['body'] === '[p]<b>x</b>', 'html goes through the purifier');
check(!array_key_exists('extra', $d), 'unknown keys dropped');
check(array_column($d['rows'], 'name') === ['a', 'b'], 'list: empty rows dropped, capped at max: ' . json_encode($d['rows']));
check($d['rows'][1]['kind'] === 'x', 'list rows get sub-field defaults');

foreach (['https://a.org/x' => true, 'http://a.org' => true, '/s/dhs/page/x' => true, 'mailto:a@b.org' => true, 'tel:+12815550100' => true,
    '//evil.org' => false, 'javascript:x' => false, 'data:text/html,x' => false, 'page.html' => false, ' JAVASCRIPT:x' => false] as $url => $ok) {
    check((Normalizer::safeUrl($url) !== '') === $ok, "safeUrl($url) " . ($ok ? 'kept' : 'removed'));
}

$june14 = new DateTime('2026-06-14');
check(OnThisDay::needle('day', $june14) === '-06-14', 'on this day: day needle');
check(OnThisDay::needle('month', $june14) === '-06-', 'on this day: month needle');

$events = [
    ['title' => 'Past', 'date' => '2026-06-01', 'end_date' => ''],
    ['title' => 'Ongoing', 'date' => '2026-06-10', 'end_date' => '2026-06-20'],
    ['title' => 'Today', 'date' => '2026-06-14', 'end_date' => ''],
    ['title' => 'Later', 'date' => '2026-07-01', 'end_date' => ''],
    ['title' => 'No date', 'date' => '', 'end_date' => ''],
];
$up = UpcomingEvents::upcoming($events, true, 10, $june14);
check(array_column($up, 'title') === ['Ongoing', 'Today', 'Later'], 'events: past dropped, multi-day kept until it ends, sorted: ' . json_encode(array_column($up, 'title')));
check(count(UpcomingEvents::upcoming($events, false, 2, $june14)) === 2, 'events: count limit');
check(UpcomingEvents::upcoming($events, true, 10, $june14)[0]['end'] instanceof DateTime, 'events: multi-day events get an end date');

// ---- calendar feeds (tests/fixtures/events.ics)
$chicago = new DateTimeZone('America/Chicago');
$ev = CalendarFeed::parse(file_get_contents(__DIR__ . '/../../fixtures/events.ics'), $chicago);
$byTitle = [];
foreach ($ev as $e) {
    $byTitle[$e['title']] = $e;
}
check(array_column($ev, 'title') === ['Old event', 'Railroad Days festival', 'Evening lecture on the interurban', 'Board meeting', 'Bad link'], 'feed: events parsed and sorted, folded lines joined, cancelled dropped: ' . json_encode(array_column($ev, 'title')));
$fest = $byTitle['Railroad Days festival'];
check($fest['date'] === '2099-06-14' && $fest['end_date'] === '2099-06-15' && $fest['time'] === '', 'feed: all-day event, end date is inclusive');
check($fest['location'] === 'The depot, Dickinson' && $fest['description'] === "Two days of history.\nBring the family.", 'feed: escaped text unescaped');
check($fest['url'] === 'https://example.org/railroad-days', 'feed: link kept');
$lec = $byTitle['Evening lecture on the interurban'];
check($lec['date'] === '2099-07-01' && $lec['time'] === '6:00 pm – 7:30 pm', 'feed: UTC times shown in the site time zone: ' . json_encode($lec));
check($byTitle['Board meeting']['time'] === '6:00 pm', 'feed: TZID times converted: ' . $byTitle['Board meeting']['time']);
check($byTitle['Bad link']['url'] === '', 'feed: unsafe links dropped');
check(CalendarFeed::parse('not a calendar') === [], 'feed: junk gives no events');

// ---- v0.7.0 helpers
check(BrowseByDecade::decades(1905, 1931) === [1900, 1910, 1920, 1930], 'decades rounded to tens');
check(BrowseByDecade::decades(1950, 1900) === [1900, 1910, 1920, 1930, 1940, 1950], 'decades: reversed range still works');
check(count(BrowseByDecade::decades(1000, 2100)) === 30, 'decades capped at 30');
$a = ['text' => 'Closed', 'start_date' => '2026-12-24', 'end_date' => '2026-12-26'];
check(!Announcement::isShowing($a, '2026-12-23') && Announcement::isShowing($a, '2026-12-24') && Announcement::isShowing($a, '2026-12-26') && !Announcement::isShowing($a, '2026-12-27'), 'announcement shows only from start through end');
check(Announcement::isShowing(['text' => 'x', 'start_date' => '', 'end_date' => ''], '2026-01-01') && !Announcement::isShowing(['text' => '', 'start_date' => '', 'end_date' => ''], '2026-01-01'), 'announcement: no dates = always; no text = never');
check(Citation::fill('"[Page title]." [Site title], [Organization]. [URL]. Accessed [Date accessed].', ['page' => 'P', 'site' => 'S', 'organization' => 'O', 'url' => 'U', 'date' => 'D']) === '"P." S, O. U. Accessed D.', 'citation placeholders filled');

// ---- v0.11.0: Open now
foreach (['10-4' => [600, 960], '10 am - 4 pm' => [600, 960], '9:30am–5pm' => [570, 1020], '10:00-16:00' => [600, 960], '12-1' => [720, 780], '8 to 12' => [480, 720], 'closed' => null, '' => null, '4 pm - 2 pm' => null] as $in => $want) {
    check(OpenNow::parseRange($in) === $want, "parseRange('$in')");
}
check(OpenNow::formatTime(960) === '4 pm' && OpenNow::formatTime(570) === '9:30 am' && OpenNow::formatTime(720) === '12 pm', 'formatTime');
$hours = ['hours_mon' => '', 'hours_tue' => '10-4', 'hours_wed' => '10-4', 'hours_thu' => '10-4', 'hours_fri' => '10-4', 'hours_sat' => '10-2', 'hours_sun' => '', 'closures' => [['date' => '2026-11-26', 'note' => 'Thanksgiving']]];
$at = function ($s) { return new DateTime($s); };
$st = OpenNow::status($hours, $at('2026-10-06 11:00')); // Tuesday
check($st['open'] && $st['until'] === '4 pm', 'open now until 4 pm: ' . json_encode($st));
$st = OpenNow::status($hours, $at('2026-10-06 08:00'));
check(!$st['open'] && $st['next'] === ['day' => 'today', 'time' => '10 am'], 'closed in the morning, opens today: ' . json_encode($st));
$st = OpenNow::status($hours, $at('2026-10-10 15:00')); // Saturday after 2
check(!$st['open'] && $st['next'] === ['day' => 'Tuesday', 'time' => '10 am'], 'closed Saturday afternoon, opens Tuesday: ' . json_encode($st));
$st = OpenNow::status($hours, $at('2026-10-05 12:00')); // Monday
check(!$st['open'] && $st['next']['day'] === 'tomorrow', 'closed Monday, opens tomorrow');
$st = OpenNow::status($hours, $at('2026-11-26 11:00')); // Thanksgiving Thursday
check(!$st['open'] && $st['note'] === 'Thanksgiving' && $st['next']['day'] === 'tomorrow', 'closed date with its reason: ' . json_encode($st));

// ---- v0.11.1: hours from Visit info lines
check(OpenNow::parseDays('Tuesday to Saturday') === ['tue', 'wed', 'thu', 'fri', 'sat'], 'days: Tuesday to Saturday');
check(OpenNow::parseDays('Tue-Sat') === ['tue', 'wed', 'thu', 'fri', 'sat'], 'days: Tue-Sat');
check(OpenNow::parseDays('Mon, Wed & Fri') === ['mon', 'wed', 'fri'], 'days: Mon, Wed & Fri');
check(OpenNow::parseDays('Weekends') === ['sat', 'sun'] && OpenNow::parseDays('Weekdays') === ['mon', 'tue', 'wed', 'thu', 'fri'] && count(OpenNow::parseDays('Daily')) === 7, 'days: weekends, weekdays, daily');
check(OpenNow::parseDays('Friday to Monday') === ['fri', 'sat', 'sun', 'mon'], 'days: a range across the weekend');
check(OpenNow::parseDays('By appointment') === [], 'days: unreadable gives none');
$h = OpenNow::hoursFromVisitInfo([['days' => 'Tuesday to Friday', 'times' => '10 am to 4 pm'], ['days' => 'Saturday', 'times' => '10-2'], ['days' => 'Sunday', 'times' => 'Closed'], ['days' => 'By appointment', 'times' => 'call us']]);
check($h['hours_tue'] === '10 am to 4 pm' && $h['hours_fri'] === '10 am to 4 pm' && $h['hours_sat'] === '10-2' && $h['hours_sun'] === '' && $h['hours_mon'] === '', 'Visit info rows become weekly hours: ' . json_encode($h));
check(OpenNow::status($h, new DateTime('2026-10-10 11:00'))['open'] && OpenNow::status($h, new DateTime('2026-10-10 11:00'))['until'] === '2 pm', 'open Saturday morning until 2 pm');

// ---- v0.11.0: Embed
$e = function ($u) { $r = Embed::toEmbedUrl($u); return $r ? $r['src'] : null; };
check($e('https://www.youtube.com/watch?v=abc_DEF-123') === 'https://www.youtube-nocookie.com/embed/abc_DEF-123', 'youtube watch');
check($e('https://youtu.be/abc123') === 'https://www.youtube-nocookie.com/embed/abc123', 'youtu.be');
check($e('https://www.youtube.com/playlist?list=PLxyz') === 'https://www.youtube-nocookie.com/embed/videoseries?list=PLxyz', 'youtube playlist');
check($e('https://vimeo.com/123456') === 'https://player.vimeo.com/video/123456', 'vimeo');
check($e('https://docs.google.com/forms/d/e/1FAIpQ/viewform?usp=sf_link') === 'https://docs.google.com/forms/d/e/1FAIpQ/viewform?embedded=true', 'google form');
check($e('https://www.google.com/maps/embed?pb=!1m18!2m3') === 'https://www.google.com/maps/embed?pb=!1m18!2m3', 'google maps embed');
check($e('https://archive.org/details/dickinson_1925') === 'https://archive.org/embed/dickinson_1925', 'internet archive');
foreach (['http://www.youtube.com/watch?v=abc', 'https://evil.example/watch?v=abc', 'https://youtube.com.evil.example/watch?v=x', 'javascript:alert(1)', 'https://www.google.com/search?q=x', 'https://www.youtube.com/watch?v=<script>'] as $bad) {
    check($e($bad) === null, "refused: $bad");
}

// ---- v0.11.0: Did you know?
$facts = [['text' => 'A'], ['text' => ''], ['text' => 'B'], ['text' => 'C']];
$d1 = DidYouKnow::pick($facts, 'daily', 'k', new DateTime('2026-10-01'));
check($d1 === DidYouKnow::pick($facts, 'daily', 'k', new DateTime('2026-10-01')) && $d1['text'] !== '', 'same fact all day, never an empty one');
$seen = [];
for ($i = 0; $i < 30; $i++) { $seen[DidYouKnow::pick($facts, 'daily', 'k', new DateTime('2026-10-01 +' . $i . ' days'))['text']] = true; }
check(count($seen) === 3, 'different facts on different days: ' . implode(',', array_keys($seen)));
check(DidYouKnow::pick([['text' => ' ']], 'daily', 'k') === null, 'no facts, nothing shown');

// ---- v1.1.0: map coordinates and timeline dates
check(PlacesMap::coord('29.4608', 90) === 29.4608 && PlacesMap::coord(' -95.0513 ', 180) === -95.0513 && PlacesMap::coord('29.46°', 90) === 29.46, 'coordinates read');
check(PlacesMap::coord('91', 90) === null && PlacesMap::coord('abc', 90) === null && PlacesMap::coord('', 90) === null && PlacesMap::coord('29,46', 90) === null, 'bad coordinates refused');
check(Timeline::parseDate('1911-06-14') === ['key' => '1911-06-14', 'year' => 1911], 'timeline date: full');
check(Timeline::parseDate('circa 1920') === ['key' => '1920-00-00', 'year' => 1920] && Timeline::parseDate('1920s')['year'] === 1920 && Timeline::parseDate('June 1911')['year'] === 1911, 'timeline date: loose forms');
check(Timeline::parseDate('undated') === null && Timeline::parseDate('12345') === null, 'timeline date: none');

// ---- v1.8.0: oral history times
check(OralHistory::seconds('12:40') === 760.0 && OralHistory::seconds('1:02:15') === 3735.0 && OralHistory::seconds('00:00:01.500') === 1.5 && OralHistory::seconds('nope') === null, 'oral history: times read');
check(OralHistory::cuesFromText("No times at all.\nJust text.") === [], 'oral history: plain transcripts stay plain');
$srt = OralHistory::cuesFromCaptions("1\n00:00:02,000 --> 00:00:03,000\nSRT line\n\n2\n00:00:04,000 --> 00:00:05,000\n<i>Second</i>\n");
check(count($srt) === 2 && $srt[0]['start'] === 2.0 && $srt[1]['text'] === 'Second', 'oral history: SRT caption files');
check(OralHistory::clock(3725) === '1:02:05' && OralHistory::clock(75.5) === '1:15', 'oral history: clock format');

// ---- v2.12.0: Story timeline dates and time bar
$td = function ($s) {
    $p = TimelineDate::parse($s);
    return $p ? TimelineDate::label($p) : null;
};
check($td('1900-09-08') === 'September 8, 1900' && $td('September 8, 1900') === 'September 8, 1900' && $td('8 Sept 1900') === 'September 8, 1900' && $td('9/8/1900') === 'September 8, 1900', 'story timeline date: full dates, written four ways');
check($td('1911-06') === 'June 1911' && $td('June 1911') === 'June 1911' && $td('Sept. 1900') === 'September 1900' && $td('1911') === '1911', 'story timeline date: month and year');
check($td('circa 1920') === 'c. 1920' && $td('ca. 1920') === 'c. 1920' && $td('about 1920') === 'c. 1920', 'story timeline date: approximate');
check($td('1920s') === '1920s' && $td('early 1930s') === 'early 1930s' && $td('1912-1918') === '1912 – 1918' && $td('1912 to 1918') === '1912 – 1918', 'story timeline date: decades and spans');
check($td('500 BC') === '500 BC' && $td('-500') === '500 BC' && TimelineDate::parse('500 BC')['pos'] < TimelineDate::parse('AD 79')['pos'], 'story timeline date: before the common era sorts first');
check($td('') === null && $td('[year]') === null && $td('n.d.') === null && $td('1900-02-30') === null && $td('2/30/1900') === null, 'story timeline date: none or impossible');
$spring = TimelineDate::parse('Spring 1925');
check($spring && $spring['y'] === 1925 && !empty($spring['loose']), 'story timeline date: other text with a year is placed by its year and shown as written');
$june = TimelineDate::parse('June 1911');
check(abs($june['pos'] - 1911.41667) < 1e-4 && abs(TimelineDate::endPos($june) - 1911.5) < 1e-3 && TimelineDate::endPos(TimelineDate::parse('1911')) == 1912.0, 'story timeline date: places and ends on the line');
check(strcmp(TimelineDate::sortKey(TimelineDate::parse('1900-09-08')), TimelineDate::sortKey(TimelineDate::parse('1900-10'))) < 0 && strcmp(TimelineDate::sortKey(TimelineDate::parse('-50')), TimelineDate::sortKey(TimelineDate::parse('50'))) < 0, 'story timeline date: sort keys keep date order');

$sl = function ($pos, $group = '', $end = null) {
    return ['pos' => $pos, 'end' => $end, 'group' => $group];
};
$lay = StoryTimeline::layout([$sl(1900.0), $sl(1900.5), $sl(1901.0), $sl(1950.0, 'Schools'), $sl(1905.0, '', 1915.0)], [['title' => 'Berry years', 'pos' => 1895.0, 'end' => 1925.0]]);
check($lay['min'] < 1895 && $lay['max'] > 1950, 'story timeline bar: covers every event and era, with room');
check($lay['markers'][0]['row'] !== $lay['markers'][1]['row'] && $lay['markers'][1]['row'] !== $lay['markers'][2]['row'], 'story timeline bar: close events stack in rows');
check($lay['markers'][3]['group'] === 1 && $lay['bands'][0]['name'] === '' && $lay['bands'][1]['name'] === 'Schools' && $lay['markers'][3]['row'] >= $lay['bands'][1]['top'], 'story timeline bar: a named row comes after the unnamed one');
check($lay['markers'][4]['width'] > 10 && $lay['markers'][0]['width'] == 0, 'story timeline bar: an event with an end date is a bar');
check($lay['markers'][3]['flip'] === true && $lay['markers'][0]['flip'] === false, 'story timeline bar: a marker at the far right hangs left of its line');
$labeled = array_values(array_filter($lay['ticks'], function ($t) { return $t['labeled']; }));
check(count($labeled) >= 3 && array_reduce($labeled, function ($ok, $t) use ($lay) { return $ok && StoryTimeline::onStep($t['pos'], $lay['shownStep']); }, true), 'story timeline bar: year labels all fall on one step (no 1925 between 1920 and 1930)');
check(count(StoryTimeline::layout(array_map(function ($y) use ($sl) { return $sl((float) $y); }, range(1800, 2000, 2)), [])['ticks']) <= 601, 'story timeline bar: tick count stays reasonable');
check(StoryTimeline::shorten(str_repeat('word ', 200), 50) === rtrim(str_repeat('word ', 10)) . '…', 'story timeline: item descriptions are cut at a word');

echo ($fail ? 'FAIL' : 'ok  ') . " special blocks: php unit: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
