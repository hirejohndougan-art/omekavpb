<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Validator\Csrf;
use Laminas\View\Model\ViewModel;
use Omeka\Mvc\Exception\NotFoundException;
use Omeka\Mvc\Exception\PermissionDeniedException;
use VisualPageBuilder\Templates\GlobalStyles;
use VisualPageBuilder\Templates\Resolver;
use VisualPageBuilder\Templates\Screens;
use VisualPageBuilder\Templates\Store;

/**
 * Theme Builder (Visual Builder › a site › Theme Builder): the site's
 * templates grouped by screen, and its Global styles.
 *
 * Route: admin/visual-page-builder-theme  →  /admin/visual-builder/:site-slug/theme
 */
class ThemeController extends AbstractActionController
{
    public function indexAction()
    {
        $site = $this->site();
        if ($this->getRequest()->isPost()) {
            return $this->handlePost($site);
        }
        $templates = Store::all($site);
        $groups = [];
        foreach (Screens::keys() as $screen) {
            $groups[$screen] = array_values(array_filter($templates, function ($t) use ($screen) {
                return $t['screen'] === $screen;
            }));
        }
        // The site's header and footer (built in the visual editor) show in the hub as
        // their screens' current design, with an Edit link. They are not templates.
        $regions = [];
        foreach (\VisualPageBuilder\SiteRegions::REGIONS as $key => $info) {
            foreach ($site->pages() as $page) {
                if ($page->slug() === $info['slug']) {
                    $regions[$key] = $page;
                    break;
                }
            }
        }
        $view = new ViewModel([
            'site' => $site,
            'groups' => $groups,
            'regions' => $regions,
            'screens' => array_map(function ($key) {
                return ['key' => $key, 'label' => Screens::label($key), 'required' => Screens::required($key)];
            }, Screens::keys()),
            'scopes' => Resolver::LEVELS,
            'styles' => GlobalStyles::values($this->siteSettings()->get(GlobalStyles::THEME_SETTINGS_KEY, [], $site->id())),
            'styleFields' => GlobalStyles::FIELDS,
            'usesCompanion' => $site->theme() === 'visual-page-builder-companion',
            'csrf' => (new Csrf(['name' => 'vpb_theme_csrf']))->getHash(),
        ]);
        $view->setTemplate('visual-page-builder/admin/theme/index');
        return $view;
    }

    /**
     * Check page: every template of the site with its required blocks. A missing
     * block can be put back here; a template with none missing can go live.
     *
     * Route: admin/visual-page-builder-check  →  /admin/visual-builder/:site-slug/check
     */
    public function checkAction()
    {
        $site = $this->site();
        if ($this->getRequest()->isPost()) {
            return $this->handlePost($site);
        }
        $templates = Store::all($site);
        $live = [];
        foreach ($templates as $t) {
            if ($t['status'] === 'live' && $t['scope'] === 'all') {
                $live[$t['screen']] = true;
            }
        }
        // Screens with no live template for every item, set and so on: the theme's own layout shows.
        $fallbacks = [];
        foreach (Screens::keys() as $key) {
            if (empty($live[$key])) {
                $fallbacks[] = ['key' => $key, 'label' => Screens::label($key)];
            }
        }
        $view = new ViewModel([
            'site' => $site,
            'templates' => $templates,
            'problems' => count(array_filter($templates, function ($t) {
                return $t['missing'] !== [];
            })),
            'fallbacks' => $fallbacks,
            'csrf' => (new Csrf(['name' => 'vpb_theme_csrf']))->getHash(),
        ]);
        $view->setTemplate('visual-page-builder/admin/theme/check');
        return $view;
    }

    protected function site()
    {
        $slug = $this->params('site-slug');
        try {
            $site = $this->api()->read('sites', ['slug' => $slug])->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            throw new NotFoundException('Site not found.');
        }
        if (!$site->userIsAllowed('update')) {
            throw new PermissionDeniedException('You do not have permission to edit this site.');
        }
        return $site;
    }

    /** Omeka's API manager (the Store takes it directly; the api() plugin is a wrapper). */
    protected function manager()
    {
        return $this->getEvent()->getApplication()->getServiceManager()->get('Omeka\ApiManager');
    }

    protected function back($site)
    {
        // Forms posted from the Check page come back to it.
        $route = $this->params()->fromPost('return') === 'check'
            ? 'admin/visual-page-builder-check'
            : 'admin/visual-page-builder-theme';
        return $this->redirect()->toRoute($route, ['site-slug' => $site->slug()]);
    }

