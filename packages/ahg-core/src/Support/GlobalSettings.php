<?php

/**
 * GlobalSettings - typed accessors for the legacy AtoM `setting` table
 * (scope IS NULL, editable=1) surfaced on /admin/settings/global.
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

namespace AhgCore\Support;

use AhgCore\Services\SettingHelper;

/**
 * Each accessor reads one row from the legacy AtoM `setting` table via
 * SettingHelper::get(). Defaults match the install seed where applicable so
 * an empty / absent row falls back to the documented Heratio default rather
 * than a string. Consumers should call these accessors rather than hardcode
 * defaults inline so the operator's /admin/settings/global change surfaces
 * everywhere immediately.
 */
class GlobalSettings
{
    // ── Publication + visibility ───────────────────────────────────────

    /**
     * Default publication_status_id for new information objects when the
     * caller doesn't supply one. Maps to status table type_id=158 status_id
     * (159 Draft / 160 Published).
     */
    public static function defaultPublicationStatusId(int $fallbackDraftId): int
    {
        $raw = SettingHelper::get('defaultPubStatus', '');
        return $raw !== '' && ctype_digit($raw) ? (int) $raw : $fallbackDraftId;
    }

    public static function publicFindingAid(): bool
    {
        return self::asBool(SettingHelper::get('publicFindingAid', '1'));
    }

    public static function generateReportsAsPubUser(): bool
    {
        return self::asBool(SettingHelper::get('generate_reports_as_pub_user', '0'));
    }

    // ── Identifier + inheritance ───────────────────────────────────────

    public static function inheritCodeInformationObject(): bool
    {
        return self::asBool(SettingHelper::get('inherit_code_informationobject', '0'));
    }

    public static function permissiveSlugCreation(): bool
    {
        return self::asBool(SettingHelper::get('permissive_slug_creation', '0'));
    }

    // ── Site identity ──────────────────────────────────────────────────

    /**
     * Operator-configured base URL. Falls back to config('app.url') so
     * Heratio's APP_URL env-driven URL stays as the canonical default
     * unless the operator explicitly sets a different absolute URL via
     * /admin/settings/global (useful behind reverse proxies whose
     * APP_URL doesn't match the public URL).
     */
    public static function siteBaseUrl(): string
    {
        $val = trim((string) SettingHelper::get('siteBaseUrl', ''));
        return $val !== '' ? rtrim($val, '/') : rtrim((string) config('app.url', ''), '/');
    }

    // ── Theme ──────────────────────────────────────────────────────────

    /**
     * CSS color value injected into the dynamic stylesheet's --ahg-primary
     * variable. Empty string means "use the theme default" (typical
     * --ahg-primary in `partials/feedback-tab.blade.php`).
     */
    public static function headerBackgroundColour(): string
    {
        return trim((string) SettingHelper::get('header_background_colour', ''));
    }

    // ── Search + sort ──────────────────────────────────────────────────

    public static function escapeQueries(): bool
    {
        return self::asBool(SettingHelper::get('escape_queries', '1'));
    }

    /**
     * Default sort field for the public browse view. Returns one of the
     * Heratio-defined sort tokens (e.g. 'title', 'identifier', 'lastUpdated',
     * 'relevance'); 'lastUpdated' matches the form default.
     */
    public static function sortBrowserAnonymous(): string
    {
        $val = trim((string) SettingHelper::get('sort_browser_anonymous', 'lastUpdated'));
        return $val !== '' ? $val : 'lastUpdated';
    }

    public static function sortBrowserUser(): string
    {
        $val = trim((string) SettingHelper::get('sort_browser_user', 'lastUpdated'));
        return $val !== '' ? $val : 'lastUpdated';
    }

    public static function sortTreeviewInformationObject(): string
    {
        $val = trim((string) SettingHelper::get('sort_treeview_informationobject', 'manual'));
        return $val !== '' ? $val : 'manual';
    }

    // ── Treeview / display ─────────────────────────────────────────────

    public static function treeviewType(): string
    {
        $val = trim((string) SettingHelper::get('treeview_type', 'sidebar'));
        return $val !== '' ? $val : 'sidebar';
    }

    public static function stripExtensions(): bool
    {
        return self::asBool(SettingHelper::get('stripExtensions', '0'));
    }

    /**
     * Display helper: returns the filename minus its extension when
     * settings.stripExtensions is on; otherwise the original name. Safe to
     * call from any blade with `{{ \AhgCore\Support\GlobalSettings::displayFilename($do->name) }}`.
     * Null-safe (returns the input when blank).
     */
    public static function displayFilename(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return $name;
        }
        if (!self::stripExtensions()) {
            return $name;
        }
        $stem = pathinfo($name, PATHINFO_FILENAME);
        return $stem !== '' ? $stem : $name;
    }

    // ── Multi-repository ───────────────────────────────────────────────

    public static function multiRepository(): bool
    {
        return self::asBool(SettingHelper::get('multi_repository', '1'));
    }

    public static function repositoryQuota(): int
    {
        $raw = SettingHelper::get('repository_quota', '0');
        return ctype_digit($raw) ? (int) $raw : 0;
    }

    // ── Finding aid ────────────────────────────────────────────────────

    public static function findingAidFormat(): string
    {
        $val = trim((string) SettingHelper::get('findingAidFormat', 'pdf'));
        return $val !== '' ? $val : 'pdf';
    }

    public static function findingAidModel(): string
    {
        $val = trim((string) SettingHelper::get('findingAidModel', 'inventory-summary'));
        return $val !== '' ? $val : 'inventory-summary';
    }

    // ── Helpers ────────────────────────────────────────────────────────

    protected static function asBool(string $val): bool
    {
        $v = strtolower(trim($val));
        return $v === '1' || $v === 'true' || $v === 'yes' || $v === 'on';
    }
}
