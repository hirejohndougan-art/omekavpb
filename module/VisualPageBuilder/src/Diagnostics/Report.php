<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Diagnostics;

use Doctrine\DBAL\Connection;
use Omeka\Module as OmekaModule;

/**
 * Read-only diagnostics for Visual Page Builder: what is installed, whether the
 * tables are there, what the search index holds, recent import and index jobs,
 * and the latest errors in Omeka's log. It never returns secrets (no token, no
 * password or key values) and gives contact details only as set or not set.
 * Job and log lines are clipped; they can name items by their identifiers.
 */
class Report
{
    /** Settings whose values are personal, so only "set" or "not set" is reported. */
    private const PRIVATE_SETTINGS = ['vpb_org_name', 'vpb_contact_email'];

    /** Settings reported by value. */
    private const VALUE_SETTINGS = [
        'vpb_new_item_status', 'vpb_items_per_page', 'vpb_show_cite', 'vpb_show_download',
        'vpb_mode',
    ];

    private const TABLES = [
        'vpb_page_revision', 'vpb_revision', 'vpb_search_log', 'vpb_draft',
        'vpb_search_doc', 'vpb_search_term', 'vpb_search_word', 'vpb_search_facet', 'vpb_search_facet_value',
    ];

    private const LOG_LINES_MAX = 20;
    private const LOG_READ_BYTES = 262144;
    private const TEXT_MAX = 300;

    /** @var \Laminas\ServiceManager\ServiceLocatorInterface */
    private $services;

    public function __construct($services)
    {
        $this->services = $services;
    }

    public function build(): array
    {
        $conn = $this->services->get('Omeka\Connection');
        $settings = $this->services->get('Omeka\Settings');

        $modules = $this->modules($conn);
        $tables = $this->tables($conn);
        $index = $this->index($conn, $settings);
        $jobs = $this->jobs($conn);
        $errors = $this->recentErrors();
        $site = $this->site($conn);
        $phpErrors = $this->phpErrors();
        $warnings = $this->warnings($modules, $tables, $index, $jobs, $errors, $site, $phpErrors);

        return [
            'generated_at' => gmdate('c'),
            'omeka_version' => OmekaModule::VERSION,
            'php_version' => PHP_VERSION,
            'visual_page_builder' => $this->ownVersion(),
            'modules' => $modules,
            'tables' => $tables,
            'settings' => $this->settings($settings),
            'index' => $index,
            'jobs' => $jobs,
            'recent_errors' => $errors,
            'site' => $site,
            'php_error_log' => $phpErrors,
            'warnings' => $warnings,
        ];
    }

    private function ownVersion(): array
    {
        $ini = parse_ini_file(dirname(__DIR__, 2) . '/config/module.ini') ?: [];
        return ['version' => $ini['version'] ?? null];
    }

    private function modules(Connection $conn): array
    {
        $rows = $conn->fetchAllAssociative(
            'SELECT id, version, is_active FROM module WHERE id IN (?, ?)',
            ['VisualPageBuilder', 'CSVImport']
        );
        $out = ['VisualPageBuilder' => null, 'CSVImport' => null];
        foreach ($rows as $row) {
            $out[$row['id']] = ['version' => $row['version'], 'active' => (bool) $row['is_active']];
        }
        return $out;
    }

    private function tables(Connection $conn): array
    {
        $out = [];
        foreach (self::TABLES as $name) {
            $exists = (int) $conn->fetchOne(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                [$name]
            );
            $out[$name] = $exists ? ['present' => true, 'rows' => (int) $conn->fetchOne("SELECT COUNT(*) FROM `$name`")]
                : ['present' => false, 'rows' => null];
        }
        return $out;
    }

    private function settings($settings): array
    {
        $out = [];
        foreach (self::PRIVATE_SETTINGS as $key) {
            $out[$key] = $settings->get($key) ? 'set' : 'not set';
        }
        foreach (self::VALUE_SETTINGS as $key) {
            $value = $settings->get($key);
            $out[$key] = $value;
        }
        return $out;
    }

