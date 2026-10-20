<?php
// SPDX-License-Identifier: GPL-2.0-or-later
namespace VisualPageBuilder\Diagnostics;

/**
 * A small Model Context Protocol server (JSON-RPC 2.0 over HTTP) that offers the
 * diagnostics report as read-only tools. Every tool only reads; none writes.
 */
class Mcp
{
    private const PROTOCOL = '2025-06-18';
    private const SERVER = ['name' => 'visual-page-builder-diagnostics', 'version' => '2.8.0'];

    /** Tool name => [description, which part of the report it returns]. */
    private const TOOLS = [
        'vpb_report' => ['The whole diagnostics report: the plugin, site-wide state, tables, settings, index, jobs, errors and warnings.', null],
        'vpb_warnings' => ['Warnings about the plugin, such as a missing table, an inactive CSV Import, or failed jobs.', 'warnings'],
        'vpb_recent_jobs' => ['The last 10 import and index jobs, with status and the start of each log.', 'jobs'],
        'vpb_recent_errors' => ['The last errors in Omeka\'s log, clipped.', 'recent_errors'],
        'vpb_index' => ['Search index counts: items, indexed documents, whether a rebuild is running.', 'index'],
        'vpb_site' => ['Site-wide diagnostics: every Omeka module and its state, every site, the database and PHP versions, PHP extensions, writable folders, upload limits, and counts.', 'site'],
    ];

    /** @var Report */
    private $report;

    public function __construct(Report $report)
    {
        $this->report = $report;
    }

    /**
     * Answers one JSON-RPC message, or returns null for a notification (no reply).
     */
    public function handle(array $message): ?array
    {
        $id = $message['id'] ?? null;
        $isNotification = !array_key_exists('id', $message);
        $method = $message['method'] ?? null;
        if (!is_string($method)) {
            return $this->error($id, -32600, 'Invalid request.');
        }
        if ($isNotification) {
            return null;
        }
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];

        switch ($method) {
            case 'initialize':
                $requested = (string) ($params['protocolVersion'] ?? '');
                return $this->result($id, [
                    'protocolVersion' => $requested === self::PROTOCOL ? $requested : self::PROTOCOL,
                    'capabilities' => ['tools' => new \stdClass()],
                    'serverInfo' => self::SERVER,
                ]);
            case 'ping':
                return $this->result($id, new \stdClass());
            case 'tools/list':
                return $this->result($id, ['tools' => $this->toolList()]);
            case 'tools/call':
                return $this->call($id, $params);
            default:
                return $this->error($id, -32601, 'Method not found: ' . $method);
        }
    }

    private function call($id, array $params): array
    {
        $name = (string) ($params['name'] ?? '');
        if (!isset(self::TOOLS[$name])) {
            return $this->error($id, -32602, 'Unknown tool: ' . $name);
        }
        $report = $this->report->build();
        $part = self::TOOLS[$name][1];
        $data = $part === null ? $report : ($report[$part] ?? null);
        return $this->result($id, [
            'content' => [['type' => 'text', 'text' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)]],
            'isError' => false,
        ]);
    }

    private function toolList(): array
    {
        $tools = [];
        foreach (self::TOOLS as $name => [$description]) {
            $tools[] = [
                'name' => $name,
                'description' => $description,
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()],
                'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false],
            ];
        }
        return $tools;
    }

    private function result($id, $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    private function error($id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}
