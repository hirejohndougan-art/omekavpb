<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Stdlib\ItemSource;

/**
 * Base for blocks that show one item's file beside a transcript stored in one
 * of the item's fields (Oral history, Document and transcription).
 * Subclasses say which file to show in pickMedia().
 */
abstract class AbstractTranscriptBlock extends AbstractSchemaBlock
{
    const TEMPLATE = 'transcript';

    /** Which of the item's files to show, or null. */
    abstract protected function pickMedia(ItemRepresentation $item, array $data);

    protected function commonFields(string $mediaLabel): array
    {
        return [
            ['key' => 'item_id', 'type' => Schema::TYPE_ITEM, 'group' => 'Item', 'summary' => true, 'label' => 'Item'], // @translate
            ['key' => 'media_number', 'type' => Schema::TYPE_NUMBER, 'group' => 'Item', 'default' => 1, 'min' => 1, 'max' => 500, 'label' => $mediaLabel], // @translate
            ['key' => 'transcript_property', 'type' => Schema::TYPE_TEXT, 'group' => 'Item', 'default' => 'bibo:content', 'label' => 'Field holding the transcript', 'info' => 'As prefix:name. bibo:content ("Content") is a common choice; dcterms:description also works.'], // @translate
            ['key' => 'show_title', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Display', 'default' => true, 'label' => 'Show the item\'s title and date'], // @translate
            ['key' => 'layout', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'side', 'label' => 'Layout', 'options' => ['side' => 'Side by side', 'stacked' => 'One above the other']], // @translate
            ['key' => 'transcript_height', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'medium', 'label' => 'Transcript box height', 'options' => ['short' => 'Short (scrolls)', 'medium' => 'Medium (scrolls)', 'full' => 'Full length']], // @translate
            ['key' => 'link_text', 'type' => Schema::TYPE_TEXT, 'group' => 'Display', 'default' => 'View the full record', 'label' => 'Link to the item', 'placeholder' => 'Leave empty for no link'], // @translate
        ];
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $item = null;
        if ($data['item_id']) {
            $found = $this->itemSource->items(['site_scope' => ItemSource::SCOPE_ALL], $site, ['id' => $data['item_id'], 'limit' => 1]);
            $item = $found[0] ?? null;
        }
        if (!$item) {
            return ['item' => null];
        }
        $term = preg_match('/^[a-z0-9_-]+:[A-Za-z0-9_-]+$/i', $data['transcript_property']) ? $data['transcript_property'] : 'bibo:content';
        $paragraphs = [];
        foreach ($item->value($term, ['all' => true]) ?: [] as $value) {
            $paragraphs[] = (string) $value->value();
        }
        return [
            'item' => $item,
            'media' => $this->pickMedia($item, $data),
            'transcript' => implode("\n\n", array_filter($paragraphs, 'strlen')),
            'date' => (string) $item->value('dcterms:date'),
            'url' => ItemSource::url($item, ['site_scope' => ItemSource::SCOPE_ALL], $site),
            'blockClass' => static::cssClass(),
        ];
    }
}
