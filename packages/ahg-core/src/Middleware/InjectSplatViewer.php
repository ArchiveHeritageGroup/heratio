<?php

/**
 * InjectSplatViewer - Heratio ahg-core
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * heratio#1193 - render a record's Gaussian-splat capture inline on its description page,
 * uniformly across every GLAM sector (museum / gallery / library / DAM / archival), without
 * editing each sector's (locked) show view. When the resolved object has a linked, ready splat
 * this injects a standard collapsible "Photoreal 3D capture" panel embedding the /splat viewer.
 * Best-effort: never breaks the page render.
 */
class InjectSplatViewer
{
    /** Record show routes across the GLAM sectors. */
    private const SHOW_ROUTES = [
        'museum.show', 'gallery.show', 'library.show', 'dam.show', 'informationobject.show',
    ];

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        try {
            if (! $this->eligible($request, $response)) {
                return $response;
            }
            $slug = (string) $request->route('slug');
            if ($slug === '') {
                return $response;
            }
            $objectId = DB::table('slug')->where('slug', $slug)->value('object_id');
            if (! $objectId) {
                return $response;
            }
            $splat = DB::table('ahg_gaussian_splat')
                ->where('information_object_id', $objectId)
                ->where('status', 'ready')->whereNotNull('file_name')
                ->orderByDesc('id')->first();
            if (! $splat) {
                return $response;
            }

            $html = (string) $response->getContent();
            if ($html === '' || ! str_contains($html, '</body>') || str_contains($html, 'ahg-splat-embed')) {
                return $response;
            }
            $response->setContent(str_replace('</body>', $this->panel($splat)."\n</body>", $html));
        } catch (Throwable $e) {
            // never break the page
        }

        return $response;
    }

    private function eligible(Request $request, $response): bool
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
        $name = optional($request->route())->getName();

        return in_array($name, self::SHOW_ROUTES, true);
    }

    private function panel(object $splat): string
    {
        $title = htmlspecialchars((string) $splat->title, ENT_QUOTES);
        $src = '/splat/'.rawurlencode((string) $splat->slug).'?embed=1';

        return <<<HTML
<section id="ahg-splat-embed" class="container my-4">
  <div class="card">
    <div class="card-header fw-bold d-flex align-items-center" style="background:var(--ahg-primary,#005837);color:#fff">
      <i class="fas fa-cube me-2"></i>Photoreal 3D capture
      <a href="/splat/{$splat->slug}" target="_blank" rel="noopener" class="ms-auto btn btn-sm btn-light" style="font-size:.75rem">Open full screen</a>
    </div>
    <div class="card-body p-0">
      <iframe title="{$title} - 3D capture" src="{$src}" loading="lazy"
        style="width:100%;height:72vh;border:0;display:block" allow="fullscreen"></iframe>
    </div>
  </div>
</section>
HTML;
    }
}
