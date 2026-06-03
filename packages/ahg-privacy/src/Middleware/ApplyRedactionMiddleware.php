<?php

/**
 * ApplyRedactionMiddleware - auto-applies field-level redaction (#1108) to an
 * information-object JSON response for non-privileged viewers. Opt-in per route
 * via the 'privacy.redact' alias. HTML responses are left untouched (those
 * controllers call PrivacyRedactionService directly).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems. AGPL-3.0-or-later.
 */

declare(strict_types=1);

namespace AhgPrivacy\Middleware;

use AhgPrivacy\Services\PrivacyRedactionService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyRedactionMiddleware
{
    public function __construct(private PrivacyRedactionService $service) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $ioId = $this->resolveIoId($request);
            if ($ioId === null || ! $response instanceof JsonResponse) {
                return $response;
            }

            $profile = $this->service->getPrivacyProfile($ioId);
            if (! $profile || $profile->fields->isEmpty()) {
                return $response;
            }

            $userId = $request->user()?->getAuthIdentifier();
            if ($this->service->canViewUnredacted($userId !== null ? (int) $userId : null)) {
                return $response; // privileged — leave full record
            }

            $payload = $response->getData(true);
            $payload = $this->redactPayload($payload, $profile);
            $response->setData($payload);

            $this->service->logAccess($ioId, $userId !== null ? (int) $userId : null, 'redacted_view');
        } catch (\Throwable $e) {
            // Fail safe: a redaction-layer error must not break the response,
            // but must NOT leak — on error we strip the known fields wholesale.
            if (isset($profile, $payload) && $response instanceof JsonResponse) {
                foreach ($profile->fields as $f) {
                    $this->stripKey($payload, $f->field_name);
                }
                $response->setData($payload);
            }
        }

        return $response;
    }

    /** Apply each field's redaction to matching keys anywhere in the payload. */
    private function redactPayload(array $payload, $profile): array
    {
        foreach ($profile->fields as $field) {
            $this->redactKey($payload, $field->field_name, function ($v) use ($field) {
                return $this->service->redactValue((string) $v, $field->redaction_type, $field->redaction_pattern);
            });
        }

        return $payload;
    }

    private function redactKey(array &$arr, string $key, callable $fn): void
    {
        foreach ($arr as $k => &$v) {
            if ($k === $key && is_scalar($v) && $v !== null && $v !== '') {
                $v = $fn($v);
            } elseif (is_array($v)) {
                $this->redactKey($v, $key, $fn);
            }
        }
    }

    private function stripKey(array &$arr, string $key): void
    {
        foreach ($arr as $k => &$v) {
            if ($k === $key) {
                $v = PrivacyRedactionService::FULL_PLACEHOLDER;
            } elseif (is_array($v)) {
                $this->stripKey($v, $key);
            }
        }
    }

    /** Resolve the IO id from a route 'id' param, or a 'slug' param via the slug table. */
    private function resolveIoId(Request $request): ?int
    {
        $id = $request->route('id');
        if (is_numeric($id)) {
            return (int) $id;
        }
        $slug = $request->route('slug');
        if (is_string($slug) && $slug !== '') {
            try {
                $row = \Illuminate\Support\Facades\DB::table('slug')->where('slug', $slug)->first();
                return $row ? (int) $row->object_id : null;
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }
}
