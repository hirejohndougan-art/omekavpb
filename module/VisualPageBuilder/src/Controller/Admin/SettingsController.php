<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Validator\Csrf;
use Laminas\View\Model\ViewModel;
use Omeka\Mvc\Exception\PermissionDeniedException;

/**
 * Settings (Phase 9): the organization's name and contact, how new items start,
 * the file kinds people may upload (mapped to Omeka's media type whitelist),
 * the cite and download buttons, and items per page. Whole installation, so only
 * a global administrator can change them.
 *
 * Route: admin/visual-page-builder-settings  →  /admin/vpb-settings
 */
class SettingsController extends AbstractActionController
{
    public const CSRF_NAME = 'vpb_settings_csrf';

    /** Plain settings: key => default. */
    public const DEFAULTS = [
        'vpb_org_name' => '',
        'vpb_contact_email' => '',
        'vpb_new_item_status' => 'private',
        'vpb_items_per_page' => 25,
        'vpb_show_cite' => true,
        'vpb_show_download' => true,
    ];

    /**
     * File kinds offered to uploaders, and the media types each one allows
     * (written to Omeka's media_type_whitelist; all kinds restores Omeka's default list).
     */
    public const FILE_KINDS = [
        'images' => ['label' => 'Images', 'types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/tiff']],
        'documents' => ['label' => 'PDF and Word documents', 'types' => ['application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain', 'text/csv']],
        'audio' => ['label' => 'Audio', 'types' => ['audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/ogg', 'audio/mp4', 'audio/x-m4a']],
        'video' => ['label' => 'Video', 'types' => ['video/mp4', 'video/webm', 'video/quicktime']],
    ];

    public function indexAction()
    {
        $this->requireGlobalAdmin();
        if ($this->getRequest()->isPost()) {
            return $this->save();
        }
        $settings = $this->settings();
        $values = [];
        foreach (self::DEFAULTS as $key => $default) {
            $values[$key] = $settings->get($key, $default);
        }
        return new ViewModel([
            'values' => $values,
            'synonyms' => (string) $settings->get(\VisualPageBuilder\Search\Index::SYNONYMS_SETTING, ''),
            'kinds' => $this->chosenKinds(),
            'fileKinds' => self::FILE_KINDS,
            'csrf' => (new Csrf(['name' => self::CSRF_NAME]))->getHash(),
            'rebuilding' => (bool) $this->settings()->get(\VisualPageBuilder\Search\ReindexJob::REBUILDING_SETTING, false),
        ]);
    }

    private function save()
    {
        $post = $this->params()->fromPost();
        if (!(new Csrf(['name' => self::CSRF_NAME]))->isValid($post[self::CSRF_NAME] ?? '')) {
            $this->messenger()->addError('That form expired. Please try again.'); // @translate
            return $this->redirect()->toRoute('admin/visual-page-builder-settings');
        }
        if (($post['vpb_settings_action'] ?? '') === 'rebuild_index') {
            return $this->rebuildIndex();
        }
        $settings = $this->settings();
        $settings->set('vpb_org_name', trim((string) ($post['vpb_org_name'] ?? '')));
        $email = trim((string) ($post['vpb_contact_email'] ?? ''));
        $settings->set('vpb_contact_email', filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '');
        $settings->set('vpb_new_item_status', ($post['vpb_new_item_status'] ?? '') === 'public' ? 'public' : 'private');
        $perPage = (int) ($post['vpb_items_per_page'] ?? 25);
        $settings->set('vpb_items_per_page', max(5, min(100, $perPage)));
        $settings->set('vpb_show_cite', !empty($post['vpb_show_cite']));
        $settings->set('vpb_show_download', !empty($post['vpb_show_download']));
        if (isset($post['synonyms'])) {
            // Search synonyms are kept here now (they used to be on the Search screen).
            $settings->set(\VisualPageBuilder\Search\Index::SYNONYMS_SETTING, \VisualPageBuilder\Search\Synonyms::clean((string) $post['synonyms']));
        }

        // File kinds: at least one must stay on.
        $chosen = array_values(array_intersect(array_keys(self::FILE_KINDS), (array) ($post['kinds'] ?? [])));
        if ($chosen === []) {
            $this->messenger()->addError('Choose at least one kind of file. The file types were not changed.'); // @translate
            return $this->redirect()->toRoute('admin/visual-page-builder-settings');
        }
        if (count($chosen) === count(self::FILE_KINDS)) {
            // Every kind: Omeka's own default list, as on a fresh install.
            $types = \Omeka\Form\SettingForm::MEDIA_TYPE_WHITELIST;
        } else {
            $types = [];
            foreach ($chosen as $kind) {
                $types = array_merge($types, self::FILE_KINDS[$kind]['types']);
            }
            $types = array_values(array_unique($types));
        }
        $settings->set('media_type_whitelist', $types);
        $settings->set('vpb_file_kinds', $chosen);

        $this->messenger()->addSuccess('Settings saved.'); // @translate
        return $this->redirect()->toRoute('admin/visual-page-builder-settings');
    }

    /** The kinds currently chosen: all of them until the screen is first saved. */
    private function chosenKinds(): array
    {
        $saved = $this->settings()->get('vpb_file_kinds');
        if (is_array($saved)) {
            return array_values(array_intersect(array_keys(self::FILE_KINDS), $saved));
        }
        return array_keys(self::FILE_KINDS);
    }

    /**
     * Starts the full search index rebuild as a background job (Phase 10). Visitors get Omeka's
     * search until it finishes. A second press while one is running does nothing.
     */
    private function rebuildIndex()
    {
        $settings = $this->settings();
        if ($settings->get(\VisualPageBuilder\Search\ReindexJob::REBUILDING_SETTING, false)) {
            $this->messenger()->addNotice('The search index is already being rebuilt. Visitors get Omeka\'s search until it finishes.'); // @translate
        } else {
            $this->jobDispatcher()->dispatch(\VisualPageBuilder\Search\ReindexJob::class);
            $this->messenger()->addSuccess('The search index is being rebuilt in the background. Search uses Omeka\'s search until it finishes.'); // @translate
        }
        return $this->redirect()->toRoute('admin/visual-page-builder-settings');
    }

    private function requireGlobalAdmin(): void
    {
        $user = $this->identity();
        if (!$user || $user->getRole() !== 'global_admin') {
            throw new PermissionDeniedException('Only a global administrator can change these settings.'); // @translate
        }
    }

    private function settings()
    {
        return $this->getEvent()->getApplication()->getServiceManager()->get('Omeka\Settings');
    }
}
