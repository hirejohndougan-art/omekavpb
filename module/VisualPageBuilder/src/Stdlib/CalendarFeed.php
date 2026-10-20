<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Stdlib;

use DateTime;
use DateTimeZone;
use Laminas\Http\Client as HttpClient;
use Throwable;

/**
 * Reads events from a calendar feed (an iCalendar ".ics" address, which
 * Google Calendar, Outlook, and most calendar apps can publish) for the
 * Upcoming events block.
 *
 * - Feeds are fetched by the server, at most once an hour per address, and
 *   kept in files/visual-page-builder/. If a fetch fails, the last good copy
 *   is used; if there is none, the feed is skipped and the page still shows.
 * - Only http(s) addresses, at most 2 MB, 8 second timeout.
 * - Repeating events show their first date only (RRULE is not expanded).
 *
 * Events come back in the block's own event shape: title, date, end_date
 * (YYYY-MM-DD), time, location, description, url.
 */
class CalendarFeed
{
    const CACHE_SECONDS = 3600;
    const MAX_BYTES = 2097152;
    const TIMEOUT = 8;

    /**
     * @var HttpClient
     */
    protected $http;

    /**
     * @var string Folder for cached feeds.
     */
    protected $cacheDir;

    /**
     * @var string Omeka's time zone setting; events are shown in it.
     */
    protected $zone;

    public function __construct(HttpClient $http, string $cacheDir, string $zone = 'UTC')
    {
        $this->http = $http;
        $this->cacheDir = rtrim($cacheDir, '/');
        $this->zone = $zone;
    }

    /**
     * Events from the feed at $url (cached), or [] if it can't be read.
     */
    public function events(string $url): array
    {
        if (!preg_match('~^https?://~i', $url)) {
            return [];
        }
        $file = $this->cacheDir . '/calendar-' . sha1($url . '|' . $this->zone) . '.json';
        $cached = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (is_array($cached) && isset($cached['fetched']) && time() - $cached['fetched'] < self::CACHE_SECONDS) {
            return $cached['events'];
        }
        $text = $this->fetch($url);
        if ($text === null) {
            return is_array($cached) ? $cached['events'] : [];
        }
        try {
            $events = self::parse($text, new DateTimeZone($this->zone));
        } catch (Throwable $e) {
            $events = self::parse($text);
        }
        if (is_dir($this->cacheDir) || @mkdir($this->cacheDir, 0775, true)) {
            @file_put_contents($file, json_encode(['fetched' => time(), 'url' => $url, 'events' => $events]));
        }
        return $events;
    }

    protected function fetch(string $url): ?string
    {
        try {
            $this->http->reset();
            $this->http->setUri($url);
            $this->http->setOptions(['timeout' => self::TIMEOUT, 'maxredirects' => 3]);
            $response = $this->http->send();
            if (!$response->isSuccess()) {
                return null;
            }
            $body = $response->getBody();
            return strlen($body) <= self::MAX_BYTES && strpos($body, 'BEGIN:VCALENDAR') !== false ? $body : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Parse iCalendar text into events, in the site's time zone.
     */
    public static function parse(string $ics, ?DateTimeZone $zone = null): array
    {
        $zone = $zone ?: new DateTimeZone(date_default_timezone_get());
        // Unfold continued lines (they start with a space or tab).
        $lines = preg_split('/\r\n|\n|\r/', preg_replace('/\r?\n[ \t]/', '', $ics));
        $events = [];
        $event = null;
        foreach ($lines as $line) {
            if ($line === 'BEGIN:VEVENT') {
                $event = [];
                continue;
            }
            if ($line === 'END:VEVENT') {
                if ($event !== null) {
                    $row = self::toRow($event, $zone);
                    if ($row) {
                        $events[] = $row;
                    }
                }
                $event = null;
                continue;
            }
            if ($event === null || strpos($line, ':') === false) {
                continue;
            }
            [$head, $value] = explode(':', $line, 2);
            $parts = explode(';', $head);
            $name = strtoupper(array_shift($parts));
            $params = [];
            foreach ($parts as $p) {
                [$k, $v] = array_pad(explode('=', $p, 2), 2, '');
                $params[strtoupper($k)] = trim($v, '"');
            }
            if (!isset($event[$name])) {
                $event[$name] = ['value' => $value, 'params' => $params];
            }
        }
        usort($events, function ($a, $b) {
            return strcmp($a['date'], $b['date']);
        });
        return $events;
    }

    /** One VEVENT as a block event row, or null if it has no usable start. */
    protected static function toRow(array $e, DateTimeZone $zone): ?array
    {
        if (empty($e['DTSTART']['value']) || (isset($e['STATUS']) && strtoupper($e['STATUS']['value']) === 'CANCELLED')) {
            return null;
        }
        $start = self::date($e['DTSTART'], $zone);
        if (!$start) {
            return null;
        }
        $allDay = self::isDateOnly($e['DTSTART']);
        $end = isset($e['DTEND']) ? self::date($e['DTEND'], $zone) : null;
        if ($end && $allDay) {
            $end->modify('-1 day'); // An all-day DTEND is the day after the event.
        }
        $time = '';
        if (!$allDay) {
            $time = $start->format('g:i a') . ($end ? ' – ' . $end->format('g:i a') : '');
        }
        $url = isset($e['URL']) ? trim($e['URL']['value']) : '';
        return [
            'title' => self::text($e['SUMMARY']['value'] ?? ''),
            'date' => $start->format('Y-m-d'),
            'end_date' => $end && $end->format('Y-m-d') > $start->format('Y-m-d') ? $end->format('Y-m-d') : '',
            'time' => $time,
            'location' => self::text($e['LOCATION']['value'] ?? ''),
            'description' => self::text($e['DESCRIPTION']['value'] ?? ''),
            'url' => preg_match('~^https?://~i', $url) ? $url : '',
        ];
    }

    protected static function isDateOnly(array $prop): bool
    {
        return ($prop['params']['VALUE'] ?? '') === 'DATE' || preg_match('/^\d{8}$/', $prop['value']);
    }

    /** A DTSTART/DTEND value as a DateTime in the site's zone. */
    protected static function date(array $prop, DateTimeZone $zone): ?DateTime
    {
        $v = trim($prop['value']);
        try {
            if (preg_match('/^(\d{8})$/', $v)) {
                return DateTime::createFromFormat('!Ymd', $v, $zone) ?: null;
            }
            if (preg_match('/^(\d{8}T\d{6})Z$/', $v, $m)) {
                $d = DateTime::createFromFormat('Ymd\THis', $m[1], new DateTimeZone('UTC'));
                return $d ? $d->setTimezone($zone) : null;
            }
            if (preg_match('/^(\d{8}T\d{6})$/', $v, $m)) {
                $tz = !empty($prop['params']['TZID']) ? new DateTimeZone($prop['params']['TZID']) : $zone;
                $d = DateTime::createFromFormat('Ymd\THis', $m[1], $tz);
                return $d ? $d->setTimezone($zone) : null;
            }
        } catch (Throwable $e) {
            return null; // Unknown TZID or a malformed value.
        }
        return null;
    }

    /** Unescape iCalendar text: \n, \, \; \\ */
    protected static function text(string $v): string
    {
        return trim(strtr($v, ['\\n' => "\n", '\\N' => "\n", '\\,' => ',', '\\;' => ';', '\\\\' => '\\']));
    }
}
