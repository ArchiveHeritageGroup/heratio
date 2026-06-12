<?php

/**
 * AccessibilityStatementService - Heratio ahg-core
 *
 * heratio#1211 north-star ("every museum for everyone"), public accessibility
 * statement slice. This service assembles the data for the OUTWARD, human-readable
 * accessibility statement published at GET /accessibility-statement. It is distinct
 * from the internal /admin/accessibility coverage report (which measures metadata
 * coverage) and /admin/alt-text (which curates image descriptions); this is the
 * public commitment, the conformance claim, and the barrier-reporting channel.
 *
 * Everything configurable (institution name, contact email, conformance level,
 * preparation/review date) is read from the existing `ahg_settings` table via
 * AhgSettingsService. NO new table is introduced; if a value is unset the service
 * supplies a neutral, jurisdiction-NEUTRAL international default (WCAG 2.2 and
 * EN 301 549 are named as recognised standards, never as one country's law).
 *
 * Read-only. It never writes, never runs ALTER, makes no AI calls, and never
 * throws: every reader is guarded so the public page can render even with no
 * database (AhgSettingsService::get already returns the default on DB failure).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\Route;

class AccessibilityStatementService
{
    /**
     * Neutral, international defaults. Used whenever the matching ahg_settings key
     * is unset. Nothing here is country-specific or a legal claim.
     */
    public const DEFAULT_INSTITUTION  = 'This institution';
    public const DEFAULT_CONTACT      = 'accessibility@your-site.example';
    public const DEFAULT_LEVEL_LABEL  = 'Partially conformant, level AA targeted';
    public const DEFAULT_WCAG_VERSION = '2.2';

    /**
     * Assemble the full statement payload for the view. Never throws.
     *
     * The settings keys are read from the existing `ahg_settings` table:
     *   - accessibility_institution_name   institution / service name
     *   - accessibility_contact_email      barrier-reporting email
     *   - accessibility_contact_url        OPTIONAL feedback form / contact page
     *   - accessibility_conformance_level  conformance claim label
     *   - accessibility_wcag_version       WCAG version targeted (default 2.2)
     *   - accessibility_statement_date     preparation / last-reviewed date (free text)
     *   - accessibility_response_days      target response time in working days
     *
     * All have neutral defaults; none is required for the page to render.
     */
    public function statement(): array
    {
        return [
            'institution'       => $this->setting('accessibility_institution_name', self::DEFAULT_INSTITUTION),
            'contact_email'     => $this->setting('accessibility_contact_email', self::DEFAULT_CONTACT),
            'contact_url'       => $this->setting('accessibility_contact_url', ''),
            'conformance_level' => $this->setting('accessibility_conformance_level', self::DEFAULT_LEVEL_LABEL),
            'wcag_version'      => $this->setting('accessibility_wcag_version', self::DEFAULT_WCAG_VERSION),
            'prepared_on'       => $this->preparedOn(),
            'response_days'     => $this->responseDays(),
            // Feature cards are gated on the relevant route existing, so the
            // statement only advertises a capability that is actually wired in.
            'features'          => $this->accessibleFeatures(),
            'limitations'       => $this->knownLimitations(),
        ];
    }

    /**
     * Read one ahg_settings value, falling back to a neutral default. Trims and
     * collapses blank strings to the default so an empty setting row does not
     * surface as an empty heading.
     */
    private function setting(string $key, string $default): string
    {
        try {
            $value = AhgSettingsService::get($key, null);
        } catch (\Throwable $e) {
            $value = null;
        }

        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? $value : $default;
    }

    /**
     * Preparation / last-reviewed date. Prefers an operator-set free-text setting;
     * otherwise falls back to the deploy date (this file's mtime) so the page
     * never fabricates a legal date and never shows a blank. Returns a display
     * string, never a parsed / asserted legal date.
     */
    private function preparedOn(): string
    {
        try {
            $configured = (string) AhgSettingsService::get('accessibility_statement_date', '');
        } catch (\Throwable $e) {
            $configured = '';
        }

        $configured = trim($configured);
        if ($configured !== '') {
            return $configured;
        }

        // Neutral fallback: the deploy date of this statement, not a fabricated
        // legal date. Use this file's modification time.
        try {
            $mtime = @filemtime(__FILE__);
            if ($mtime) {
                return date('j F Y', $mtime);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return date('j F Y');
    }

    /**
     * Target response time in working days for a reported barrier. Clamped to a
     * sane 1..30 range; neutral default of 10 working days.
     */
    private function responseDays(): int
    {
        try {
            $days = (int) AhgSettingsService::get('accessibility_response_days', 10);
        } catch (\Throwable $e) {
            $days = 10;
        }

        if ($days < 1) {
            $days = 1;
        }
        if ($days > 30) {
            $days = 30;
        }

        return $days;
    }

    /**
     * Accessible-feature claims. Each is only listed when the platform actually
     * ships the capability, gated on the relevant route resolving. This keeps the
     * outward statement honest: we never advertise a feature that is not wired in.
     *
     * @return array<int,array{label:string,detail:string,route:?string}>
     */
    private function accessibleFeatures(): array
    {
        $candidates = [
            [
                'label'  => 'Keyboard navigation',
                'detail' => 'The core browse, search, and record pages can be operated with a keyboard alone, with a visible focus indicator.',
                'route'  => null,
            ],
            [
                'label'  => 'Semantic structure',
                'detail' => 'Pages use headings, landmarks, and lists so assistive technology can announce and navigate the structure.',
                'route'  => null,
            ],
            [
                'label'  => 'Text alternatives for images',
                'detail' => 'Human-authored descriptions are curated for catalogue images through an ongoing alt-text programme, so screen-reader users get a meaningful description rather than a file name.',
                'route'  => 'alt-text.index',
            ],
            [
                'label'  => 'Read a record in your language',
                'detail' => 'Visitors can read a record translated on demand into their preferred language, with the preference remembered between visits.',
                'route'  => 'read-in-language.show',
            ],
            [
                'label'  => 'Read aloud',
                'detail' => 'A read-aloud control can speak page and document text for visitors who prefer or need audio.',
                'route'  => 'tts.settings',
            ],
        ];

        $out = [];
        foreach ($candidates as $c) {
            if ($c['route'] === null || $this->routeExists($c['route'])) {
                $out[] = $c;
            }
        }

        return $out;
    }

    /**
     * Known limitations, stated honestly as gaps rather than hidden. These are
     * inherent to a digital heritage platform and are jurisdiction-neutral.
     *
     * @return array<int,string>
     */
    private function knownLimitations(): array
    {
        return [
            'Some legacy scanned material (older documents, registers, and photographs) does not yet have full searchable text or a complete text alternative. We add descriptions and transcriptions over time, prioritising the most-used material.',
            'Deep-zoom image viewers and 3D / point-cloud viewers are interactive, visual tools provided in part by third-party components. They may not be fully operable with a keyboard or screen reader. Where one is used, a non-visual alternative (a description or a downloadable file) is provided wherever possible.',
            'User-contributed content (comments, tags, and uploaded descriptions) is created by visitors and may not always meet the same accessibility standard as our own content. We review and improve it where we can.',
            'Some embedded or downloadable documents (for example older PDFs) may not be fully tagged for accessibility. We are reprocessing these over time and can provide an accessible version of an individual document on request.',
        ];
    }

    /**
     * Guarded Route::has so a missing router binding can never 500 the page.
     */
    private function routeExists(string $name): bool
    {
        try {
            return Route::has($name);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
