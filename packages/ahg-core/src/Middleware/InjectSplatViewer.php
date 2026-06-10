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
            // Primary path: a splat uploaded as a normal digital object on this record
            // (via "Link digital object") - no separate manager, no attach step.
            $doSplat = DB::table('digital_object')
                ->where('object_id', $objectId)
                ->where(function ($q) {
                    $q->where('name', 'like', '%.splat')->orWhere('name', 'like', '%.ksplat')->orWhere('name', 'like', '%.ply');
                })
                ->orderByDesc('id')->first();

            // A .ply is only a splat if its header carries the 3DGS signature; a mesh .ply
            // stays with the standard 3D-model viewer.
            if ($doSplat) {
                $ext = strtolower(pathinfo((string) $doSplat->name, PATHINFO_EXTENSION));
                $isSplat = in_array($ext, ['splat', 'ksplat'], true)
                    || ($ext === 'ply' && app(\AhgCore\Services\GaussianSplatService::class)->isGaussianPly($doSplat));
                if (! $isSplat) {
                    $doSplat = null;
                }
            }

            if ($doSplat) {
                $embedUrl = '/splat/do/'.$doSplat->id.'?embed=1';
                $title = (string) ($doSplat->name ?: 'Capture');
            } else {
                // Fallback: a splat linked from the standalone splat manager.
                $splat = DB::table('ahg_gaussian_splat')
                    ->where('information_object_id', $objectId)
                    ->where('status', 'ready')->whereNotNull('file_name')
                    ->orderByDesc('id')->first();
                if (! $splat) {
                    return $response;
                }
                $embedUrl = '/splat/'.rawurlencode((string) $splat->slug).'?embed=1';
                $title = (string) $splat->title;
            }

            $html = (string) $response->getContent();
            // Skip if already injected, or the page already renders the splat inline via the
            // shared digital-object viewer's "3D" mode (btn-splat-*) - no double render.
            if ($html === '' || str_contains($html, 'ahg-splat-embed') || str_contains($html, 'btn-splat-')) {
                return $response;
            }
            $panel = $this->panel($title, $embedUrl);
            // Prefer the top of the record content (shared theme anchor) so the viewer is
            // visible, not buried below the footer. Fall back to before the footer, then </body>.
            if (str_contains($html, '<div id="content">')) {
                $html = preg_replace('/<div id="content">/', '<div id="content">'.$panel, $html, 1);
            } elseif (str_contains($html, '<footer')) {
                $html = preg_replace('/<footer/', $panel.'<footer', $html, 1);
            } elseif (str_contains($html, '</body>')) {
                $html = str_replace('</body>', $panel."\n</body>", $html);
            } else {
                return $response;
            }
            $response->setContent($html);
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

    private function panel(string $title, string $embedUrl): string
    {
        $t = htmlspecialchars($title, ENT_QUOTES);
        $src = htmlspecialchars($embedUrl, ENT_QUOTES);
        $full = htmlspecialchars(preg_replace('/\?embed=1$/', '', $embedUrl), ENT_QUOTES);

        return <<<HTML
<section id="ahg-splat-embed" class="my-4">
  <div class="card">
    <div class="card-header fw-bold d-flex align-items-center" style="background:var(--ahg-primary,#005837);color:#fff">
      <i class="fas fa-cube me-2"></i>Photoreal 3D capture
      <a href="{$full}" target="_blank" rel="noopener" class="ms-auto btn btn-sm btn-light" style="font-size:.75rem">Open full screen</a>
    </div>
    <div class="card-body p-0">
      <iframe title="{$t} - 3D capture" src="{$src}" loading="lazy"
        style="width:100%;height:72vh;border:0;display:block" allow="fullscreen"></iframe>
    </div>
  </div>
</section>
HTML;
    }
}
