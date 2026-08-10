<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgApi\Concerns;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

/**
 * Shared scaffolding for the self-documenting API "cockpit" controllers
 * (Cookbook, Maturity, Protocol). These content-negotiation / CORS / route-
 * resolution helpers were byte-identical across all three; each controller
 * keeps only its own document() (the JSON body) and html() (the human page),
 * which index() calls.
 *
 * resolveRoute() is kept as an alias of resolve() so CookbookController's
 * existing $this->resolveRoute() call sites are unchanged.
 */
trait ServesApiDocument
{
    public function options(): Response
    {
        return $this->withCors(response('', 204));
    }

    public function index(Request $request, bool $forceJson = false): Response
    {
        if (! $forceJson && $this->wantsHtml($request)) {
            return $this->withCors(response($this->html(), 200, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]));
        }

        $body = json_encode(
            $this->document(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return $this->withCors(response($body, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
        ]));
    }

    protected function wantsHtml(Request $request): bool
    {
        $accept = strtolower((string) $request->header('Accept', ''));

        if (str_contains($accept, 'application/json') || str_contains($accept, 'application/ld+json')) {
            return false;
        }

        return str_contains($accept, 'text/html') || str_contains($accept, 'application/xhtml');
    }

    protected function withCors(Response $response): Response
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Accept, Content-Type');
        $response->headers->set('Vary', 'Accept');
        $response->headers->set('X-Open-Data', 'true');

        return $response;
    }

    protected function base(): string
    {
        return rtrim((string) url('/'), '/');
    }

    protected function resolve(string $routeName, ?string $fallbackPath = null): ?string
    {
        if (Route::has($routeName)) {
            try {
                return route($routeName);
            } catch (\Throwable $e) {
                // fall through to the literal path
            }
        }

        return $fallbackPath !== null ? url($fallbackPath) : null;
    }

    /** Alias of resolve() - kept for CookbookController's call sites. */
    protected function resolveRoute(string $routeName, ?string $fallbackPath = null): ?string
    {
        return $this->resolve($routeName, $fallbackPath);
    }

    protected function surfacesById(): array
    {
        try {
            $surfaces = app(\AhgApi\Controllers\ProtocolController::class)->surfaces();
        } catch (\Throwable $e) {
            return [];
        }

        $byId = [];
        foreach ($surfaces as $surface) {
            if (! empty($surface['id'])) {
                $byId[(string) $surface['id']] = $surface;
            }
        }

        return $byId;
    }
}
