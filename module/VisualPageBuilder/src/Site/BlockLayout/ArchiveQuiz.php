<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SiteRepresentation;
use VisualPageBuilder\Schema\Schema;
use VisualPageBuilder\Stdlib\ItemSource;

/**
 * Archive quiz: multiple-choice questions whose answers are found by
 * searching the archive. Each question has a "Search the archive" link (a
 * ready-made search, opening in a new tab) and, after answering, shows
 * whether it was right, an explanation, and the item where the answer is.
 *
 * Answers are checked in the visitor's browser (special-blocks/js/quiz.js);
 * nothing is stored. The right answers are in the page lightly scrambled so
 * they aren't readable at a glance, but this is for fun, not a test: anyone
 * determined can find them.
 */
class ArchiveQuiz extends AbstractSchemaBlock
{
    const TEMPLATE = 'archive-quiz';

    const DESCRIPTION = 'A quiz with multiple-choice questions. Visitors search the archive to find each answer, then check it and see where the answer was.'; // @translate

    const GOOD_FOR = 'Scavenger hunts, school groups, and getting visitors to explore the collection.'; // @translate

    public function getLabel()
    {
        return 'Archive quiz'; // @translate
    }

    public function schema(): array
    {
        return [
            ['key' => 'intro', 'type' => Schema::TYPE_TEXTAREA, 'group' => 'Quiz', 'default' => 'Search the archive to find the answers. Use the links to start your search.', 'label' => 'Instructions'], // @translate
            [
                'key' => 'questions', 'type' => Schema::TYPE_LIST, 'group' => 'Questions', 'summary' => true, 'max' => 50,
                'label' => 'Questions', // @translate
                'fields' => [
                    ['key' => 'question', 'type' => Schema::TYPE_TEXT, 'label' => 'Question', 'placeholder' => 'e.g. What year did the depot open?'], // @translate
                    ['key' => 'choices', 'type' => Schema::TYPE_TEXTAREA, 'label' => 'Choices, one per line'], // @translate
                    ['key' => 'correct', 'type' => Schema::TYPE_NUMBER, 'default' => 1, 'min' => 1, 'max' => 10, 'label' => 'Which choice is right (1 = first line)'], // @translate
                    ['key' => 'search', 'type' => Schema::TYPE_TEXT, 'label' => 'Search to suggest', 'info' => 'Words for the "Search the archive" link, e.g. depot.', 'placeholder' => 'e.g. depot'], // @translate
                    ['key' => 'hint', 'type' => Schema::TYPE_TEXT, 'label' => 'Hint (optional)'], // @translate
                    ['key' => 'item_id', 'type' => Schema::TYPE_ITEM, 'label' => 'Item where the answer is (shown after answering)'], // @translate
                    ['key' => 'explanation', 'type' => Schema::TYPE_TEXTAREA, 'label' => 'Explanation (shown after answering)'], // @translate
                ],
            ],
            ['key' => 'search_in', 'type' => Schema::TYPE_SELECT, 'group' => 'Display', 'default' => 'items', 'label' => 'Search links go to', 'options' => ['items' => 'Items', 'site' => 'The whole site (items, pages, collections)']], // @translate
            ['key' => 'show_score', 'type' => Schema::TYPE_CHECKBOX, 'group' => 'Display', 'default' => true, 'label' => 'Show the score at the end'], // @translate
        ];
    }

    /** Scramble a number so it isn't readable in the page at a glance (quiz.js reverses it). */
    public static function scramble(int $n, string $salt): string
    {
        return base64_encode(($n * 7 + strlen($salt) * 3) . ':' . substr(sha1($salt . $n), 0, 6));
    }

    protected function viewVars(PhpRenderer $view, array $data, SiteRepresentation $site, string $blockKey): array
    {
        $questions = [];
        foreach ($data['questions'] as $i => $q) {
            $choices = array_values(array_filter(array_map('trim', preg_split('/\R/', $q['choices'])), 'strlen'));
            if ($q['question'] === '' || count($choices) < 2) {
                continue;
            }
            $correct = max(1, min(count($choices), (int) $q['correct']));
            $search = null;
            if ($q['search'] !== '') {
                $search = $data['search_in'] === 'site'
                    ? $view->url('site/resource', ['site-slug' => $site->slug(), 'controller' => 'index', 'action' => 'search'], ['query' => ['fulltext_search' => $q['search']]])
                    : $view->url('site/resource', ['site-slug' => $site->slug(), 'controller' => 'item', 'action' => 'browse'], ['query' => ['fulltext_search' => $q['search']]]);
            }
            $item = null;
            if ($q['item_id']) {
                $found = $this->itemSource->items(['site_scope' => ItemSource::SCOPE_ALL], $site, ['id' => $q['item_id'], 'limit' => 1]);
                $item = $found[0] ?? null;
            }
            $salt = $blockKey . '#' . $i;
            $questions[] = [
                'question' => $q['question'], 'choices' => $choices, 'key' => self::scramble($correct, $salt), 'salt' => $salt,
                'search' => $search, 'searchWords' => $q['search'], 'hint' => $q['hint'], 'explanation' => $q['explanation'],
                'itemUrl' => $item ? ItemSource::url($item, ['site_scope' => ItemSource::SCOPE_ALL], $site) : null,
                'itemTitle' => $item ? $item->displayTitle() : null,
            ];
        }
        return ['questions' => $questions, 'quizId' => 'vpb-quiz-' . preg_replace('/[^a-z0-9]/i', '', $blockKey)];
    }
}