    private function index(Connection $conn, $settings): array
    {
        $items = (int) $conn->fetchOne('SELECT COUNT(*) FROM item');
        $docs = (int) $conn->fetchOne('SELECT COUNT(*) FROM vpb_search_doc');
        return [
            'items' => $items,
            'indexed_documents' => $docs,
            'rebuilding' => (bool) $settings->get(\VisualPageBuilder\Search\ReindexJob::REBUILDING_SETTING, false),
            'synonyms_set' => trim((string) $settings->get(\VisualPageBuilder\Search\Index::SYNONYMS_SETTING, '')) !== '',
        ];
    }

    private function jobs(Connection $conn): array
    {
        $rows = $conn->fetchAllAssociative(
            "SELECT id, class, status, started, ended, LEFT(log, 300) AS log FROM job
             WHERE class LIKE '%VisualPageBuilder%' OR class LIKE '%CSVImport%'
             ORDER BY id DESC LIMIT 10"
        );
        return array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'class' => str_replace('\\\\', '\\', (string) $row['class']),
                'status' => $row['status'],
                'started' => $row['started'],
                'ended' => $row['ended'],
                'log' => $this->clip((string) ($row['log'] ?? '')),
            ];
        }, $rows);
    }

    /** The last few error lines in Omeka's application log (path only checked, not returned). */
    private function recentErrors(): array
    {
        $path = OMEKA_PATH . '/logs/application.log';
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }
        $size = filesize($path);
        $handle = fopen($path, 'rb');
        if (!$handle) {
            return [];
        }
        fseek($handle, max(0, $size - self::LOG_READ_BYTES));
        $chunk = (string) stream_get_contents($handle);
        fclose($handle);

        $lines = array_filter(explode("\n", $chunk), function ($line) {
            return strpos($line, ' ERR ') !== false || strpos($line, ' ERR(') !== false;
        });
        $lines = array_slice(array_values($lines), -self::LOG_LINES_MAX);
        return array_map(fn ($line) => $this->clip($line), $lines);
    }

    /**
     * The tail of PHP's own error log, where fatal errors go when Omeka's log
     * never sees them. Only the lines that look like errors are returned.
     */
    private function phpErrors(): array
    {
        $path = (string) ini_get('error_log');
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            return ['configured' => $path !== '', 'readable' => false, 'lines' => []];
        }
        $size = filesize($path);
        $handle = fopen($path, 'rb');
        if (!$handle) {
            return ['configured' => true, 'readable' => false, 'lines' => []];
        }
        fseek($handle, max(0, $size - self::LOG_READ_BYTES));
        $chunk = (string) stream_get_contents($handle);
        fclose($handle);
        $lines = array_filter(explode("\n", $chunk), function ($line) {
            return (bool) preg_match('/PHP (Fatal|Warning|Parse error)|Exception|Uncaught|Error:/', $line);
        });
        $lines = array_slice(array_values($lines), -self::LOG_LINES_MAX);
        return [
            'configured' => true,
            'readable' => true,
            'lines' => array_map(fn ($line) => $this->clip($line), $lines),
        ];
    }

    /**
     * Site-wide state: every Omeka module and its state, every site, PHP and
     * database versions, PHP extensions the plugin needs, the folders Omeka and
     * the plugin write to, and counts. No credentials and no user details.
     */
    private function site(Connection $conn): array
    {
        $modules = [];
        foreach ($conn->fetchAllAssociative('SELECT id, version, is_active FROM module ORDER BY id') as $row) {
            $modules[$row['id']] = [
                'version' => $row['version'],
                'state' => $row['is_active'] ? 'active' : 'inactive',
            ];
        }
        $sites = [];
        foreach ($conn->fetchAllAssociative('SELECT id, slug, title, theme, is_public FROM site ORDER BY id') as $row) {
            $sites[] = [
                'id' => (int) $row['id'],
                'slug' => $row['slug'],
                'title' => $row['title'],
                'theme' => $row['theme'],
                'public' => (bool) $row['is_public'],
                'item_sets' => (int) $conn->fetchOne('SELECT COUNT(*) FROM site_item_set WHERE site_id = ?', [$row['id']]),
            ];
        }
        $extensions = [];
        foreach (['pdo_mysql', 'mbstring', 'intl', 'gd', 'fileinfo', 'zip', 'json', 'curl', 'dom', 'xml'] as $name) {
            $extensions[$name] = extension_loaded($name);
        }
        $folders = [];
        foreach (['files' => OMEKA_PATH . '/files', 'logs' => OMEKA_PATH . '/logs', 'temp' => sys_get_temp_dir()] as $name => $path) {
            $folders[$name] = is_dir($path) && is_writable($path);
        }
        $roles = [];
        foreach ($conn->fetchAllAssociative('SELECT role, COUNT(*) AS n FROM user GROUP BY role') as $row) {
            $roles[$row['role']] = (int) $row['n'];
        }
        return [
            'database' => (string) $conn->fetchOne('SELECT VERSION()'),
            'modules' => $modules,
            'sites' => $sites,
            'counts' => [
                'items' => (int) $conn->fetchOne('SELECT COUNT(*) FROM item'),
                'media' => (int) $conn->fetchOne('SELECT COUNT(*) FROM media'),
                'item_sets' => (int) $conn->fetchOne('SELECT COUNT(*) FROM item_set'),
                'pages' => (int) $conn->fetchOne('SELECT COUNT(*) FROM site_page'),
                'users_by_role' => $roles,
            ],
            'extensions' => $extensions,
            'folders' => $folders,
            'php_memory_limit' => ini_get('memory_limit'),
            'max_upload' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
        ];
    }

    private function warnings(array $modules, array $tables, array $index, array $jobs, array $errors, array $site, array $phpErrors): array
    {
        $warnings = [];
        if (empty($modules['VisualPageBuilder']['active'])) {
            $warnings[] = 'Visual Page Builder is not active.';
        }
        if (empty($modules['CSVImport']['active'])) {
            $warnings[] = 'CSV Import is not active, so the import wizard cannot run.';
        }
        foreach ($tables as $name => $info) {
            if (!$info['present']) {
                $warnings[] = "Table $name is missing. Upgrade or reinstall the module.";
            }
        }
        if ($index['items'] > 0 && $index['indexed_documents'] === 0) {
            $warnings[] = 'The search index is empty although there are items. Rebuild the search index in Search settings.';
        }
        if ($index['rebuilding']) {
            $warnings[] = 'A search index rebuild is running.';
        }
        $failed = array_filter($jobs, fn ($job) => $job['status'] === 'error');
        if ($failed) {
            $warnings[] = count($failed) . ' of the last ' . count($jobs) . ' import or index jobs failed.';
        }
        if (!is_writable(sys_get_temp_dir())) {
            $warnings[] = 'The PHP temporary folder is not writable, so imports cannot upload files.';
        }
        if ($errors) {
            $warnings[] = 'Omeka\'s log has recent errors (see recent_errors).';
        }
        if ($phpErrors['lines']) {
            $warnings[] = 'PHP\'s error log has recent problems (see php_error_log).';
        }
        foreach ($site['extensions'] as $name => $loaded) {
            if (!$loaded) {
                $warnings[] = "PHP extension $name is not loaded.";
            }
        }
        foreach ($site['folders'] as $name => $ok) {
            if (!$ok) {
                $warnings[] = "The $name folder is not writable.";
            }
        }
        foreach ($site['modules'] as $name => $module) {
            if ($module['state'] === 'needs_upgrade' || $module['state'] === 'not_installed') {
                $warnings[] = "Module $name is " . str_replace('_', ' ', $module['state']) . '.';
            }
        }
        return $warnings;
    }

    private function clip(string $text): string
    {
        return mb_strlen($text) > self::TEXT_MAX ? mb_substr($text, 0, self::TEXT_MAX) . '…' : $text;
    }
}
