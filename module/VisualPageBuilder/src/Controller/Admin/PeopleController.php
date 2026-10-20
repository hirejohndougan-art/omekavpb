<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Validator\Csrf;
use Laminas\View\Model\ViewModel;
use Omeka\Mvc\Exception\PermissionDeniedException;

/**
 * People (Phase 9): who has an account, what each role can do in plain words,
 * changing roles (never your own), and invitations that send Omeka's own
 * activation email.
 *
 * Route: admin/visual-page-builder-people  →  /admin/vpb-people
 */
class PeopleController extends AbstractActionController
{
    public const CSRF_NAME = 'vpb_people_csrf';

    /** What each role can do, in plain words (Omeka's role names). */
    public const ROLE_HELP = [
        'global_admin' => 'Everything, including settings and people.',
        'site_admin' => 'Runs one or more sites: their pages, templates and items.',
        'editor' => 'Adds and changes items and pages.',
        'reviewer' => 'Looks at items and can add notes, but can’t change them.',
        'author' => 'Adds items and changes only the items they added.',
        'researcher' => 'Sees private items, but can’t change anything.',
    ];

    public function indexAction()
    {
        $this->requireGlobalAdmin();
        if ($this->getRequest()->isPost()) {
            return $this->save();
        }
        $people = [];
        foreach ($this->api()->search('users', ['sort_by' => 'name'])->getContent() as $user) {
            $people[] = [
                'id' => $user->id(),
                'name' => $user->name(),
                'email' => $user->email(),
                'role' => $user->role(),
                'active' => $user->isActive(),
                'isYou' => $user->id() === $this->identity()->getId(),
            ];
        }
        return new ViewModel([
            'people' => $people,
            'roles' => $this->roleLabels(),
            'roleHelp' => self::ROLE_HELP,
            'csrf' => (new Csrf(['name' => self::CSRF_NAME]))->getHash(),
        ]);
    }

    private function save()
    {
        $post = $this->params()->fromPost();
        if (!(new Csrf(['name' => self::CSRF_NAME]))->isValid($post[self::CSRF_NAME] ?? '')) {
            $this->messenger()->addError('That form expired. Please try again.'); // @translate
            return $this->back();
        }
        $manager = $this->getEvent()->getApplication()->getServiceManager();
        $api = $manager->get('Omeka\ApiManager');
        if (($post['vpb_action'] ?? '') === 'invite') {
            return $this->invite($api, $manager, $post);
        }
        return $this->changeRole($api, $post);
    }

    private function changeRole($api, array $post)
    {
        $userId = (int) ($post['user_id'] ?? 0);
        $role = (string) ($post['role'] ?? '');
        if ($userId === (int) $this->identity()->getId()) {
            $this->messenger()->addError('You can’t change your own role. Ask another global administrator.'); // @translate
            return $this->back();
        }
        if (!array_key_exists($role, $this->roleLabels())) {
            $this->messenger()->addError('That role does not exist.'); // @translate
            return $this->back();
        }
        $api->update('users', $userId, ['o:role' => $role], [], ['isPartial' => true]);
        $this->messenger()->addSuccess('Role changed.'); // @translate
        return $this->back();
    }

    private function invite($api, $manager, array $post)
    {
        $name = trim((string) ($post['name'] ?? ''));
        $email = trim((string) ($post['email'] ?? ''));
        $role = (string) ($post['role'] ?? 'author');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '' || !array_key_exists($role, $this->roleLabels())) {
            $this->messenger()->addError('Enter a name, a valid email address and a role.'); // @translate
            return $this->back();
        }
        // Not active until the person follows the activation link in the email.
        $response = $api->create('users', ['o:name' => $name, 'o:email' => $email, 'o:role' => $role, 'o:is_active' => false]);
        $user = $response->getContent();
        try {
            $manager->get('Omeka\Mailer')->sendUserActivation($user->getEntity());
            $this->messenger()->addSuccess(sprintf($this->translate('Invitation sent to %s.'), $email)); // @translate
        } catch (\Throwable $e) {
            $this->messenger()->addWarning(sprintf($this->translate('%s was added, but the invitation email could not be sent.'), $email)); // @translate
        }
        return $this->back();
    }

    private function roleLabels(): array
    {
        return $this->getEvent()->getApplication()->getServiceManager()->get('Omeka\Acl')->getRoleLabels();
    }

    private function back()
    {
        return $this->redirect()->toRoute('admin/visual-page-builder-people');
    }

    private function requireGlobalAdmin(): void
    {
        $user = $this->identity();
        if (!$user || $user->getRole() !== 'global_admin') {
            throw new PermissionDeniedException('Only a global administrator can manage people.'); // @translate
        }
    }
}