    protected function handlePost($site)
    {
        $post = $this->params()->fromPost();
        if (!(new Csrf(['name' => 'vpb_theme_csrf']))->isValid($post['vpb_theme_csrf'] ?? '')) {
            $this->messenger()->addError('That form expired. Please try again.'); // @translate
            return $this->back($site);
        }
        $action = $post['vpb_action'] ?? '';
        try {
            switch ($action) {
                case 'globals':
                    return $this->saveGlobals($site, $post);
                case 'add':
                    return $this->addTemplate($site, $post);
                case 'duplicate':
                case 'status':
                case 'move':
                case 'restore':
                case 'delete':
                    return $this->changeTemplate($site, $action, $post);
            }
        } catch (\InvalidArgumentException $e) {
            $this->messenger()->addError($e->getMessage());
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            $this->messenger()->addError($this->translate('That template was not found.')); // @translate
        }
        return $this->back($site);
    }

    protected function saveGlobals($site, array $post)
    {
        $key = GlobalStyles::THEME_SETTINGS_KEY;
        $saved = $this->siteSettings()->get($key, [], $site->id());
        $saved = is_array($saved) ? $saved : [];
        $this->siteSettings()->set($key, array_merge($saved, GlobalStyles::clean($post['style'] ?? [])), $site->id());
        $this->messenger()->addSuccess($this->translate('Global styles saved.')); // @translate
        return $this->back($site);
    }

    protected function addTemplate($site, array $post)
    {
        $screen = (string) ($post['screen'] ?? '');
        $scope = (string) ($post['scope'] ?? 'all');
        $value = $scope === 'all' ? null : (int) ($post['value'] ?? 0);
        $name = trim((string) ($post['name'] ?? ''));
        $page = Store::create($this->manager(), $site, $screen, $scope, $value, $name);
        $this->messenger()->addSuccess(sprintf($this->translate('Made "%s". It is a draft until its required blocks are in place.'), $page->title())); // @translate
        return $this->back($site);
    }

    protected function changeTemplate($site, string $action, array $post)
    {
        $id = (int) ($post['template_id'] ?? 0);
        $template = $this->templateOf($site, $id);
        if ($action === 'delete') {
            $title = $template['page']->title();
            Store::delete($this->manager(), $template['page']);
            $this->messenger()->addSuccess(sprintf($this->translate('Deleted "%s".'), $title)); // @translate
        } elseif ($action === 'duplicate') {
            $copy = Store::duplicate($this->manager(), $site, $template['page']);
            $this->messenger()->addSuccess(sprintf($this->translate('Made "%s". It starts as a draft.'), $copy->title())); // @translate
        } elseif ($action === 'status') {
            $status = ($post['status'] ?? '') === 'live' ? 'live' : 'draft';
            $missing = Store::setStatus($this->manager(), $template['page'], $status);
            if ($missing) {
                $labels = array_map(function ($k) {
                    return ucwords(str_replace('-', ' ', $k));
                }, $missing);
                $this->messenger()->addError(sprintf($this->translate('It cannot go live yet. Its required blocks are missing: %s.'), implode(', ', $labels))); // @translate
            } else {
                $this->messenger()->addSuccess($status === 'live' ? $this->translate('Live.') : $this->translate('Back to draft.')); // @translate
            }
        } elseif ($action === 'move') {
            $this->move($site, $template, ($post['direction'] ?? '') === 'up' ? -1 : 1);
        } elseif ($action === 'restore') {
            $conn = $this->getEvent()->getApplication()->getServiceManager()->get('Omeka\Connection');
            $added = Store::restoreRequired($this->manager(), $conn, $template['page']);
            if ($added) {
                $labels = array_map(function ($k) {
                    return ucwords(str_replace('-', ' ', $k));
                }, $added);
                $this->messenger()->addSuccess(sprintf($this->translate('Put back: %s. They are at the end of the template; move them where you want them.'), implode(', ', $labels))); // @translate
            } else {
                $this->messenger()->addSuccess($this->translate('No required blocks were missing.')); // @translate
            }
        }
        return $this->back($site);
    }

    /** Swap a template with its neighbor on the same screen. */
    protected function move($site, array $template, int $step): void
    {
        $group = array_values(array_filter(Store::all($site), function ($t) use ($template) {
            return $t['screen'] === $template['screen'];
        }));
        $index = null;
        foreach ($group as $i => $t) {
            if ($t['id'] === $template['id']) {
                $index = $i;
            }
        }
        $other = $index === null ? null : ($group[$index + $step] ?? null);
        if (!$other) {
            return;
        }
        Store::setOrder($this->manager(), $template['page'], $other['order']);
        Store::setOrder($this->manager(), $other['page'], $template['order']);
    }

    /** A template of this site by id (not any other page). */
    protected function templateOf($site, int $id): array
    {
        foreach (Store::all($site) as $t) {
            if ($t['id'] === $id) {
                return $t;
            }
        }
        throw new \Omeka\Api\Exception\NotFoundException('No such template.');
    }
}
