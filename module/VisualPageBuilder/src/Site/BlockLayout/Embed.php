<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;

/**
 * Embeds a map, form, video, or book from a short list of trusted sites.
 * Any other address is refused, so this is safe even with Omeka's HTML
 * cleaning on. Paste the page's ordinary address; it's turned into the
 * site's embed address here (toEmbedUrl).
 */
class Embed extends AbstractSchemaBlock
{
    const TEMPLATE = 'embed';

    const DESCRIPTION = 'Shows a Google Map, Google Form, Google Calendar, YouTube video or playlist, Vimeo video, or Internet Archive item right on the page. Only these trusted sites are allowed.'; // @translate

    const GOOD_FOR = 'Maps on visit pages, volunteer sign-up forms, and videos.'; // @translate

    /** The trusted sites, for the editor's help text. */
    const SITES = 'Google Maps, Google Forms, Google Calendar, YouTube, Vimeo, Internet Archive';

    public function getLabel()
    {
        return 'Embed from a trusted site'; // @translate
    }

    public function schema(): array
    {
        return [
            ['key' => 'url', 'type' => Schema::TYPE_URL, 'group' => 'Embed', 'summary' => true, 'label' => 'Address', 'info' => 'Paste the address from one of: ' . self::SITES . '. For Google Maps, use Share › Embed a map and paste the address inside src="…".', 'placeholder' => 'https://…'], // @translate
            ['key' => 'title', 'type' => Schema::TYPE_TEXT, 'group' => 'Embed', 'summary' => true, 'label' => 'Title', 'info' => 'What it is, for people using screen readers. e.g. "Map to the museum".', 'placeholder' => 'Required'], // @translate
            ['key' => 'shape', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'wide', 'label' => 'Shape', 'options' => ['wide' => 'Wide (video)', 'square' => 'Square', 'tall' => 'Tall (forms)']], // @translate
            ['key' => 'caption', 'type' => Schema::TYPE_TEXT, 'group' => 'Display', 'label' => 'Caption (optional)'], // @translate
        ];
    }

    /**
     * The embed address for a trusted site's page address, or null.
     * Returns ['src' => …, 'site' => 'YouTube'].
     */
    public static function toEmbedUrl(string $url): ?array
    {
        $url = trim($url);
        $parts = parse_url($url);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return null;
        }
        $host = strtolower(preg_replace('/^www\./', '', $parts['host']));
        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $q);
        $id = '[A-Za-z0-9_-]+';
        if ($host === 'youtube.com' || $host === 'm.youtube.com' || $host === 'youtu.be' || $host === 'youtube-nocookie.com') {
            if (!empty($q['list']) && preg_match("/^$id$/", $q['list']) && ($path === '/playlist' || empty($q['v']))) {
                return ['src' => 'https://www.youtube-nocookie.com/embed/videoseries?list=' . $q['list'], 'site' => 'YouTube'];
            }
            $video = $host === 'youtu.be' ? ltrim($path, '/') : ($q['v'] ?? (preg_match("~^/(?:embed|shorts)/($id)~", $path, $m) ? $m[1] : ''));
            return preg_match("/^$id$/", $video) ? ['src' => 'https://www.youtube-nocookie.com/embed/' . $video, 'site' => 'YouTube'] : null;
        }
        if ($host === 'vimeo.com' || $host === 'player.vimeo.com') {
            return preg_match('~/(\d+)~', $path, $m) ? ['src' => 'https://player.vimeo.com/video/' . $m[1], 'site' => 'Vimeo'] : null;
        }
        if ($host === 'google.com' && strpos($path, '/maps/embed') === 0) {
            return ['src' => 'https://www.google.com' . $path . (isset($parts['query']) ? '?' . $parts['query'] : ''), 'site' => 'Google Maps'];
        }
        if ($host === 'docs.google.com' && preg_match("~^/forms/d/(e/)?($id)/~", $path, $m)) {
            return ['src' => 'https://docs.google.com/forms/d/' . $m[1] . $m[2] . '/viewform?embedded=true', 'site' => 'Google Forms'];
        }
        if ($host === 'calendar.google.com' && strpos($path, '/calendar/embed') === 0) {
            return ['src' => 'https://calendar.google.com' . $path . (isset($parts['query']) ? '?' . $parts['query'] : ''), 'site' => 'Google Calendar'];
        }
        if ($host === 'archive.org' && preg_match('~^/(?:details|embed)/([A-Za-z0-9._-]+)~', $path, $m)) {
            return ['src' => 'https://archive.org/embed/' . $m[1], 'site' => 'Internet Archive'];
        }
        return null;
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        return ['embed' => self::toEmbedUrl($data['url']), 'isPreview' => $blockKey === 'preview'];
    }
}
