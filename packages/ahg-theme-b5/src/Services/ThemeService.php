<?php

/**
 * ThemeService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems.co.za
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



namespace AhgThemeB5\Services;

use AhgCore\Services\AclService;
use AhgCore\Services\AhgSettingsService;
use AhgCore\Services\MenuService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Theme service — provides all data needed by layout templates.
 */
class ThemeService
{
    /**
     * Get all data needed for the master layout.
     */
    public function getLayoutData(): array
    {
        $user = Auth::user();
        $culture = 'en';
        $enabledPlugins = MenuService::getEnabledPlugins();

        return [
            'user' => $user,
            'isAuthenticated' => (bool) $user,
            'isAdmin' => $user ? AclService::isAdministrator($user) : false,
            'isEditor' => $user ? AclService::isEditor($user) : false,
            'culture' => $culture,
            'siteTitle' => $this->getSetting('siteTitle', 'Heratio'),
            'siteDescription' => $this->getSetting('siteDescription', ''),
            'toggleLogo' => $this->getSettingBool('toggleLogo', true),
            'toggleTitle' => $this->getSettingBool('toggleTitle', true),
            'toggleDescription' => $this->getSettingBool('toggleDescription', false),
            'toggleLanguageMenu' => $this->getSettingBool('toggleLanguageMenu', false),
            'customLogo' => $this->getCustomLogo(),
            'browseMenu' => MenuService::getBrowseMenu($culture),
            'mainMenu' => MenuService::getMainMenu($culture),
            'quickLinks' => MenuService::getQuickLinks($culture),
            'enabledPlugins' => $enabledPlugins,
            'enabledPluginMap' => array_flip($enabledPlugins),
            'userGroups' => $user ? AclService::getUserGroups($user->id) : [],
            'footerText' => AhgSettingsService::get('ahg_footer_text', ''),
            'showBranding' => AhgSettingsService::getBool('ahg_show_branding', true),
            'vendorJsBundle' => $this->findBundle('js', 'vendor.bundle.*.js'),
            'themeJsBundle' => $this->findBundle('js', 'ahgThemeB5Plugin.bundle.*.js'),
            'themeCssBundle' => $this->findBundle('css', 'ahgThemeB5Plugin.bundle.*.css'),
        ];
    }

    /**
     * Get an app setting (from setting/setting_i18n tables).
     */
    private function getSetting(string $name, $default = null)
    {
        try {
            $setting = DB::table('setting')
                ->where('name', $name)
                ->first();

            if (! $setting) {
                return $default;
            }

            $i18n = DB::table('setting_i18n')
                ->where('id', $setting->id)
                ->where('culture', 'en')
                ->first();

            return $i18n?->value ?? $default;
        } catch (\Exception $e) {
            return $default;
        }
    }

    /**
     * Get a boolean app setting.
     */
    private function getSettingBool(string $name, bool $default = false): bool
    {
        $value = $this->getSetting($name, $default ? '1' : '0');

        return in_array($value, ['1', 'true', true, 1], true);
    }

    /**
     * Get custom logo path from ahg_settings.
     */
    private function getCustomLogo(): ?string
    {
        $logoPath = AhgSettingsService::get('ahg_logo_path');

        if (! $logoPath) {
            return null;
        }

        // Check if file exists in the archive uploads
        $fullPath = '/usr/share/nginx/archive' . $logoPath;
        if (file_exists($fullPath)) {
            return $logoPath;
        }

        return null;
    }

    /**
     * Find a webpack bundle by glob pattern in the dist directory.
     */
    private function findBundle(string $subdir, string $pattern): ?string
    {
        $distPath = public_path('vendor/ahg-theme-b5/dist/' . $subdir);
        $files = glob($distPath . '/' . $pattern);

        if (! empty($files)) {
            return '/vendor/ahg-theme-b5/dist/' . $subdir . '/' . basename($files[0]);
        }

        return null;
    }

    /**
     * Check if a plugin is enabled.
     */
    public function isPluginEnabled(string $name): bool
    {
        return MenuService::isPluginEnabled($name);
    }
}
