<?php

namespace App\Support;

use AhgCore\Services\AhgSettingsService;

/**
 * Centralised reader for the 9 IIIF Viewer settings shown on
 * /admin/ahgSettings/iiif. Closes audit issue #81: every key on that page
 * is now consumed by either IiifController::getSettings (the JSON
 * endpoint) or by the master.blade.php injector that exposes the values
 * as window.AHG_IIIF for the front-end viewer to read at init.
 *
 * Defaults match the seeded values so a fresh install behaves the same
 * whether or not the operator has visited the form. Empty
 * iiif_server_url means "use the local Cantaloupe proxy at /iiif/3/" —
 * matches the pre-wiring hardcoded behaviour.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */
class IiifSettings
{
    public static function enabled(): bool
    {
        return AhgSettingsService::getBool('iiif_enabled', true);
    }

    public static function viewer(): string
    {
        $v = (string) AhgSettingsService::get('iiif_viewer', 'openseadragon');
        return $v !== '' ? $v : 'openseadragon';
    }

    public static function serverUrl(): string
    {
        // Empty string -> use the local Cantaloupe proxy (default). Operators
        // serving from a remote IIIF server (e.g. Internet Archive) set this
        // to the absolute origin like https://iiif.example.org.
        return rtrim((string) AhgSettingsService::get('iiif_server_url', ''), '/');
    }

    public static function defaultZoom(): float
    {
        $z = (float) AhgSettingsService::get('iiif_default_zoom', '1');
        return $z > 0 ? $z : 1.0;
    }

    public static function maxZoom(): float
    {
        $z = (float) AhgSettingsService::get('iiif_max_zoom', '10');
        return $z > 0 ? $z : 10.0;
    }

    public static function showNavigator(): bool
    {
        return AhgSettingsService::getBool('iiif_show_navigator', true);
    }

    public static function showFullscreen(): bool
    {
        return AhgSettingsService::getBool('iiif_show_fullscreen', false);
    }

    public static function showRotation(): bool
    {
        return AhgSettingsService::getBool('iiif_show_rotation', true);
    }

    public static function enableAnnotations(): bool
    {
        return AhgSettingsService::getBool('iiif_enable_annotations', false);
    }

    /**
     * Single-call payload for the JSON endpoint and the master.blade.php
     * window.AHG_IIIF injector. Keys deliberately stay snake_case so they
     * match the ahg_settings storage and the ahg-iiif-viewer.js consumer
     * pattern (no camel-case translation surface in the middle).
     */
    public static function payload(): array
    {
        return [
            'enabled'             => self::enabled(),
            'viewer'              => self::viewer(),
            'server_url'          => self::serverUrl(),
            'default_zoom'        => self::defaultZoom(),
            'max_zoom'            => self::maxZoom(),
            'show_navigator'      => self::showNavigator(),
            'show_fullscreen'     => self::showFullscreen(),
            'show_rotation'       => self::showRotation(),
            'enable_annotations'  => self::enableAnnotations(),
        ];
    }
}
