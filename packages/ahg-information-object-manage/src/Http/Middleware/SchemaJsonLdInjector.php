<?php

/**
 * SchemaJsonLdInjector — server-side HTML response filter that injects a
 * Schema.org JSON-LD block into the `<head>` of information-object,
 * actor, and repository show pages.
 *
 * Phase 1 of #670 (Schema.org SEO sub-task). Mirrors the existing
 * VersionLinkInjector / ShareLinkInjector pattern so we don't have to
 * edit any locked show.blade.php. Output:
 *
 *   <script type="application/ld+json">{ ... }</script>
 *
 * Schema.org types emitted:
 *   - information_object  → ArchiveComponent (with isPartOf when hierarchical)
 *   - actor / repository  → Organization
 *
 * Silent on failure (non-HTML response, no slug match, entity not found).
 * Never breaks the response.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgInformationObjectManage\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class SchemaJsonLdInjector
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            if (! $this->isCandidate($request, $response)) {
                return $response;
            }
            $jsonLd = $this->buildJsonLd($request);
            if ($jsonLd === null) {
                return $response;
            }
            $body = (string) $response->getContent();
            $modified = $this->inject($body, $jsonLd);
            if ($modified !== null) {
                $response->setContent($modified);
            }
        } catch (\Throwable $e) {
            // Never break a show-page render on injection failure
        }

        return $response;
    }

    private function isCandidate(Request $request, Response $response): bool
    {
        if ($request->method() !== 'GET' || $request->ajax() || $request->wantsJson()) {
            return false;
        }
        $contentType = (string) $response->headers->get('Content-Type', '');
        if (stripos($contentType, 'text/html') !== 0) {
            return false;
        }
        return true;
    }

    /**
     * Resolve the entity from the request path and build the JSON-LD doc.
     * Returns null when the request isn't a recognised show page.
     */
    private function buildJsonLd(Request $request): ?string
    {
        $path = trim($request->path(), '/');
        // Bare slug at the root (Heratio's IO show URL pattern), or /actor/{slug}
        // for actor show, or /repository/{slug} for repository show.
        $segments = explode('/', $path);
        if (count($segments) < 1) {
            return null;
        }

        // Try the bare-slug path first (information-object show)
        $slug = end($segments);
        if (empty($slug) || $slug === 'search' || str_contains($slug, '.')) {
            return null;
        }

        if (! Schema::hasTable('slug') || ! Schema::hasTable('information_object')) {
            return null;
        }

        $slugRow = DB::table('slug')->where('slug', $slug)->first(['object_id']);
        if (! $slugRow) {
            return null;
        }
        $objectId = (int) $slugRow->object_id;

        // What kind of entity does this slug attach to? Try IO first.
        $io = DB::table('information_object as i')
            ->join('information_object_i18n as i18n', function ($j) {
                $j->on('i.id', '=', 'i18n.id')->where('i18n.culture', '=', app()->getLocale());
            })
            ->where('i.id', $objectId)
            ->select('i.id', 'i.identifier', 'i.parent_id',
                    'i18n.title', 'i18n.scope_and_content')
            ->first();
        if ($io) {
            return $this->buildIoJsonLd($io, $slug, $request);
        }

        // Try actor (used for actor + repository + donor + rights_holder)
        if (Schema::hasTable('actor') && Schema::hasTable('actor_i18n')) {
            $actor = DB::table('actor as a')
                ->join('actor_i18n as ai', function ($j) {
                    $j->on('a.id', '=', 'ai.id')->where('ai.culture', '=', app()->getLocale());
                })
                ->where('a.id', $objectId)
                ->select('a.id', 'a.entity_type_id', 'ai.authorized_form_of_name', 'ai.history')
                ->first();
            if ($actor) {
                return $this->buildActorJsonLd($actor, $slug, $request);
            }
        }

        return null;
    }

    private function buildIoJsonLd(object $io, string $slug, Request $request): string
    {
        $base = rtrim($request->getSchemeAndHttpHost(), '/');
        $url = $base . '/' . $slug;

        $doc = [
            '@context' => 'https://schema.org',
            '@type'    => 'ArchiveComponent',
            '@id'      => $url,
            'name'     => (string) ($io->title ?? ''),
            'url'      => $url,
        ];
        if (!empty($io->identifier)) {
            $doc['identifier'] = (string) $io->identifier;
        }
        if (!empty($io->scope_and_content)) {
            $doc['description'] = mb_substr(
                trim(preg_replace('/\s+/', ' ', strip_tags((string) $io->scope_and_content))),
                0,
                5000
            );
        }
        if (!empty($io->parent_id) && (int) $io->parent_id !== 1) {
            $parentSlug = DB::table('slug')->where('object_id', $io->parent_id)->value('slug');
            if ($parentSlug) {
                $doc['isPartOf'] = [
                    '@type' => 'ArchiveComponent',
                    '@id'   => $base . '/' . $parentSlug,
                ];
            }
        }

        return $this->renderScript($doc, $request);
    }

    private function buildActorJsonLd(object $actor, string $slug, Request $request): string
    {
        $base = rtrim($request->getSchemeAndHttpHost(), '/');
        $url = $base . '/' . $slug;
        // Map entity_type_id to Schema.org type:
        //   131 = corporate  -> Organization
        //   132 = personal   -> Person
        //   130 = family     -> Person  (no Schema.org Family type — Person is acceptable per spec)
        $type = match ((int) ($actor->entity_type_id ?? 0)) {
            131     => 'Organization',
            132     => 'Person',
            130     => 'Person',
            default => 'Thing',
        };
        $doc = [
            '@context' => 'https://schema.org',
            '@type'    => $type,
            '@id'      => $url,
            'name'     => (string) ($actor->authorized_form_of_name ?? ''),
            'url'      => $url,
        ];
        if (!empty($actor->history)) {
            $doc['description'] = mb_substr(
                trim(preg_replace('/\s+/', ' ', strip_tags((string) $actor->history))),
                0,
                5000
            );
        }
        return $this->renderScript($doc, $request);
    }

    private function renderScript(array $doc, Request $request): string
    {
        $json = json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        // CSP nonce — same source as the theme uses for inline scripts
        $nonce = '';
        try {
            $cspNonce = config('csp.nonce') ?? (function_exists('csp_nonce') ? csp_nonce() : null);
            if ($cspNonce) {
                $nonce = ' nonce="' . htmlspecialchars((string) $cspNonce, ENT_QUOTES) . '"';
            }
        } catch (\Throwable $e) {
            // Best effort — CSP nonce is optional for JSON-LD per spec
        }
        return "\n<script type=\"application/ld+json\"{$nonce}>\n{$json}\n</script>\n";
    }

    private function inject(string $body, string $scriptTag): ?string
    {
        // Inject just before the closing </head>. If no </head> (non-HTML
        // page, partial response), bail.
        if (stripos($body, '</head>') === false) {
            return null;
        }
        return preg_replace('#</head>#i', $scriptTag . '</head>', $body, 1);
    }
}
