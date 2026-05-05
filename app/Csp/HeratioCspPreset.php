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

            // Scripts: self + nonce (auto-injected) + CDN bundles used by views
            ->add(Directive::SCRIPT, [
                Keyword::SELF,
                'https://cdn.jsdelivr.net',
                'https://cdnjs.cloudflare.com',
                'https://unpkg.com',
            ])
            ->addNonce(Directive::SCRIPT)

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
            ]);
    }
}
