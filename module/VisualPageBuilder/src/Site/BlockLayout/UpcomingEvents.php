<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use DateTime;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Stdlib\CalendarFeed;

/**
 * Upcoming events: typed into the block, read from a calendar feed (an .ics
 * address from Google Calendar, Outlook…), or both. Past events drop off by
 * themselves. See Stdlib\CalendarFeed for how feeds are fetched and cached.
 */
class UpcomingEvents extends AbstractSchemaBlock
{
    const DESCRIPTION = 'A list of upcoming events. Past events disappear by themselves.'; // @translate

    const GOOD_FOR = 'Home pages and an events page.'; // @translate

    const TEMPLATE = 'upcoming-events';

    public function getLabel()
    {
        return 'Upcoming events'; // @translate
    }

    public function schema(): array
    {
        return [
            DisplayFields::heading('Upcoming events'),
            [
                'key' => 'feed_url', 'type' => Schema::TYPE_URL, 'group' => 'Calendar feed', 'summary' => true,
                'label' => 'Calendar feed address (optional)', // @translate
                'info' => 'An iCalendar (.ics) address. In Google Calendar: Settings › your calendar › "Public address in iCal format". Its events are listed along with any typed below, and checked again every hour. Repeating events show their first date only.', // @translate
                'placeholder' => 'https://….ics',
            ],
            [
                'key' => 'events', 'type' => Schema::TYPE_LIST, 'group' => 'Events', 'summary' => true, 'max' => 50,
                'label' => 'Events', // @translate
                'fields' => [
                    ['key' => 'title', 'type' => Schema::TYPE_TEXT, 'label' => 'Title'], // @translate
                    ['key' => 'date', 'type' => Schema::TYPE_DATE, 'label' => 'Date'], // @translate
                    ['key' => 'end_date', 'type' => Schema::TYPE_DATE, 'label' => 'End date (for events over several days)'], // @translate
                    ['key' => 'time', 'type' => Schema::TYPE_TEXT, 'label' => 'Time', 'placeholder' => 'e.g. 6 to 8 pm'], // @translate
                    ['key' => 'location', 'type' => Schema::TYPE_TEXT, 'label' => 'Location'], // @translate
                    ['key' => 'description', 'type' => Schema::TYPE_TEXTAREA, 'label' => 'Description'], // @translate
                    ['key' => 'url', 'type' => Schema::TYPE_URL, 'label' => 'More information link', 'placeholder' => 'https://… or /s/…'], // @translate
                ],
            ],
            ['key' => 'count', 'type' => Schema::TYPE_NUMBER, 'group' => 'Display', 'default' => 5, 'min' => 1, 'max' => 50, 'label' => 'Most events to show'], // @translate
            ['key' => 'hide_past', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Display', 'default' => true, 'label' => 'Hide events that are over'], // @translate
            ['key' => 'empty_text', 'type' => Schema::TYPE_TEXT, 'group' => 'Display', 'default' => 'No upcoming events. Check back soon.', 'label' => 'When there are no events', 'info' => 'Leave empty to hide the block.'], // @translate
        ];
    }

    /**
     * @var CalendarFeed|null
     */
    protected $calendarFeed;

    /** Set by the block factory. */
    public function setCalendarFeed(CalendarFeed $feed): void
    {
        $this->calendarFeed = $feed;
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $events = $data['events'];
        if ($data['feed_url'] !== '' && $this->calendarFeed) {
            $events = array_merge($events, $this->calendarFeed->events($data['feed_url']));
        }
        return ['events' => self::upcoming($events, $data['hide_past'], $data['count'], $this->today())];
    }

    /**
     * Events sorted by date, past ones dropped (an event lasts through its end
     * date), each with a DateTime for formatting.
     */
    public static function upcoming(array $events, bool $hidePast, int $count, ?DateTime $today = null): array
    {
        $today = $today ?: new DateTime('today');
        // Dates are made at noon in the society's time zone, so formatting
        // them (Omeka converts to its time zone) never shifts the day.
        $zone = $today->getTimezone();
        $today = $today->format('Y-m-d');
        $out = [];
        foreach ($events as $event) {
            if ($event['date'] === '') {
                continue;
            }
            $last = $event['end_date'] !== '' && $event['end_date'] > $event['date'] ? $event['end_date'] : $event['date'];
            if ($hidePast && $last < $today) {
                continue;
            }
            $event['start'] = new DateTime($event['date'] . ' 12:00', $zone);
            $event['end'] = $last !== $event['date'] ? new DateTime($last . ' 12:00', $zone) : null;
            $out[] = $event;
        }
        usort($out, function ($a, $b) {
            return strcmp($a['date'], $b['date']);
        });
        return array_slice($out, 0, $count);
    }
}
