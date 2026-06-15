<?php

/**
 * HeratioCspPreset — defense-in-depth CSP for the Heratio platform.
 *
 * Allowlists:
 *   self                 — default for everything
 *   nonce                — auto-injected by spatie/laravel-csp into <script> / <style> tags
 *   jsdelivr / cdnjs     — Bootstrap, Font Awesome, the AtoM-era theme bundle
 *   Google Fonts         — fonts.googleapis.com (CSS) + fonts.gstatic.com (font files)
 *   data: in img-src     — needed for SVG-as-data-URL icons embedded in views
 *   https: in img-src    — registry/institutions/vendors host external logo URLs
 *   IIIF (Cantaloupe)    — local proxy on /iiif/, OpenSeadragon needs blob:
 *
 * Soft-rollout intent: install this in `report_only_presets` first to log
 * violations without breaking pages, then promote to `presets` once the
 * report log is clean.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace App\Csp;

use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Policy;
use Spatie\Csp\Preset;
use Spatie\Csp\Scheme;

class HeratioCspPreset implements Preset
{
    public function configure(Policy $policy): void
    {
        $policy
            ->add(Directive::BASE, Keyword::SELF)
            ->add(Directive::DEFAULT, Keyword::SELF)
            ->add(Directive::OBJECT, Keyword::NONE)
            ->add(Directive::FORM_ACTION, Keyword::SELF)
            ->add(Directive::FRAME_ANCESTORS, Keyword::SELF)

            // Scripts: self + 'unsafe-inline' + CDN bundles used by views.
            //
            // We deliberately do NOT add a nonce to script-src. The AtoM-ported
            // admin UI uses inline event-handler attributes (onclick=, onchange=,
            // ...) pervasively. CSP nonces apply to <script> tags but CANNOT be
            // attached to inline event handlers, and the presence of a nonce makes
            // the browser ignore 'unsafe-inline' — so a nonce-source silently
            // breaks every inline handler (buttons do nothing). Until those
            // handlers are migrated to addEventListener, keep 'unsafe-inline' for
            // scripts (mirrors the style-src decision below).
            ->add(Directive::SCRIPT, [
                Keyword::SELF,
                Keyword::UNSAFE_INLINE,
                // Draco-compressed 3D models decode in WebAssembly inside
                // <model-viewer>; without this the geometry never loads.
                Keyword::UNSAFE_WEB_ASSEMBLY_EXECUTION,
                // 'unsafe-eval': several bundled libraries evaluate strings
                // (new Function / setTimeout(string)) - the 3D / splat stack and
                // some CDN widgets. The nginx-level CSP already allows it; without
                // it here the two CSP headers intersect and the browser blocks eval
                // ("CSP blocks the use of 'eval'"), breaking those scripts.
                Keyword::UNSAFE_EVAL,
                // blob: so the Gaussian-splat viewer's sort worker
                // (@mkkellogg/gaussian-splats-3d spawns a blob: Web Worker whose
                // script the browser also checks against script-src) is not
                // blocked - otherwise the splat viewer hangs at "Processing splats".
                // (worker-src + connect-src already allow blob: for OSD/Mirador.)
                Scheme::BLOB,
                'https://cdn.jsdelivr.net',
                'https://cdnjs.cloudflare.com',
                'https://unpkg.com',
                // <model-viewer> bundle + its Draco/KTX2 decoders (gstatic).
                'https://ajax.googleapis.com',
                'https://www.gstatic.com',
            ])

            // Styles: self + 'unsafe-inline' + CDN bundles + Google Fonts.
            //
            // We deliberately do NOT add a nonce to style-src. Mirador 3 (and
            // any other library that bundles MUI v5 / Emotion) injects runtime
            // <style> blocks from a pre-built bundle that has no way to learn
            // our per-request nonce. CSP's "nonce-source ignores unsafe-inline"
            // rule would block those injections and the library renders
            // unstyled. Keeping unsafe-inline for styles only is the standard
            // trade-off: style-based attacks are exfiltration via attribute
            // selectors, materially smaller than the script-RCE class that the
            // script nonce defends against.
            ->add(Directive::STYLE, [
                Keyword::SELF,
                Keyword::UNSAFE_INLINE,
                'https://cdn.jsdelivr.net',
                'https://cdnjs.cloudflare.com',
                'https://fonts.googleapis.com',
            ])

            // Fonts
            ->add(Directive::FONT, [
                Keyword::SELF,
                'https://fonts.gstatic.com',
                'https://cdnjs.cloudflare.com',
                Scheme::DATA,
            ])

            // Images: external logos, data-URI SVGs, IIIF tiles
            ->add(Directive::IMG, [
                Keyword::SELF,
                Scheme::DATA,
                Scheme::HTTPS,
                Scheme::BLOB,
            ])

            // Media: audio/video derivative previews
            ->add(Directive::MEDIA, [
                Keyword::SELF,
                Scheme::BLOB,
            ])

            // Connect: AJAX endpoints (autocomplete, IIIF info.json, search).
            // blob: is required for Mirador, which fetches its manifest from a
            // URL.createObjectURL() blob built client-side. cdnjs allowed so
            // PDF.js can fetch its cmap / standard-fonts data files
            // (referenced relative to the worker URL).
            ->add(Directive::CONNECT, [
                Keyword::SELF,
                Scheme::BLOB,
                'https://cdn.jsdelivr.net',
                'https://cdnjs.cloudflare.com',
                // <model-viewer> fetches its Draco/KTX2 decoder data at runtime.
                'https://ajax.googleapis.com',
                'https://www.gstatic.com',
            ])

            // Frames: same-origin only (PDF previews, IIIF embed)
            ->add(Directive::FRAME, Keyword::SELF)

            // Workers: PDF.js loads pdf.worker.min.js from cdnjs, so the
            // worker source must be allow-listed there in addition to self
            // + blob (OpenSeadragon's tile-renderer worker, Mirador's
            // worker bundle which lives at /vendor/, etc.). Without
            // cdnjs here the redaction page silently fails to render any
            // PDF because PDF.js cannot start its background thread.
            ->add(Directive::WORKER, [
                Keyword::SELF,
                Scheme::BLOB,
                'https://cdnjs.cloudflare.com',
                // <model-viewer> runs the Draco decoder in a worker.
                'https://www.gstatic.com',
            ]);
    }
}
