<?php

/**
 * AuditLog Middleware - Logs requests to security_audit_log
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AuditLog
{
    public function handle(Request $request, Closure $next)
    {
        // Who was signed in BEFORE the request. After a logout auth()->id() is
        // already null, so without this every logout row was anonymous.
        $userIdBefore = auth()->id();

        $response = $next($request);

        try {
            $this->logRequest($request, $response, $userIdBefore);
        } catch (\Throwable $e) {
            // Never let audit logging break the app - but never fail silently:
            // a swallowed error here made a dead audit trail look like a quiet one.
            Log::warning('audit-trail: security_audit_log write failed', [
                'path' => $request->path(),
                'error' => $e->getMessage(),
            ]);
        }

        return $response;
    }

    private function logRequest(Request $request, $response, $userIdBefore = null): void
    {
        if (! Schema::hasTable('security_audit_log') || ! Schema::hasTable('ahg_settings')) {
            return;
        }

        // Load audit settings, cached per REQUEST. This was a function-level
        // static, which lives as long as the PHP process: harmless under
        // php-fpm, but in any long-lived process (a test run, Octane) the first
        // request's settings stuck for good and a changed toggle never applied.
        $settings = $request->attributes->get('audit.settings');
        if (! is_array($settings)) {
            try {
                $settings = DB::table('ahg_settings')
                    ->where('setting_group', 'audit')
                    ->pluck('setting_value', 'setting_key')
                    ->toArray();
            } catch (\Throwable $e) {
                $settings = [];
            }
            $request->attributes->set('audit.settings', $settings);
        }

        // Global kill switch
        if (($settings['audit_enabled'] ?? '1') !== '1') {
            return;
        }

        $action = $this->classifyAction($request);
        if (! $action) {
            return;
        }

        // Check per-category toggles
        $category = $action['category'];
        $categorySettingMap = [
            'access' => 'audit_views',
            'search' => 'audit_searches',
            'download' => 'audit_downloads',
            'api' => 'audit_api_requests',
            'auth' => 'audit_authentication',
            'security' => 'audit_sensitive_access',
        ];
        $settingKey = $categorySettingMap[$category] ?? null;
        if ($settingKey && ($settings[$settingKey] ?? '0') !== '1') {
            return;
        }

        // Skip non-success responses for view logging (don't log 404s, redirects to login, etc.)
        $status = $response->getStatusCode();
        if ($category === 'access' && ($status < 200 || $status >= 400)) {
            return;
        }

        // A POST /login is only a login if someone is signed in afterwards;
        // otherwise it is a failed attempt, and recording it as 'login' would
        // make a password-guessing run look like successful sign-ins.
        if ($action['action'] === 'login' && ! auth()->check()) {
            $action['action'] = 'login_failed';
        }

        // Build the log row
        $userId = auth()->id() ?? $userIdBefore;
        $userName = null;
        if ($userId) {
            try {
                $userName = DB::table('user')->where('id', $userId)->value('username');
            } catch (\Throwable $e) {
            }
        }

        $ip = $request->ip();
        if (($settings['audit_ip_anonymize'] ?? '1') === '1' && $ip) {
            $ip = preg_replace('/\.\d+$/', '.0', $ip);
        }

        $details = $action['details'] ?? null;

        // Merge any service-layer before/after diff captured during the
        // request via \AhgCore\Support\AuditLog::captureEdit(). The
        // service stashes the diff + entity coords on request attributes
        // (audit.diff / audit.object_id / audit.object_type); we splice
        // them into details and override the URL-derived object coords
        // when the service supplied a more authoritative value. This
        // keeps the audit log to one row per request - the middleware
        // row gets enriched rather than competing with a service row.
        $diff = $request->attributes->get('audit.diff');
        if (is_array($diff) && ! empty($diff)) {
            $decoded = is_string($details) ? (json_decode($details, true) ?: []) : (is_array($details) ? $details : []);
            $details = json_encode(array_merge($decoded, $diff));
            if (empty($action['object_id']) && $request->attributes->has('audit.object_id')) {
                $action['object_id'] = (int) $request->attributes->get('audit.object_id');
            }
            if (empty($action['object_type']) && $request->attributes->has('audit.object_type')) {
                $action['object_type'] = (string) $request->attributes->get('audit.object_type');
            }
            // Sub-entity endpoints set mutation_action = 'event_create',
            // 'attachment_delete' etc. so the audit log distinguishes them
            // from generic 'update' rows on the parent IO/accession.
            if (! empty($diff['mutation_action'])) {
                $action['action'] = (string) $diff['mutation_action'];
            }
        }

        if (($settings['audit_mask_sensitive'] ?? '1') === '1' && $details) {
            // Strip password/token/key fields from details
            $decoded = is_string($details) ? json_decode($details, true) : $details;
            if (is_array($decoded)) {
                foreach (['password', 'token', 'api_key', 'secret', 'salt'] as $sensitive) {
                    if (isset($decoded[$sensitive])) {
                        $decoded[$sensitive] = '***';
                    }
                    // Also mask inside before/after sub-arrays
                    if (isset($decoded['before'][$sensitive])) {
                        $decoded['before'][$sensitive] = '***';
                    }
                    if (isset($decoded['after'][$sensitive])) {
                        $decoded['after'][$sensitive] = '***';
                    }
                }
                $details = json_encode($decoded);
            }
        }

        $details = is_string($details) ? $details : ($details ? json_encode($details) : null);

        $auditId = DB::table('security_audit_log')->insertGetId([
            'action' => $action['action'],
            'action_category' => $category,
            'object_id' => $action['object_id'] ?? null,
            'object_type' => $action['object_type'] ?? null,
            'user_id' => $userId,
            'user_name' => $userName,
            'details' => $details,
            'ip_address' => $ip,
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        $this->chain($category, $action, $details, (int) $auditId);
    }

    /**
     * Also append the security-relevant events - sign-ins and changes - to the
     * hash-chained, Ed25519-signed ahg_audit_log (#676), so they are
     * tamper-evident. security_audit_log has no chain, and before this the
     * chain held almost nothing but a few special writers.
     *
     * ponytail: views, downloads and searches stay unchained - they are the
     * high-volume categories (tens of thousands of rows) and the chain takes a
     * row lock per append. Upgrade path: chain them too if a jurisdiction
     * requires tamper-evidence for read access.
     */
    private function chain(string $category, array $action, ?string $details, int $auditId): void
    {
        if (! in_array($category, ['auth', 'admin', 'security'], true)
            || ! class_exists(\AhgAuditTrail\Services\AuditLogger::class)) {
            return;
        }

        (new \AhgAuditTrail\Services\AuditLogger)->logAction(
            $action['action'],
            (string) ($action['object_type'] ?? $category),
            isset($action['object_id']) ? (int) $action['object_id'] : null,
            array_filter([
                'security_audit_log_id' => $auditId,
                'category' => $category,
                'details' => $details !== null ? (json_decode($details, true) ?? $details) : null,
            ], fn ($v) => $v !== null),
        );
    }

    private function classifyAction(Request $request): ?array
    {
        $method = $request->method();
        $path = $request->path();

        // Skip static assets, health checks, AJAX polling
        if (preg_match('/\.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|map)$/', $path)) {
            return null;
        }
        if ($path === 'up' || $path === 'api/log-error' || str_starts_with($path, '_debugbar')) {
            return null;
        }

        // Auth events
        if ($path === 'login' && $method === 'POST') {
            return ['action' => 'login', 'category' => 'auth', 'details' => json_encode(['username' => $request->input('email', $request->input('username'))])];
        }
        if ($path === 'logout') {
            return ['action' => 'logout', 'category' => 'auth'];
        }

        // Search
        if (str_contains($path, 'browse') && $request->has('query')) {
            return ['action' => 'search', 'category' => 'search', 'details' => json_encode(['query' => $request->input('query'), 'path' => $path])];
        }
        if ($path === 'glam/browse' && $request->has('query')) {
            return ['action' => 'search', 'category' => 'search', 'details' => json_encode(['query' => $request->input('query')])];
        }
        if (str_starts_with($path, 'search/')) {
            return ['action' => 'search', 'category' => 'search', 'details' => json_encode(['path' => $path, 'query' => $request->input('query')])];
        }

        // Downloads / exports
        if (str_contains($path, '/export/') || str_contains($path, '/download') || str_contains($path, 'findingaid/download')) {
            $objectId = $this->extractObjectId($path);

            return ['action' => 'download', 'category' => 'download', 'object_id' => $objectId, 'object_type' => 'information_object', 'details' => json_encode(['path' => $path])];
        }

        // API requests
        if (str_starts_with($path, 'api/')) {
            return ['action' => 'api_'.$method, 'category' => 'api', 'details' => json_encode(['path' => $path, 'method' => $method])];
        }

        // CRUD operations (POST/PUT/DELETE on entity routes)
        if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
            $objectType = $this->extractObjectType($path);
            if ($objectType) {
                $action = match ($method) {
                    'POST' => 'create', 'PUT' => 'update', 'DELETE' => 'delete', default => 'modify'
                };
                // Distinguish update from create: POST to /entity/slug/edit is update
                if ($method === 'POST' && str_contains($path, '/edit')) {
                    $action = 'update';
                }

                return ['action' => $action, 'category' => 'admin', 'object_type' => $objectType, 'details' => json_encode(['path' => $path])];
            }
        }

        // View events (GET on entity show pages) - only log for authenticated users
        if ($method === 'GET' && auth()->check()) {
            $objectType = $this->extractObjectType($path);
            if ($objectType && ! str_contains($path, '/browse') && ! str_contains($path, '/add') && ! str_contains($path, '/edit')) {
                return ['action' => 'view', 'category' => 'access', 'object_type' => $objectType, 'details' => json_encode(['path' => $path])];
            }
        }

        return null;
    }

    private function extractObjectType(string $path): ?string
    {
        $map = [
            'informationobject' => 'information_object',
            'actor' => 'actor',
            'repository' => 'repository',
            'accession' => 'accession',
            'donor' => 'donor',
            'physicalobject' => 'physical_object',
            'term' => 'term',
            'function' => 'function',
            'digitalobject' => 'digital_object',
            'user' => 'user',
        ];
        foreach ($map as $prefix => $type) {
            if (str_starts_with($path, $prefix.'/') || str_starts_with($path, $prefix.'s/')) {
                return $type;
            }
        }
        // Catch-all for slug-based IO show pages
        if (preg_match('/^[a-z0-9][a-z0-9-]+$/', $path) && ! str_contains($path, '/')) {
            return 'information_object';
        }

        return null;
    }

    private function extractObjectId(string $path): ?int
    {
        if (preg_match('/\/(\d+)(?:\/|$)/', $path, $m)) {
            return (int) $m[1];
        }

        return null;
    }
}
