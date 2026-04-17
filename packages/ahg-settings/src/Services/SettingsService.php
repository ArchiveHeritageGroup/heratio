<?php

/**
 * SettingsService - Service for Heratio
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



namespace AhgSettings\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SettingsService
{
    public function getSetting(string $name, ?string $scope = null, string $culture = 'en'): ?string
    {
        $query = DB::table('setting')
            ->leftJoin('setting_i18n', function ($join) use ($culture) {
                $join->on('setting.id', '=', 'setting_i18n.id')
                    ->where('setting_i18n.culture', '=', $culture);
            })
            ->where('setting.name', $name);

        if ($scope === null) {
            $query->whereNull('setting.scope');
        } else {
            $query->where('setting.scope', $scope);
        }

        return $query->value('setting_i18n.value');
    }

    public function saveSetting(string $name, ?string $scope, string $value, string $culture = 'en'): void
    {
        $query = DB::table('setting')->where('name', $name);
        if ($scope === null) {
            $query->whereNull('scope');
        } else {
            $query->where('scope', $scope);
        }

        $setting = $query->first();

        if ($setting) {
            DB::table('setting_i18n')->updateOrInsert(
                ['id' => $setting->id, 'culture' => $culture],
                ['value' => $value]
            );
        }
    }

    public function getSettingsByScope(?string $scope, string $culture = 'en'): array
    {
        $query = DB::table('setting')
            ->leftJoin('setting_i18n', function ($join) use ($culture) {
                $join->on('setting.id', '=', 'setting_i18n.id')
                    ->where('setting_i18n.culture', '=', $culture);
            });

        if ($scope === null) {
            $query->whereNull('setting.scope');
        } else {
            $query->where('setting.scope', $scope);
        }

        return $query
            ->select('setting.id', 'setting.name', 'setting.scope', 'setting_i18n.value')
            ->orderBy('setting.name')
            ->get()
            ->keyBy('name')
            ->toArray();
    }

    public function getAhgSetting(string $key): ?string
    {
        return DB::table('ahg_settings')
            ->where('setting_key', $key)
            ->value('setting_value');
    }

    public function saveAhgSetting(string $key, ?string $value): void
    {
        DB::table('ahg_settings')
            ->where('setting_key', $key)
            ->update(['setting_value' => $value]);
    }

    public function getAhgSettingsByGroup(string $group): \Illuminate\Support\Collection
    {
        return DB::table('ahg_settings')
            ->where('setting_group', $group)
            ->orderBy('setting_key')
            ->get();
    }

    public function saveAhgSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            DB::table('ahg_settings')
                ->where('setting_key', $key)
                ->update(['setting_value' => $value]);
        }
    }

    public function getEmailSettings(): array
    {
        if (!Schema::hasTable('email_setting')) {
            return ['smtp' => collect(), 'notification' => collect(), 'template' => collect(), 'toggles' => []];
        }

        $all = DB::table('email_setting')->orderBy('setting_key')->get();

        $smtp = $all->filter(fn ($s) => str_starts_with($s->setting_key, 'smtp_'));
        $notification = $all->filter(fn ($s) => str_starts_with($s->setting_key, 'notify_'));
        $template = $all->filter(fn ($s) => str_starts_with($s->setting_key, 'email_'));

        $toggles = [];
        foreach (['research_email_notifications', 'access_request_email_notifications', 'workflow_email_notifications'] as $key) {
            $toggles[$key] = $this->getAhgSetting($key) ?? '1';
        }

        return compact('smtp', 'notification', 'template', 'toggles');
    }

    public function saveEmailSettings(array $settings, array $toggles = []): void
    {
        foreach ($settings as $key => $value) {
            DB::table('email_setting')
                ->where('setting_key', $key)
                ->update(['setting_value' => $value]);
        }

        foreach ($toggles as $key => $value) {
            $this->saveAhgSetting($key, $value);
        }
    }

    public function getGlobalSettings(string $culture = 'en'): array
    {
        $names = [
            'hits_per_page', 'sort_browser_user', 'sort_browser_anonymous',
            'default_archival_description_browse_view', 'default_repository_browse_view',
            'escape_queries', 'show_tooltips', 'draft_notification_enabled',
            'multi_repository', 'enable_institutional_scoping',
            'slug_basis_informationobject', 'permissive_slug_creation',
            'audit_log_enabled', 'generate_reports_as_pub_user',
            'cache_xml_on_save', 'defaultPubStatus',
            'google_maps_api_key', 'sword_deposit_dir',
            'version', 'check_for_updates',
        ];

        $settings = [];
        foreach ($names as $name) {
            $settings[$name] = $this->getSetting($name, null, $culture);
        }
        return $settings;
    }

    public function saveGlobalSettings(array $data, string $culture = 'en'): void
    {
        foreach ($data as $name => $value) {
            $this->saveSetting($name, null, $value ?? '', $culture);
        }
    }

    public function getSiteInformation(string $culture = 'en'): array
    {
        return [
            'siteTitle' => $this->getSetting('siteTitle', null, $culture) ?? '',
            'siteDescription' => $this->getSetting('siteDescription', null, $culture) ?? '',
            'siteBaseUrl' => $this->getSetting('siteBaseUrl', null, $culture) ?? '',
        ];
    }

    public function getSecuritySettings(string $culture = 'en'): array
    {
        return [
            'limit_admin_ip' => $this->getSetting('limit_admin_ip', null, $culture) ?? '',
            'require_ssl_admin' => $this->getSetting('require_ssl_admin', null, $culture) ?? '0',
            'require_strong_passwords' => $this->getSetting('require_strong_passwords', null, $culture) ?? '0',
        ];
    }

    public function getIdentifierSettings(string $culture = 'en'): array
    {
        $names = [
            'accession_mask_enabled', 'accession_mask', 'accession_counter',
            'identifier_mask_enabled', 'identifier_mask', 'identifier_counter',
            'separator_character', 'inherit_code_informationobject',
            'inherit_code_dc_xml', 'prevent_duplicate_actor_identifiers',
        ];
        $settings = [];
        foreach ($names as $name) {
            $settings[$name] = $this->getSetting($name, null, $culture) ?? '';
        }
        return $settings;
    }

    public function getTreeviewSettings(string $culture = 'en'): array
    {
        $names = [
            'treeview_type', 'show_browse_hierarchy_page', 'allow_full_width_treeview_collapse',
            'sort', 'treeview_show_identifier', 'treeview_show_level_of_description',
            'treeview_show_dates', 'treeview_items_per_page',
        ];
        $settings = [];
        foreach ($names as $name) {
            $settings[$name] = $this->getSetting($name, null, $culture) ?? '';
        }
        return $settings;
    }

    public function getOaiSettings(string $culture = 'en'): array
    {
        $settings = $this->getSettingsByScope('oai', $culture);
        $names = [
            'oai_authentication_enabled', 'oai_repository_code', 'oai_admin_emails',
            'oai_repository_identifier', 'sample_oai_identifier',
            'resumption_token_limit', 'oai_additional_sets_enabled',
        ];
        $result = [];
        foreach ($names as $name) {
            $result[$name] = isset($settings[$name]) ? ($settings[$name]->value ?? '') : '';
        }
        return $result;
    }

    public function getSystemInfo(): array
    {
        $info = [];
        $info['php_version'] = PHP_VERSION;
        $info['laravel_version'] = app()->version();
        $info['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'CLI';
        $info['os'] = php_uname();
        $info['memory_limit'] = ini_get('memory_limit');
        $info['max_execution_time'] = ini_get('max_execution_time');
        $info['upload_max_filesize'] = ini_get('upload_max_filesize');
        $info['post_max_size'] = ini_get('post_max_size');

        $extensions = get_loaded_extensions();
        sort($extensions);
        $info['extensions'] = $extensions;

        $dbSize = DB::select("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.tables WHERE table_schema = DATABASE()");
        $info['database_size_mb'] = $dbSize[0]->size_mb ?? 'N/A';
        $info['table_count'] = DB::select("SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema = DATABASE()")[0]->cnt ?? 0;

        return $info;
    }

    public function getDigitalObjectSettings(string $culture = 'en'): array
    {
        $names = [
            'digital_object_derivatives_pdf_page_number',
            'reference_image_maxwidth',
        ];
        $settings = [];
        foreach ($names as $name) {
            $settings[$name] = $this->getSetting($name, null, $culture) ?? '';
        }
        return $settings;
    }

    public function getInterfaceLabelSettings(string $culture = 'en'): array
    {
        return $this->getSettingsByScope('ui_label', $culture);
    }

    public function getLanguageSettings(string $culture = 'en'): array
    {
        return $this->getSettingsByScope('i18n_languages', $culture);
    }
}
