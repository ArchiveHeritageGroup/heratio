<?php

/**
 * InjectFieldRedactionPanel - surfaces the field-level redaction panel on the
 * archival-description detail page (#1108 deliverable 4) WITHOUT editing that
 * page's Blade template, which lives under the hard-locked
 * packages/ahg-information-object-manage/ tree.
 *
 * The IO show route (/{slug}) and its views are locked end-to-end, so we use
 * the documented response-injection pattern: a 'web'-group middleware that,
 * for admin viewers only, detects the rendered IO show page and injects a
 * self-contained collapsible panel before </body>. Everything is best-effort
 * and wrapped so it can never break the page render.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio. Licensed AGPL-3.0-or-later.
 */

declare(strict_types=1);

namespace AhgPrivacy\Middleware;

use AhgPrivacy\Services\PrivacyRedactionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Throwable;

class InjectFieldRedactionPanel
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        try {
            if (! $this->shouldInject($request, $response)) {
                return $response;
            }

            $svc = app(PrivacyRedactionService::class);
            $userId = (int) (auth()->id() ?? 0);
            // Admin-only surface: the same gate that bypasses redaction.
            if (! $svc->canViewUnredacted($userId)) {
                return $response;
            }

            $ioId = $this->resolveIoId($request);
            if ($ioId === null) {
                return $response;
            }

            $profile = $svc->getPrivacyProfile($ioId);
            $activeDsar = $this->hasActiveDsar();

            $panel = View::make('privacy::description-privacy-panel-embed', [
                'ioId'       => $ioId,
                'profile'    => $profile,
                'activeDsar' => $activeDsar,
            ])->render();

            $html = (string) $response->getContent();
            $response->setContent(str_replace('</body>', $panel . "\n</body>", $html));
        } catch (Throwable $e) {
            // Never let the privacy panel break the page render.
        }

        return $response;
    }

    private function shouldInject(Request $request, $response): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }
        if (method_exists($response, 'getStatusCode') && $response->getStatusCode() !== 200) {
            return false;
        }
        $ct = (string) $response->headers->get('Content-Type', '');
        if ($ct !== '' && ! str_contains($ct, 'text/html')) {
            return false;
        }
        $content = $response->getContent();
        if (! is_string($content) || $content === '') {
            return false;
        }
        // Signature of the IO show page (@section('body-class','view informationobject')).
        return str_contains($content, 'view informationobject') && str_contains($content, '</body>');
    }

    /** Resolve the IO id from a single-segment /{slug} URL; null otherwise. */
    private function resolveIoId(Request $request): ?int
    {
        $path = trim($request->path(), '/');
        if ($path === '' || str_contains($path, '/')) {
            return null; // only the top-level /{slug} show page
        }
        try {
            $objectId = DB::table('slug')->where('slug', $path)->value('object_id');
            if (! $objectId) {
                return null;
            }
            $isIo = DB::table('information_object')->where('id', $objectId)->exists();
            return $isIo ? (int) $objectId : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function hasActiveDsar(): bool
    {
        try {
            return DB::table('privacy_dsar')
                ->whereIn('status', ['processing', 'received', 'in_progress', 'verifying'])
                ->exists();
        } catch (Throwable $e) {
            return false;
        }
    }
}
