<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder;

/**
 * The Special Blocks this module adds: layout name => class.
 *
 * Layout names are stored in page data, so never rename one once pages use it.
 * (Before v0.5.0 these blocks were a separate "Society Blocks" module with
 * names like "societyRandomItems"; Module::renameOldLayouts() converts those.)
 * To add a block: write its class in src/Site/BlockLayout (extend
 * AbstractSchemaBlock or AbstractItemBlock), its template in
 * view/common/block-layout/special-blocks/, and list it here.
 */
class SpecialBlocks
{
    const LAYOUTS = [
        'specialRandomItems' => Site\BlockLayout\RandomItems::class,
        'specialRecentItems' => Site\BlockLayout\RecentItems::class,
        'specialItemSpotlight' => Site\BlockLayout\ItemSpotlight::class,
        'specialOnThisDay' => Site\BlockLayout\OnThisDay::class,
        'specialCollectionCards' => Site\BlockLayout\CollectionCards::class,
        'specialCollectionCounts' => Site\BlockLayout\CollectionCounts::class,
        'specialSearchBox' => Site\BlockLayout\SearchBox::class,
        'specialHeroBanner' => Site\BlockLayout\HeroBanner::class,
        'specialThenAndNow' => Site\BlockLayout\ThenAndNow::class,
        'specialQuote' => Site\BlockLayout\Quote::class,
        'specialAccordion' => Site\BlockLayout\Accordion::class,
        'specialButtons' => Site\BlockLayout\Buttons::class,
        'specialVisitInfo' => Site\BlockLayout\VisitInfo::class,
        'specialUpcomingEvents' => Site\BlockLayout\UpcomingEvents::class,
        // Added in v0.7.0.
        'specialAnnouncement' => Site\BlockLayout\Announcement::class,
        'specialSlideshow' => Site\BlockLayout\Slideshow::class,
        'specialGallery' => Site\BlockLayout\Gallery::class,
        'specialTopicTiles' => Site\BlockLayout\TopicTiles::class,
        'specialBrowseByDecade' => Site\BlockLayout\BrowseByDecade::class,
        'specialSubjectsList' => Site\BlockLayout\SubjectsList::class,
        'specialOralHistory' => Site\BlockLayout\OralHistory::class,
        'specialDocumentTranscription' => Site\BlockLayout\DocumentTranscription::class,
        'specialPeople' => Site\BlockLayout\People::class,
        'specialPartners' => Site\BlockLayout\Partners::class,
        'specialFundraising' => Site\BlockLayout\Fundraising::class,
        'specialNewsletter' => Site\BlockLayout\Newsletter::class,
        'specialCitation' => Site\BlockLayout\Citation::class,
        // Added in v0.10.0, for headers and footers.
        'specialLogo' => Site\BlockLayout\Logo::class,
        'specialSiteTitle' => Site\BlockLayout\SiteTitle::class,
        'specialSiteMenu' => Site\BlockLayout\SiteMenu::class,
        'specialSocialLinks' => Site\BlockLayout\SocialLinks::class,
        'specialCopyright' => Site\BlockLayout\Copyright::class,
        // Added in v0.11.0.
        'specialPhotoHotspots' => Site\BlockLayout\PhotoHotspots::class,
        'specialItemTable' => Site\BlockLayout\ItemTable::class,
        'specialMilestones' => Site\BlockLayout\Milestones::class,
        'specialDidYouKnow' => Site\BlockLayout\DidYouKnow::class,
        'specialOpenNow' => Site\BlockLayout\OpenNow::class,
        'specialEmbed' => Site\BlockLayout\Embed::class,
        'specialSharePage' => Site\BlockLayout\SharePage::class,
        'specialBreadcrumbs' => Site\BlockLayout\Breadcrumbs::class,
        // Added in v1.1.0.
        'specialMap' => Site\BlockLayout\PlacesMap::class,
        'specialTimeline' => Site\BlockLayout\Timeline::class,
        // Added in v1.6.0.
        'specialStoryMap' => Site\BlockLayout\StoryMap::class,
        // Added in v1.7.0.
        'specialArchiveQuiz' => Site\BlockLayout\ArchiveQuiz::class,
        // Added in v1.9.0.
        'specialJumpLinks' => Site\BlockLayout\JumpLinks::class,
        // Added in v1.12.0.
        'specialFootnotes' => Site\BlockLayout\FootnotesList::class,
        'specialSharedSection' => Site\BlockLayout\SharedSection::class,
        // Added in v1.14.0.
        'specialTableLink' => Site\BlockLayout\TableLink::class,
        // Added in v2.1.0: item field blocks, for Theme Builder item templates.
        'itemTitle' => Site\BlockLayout\ItemTitle::class,
        'itemMainImage' => Site\BlockLayout\ItemMainImage::class,
        'itemDescription' => Site\BlockLayout\ItemDescription::class,
        'itemDate' => Site\BlockLayout\ItemDate::class,
        'itemDetails' => Site\BlockLayout\ItemDetails::class,
        'itemOneField' => Site\BlockLayout\ItemOneField::class,
        'itemSubjects' => Site\BlockLayout\ItemSubjects::class,
        'itemSets' => Site\BlockLayout\ItemSets::class,
        'itemCite' => Site\BlockLayout\ItemCite::class,
        'itemDownload' => Site\BlockLayout\ItemDownload::class,
        'itemPrevNext' => Site\BlockLayout\ItemPrevNext::class,
        // The results and page links of the browse screens (Theme Builder, Phase 4).
        'results' => Site\BlockLayout\BrowseResults::class,
        'pagination' => Site\BlockLayout\BrowsePagination::class,
        'itemSetHeader' => Site\BlockLayout\ItemSetHeader::class,
        'facets' => Site\BlockLayout\BrowseFacets::class,
        'perPage' => Site\BlockLayout\BrowsePerPage::class,
        'sort' => Site\BlockLayout\BrowseSort::class,
        // The media file on a Media template (Theme Builder).
        'media-viewer' => Site\BlockLayout\MediaViewer::class,
        // Added in v2.12.0: slides over a zoomable time bar, like TimelineJS.
        'specialStoryTimeline' => Site\BlockLayout\StoryTimeline::class,
    ];

