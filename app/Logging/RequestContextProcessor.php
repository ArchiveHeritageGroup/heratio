<?php

/**
 * RequestContextProcessor — Monolog processor that injects request-scoped
 * context (request_id, user_id, tenant_id, http.method, http.uri) into
 * every log record.
 *
 * Phase 1+2 of #677 logging + observability: gives every log line a
 * consistent shape that lets a downstream aggregator (Loki, ELK, etc.)
 * filter by request, user, or tenant without parsing free-form messages.
 *
 * Activated via the 'json' channel in config/logging.php.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class RequestContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $record->extra;

        // Request ID — populated by RequestIdMiddleware via app('request.id').
        // Falls back to '-' for CLI / queue worker / boot-time logs.
        try {
            if (app()->bound('request.id')) {
                $context['request_id'] = (string) app('request.id');
            } elseif (defined('LARAVEL_START')) {
                $context['request_id'] = '-';
            }
        } catch (\Throwable $e) {
            // Container not built yet (very early boot) — skip
        }

        // User ID + role (when auth is bound)
        try {
            if (function_exists('auth') && auth()->check()) {
                $context['user_id'] = auth()->id();
                $u = auth()->user();
                if ($u && isset($u->username)) {
                    $context['username'] = (string) $u->username;
                }
            }
        } catch (\Throwable $e) {
            // No auth context — skip
        }

        // Tenant ID (when multi-tenancy resolved)
        try {
            if (app()->bound('tenant.current')) {
                $tenant = app('tenant.current');
                if (is_object($tenant) && isset($tenant->id)) {
                    $context['tenant_id'] = (int) $tenant->id;
                }
            }
        } catch (\Throwable $e) {
            // Multi-tenancy not active — skip
        }

        // HTTP method + path (web requests only)
        try {
            if (app()->bound('request')) {
                $req = app('request');
                if ($req && method_exists($req, 'method')) {
                    $context['http.method'] = (string) $req->method();
                    $context['http.path'] = (string) $req->path();
                }
            }
        } catch (\Throwable $e) {
            // Not a web request (CLI / queue) — skip
        }

        // Hostname + process ID — useful for multi-instance deployments
        if (!isset($context['host'])) {
            $context['host'] = gethostname() ?: 'unknown';
        }
        if (!isset($context['pid'])) {
            $context['pid'] = getmypid() ?: 0;
        }

        return $record->with(extra: $context);
    }
}
