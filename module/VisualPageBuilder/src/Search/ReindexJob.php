<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Search;

use Omeka\Job\AbstractJob;

/**
 * Rebuilds the whole VPB Search index in the background (plan section 2: a full
 * reindex is a background job, with progress shown in Jobs and logs).
 *
 * Reads every item in pages of 100 and clears the detached entities between pages,
 * so a large collection doesn't run out of memory.
 */
class ReindexJob extends AbstractJob
{
    public const PER_PAGE = 100;

    /** Omeka setting that is true while a rebuild runs; visitors get Omeka's search meanwhile. */
    public const REBUILDING_SETTING = 'vpb_search_rebuilding';

    public function perform()
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $em = $services->get('Omeka\EntityManager');
        $conn = $services->get('Omeka\Connection');

        $settings = $services->get('Omeka\Settings');
        Index::createTables($conn);
        $settings->set(self::REBUILDING_SETTING, true);
        try {
            $this->rebuild($api, $em, $conn);
        } finally {
            $settings->set(self::REBUILDING_SETTING, false);
        }
    }

    private function rebuild($api, $em, $conn): void
    {
        foreach ([Index::WORD, Index::FACET, Index::FACET_VALUE, Index::TERM, Index::DOC] as $table) {
            $conn->executeStatement('DELETE FROM ' . $table);
        }

        $page = 1;
        do {
            if ($this->shouldStop()) {
                return;
            }
            $items = $api->search('items', [
                'page' => $page,
                'per_page' => self::PER_PAGE,
                'sort_by' => 'id',
                'sort_order' => 'asc',
            ])->getContent();
            foreach ($items as $item) {
                Index::indexItem($conn, $item);
            }
            $em->clear();
            $page++;
        } while (count($items) === self::PER_PAGE);
    }
}
