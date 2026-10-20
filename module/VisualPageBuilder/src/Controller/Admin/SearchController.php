<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Validator\Csrf;
use Laminas\View\Model\ViewModel;
use Omeka\Mvc\Exception\PermissionDeniedException;
use VisualPageBuilder\Search\Index;
use VisualPageBuilder\Search\Synonyms;

/**
 * Search settings (plan section 2 and Phase 3): the synonyms list, one
 * "term = alternative, alternative" per line. Settings are for the whole
 * installation, so only a global administrator can change them.
 *
 * Route: admin/visual-page-builder-search  →  /admin/vpb-search-settings
 */
class SearchController extends AbstractActionController
{
    public const CSRF_NAME = 'vpb_search_csrf';

    public function indexAction()
    {
        $this->requireGlobalAdmin();
        $settings = $this->settings();
        if ($this->getRequest()->isPost()) {
            return $this->save($settings);
        }
        $text = (string) $settings->get(Index::SYNONYMS_SETTING, '');
        return new ViewModel([
            'synonyms' => $text,
            'count' => count(array_filter(preg_split('/\r\n|\r|\n/', $text), fn ($l) => strpos($l, '=') !== false)),
            'csrf' => $this->csrf(),
        ]);
    }

    private function save($settings)
    {
        $post = $this->params()->fromPost();
        if (!(new Csrf(['name' => self::CSRF_NAME]))->isValid($post[self::CSRF_NAME] ?? '')) {
            $this->messenger()->addError('That form expired. Please try again.'); // @translate
            return $this->redirect()->toRoute('admin/visual-page-builder-search');
        }
        // Keep only lines that look like "term = alternative"; the rest are ignored.
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) ($post['synonyms'] ?? '')) as $line) {
            $line = trim($line);
            if ($line !== '' && strpos($line, '=') !== false) {
                $lines[] = $line;
            }
        }
        $settings->set(Index::SYNONYMS_SETTING, implode("\n", $lines));
        $this->messenger()->addSuccess('Search synonyms saved.'); // @translate
        return $this->redirect()->toRoute('admin/visual-page-builder-search');
    }

    private function requireGlobalAdmin(): void
    {
        $user = $this->identity();
        if (!$user || $user->getRole() !== 'global_admin') {
            throw new PermissionDeniedException('Only a global administrator can change search settings.'); // @translate
        }
    }

    private function settings()
    {
        return $this->getEvent()->getApplication()->getServiceManager()->get('Omeka\Settings');
    }

    private function csrf(): string
    {
        return (new Csrf(['name' => self::CSRF_NAME]))->getHash();
    }
}
