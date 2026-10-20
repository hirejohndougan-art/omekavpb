<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Validator\Csrf;
use Laminas\View\Model\ViewModel;
use VisualPageBuilder\ItemForm;
use VisualPageBuilder\Questions;

/**
 * Edit questions (Phase 7): change each question's label and help text, and mark it Needed.
 * The changes are saved to the resource template's alternate labels, comments and required flags.
 *
 * Route: admin/visual-page-builder-questions  →  /admin/vpb-questions
 */
class QuestionsController extends AbstractActionController
{
    public const CSRF_NAME = 'vpb_questions_csrf';

    public function indexAction()
    {
        $manager = $this->getEvent()->getApplication()->getServiceManager();
        $api = $manager->get('Omeka\ApiManager');
        $settings = $manager->get('Omeka\Settings');
        $csrf = new Csrf(['name' => self::CSRF_NAME]);

        $templates = [];
        foreach ($api->search('resource_templates', ['sort_by' => 'label', 'per_page' => 200])->getContent() as $tpl) {
            $templates[] = ['id' => (int) $tpl->id(), 'label' => (string) $tpl->label()];
        }
        $templateId = (int) $this->params()->fromQuery('template', $settings->get(Questions::SETTING, 0));
        if ($this->getRequest()->isPost()) {
            $post = $this->params()->fromPost();
            $templateId = (int) ($post['template'] ?? 0);
            if (!$csrf->isValid($post[self::CSRF_NAME] ?? '')) {
                $this->messenger()->addError('That form expired. Please try again.'); // @translate
            } else {
                $this->save($api, $templateId, $post);
                $settings->set(Questions::SETTING, $templateId);
                $this->messenger()->addSuccess('Questions saved. Add an item and One item use them now.'); // @translate
            }
            return $this->redirect()->toRoute('admin/visual-page-builder-questions', [], ['query' => ['template' => $templateId]], true);
        }

        $questions = [];
        if ($templateId) {
            try {
                $template = $api->read('resource_templates', $templateId)->getContent();
                foreach ($template->resourceTemplateProperties() as $prop) {
                    $questions[] = [
                        'property' => (int) $prop->property()->id(),
                        'term' => (string) $prop->property()->term(),
                        'name' => (string) $prop->property()->label(),
                        'label' => (string) ($prop->alternateLabel() ?: $prop->property()->label()),
                        'comment' => (string) ($prop->alternateComment() ?: ''),
                        'needed' => (bool) $prop->isRequired(),
                    ];
                }
            } catch (\Exception $e) {
                $templateId = 0;
            }
        }

        $view = new ViewModel([
            'templates' => $templates,
            'templateId' => $templateId,
            'questions' => $questions,
            'csrf' => $csrf->getHash(),
        ]);
        $view->setTemplate('visual-page-builder/admin/questions/index');
        return $view;
    }

    /**
     * Write the posted labels, help text and Needed flags. Every property the template already
     * has is sent back, so the others are unchanged.
     */
    private function save($api, int $templateId, array $post): void
    {
        if (!$templateId) {
            return;
        }
        $template = $api->read('resource_templates', $templateId)->getContent();
        $list = [];
        foreach ($template->resourceTemplateProperties() as $prop) {
            $id = (int) $prop->property()->id();
            $row = $prop->jsonSerialize();
            $row['o:property'] = ['o:id' => $id]; // the API takes the id, not the reference object
            $row['o:alternate_label'] = trim((string) ($post['label'][$id] ?? '')) ?: null;
            $row['o:alternate_comment'] = trim((string) ($post['comment'][$id] ?? '')) ?: null;
            $row['o:is_required'] = isset($post['needed'][$id]);
            $list[] = $row;
        }
        $api->update('resource_templates', $templateId, ['o:resource_template_property' => $list], [], ['isPartial' => true]);
    }
}
