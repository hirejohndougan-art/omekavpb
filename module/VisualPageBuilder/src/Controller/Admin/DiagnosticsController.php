<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Validator\Csrf;
use Laminas\View\Model\ViewModel;
use Omeka\Mvc\Exception\PermissionDeniedException;
use VisualPageBuilder\Diagnostics\Report;

/**
 * Diagnostics: a read-only report of the plugin's state for Claude or a support
 * person. A global administrator creates a token on the admin page; the token is
 * shown once and only its hash is stored. The report is then available at
 * /vpb-diagnostics with the token in an Authorization: Bearer header (or the
 * X-VPB-Diagnostics-Token header). With no token set, the endpoint is not found.
 *
 * Routes: admin/visual-page-builder-diagnostics  →  /admin/vpb-diagnostics
 *         visual-page-builder-diagnostics-report →  /vpb-diagnostics (token)
 */
class DiagnosticsController extends AbstractActionController
{
    public const CSRF_NAME = 'vpb_diagnostics_csrf';
    public const HASH_SETTING = 'vpb_diagnostics_token_hash';
    public const CREATED_SETTING = 'vpb_diagnostics_token_created';
    public const TOKEN_HEADER = 'X-VPB-Diagnostics-Token';

    public function indexAction()
    {
        $this->requireGlobalAdmin();
        $settings = $this->settings();
        $newToken = null;
        if ($this->getRequest()->isPost()) {
            $post = $this->params()->fromPost();
            if (!(new Csrf(['name' => self::CSRF_NAME]))->isValid($post[self::CSRF_NAME] ?? '')) {
                $this->messenger()->addError('That form expired. Please try again.'); // @translate
                return $this->redirect()->toRoute('admin/visual-page-builder-diagnostics');
            }
            if (($post['vpb_diagnostics_action'] ?? '') === 'create') {
                $newToken = bin2hex(random_bytes(24));
                $settings->set(self::HASH_SETTING, hash('sha256', $newToken));
                $settings->set(self::CREATED_SETTING, gmdate('c'));
            } elseif (($post['vpb_diagnostics_action'] ?? '') === 'revoke') {
                $settings->set(self::HASH_SETTING, '');
                $settings->set(self::CREATED_SETTING, '');
                $this->messenger()->addSuccess('The diagnostics token is turned off.'); // @translate
                return $this->redirect()->toRoute('admin/visual-page-builder-diagnostics');
            }
        }
        return new ViewModel([
            'enabled' => (string) $settings->get(self::HASH_SETTING, '') !== '',
            'created' => (string) $settings->get(self::CREATED_SETTING, ''),
            'newToken' => $newToken,
            'csrf' => (new Csrf(['name' => self::CSRF_NAME]))->getHash(),
            'csrfName' => self::CSRF_NAME,
        ]);
    }

    /** The read-only report, for a request that carries the token. Answers with JSON. */
    public function reportAction()
    {
        $settings = $this->settings();
        $hash = (string) $settings->get(self::HASH_SETTING, '');
        if ($hash === '') {
            return $this->json(404, ['error' => 'Not found.']);
        }
        $token = $this->token();
        if ($token === '' || !hash_equals($hash, hash('sha256', $token))) {
            return $this->json(403, ['error' => 'The token is missing or not accepted.']);
        }
        $services = $this->getEvent()->getApplication()->getServiceManager();
        return $this->json(200, (new Report($services))->build());
    }

    /** Writes the JSON answer straight to the response, without a view template. */
    private function json(int $status, array $body)
    {
        $response = $this->getResponse();
        $response->setStatusCode($status);
        $response->getHeaders()->addHeaderLine('Content-Type', 'application/json; charset=utf-8');
        $response->getHeaders()->addHeaderLine('Cache-Control', 'no-store');
        $response->setContent(json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
        return $response;
    }

    private function token(): string
    {
        $headers = $this->getRequest()->getHeaders();
        if ($headers->has(self::TOKEN_HEADER)) {
            return trim((string) $headers->get(self::TOKEN_HEADER)->getFieldValue());
        }
        if ($headers->has('Authorization')) {
            $value = trim((string) $headers->get('Authorization')->getFieldValue());
            if (stripos($value, 'Bearer ') === 0) {
                return trim(substr($value, 7));
            }
        }
        return '';
    }

    private function requireGlobalAdmin(): void
    {
        $user = $this->identity();
        if (!$user || $user->getRole() !== 'global_admin') {
            throw new PermissionDeniedException('Only a global administrator can manage the diagnostics token.'); // @translate
        }
    }

    private function settings()
    {
        return $this->getEvent()->getApplication()->getServiceManager()->get('Omeka\Settings');
    }
}