    /**
     * How the palette groups the Special Blocks, in order (v1.13.0). Blocks
     * that work together sit side by side. Every layout belongs to exactly
     * one group (checked by the tests).
     */
    const GROUPS = [
        'From your collection' => ['specialRandomItems', 'specialRecentItems', 'specialItemSpotlight', 'specialOnThisDay', 'specialGallery', 'specialItemTable', 'specialCollectionCards', 'specialCollectionCounts', 'specialBrowseByDecade', 'specialSubjectsList', 'specialTopicTiles'], // @translate
        'Telling history' => ['specialHeroBanner', 'specialThenAndNow', 'specialQuote', 'specialDidYouKnow', 'specialMilestones', 'specialTimeline', 'specialStoryTimeline', 'specialPhotoHotspots', 'specialSlideshow', 'specialAccordion'], // @translate
        'Maps' => ['specialMap', 'specialStoryMap'], // @translate
        'Recordings and documents' => ['specialOralHistory', 'specialDocumentTranscription', 'specialEmbed'], // @translate
        'Visiting and events' => ['specialVisitInfo', 'specialOpenNow', 'specialUpcomingEvents', 'specialAnnouncement'], // @translate
        'Getting involved' => ['specialButtons', 'specialNewsletter', 'specialFundraising', 'specialPeople', 'specialPartners', 'specialArchiveQuiz', 'specialSharePage', 'specialSocialLinks'], // @translate
        'Finding your way' => ['specialSearchBox', 'specialJumpLinks', 'specialTableLink', 'specialBreadcrumbs', 'specialFootnotes', 'specialCitation'], // @translate
        'Reuse' => ['specialSharedSection'], // @translate
        // Only offered in headers and footers (where the palette shows them all together).
        'Header and footer' => ['specialLogo', 'specialSiteTitle', 'specialSiteMenu', 'specialCopyright'], // @translate
        // Only offered when editing a Theme Builder browse or search template.
        'Browse pages' => ['results', 'pagination', 'itemSetHeader', 'facets', 'perPage', 'sort'], // @translate
        'Media' => ['media-viewer'], // @translate
        // Only offered when editing a Theme Builder item template (placement "item").
        'Item fields' => ['itemTitle', 'itemMainImage', 'itemDescription', 'itemDate', 'itemDetails', 'itemOneField', 'itemSubjects', 'itemSets', 'itemCite', 'itemDownload', 'itemPrevNext'], // @translate
    ];

    /** "Works well with" notes shown in a block's ⓘ info. */
    const WORKS_WITH = [
        'specialJumpLinks' => 'Your headings (Heading 2, 3, and 4 in text blocks), Table link for spots with no heading, and Footnotes on long pages.', // @translate
        'specialTableLink' => 'Table of contents: put a Table link just above a map, gallery, or anything else with no heading.', // @translate
        'specialFootnotes' => 'The footnote button in Text formatting, and Table of contents on long pages.', // @translate
        'specialTimeline' => 'Story map, which plays the same kind of dated stops on a map.', // @translate
        'specialStoryMap' => 'Timeline and Map of places.', // @translate
        'specialStoryTimeline' => 'Timeline (a quick card view of the same kind of dated events) and Story map (the same idea on a map).', // @translate
        'specialMap' => 'Story map (the same places, played in date order).', // @translate
        'specialVisitInfo' => 'Open now and Upcoming events.', // @translate
        'specialOpenNow' => 'Visit info (it reads the same hours).', // @translate
        'specialUpcomingEvents' => 'Announcement bar and Open now.', // @translate
        'specialOralHistory' => 'Its own topic index (in its settings) and Document and transcription.', // @translate
        'specialDocumentTranscription' => 'Oral history.', // @translate
        'specialSiteMenu' => 'Logo and Search box in a header.', // @translate
        'specialLogo' => 'Site menu and Site title in a header.', // @translate
        'specialSearchBox' => 'Site menu in a header, or Browse by decade and Subjects list on a browse page.', // @translate
        'specialBrowseByDecade' => 'Subjects list and Search box.', // @translate
        'specialSubjectsList' => 'Browse by decade and Search box.', // @translate
        'specialArchiveQuiz' => 'Search box (visitors search to find the answers).', // @translate
        'specialNewsletter' => 'Fundraising progress and Social links.', // @translate
        'specialFundraising' => 'Buttons (a donate button) and Newsletter sign-up.', // @translate
        'specialSharedSection' => 'Anything you repeat across pages, like a "Visit us" strip.', // @translate
        'specialCitation' => 'Footnotes and Share this page.', // @translate
        'specialThenAndNow' => 'Map of places (where each photo was taken).', // @translate
        'specialHeroBanner' => 'Table of contents just below it on long pages.', // @translate
    ];

    /** The palette group of a Special Block class, or null. */
    public static function groupOf(string $class): ?string
    {
        $name = array_search($class, self::LAYOUTS, true);
        foreach (self::GROUPS as $group => $names) {
            if ($name !== false && in_array($name, $names, true)) {
                return $group;
            }
        }
        return null;
    }
}
