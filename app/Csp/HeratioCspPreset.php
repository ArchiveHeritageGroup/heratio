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

            // Styles: self + nonce + CDN bundles + Google Fonts
            // 'unsafe-inline' is included as a fallback — many AtoM-port views
            // still emit ad-hoc style="" attributes that aren't nonce-able.
            ->add(Directive::STYLE, [
                Keyword::SELF,
                Keyword::UNSAFE_INLINE,
                'https://cdn.jsdelivr.net',
                'https://cdnjs.cloudflare.com',
                'https://fonts.googleapis.com',
            ])
            ->addNonce(Directive::STYLE)

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
            // URL.createObjectURL() blob built client-side.
            ->add(Directive::CONNECT, [
                Keyword::SELF,
                Scheme::BLOB,
                'https://cdn.jsdelivr.net',
            ])

            // Frames: same-origin only (PDF previews, IIIF embed)
            ->add(Directive::FRAME, Keyword::SELF)

            // Workers (OpenSeadragon, etc.)
            ->add(Directive::WORKER, [Keyword::SELF, Scheme::BLOB]);
    }
}
