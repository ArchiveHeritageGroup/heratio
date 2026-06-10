<?php
/**
 * Heratio - Content Credentials "Verify" badge injector (issue #1201).
 *
 * Surfaces a small, plain-language "Content Credentials - Verify" badge on the
 * record show pages of every GLAM sector (archival / DAM / library / museum /
 * gallery) WITHOUT editing those (locked) show blades. It mirrors the proven
 * response-middleware pattern of AhgCore\Middleware\InjectSplatViewer: act only
 * on text/html GET 200 responses for a known show route, resolve the slug to an
 * information_object id, and - when that record has a signed C2PA manifest -
 * inject a badge that links to the public /verify/id/{ioId} authenticity page.
 *
 * Cheap and safe: one indexed lookup against ahg_c2pa_provenance (idx_io), and
 * it no-ops on every page that is not a signed show page. It never breaks the
 * render (best-effort, Throwable-guarded).
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class InjectContentCredentialsBadge
{
    /** Record show routes across the GLAM sectors (matches InjectSplatViewer). */
    private const SHOW_ROUTES = [
        'museum.show', 'gallery.show', 'library.show', 'dam.show', 'informationobject.show',
    ];

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        try {
            if (!$this->eligible($request, $response)) {
                return $response;
            }

            $slug = (string) $request->route('slug');
            if ($slug === '' || !Schema::hasTable('slug')) {
                return $response;
            }

            $objectId = (int) (DB::table('slug')->where('slug', $slug)->value('object_id') ?? 0);
            if ($objectId <= 0) {
                return $response;
            }

            // Cheap, indexed (idx_io): does this record carry a signed manifest?
            if (!Schema::hasTable('ahg_c2pa_provenance')) {
                return $response;
            }
            $signed = DB::table('ahg_c2pa_provenance')
                ->where('information_object_id', $objectId)
                ->whereNotNull('manifest_id')
                ->exists();
            if (!$signed) {
                return $response;
            }

            $html = (string) $response->getContent();
            // Skip empty bodies and double-injection.
            if ($html === '' || str_contains($html, 'ahg-c2pa-badge')) {
                return $response;
            }

            $badge = $this->badge($objectId);
            $injected = $this->inject($html, $badge);
            if ($injected === null) {
                return $response;
            }
            $response->setContent($injected);
        } catch (Throwable $e) {
            // Never break the page.
        }

        return $response;
    }

    private function eligible(Request $request, $response): bool
    {
        if (!$request->isMethod('GET')) {
            return false;
        }
        if (method_exists($response, 'getStatusCode') && $response->getStatusCode() !== 200) {
            return false;
        }
        $ct = (string) $response->headers->get('Content-Type', '');
        if ($ct !== '' && !str_contains($ct, 'text/html')) {
            return false;
        }
        $name = optional($request->route())->getName();

        return in_array($name, self::SHOW_ROUTES, true);
    }

    /**
     * Insert the badge near the record header. Prefers the shared theme content
     * anchor so it sits at the top of the record, then falls back to before the
     * footer / </main> / </body>. Returns the new HTML, or null when no anchor
     * was found (caller leaves the response untouched).
     */
    private function inject(string $html, string $badge): ?string
    {
        if (str_contains($html, '<div id="content">')) {
            return preg_replace('/<div id="content">/', '<div id="content">' . $badge, $html, 1);
        }
        if (str_contains($html, '</main>')) {
            return preg_replace('/<\/main>/', $badge . '</main>', $html, 1);
        }
        if (str_contains($html, '<footer')) {
            return preg_replace('/<footer/', $badge . '<footer', $html, 1);
        }
        if (str_contains($html, '</body>')) {
            return str_replace('</body>', $badge . "\n</body>", $html);
        }
        return null;
    }

    private function badge(int $ioId): string
    {
        $href = htmlspecialchars('/verify/id/' . $ioId, ENT_QUOTES);

        return <<<HTML
<div id="ahg-c2pa-badge" class="my-2" data-io="{$ioId}">
  <a href="{$href}" rel="noopener"
     class="d-inline-flex align-items-center text-decoration-none"
     title="This record carries signed Content Credentials. Click to verify its authenticity."
     style="gap:.4rem;padding:.25rem .6rem;border:1px solid var(--ahg-primary,#005837);border-radius:999px;background:#fff;color:var(--ahg-primary,#005837);font-size:.8rem;line-height:1.2">
    <i class="fas fa-shield-alt" aria-hidden="true"></i>
    <span class="fw-semibold">Content Credentials</span>
    <span class="text-muted" style="color:#6c757d!important">&middot; Verify</span>
  </a>
</div>
HTML;
    }
}
