<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use DateTime;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;

/**
 * A notice ("Closed Monday for the holiday") that shows itself between two
 * dates (in Omeka's time zone) and hides itself the rest of the time.
 * Visitors can close it; it stays closed for them until the text changes
 * (special-blocks/js/announcement.js).
 */
class Announcement extends AbstractSchemaBlock
{
    /** On pages, and in the site's header and footer. */
    const PLACEMENT = 'both';

    const TEMPLATE = 'announcement';

    const DESCRIPTION = 'A short notice, like a closing or a new exhibit, that shows itself between two dates you choose and hides itself afterward.'; // @translate

    const GOOD_FOR = 'Holiday closings, weather notices, and news.'; // @translate

    public function getLabel()
    {
        return 'Announcement bar'; // @translate
    }

    public function schema(): array
    {
        return [
            ['key' => 'text', 'type' => Schema::TYPE_TEXTAREA, 'group' => 'Notice', 'summary' => true, 'label' => 'Notice'], // @translate
            ['key' => 'link_text', 'type' => Schema::TYPE_TEXT, 'group' => 'Notice', 'label' => 'Link text (optional)'], // @translate
            ['key' => 'link_url', 'type' => Schema::TYPE_URL, 'group' => 'Notice', 'label' => 'Link to', 'placeholder' => 'https://… or /s/…'], // @translate
            ['key' => 'start_date', 'type' => Schema::TYPE_DATE, 'group' => 'When', 'summary' => true, 'label' => 'Show from (optional)', 'info' => 'Leave empty to show it now.'], // @translate
            ['key' => 'end_date', 'type' => Schema::TYPE_DATE, 'group' => 'When', 'summary' => true, 'label' => 'Show through (optional)', 'info' => 'Shown through the end of this day. Leave empty to keep it up.'], // @translate
            ['key' => 'style', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'info', 'label' => 'Style', 'options' => ['info' => 'News (calm)', 'warning' => 'Notice (yellow)', 'urgent' => 'Urgent (red)']], // @translate
            ['key' => 'dismissible', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Display', 'default' => true, 'label' => 'Visitors can close it'], // @translate
        ];
    }

    /** Whether the notice is up on $today (YYYY-MM-DD). */
    public static function isShowing(array $data, ?string $today = null): bool
    {
        $today = $today ?: (new DateTime('today'))->format('Y-m-d');
        return $data['text'] !== ''
            && ($data['start_date'] === '' || $data['start_date'] <= $today)
            && ($data['end_date'] === '' || $data['end_date'] >= $today);
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        return [
            // The visual editor's preview always shows it, with a note.
            'showing' => self::isShowing($data, $this->today()->format('Y-m-d')) || $blockKey === 'preview',
            'scheduled' => !self::isShowing($data, $this->today()->format('Y-m-d')),
            // Closing is remembered per text, so a new notice shows again.
            'dismissKey' => 'vpb-announcement-' . substr(sha1($blockKey . '|' . $data['text']), 0, 12),
        ];
    }
}
