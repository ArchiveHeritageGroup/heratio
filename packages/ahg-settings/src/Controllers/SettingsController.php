<?php

/**
 * SettingsController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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



namespace AhgSettings\Controllers;

use AhgSettings\Services\SettingsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SettingsController extends Controller
{
    private SettingsService $service;

    private array $scopeLabels = [
        '_global' => 'Global settings',
        'default_template' => 'Default templates',
        'ui_label' => 'User interface labels',
        'element_visibility' => 'Visible elements',
        'i18n_languages' => 'Languages',
        'oai' => 'OAI repository',
        'federation' => 'Federation',
        'access_statement' => 'Access statement',
    ];

    private array $scopeIcons = [
        '_global' => 'fa-cogs',
        'default_template' => 'fa-file-alt',
        'ui_label' => 'fa-tags',
        'element_visibility' => 'fa-eye',
        'i18n_languages' => 'fa-language',
        'oai' => 'fa-cloud',
        'federation' => 'fa-network-wired',
        'access_statement' => 'fa-lock',
    ];

    private array $scopeDescriptions = [
        '_global' => 'Site title, base URL, search, identifiers, digital object derivatives, and other global options.',
        'default_template' => 'Default display templates for information objects, actors, and repositories.',
        'ui_label' => 'Customize labels used throughout the user interface.',
        'element_visibility' => 'Control which descriptive-standard fields are shown or hidden in ISAD, RAD, MODS, etc.',
        'i18n_languages' => 'Enabled languages for internationalization.',
        'oai' => 'OAI-PMH repository settings for metadata harvesting.',
        'federation' => 'Federated search and repository federation settings.',
        'access_statement' => 'Access statement configuration.',
    ];

    private array $menuNodes = [
        // Matching AtoM sidebar alphabetically + Heratio extras
        ['action' => 'index', 'label' => 'AHG Settings', 'icon' => 'fa-home'],
        ['action' => 'clipboard', 'label' => 'Clipboard', 'icon' => 'fa-paperclip'],
        ['action' => 'csv-validator', 'label' => 'CSV Validator', 'icon' => 'fa-check-circle'],
        ['action' => 'visible-elements', 'label' => 'Visible elements', 'icon' => 'fa-eye'],
        ['action' => 'default-template', 'label' => 'Default template', 'icon' => 'fa-file-alt'],
        ['action' => 'diacritics', 'label' => 'Diacritics', 'icon' => 'fa-font'],
        ['action' => 'digital-objects', 'label' => 'Digital object derivatives', 'icon' => 'fa-photo-video'],
        ['action' => 'dip-upload', 'label' => 'DIP upload', 'icon' => 'fa-upload'],
        ['action' => 'email', 'label' => 'Email', 'icon' => 'fa-envelope'],
        ['action' => 'finding-aid', 'label' => 'Finding Aid', 'icon' => 'fa-book'],
        ['action' => 'global', 'label' => 'Global', 'icon' => 'fa-globe'],
        ['action' => 'languages', 'label' => 'I18n languages', 'icon' => 'fa-language'],
        ['action' => 'identifier', 'label' => 'Identifiers', 'icon' => 'fa-fingerprint'],
        ['action' => 'inventory', 'label' => 'Inventory', 'icon' => 'fa-clipboard-list'],
        ['action' => 'markdown', 'label' => 'Markdown', 'icon' => 'fa-pen-fancy'],
        ['action' => 'oai', 'label' => 'OAI repository', 'icon' => 'fa-cloud'],
        ['action' => 'permissions', 'label' => 'Permissions', 'icon' => 'fa-user-lock'],
        ['action' => 'privacy-notification', 'label' => 'Privacy Notification', 'icon' => 'fa-user-shield'],
        ['action' => 'security', 'label' => 'Security', 'icon' => 'fa-shield-alt'],
        ['action' => 'site-information', 'label' => 'Site information', 'icon' => 'fa-info-circle'],
        ['action' => 'treeview', 'label' => 'Treeview', 'icon' => 'fa-sitemap'],
        ['action' => 'uploads', 'label' => 'Uploads', 'icon' => 'fa-cloud-upload-alt'],
        ['action' => 'interface-labels', 'label' => 'User interface labels', 'icon' => 'fa-tags'],
        ['action' => 'ai-condition', 'label' => 'AI Condition Assessment', 'icon' => 'fa-robot'],
        ['action' => 'header-customizations', 'label' => 'Header customizations', 'icon' => 'fa-heading'],
        ['action' => 'storage-service', 'label' => 'Storage service', 'icon' => 'fa-hdd'],
        ['action' => 'web-analytics', 'label' => 'Web analytics', 'icon' => 'fa-chart-bar'],
        ['action' => 'ldap', 'label' => 'LDAP authentication', 'icon' => 'fa-network-wired'],
        ['action' => 'levels', 'label' => 'Levels of description', 'icon' => 'fa-layer-group'],
        ['action' => 'paths', 'label' => 'Paths', 'icon' => 'fa-folder-open'],
        ['action' => 'preservation', 'label' => 'Preservation', 'icon' => 'fa-cloud-upload-alt'],
        ['action' => 'webhooks', 'label' => 'Webhooks', 'icon' => 'fa-broadcast-tower'],
        ['action' => 'tts', 'label' => 'Text-to-Speech', 'icon' => 'fa-volume-up'],
        ['action' => 'icip-settings', 'label' => 'ICIP Settings', 'icon' => 'fa-shield-alt'],
        ['action' => 'sector-numbering', 'label' => 'Sector numbering', 'icon' => 'fa-hashtag'],
        ['action' => 'numbering-schemes', 'label' => 'Numbering schemes', 'icon' => 'fa-hashtag'],
        ['action' => 'dam-tools', 'label' => 'DAM tools', 'icon' => 'fa-photo-video'],
        ['action' => 'ai-services', 'label' => 'AI services', 'icon' => 'fa-brain'],
        ['action' => 'ahg-import', 'label' => 'Import settings', 'icon' => 'fa-upload'],
        ['action' => 'ahg-integration', 'label' => 'AHG Central', 'icon' => 'fa-cloud'],
        ['action' => 'page-elements', 'label' => 'Default page elements', 'icon' => 'fa-th-large'],
        // Heratio extras
        ['action' => 'system-info', 'label' => 'System information', 'icon' => 'fa-server'],
        ['action' => 'services', 'label' => 'Services monitor', 'icon' => 'fa-heartbeat'],
        ['action' => 'themes', 'label' => 'Theme configuration', 'icon' => 'fa-palette'],
        ['action' => 'cron-jobs', 'label' => 'Cron Jobs', 'icon' => 'fa-clock'],
    ];

    public function __construct(SettingsService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $scopes = DB::table('setting')
            ->where('editable', 1)
            ->select('scope')
            ->distinct()
            ->pluck('scope')
            ->map(fn ($s) => $s ?? '_global')
            ->unique()
            ->sort()
            ->values();

        $scopeCards = $scopes->map(function ($scope) {
            return (object) [
                'key' => $scope,
                'label' => $this->scopeLabels[$scope] ?? ucfirst(str_replace('_', ' ', $scope)),
                'icon' => $this->scopeIcons[$scope] ?? 'fa-sliders-h',
                'description' => $this->scopeDescriptions[$scope] ?? 'Manage ' . strtolower($this->scopeLabels[$scope] ?? str_replace('_', ' ', $scope)) . '.',
                'count' => DB::table('setting')
                    ->where('editable', 1)
                    ->when($scope === '_global', fn ($q) => $q->whereNull('scope'), fn ($q) => $q->where('scope', $scope))
                    ->count(),
            ];
        });

        $ahgGroups = collect();
        if (Schema::hasTable('ahg_settings')) {
            $ahgGroups = DB::table('ahg_settings')
                ->select('setting_group', DB::raw('COUNT(*) as cnt'))
                ->groupBy('setting_group')
                ->orderBy('setting_group')
                ->get()
                ->map(fn ($row) => (object) ['key' => $row->setting_group, 'label' => ucfirst(str_replace('_', ' ', $row->setting_group)), 'count' => $row->cnt]);
        }

        $ahgIcons = [
            'accession' => 'fa-archive', 'ai_condition' => 'fa-robot', 'compliance' => 'fa-clipboard-check',
            'data_protection' => 'fa-shield-alt', 'email' => 'fa-envelope', 'encryption' => 'fa-key',
            'faces' => 'fa-user-circle', 'features' => 'fa-star', 'fuseki' => 'fa-project-diagram',
            'general' => 'fa-cogs', 'iiif' => 'fa-images', 'ingest' => 'fa-upload',
            'integrity' => 'fa-check-double', 'jobs' => 'fa-tasks', 'media' => 'fa-photo-video',
            'metadata' => 'fa-database', 'multi_tenant' => 'fa-building', 'photos' => 'fa-camera',
            'portable_export' => 'fa-file-export', 'security' => 'fa-lock',
            'spectrum' => 'fa-clipboard-list', 'voice_ai' => 'fa-microphone',
        ];

        return view('ahg-settings::index', compact('scopeCards', 'ahgGroups', 'ahgIcons'));
    }

    public function section(Request $request, string $section)
    {
        // Redirect scopes that have dedicated pages
        $redirectMap = [
            '_global' => 'settings.global',
            'default_template' => 'settings.default-template',
            'element_visibility' => 'settings.visible-elements',
            'i18n_languages' => 'settings.languages',
            'ui_label' => 'settings.interface-labels',
            'oai' => 'settings.oai',
        ];
        if (isset($redirectMap[$section])) {
            return redirect()->route($redirectMap[$section]);
        }

        $culture = app()->getLocale();
        $isGlobal = ($section === '_global');

        $query = DB::table('setting')
            ->leftJoin('setting_i18n', function ($join) use ($culture) {
                $join->on('setting.id', '=', 'setting_i18n.id')
                    ->where('setting_i18n.culture', '=', $culture);
            })
            ->where('setting.editable', 1);

        if ($isGlobal) {
            $query->whereNull('setting.scope');
        } else {
            $query->where('setting.scope', $section);
        }

        $settings = $query->select('setting.id', 'setting.name', 'setting.scope', 'setting_i18n.value')
            ->orderBy('setting.name')
            ->get();

        $sectionLabel = $this->scopeLabels[$section] ?? ucfirst(str_replace('_', ' ', $section));

        if ($request->isMethod('post')) {
            foreach ($request->input('settings', []) as $id => $value) {
                DB::table('setting_i18n')->updateOrInsert(
                    ['id' => $id, 'culture' => $culture],
                    ['value' => $value]
                );
            }
            return redirect()->route('settings.section', $section)->with('success', $sectionLabel . ' settings saved.');
        }

        return view('ahg-settings::section', compact('settings', 'section', 'sectionLabel'));
    }

    public function defaultTemplate(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('default-template');

        // Load current template settings
        $templateSettings = DB::table('setting')
            ->leftJoin('setting_i18n', function ($join) use ($culture) {
                $join->on('setting.id', '=', 'setting_i18n.id')
                    ->where('setting_i18n.culture', '=', $culture);
            })
            ->where('setting.scope', 'default_template')
            ->where('setting.editable', 1)
            ->select('setting.id', 'setting.name', 'setting_i18n.value')
            ->orderBy('setting.name')
            ->get()
            ->keyBy('name');

        if ($request->isMethod('post')) {
            foreach ($request->input('settings', []) as $id => $value) {
                DB::table('setting_i18n')->updateOrInsert(
                    ['id' => $id, 'culture' => $culture],
                    ['value' => $value]
                );
            }
            return redirect()->route('settings.default-template')->with('success', 'Default templates saved.');
        }

        // Template choices matching AtoM exactly
        $ioChoices = [
            'isad' => 'ISAD(G), 2nd ed. International Council on Archives',
            'dc' => 'Dublin Core, Version 1.1. Dublin Core Metadata Initiative',
            'mods' => 'MODS, Version 3.3. U.S. Library of Congress',
            'rad' => 'RAD, July 2008 version. Canadian Council of Archives',
            'dacs' => 'DACS, 2nd ed. Society of American Archivists',
        ];
        $actorChoices = [
            'isaar' => 'ISAAR(CPF), 2nd ed. International Council on Archives',
        ];
        $repoChoices = [
            'isdiah' => 'ISDIAH, 1st ed. International Council on Archives',
        ];

        return view('ahg-settings::default-template', compact('templateSettings', 'ioChoices', 'actorChoices', 'repoChoices', 'menu'));
    }

    public function global(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('global');

        if ($request->isMethod('post')) {
            $this->service->saveGlobalSettings($request->input('settings', []), $culture);
            return redirect()->route('settings.global')->with('success', 'Global settings saved.');
        }

        $settings = $this->service->getGlobalSettings($culture);
        return view('ahg-settings::global', compact('settings', 'menu'));
    }

    public function siteInformation(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('site-information');

        if ($request->isMethod('post')) {
            foreach (['siteTitle', 'siteDescription', 'siteBaseUrl'] as $name) {
                $this->service->saveSetting($name, null, $request->input($name, ''), $culture);
            }
            return redirect()->route('settings.site-information')->with('success', 'Site information saved.');
        }

        $settings = $this->service->getSiteInformation($culture);
        return view('ahg-settings::site-information', compact('settings', 'menu'));
    }

    public function security(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('security');

        if ($request->isMethod('post')) {
            foreach (['limit_admin_ip', 'require_ssl_admin', 'require_strong_passwords'] as $name) {
                $this->service->saveSetting($name, null, $request->input($name, ''), $culture);
            }
            return redirect()->route('settings.security')->with('success', 'Security settings saved.');
        }

        $settings = $this->service->getSecuritySettings($culture);
        return view('ahg-settings::security', compact('settings', 'menu'));
    }

    public function identifier(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('identifier');

        if ($request->isMethod('post')) {
            foreach ($request->input('settings', []) as $name => $value) {
                $this->service->saveSetting($name, null, $value ?? '', $culture);
            }
            return redirect()->route('settings.identifier')->with('success', 'Identifier settings saved.');
        }

        $settings = $this->service->getIdentifierSettings($culture);
        return view('ahg-settings::identifier', compact('settings', 'menu'));
    }

    public function email(Request $request)
    {
        $menu = $this->buildMenu('email');
        $emailData = $this->service->getEmailSettings();

        if ($request->isMethod('post')) {
            $this->service->saveEmailSettings(
                $request->input('settings', []),
                $request->input('notif_toggles', [])
            );
            return redirect()->route('settings.email')->with('success', 'Email settings saved.');
        }

        return view('ahg-settings::email', [
            'menu' => $menu,
            'smtpSettings' => $emailData['smtp'],
            'notificationSettings' => $emailData['notification'],
            'templateSettings' => $emailData['template'],
            'notifToggles' => $emailData['toggles'],
        ]);
    }

    public function treeview(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('treeview');

        if ($request->isMethod('post')) {
            foreach ($request->input('settings', []) as $name => $value) {
                $this->service->saveSetting($name, null, $value ?? '', $culture);
            }
            return redirect()->route('settings.treeview')->with('success', 'Treeview settings saved.');
        }

        $settings = $this->service->getTreeviewSettings($culture);
        return view('ahg-settings::treeview', compact('settings', 'menu'));
    }

    public function digitalObjects(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('digital-objects');

        if ($request->isMethod('post')) {
            foreach ($request->input('settings', []) as $name => $value) {
                $this->service->saveSetting($name, null, $value ?? '', $culture);
            }
            return redirect()->route('settings.digital-objects')->with('success', 'Digital object settings saved.');
        }

        $settings = $this->service->getDigitalObjectSettings($culture);
        return view('ahg-settings::digital-objects', compact('settings', 'menu'));
    }

    public function interfaceLabels(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('interface-labels');

        if ($request->isMethod('post')) {
            foreach ($request->input('settings', []) as $id => $value) {
                DB::table('setting_i18n')->updateOrInsert(
                    ['id' => $id, 'culture' => $culture],
                    ['value' => $value]
                );
            }
            return redirect()->route('settings.interface-labels')->with('success', 'Interface labels saved.');
        }

        $settings = DB::table('setting')
            ->leftJoin('setting_i18n', function ($join) use ($culture) {
                $join->on('setting.id', '=', 'setting_i18n.id')
                    ->where('setting_i18n.culture', '=', $culture);
            })
            ->where('setting.scope', 'ui_label')
            ->where('setting.editable', 1)
            ->select('setting.id', 'setting.name', 'setting_i18n.value')
            ->orderBy('setting.name')
            ->get();

        return view('ahg-settings::interface-labels', compact('settings', 'menu'));
    }

    public function visibleElements(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('visible-elements');

        // AtoM uses QubitSetting::getByScope('element_visibility') with no editable filter.
        $settings = DB::table('setting')
            ->leftJoin('setting_i18n', function ($join) use ($culture) {
                $join->on('setting.id', '=', 'setting_i18n.id')
                    ->where('setting_i18n.culture', '=', $culture);
            })
            ->where('setting.scope', 'element_visibility')
            ->select('setting.id', 'setting.name', 'setting_i18n.value')
            ->orderBy('setting.name')
            ->get()
            ->keyBy('name');

        if ($request->isMethod('post')) {
            foreach ($settings as $name => $setting) {
                $value = $request->has("settings.{$setting->id}") ? '1' : '0';
                DB::table('setting_i18n')->updateOrInsert(
                    ['id' => $setting->id, 'culture' => $culture],
                    ['value' => $value]
                );
            }
            return redirect()->route('settings.visible-elements')->with('success', 'Visible elements saved.');
        }

        // Group settings by prefix for accordion sections
        $groups = [];
        foreach ($settings as $name => $setting) {
            $parts = explode('_', $name, 2);
            $prefix = $parts[0] ?? 'other';
            $groups[$prefix][] = $setting;
        }

        return view('ahg-settings::visible-elements', compact('settings', 'groups', 'menu'));
    }

    public function languages(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('languages');

        $languages = DB::table('setting')
            ->leftJoin('setting_i18n', function ($join) use ($culture) {
                $join->on('setting.id', '=', 'setting_i18n.id')
                    ->where('setting_i18n.culture', '=', $culture);
            })
            ->where('setting.scope', 'i18n_languages')
            ->where('setting.editable', 1)
            ->select('setting.id', 'setting.name', 'setting_i18n.value')
            ->orderBy('setting.name')
            ->get();

        if ($request->isMethod('post') && $request->input('action') === 'add') {
            $code = strtolower(trim($request->input('languageCode', '')));
            if (preg_match('/^[a-z]{2,3}$/', $code)) {
                $exists = DB::table('setting')
                    ->where('scope', 'i18n_languages')
                    ->where('name', $code)
                    ->exists();
                if (!$exists) {
                    $id = DB::table('setting')->insertGetId([
                        'name' => $code,
                        'scope' => 'i18n_languages',
                        'editable' => 1,
                        'deleteable' => 1,
                        'source_culture' => $culture,
                        'serial_number' => 0,
                    ]);
                    DB::table('setting_i18n')->insert([
                        'id' => $id,
                        'culture' => $culture,
                        'value' => $code,
                    ]);
                    return redirect()->route('settings.languages')->with('success', "Language '{$code}' added.");
                }
                return redirect()->route('settings.languages')->with('error', "Language '{$code}' already exists.");
            }
            return redirect()->route('settings.languages')->with('error', 'Invalid language code. Use 2-3 lowercase letters (e.g. en, fr, af).');
        }

        if ($request->isMethod('post') && $request->input('action') === 'delete') {
            $deleteId = (int) $request->input('delete_id');
            $setting = DB::table('setting')->where('id', $deleteId)->where('scope', 'i18n_languages')->first();
            if ($setting && $setting->deleteable) {
                DB::table('setting_i18n')->where('id', $deleteId)->delete();
                DB::table('setting')->where('id', $deleteId)->delete();
                return redirect()->route('settings.languages')->with('success', 'Language removed.');
            }
            return redirect()->route('settings.languages')->with('error', 'This language cannot be deleted.');
        }

        return view('ahg-settings::languages', compact('languages', 'menu'));
    }

    public function oai(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('oai');

        if ($request->isMethod('post')) {
            foreach ($request->input('settings', []) as $name => $value) {
                $setting = DB::table('setting')->where('name', $name)->where('scope', 'oai')->first();
                if ($setting) {
                    DB::table('setting_i18n')->updateOrInsert(
                        ['id' => $setting->id, 'culture' => $culture],
                        ['value' => $value ?? '']
                    );
                }
            }
            return redirect()->route('settings.oai')->with('success', 'OAI repository settings saved.');
        }

        $settings = $this->service->getOaiSettings($culture);
        return view('ahg-settings::oai', compact('settings', 'menu'));
    }

    public function systemInfo()
    {
        $menu = $this->buildMenu('system-info');
        $info = $this->service->getSystemInfo();
        return view('ahg-settings::system-info', compact('info', 'menu'));
    }

    public function services()
    {
        $menu = $this->buildMenu('services');
        $serviceChecks = [];

        // Check MySQL
        try {
            DB::select('SELECT 1');
            $serviceChecks['MySQL'] = ['status' => 'ok', 'message' => 'Connected'];
        } catch (\Exception $e) {
            $serviceChecks['MySQL'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        // Check Elasticsearch
        try {
            $esHost = config('services.elasticsearch.host', 'localhost:9200');
            $ch = curl_init("http://{$esHost}/_cluster/health");
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode === 200) {
                $health = json_decode($response, true);
                $serviceChecks['Elasticsearch'] = ['status' => $health['status'] ?? 'ok', 'message' => 'Cluster: ' . ($health['cluster_name'] ?? 'unknown') . ' (' . ($health['status'] ?? 'unknown') . ')'];
            } else {
                $serviceChecks['Elasticsearch'] = ['status' => 'warning', 'message' => "HTTP {$httpCode}"];
            }
        } catch (\Exception $e) {
            $serviceChecks['Elasticsearch'] = ['status' => 'error', 'message' => 'Not available'];
        }

        // Check disk space
        $free = disk_free_space('/');
        $total = disk_total_space('/');
        $usedPct = round((1 - $free / $total) * 100, 1);
        $serviceChecks['Disk space'] = [
            'status' => $usedPct > 90 ? 'error' : ($usedPct > 75 ? 'warning' : 'ok'),
            'message' => round($free / 1073741824, 1) . ' GB free (' . $usedPct . '% used)',
        ];

        // Check uploads directory
        $uploadsPath = config('heratio.uploads_path');
        $serviceChecks['Uploads directory'] = [
            'status' => is_writable($uploadsPath) ? 'ok' : 'error',
            'message' => is_writable($uploadsPath) ? 'Writable' : 'Not writable or missing',
        ];

        return view('ahg-settings::services', compact('serviceChecks', 'menu'));
    }

    public function themes(Request $request)
    {
        $themeKeys = [
            'ahg_theme_enabled', 'ahg_primary_color', 'ahg_secondary_color',
            'ahg_body_bg', 'ahg_body_text',
            'ahg_footer_bg', 'ahg_footer_text_color', 'ahg_footer_copyright',
            'ahg_footer_disclaimer', 'ahg_footer_system_name',
            'ahg_footer_org_name', 'ahg_footer_org_url',
            'ahg_footer_standards', 'ahg_footer_links', 'ahg_footer_utility_links',
            'ahg_header_bg', 'ahg_header_text',
            'ahg_descbar_bg', 'ahg_descbar_text', 'ahg_descbar_align',
            'ahg_card_header_bg', 'ahg_card_header_text',
            'ahg_button_bg', 'ahg_button_text',
            'ahg_link_color', 'ahg_sidebar_bg', 'ahg_sidebar_text',
            'ahg_logo_path', 'ahg_footer_text', 'ahg_show_branding',
            'ahg_custom_css',
            'ahg_font_size_body', 'ahg_font_size_sidebar', 'ahg_font_size_sidebar_header',
            'ahg_success_color', 'ahg_danger_color', 'ahg_warning_color',
            'ahg_info_color', 'ahg_light_color', 'ahg_dark_color',
            'ahg_muted_color', 'ahg_border_color',
        ];

        if ($request->isMethod('post')) {
            foreach ($themeKeys as $key) {
                $value = $request->input($key, '');
                DB::table('ahg_settings')
                    ->where('setting_key', $key)
                    ->update(['setting_value' => $value]);
            }
            $this->regenerateThemeCss();
            return redirect()->route('settings.themes')->with('success', 'Theme settings saved.');
        }

        $settings = DB::table('ahg_settings')
            ->whereIn('setting_key', $themeKeys)
            ->pluck('setting_value', 'setting_key');

        return view('ahg-settings::themes', ['settings' => $settings]);
    }

    private function regenerateThemeCss(): void
    {
        $rows = DB::table('ahg_settings')
            ->where('setting_group', 'general')
            ->pluck('setting_value', 'setting_key');

        $css = "/* AHG Theme - Generated CSS */\n/* Do not edit - regenerated when settings saved */\n";
        $css .= ":root {\n";
        $vars = $this->getThemeVars();
        foreach ($vars as $key => [$var, $default]) {
            $value = $rows[$key] ?? $default;
            if (str_contains($var, '--ahg-font') && is_numeric($value)) {
                $value .= 'rem';
            }
            $css .= "    {$var}: {$value};\n";
        }
        $css .= "}\n";
        $css .= $this->getThemeRules();

        $path = public_path('vendor/ahg-theme-b5/css/ahg-generated.css');
        file_put_contents($path, $css);

        // Also write to the dynamic CSS path served by nginx
        $dynamicPath = public_path('css/ahg-theme-dynamic.css');
        $dir = dirname($dynamicPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($dynamicPath, $css);
    }

    public function dynamicCss()
    {
        $rows = DB::table('ahg_settings')
            ->where('setting_group', 'general')
            ->pluck('setting_value', 'setting_key');

        $css = "/* AHG Theme - Dynamic CSS */\n:root {\n";
        $vars = $this->getThemeVars();
        foreach ($vars as $key => [$var, $default]) {
            $css .= "    {$var}: " . ($rows[$key] ?? $default) . ";\n";
        }
        $css .= "}\n";
        $css .= $this->getThemeRules();

        $customCss = $rows['ahg_custom_css'] ?? '';
        if (!empty(trim($customCss))) {
            $css .= "\n/* Custom CSS */\n" . $customCss . "\n";
        }

        return response($css, 200)->header('Content-Type', 'text/css');
    }

    private function getThemeVars(): array
    {
        return [
            'ahg_primary_color' => ['--ahg-primary', '#005837'],
            'ahg_secondary_color' => ['--ahg-secondary', '#37A07F'],
            'ahg_body_bg' => ['--ahg-background-light', '#ffffff'],
            'ahg_body_text' => ['--ahg-body-text', '#212529'],
            'ahg_footer_bg' => ['--ahg-footer-bg', '#005837'],
            'ahg_footer_text_color' => ['--ahg-footer-text', '#ffffff'],
            'ahg_header_bg' => ['--ahg-header-bg', '#212529'],
            'ahg_header_text' => ['--ahg-header-text', '#ffffff'],
            'ahg_descbar_bg' => ['--ahg-descbar-bg', '#005837'],
            'ahg_descbar_text' => ['--ahg-descbar-text', '#ffffff'],
            'ahg_descbar_align' => ['--ahg-descbar-align', 'left'],
            'ahg_card_header_bg' => ['--ahg-card-header-bg', '#005837'],
            'ahg_card_header_text' => ['--ahg-card-header-text', '#ffffff'],
            'ahg_button_bg' => ['--ahg-btn-bg', '#005837'],
            'ahg_button_text' => ['--ahg-btn-text', '#ffffff'],
            'ahg_link_color' => ['--ahg-link-color', '#005837'],
            'ahg_sidebar_bg' => ['--ahg-sidebar-bg', '#f8f9fa'],
            'ahg_sidebar_text' => ['--ahg-sidebar-text', '#333333'],
            'ahg_success_color' => ['--ahg-success', '#28a745'],
            'ahg_danger_color' => ['--ahg-danger', '#dc3545'],
            'ahg_warning_color' => ['--ahg-warning', '#ffc107'],
            'ahg_info_color' => ['--ahg-info', '#17a2b8'],
            'ahg_light_color' => ['--ahg-light', '#f8f9fa'],
            'ahg_dark_color' => ['--ahg-dark', '#343a40'],
            'ahg_muted_color' => ['--ahg-muted', '#6c757d'],
            'ahg_border_color' => ['--ahg-border', '#dee2e6'],
            'ahg_font_size_body' => ['--ahg-font-body', '0.95rem'],
            'ahg_font_size_sidebar' => ['--ahg-font-sidebar', '0.85rem'],
            'ahg_font_size_sidebar_header' => ['--ahg-font-sidebar-header', '0.82rem'],
        ];
    }

    private function getThemeRules(): string
    {
        return ".card-header { background-color: var(--ahg-card-header-bg) !important; color: var(--ahg-card-header-text) !important; }\n"
            . ".card-header * { color: var(--ahg-card-header-text) !important; }\n"
            . ".btn-primary { background-color: var(--ahg-btn-bg) !important; border-color: var(--ahg-btn-bg) !important; color: var(--ahg-btn-text) !important; }\n"
            . ".btn-primary:hover, .btn-primary:focus { filter: brightness(0.9); }\n"
            . "a:not(.btn):not(.nav-link):not(.dropdown-item) { color: var(--ahg-link-color); }\n"
            . ".sidebar, #sidebar-content { background-color: var(--ahg-sidebar-bg) !important; color: var(--ahg-sidebar-text) !important; }\n"
            . ":root { --ahg-background-white: var(--ahg-background-light); --bs-body-bg: var(--ahg-background-light); }\n"
            . "#wrapper { background: var(--ahg-background-light) !important; color: var(--ahg-body-text); }\n"
            . "body { background-color: var(--ahg-background-light) !important; color: var(--ahg-body-text) !important; }\n"
            . "#top-bar { background-color: var(--ahg-header-bg) !important; }\n"
            . "#top-bar, #top-bar .navbar-brand, #top-bar .nav-link, #top-bar .nav-link i { color: var(--ahg-header-text) !important; }\n"
            . ".ahg-description-bar { text-align: var(--ahg-descbar-align, left); }\n";
    }

    public function ahgSection(Request $request, string $group)
    {
        $checkboxFields = [
            // general
            'ahg_theme_enabled', 'ahg_show_branding', 'enable_glam_browse',
            // spectrum
            'spectrum_enabled', 'spectrum_auto_create_movement', 'spectrum_require_photos',
            'spectrum_email_notifications', 'spectrum_enable_barcodes', 'spectrum_auto_numbering',
            'spectrum_require_insurance', 'spectrum_require_valuation',
            // media
            'media_autoplay', 'media_show_controls', 'media_loop', 'media_show_waveform',
            'media_transcription_enabled', 'media_show_download',
            // photos
            'photo_create_thumbnails', 'photo_extract_exif', 'photo_auto_rotate',
            'photo_auto_orient', 'photo_exif_strip', 'photo_watermark_enabled',
            // data_protection
            'dp_enabled', 'dp_notify_overdue', 'dp_anonymize_on_delete', 'dp_audit_logging',
            'dp_consent_required', 'dp_auto_deadline', 'dp_require_dpo_approval',
            // iiif
            'iiif_enabled', 'iiif_show_navigator', 'iiif_show_rotation', 'iiif_show_fullscreen',
            'iiif_enable_annotations',
            // jobs
            'jobs_enabled', 'jobs_notify_failure', 'jobs_notify_on_failure',
            // faces
            'face_detect_enabled', 'face_auto_match', 'face_auto_link', 'face_blur_unmatched',
            'face_store_embeddings', 'face_save_crops',
            // fuseki
            'fuseki_sync_enabled', 'fuseki_queue_enabled', 'fuseki_sync_on_save',
            'fuseki_sync_on_delete', 'fuseki_cascade_delete',
            // ingest
            'ingest_ner', 'ingest_ocr', 'ingest_virus_scan', 'ingest_summarize',
            'ingest_spellcheck', 'ingest_translate', 'ingest_format_id', 'ingest_face_detect',
            'ingest_create_records', 'ingest_generate_sip', 'ingest_generate_aip', 'ingest_generate_dip',
            'ingest_thumbnails', 'ingest_reference',
            // encryption
            'encryption_enabled', 'encryption_encrypt_derivatives',
            'encryption_field_contact_details', 'encryption_field_financial_data',
            'encryption_field_donor_information', 'encryption_field_personal_notes',
            'encryption_field_access_restrictions',
            // voice_ai
            'voice_enabled', 'voice_continuous_listening', 'voice_show_floating_btn',
            'voice_hover_read_enabled', 'voice_audit_ai_calls',
            // integrity
            'integrity_enabled', 'integrity_auto_baseline', 'integrity_notify_on_failure',
            'integrity_notify_on_mismatch',
            // multi_tenant
            'tenant_enabled', 'tenant_enforce_filter', 'tenant_show_switcher', 'tenant_allow_branding',
            // metadata
            'meta_extract_on_upload', 'meta_auto_populate', 'meta_images', 'meta_pdf', 'meta_office',
            'meta_video', 'meta_audio', 'meta_extract_gps', 'meta_extract_technical',
            'meta_extract_xmp', 'meta_extract_iptc', 'meta_overwrite_existing',
            'meta_create_access_points', 'meta_field_mappings',
            'meta_dam_batch_mode', 'meta_dam_preserve_filename', 'meta_dam_extract_color',
            'meta_dam_extract_faces', 'meta_dam_auto_tag', 'meta_dam_generate_thumbnail',
            'meta_dam_thumb_small', 'meta_dam_thumb_medium', 'meta_dam_thumb_large', 'meta_dam_thumb_preview',
            'map_title_dam', 'map_creator_dam', 'map_keywords_dam', 'map_description_dam',
            'map_date_dam', 'map_copyright_dam', 'map_technical_dam', 'map_gps_dam',
            'meta_replace_placeholders', 'meta_extract_images', 'meta_extract_pdf',
            'meta_extract_office', 'meta_extract_video', 'meta_extract_audio',
            // accession
            'accession_auto_assign_enabled', 'accession_allow_container_barcodes',
            'accession_require_appraisal', 'accession_require_donor_agreement',
            'accession_rights_inheritance_enabled',
            // portable_export
            'portable_export_enabled', 'portable_export_include_objects',
            'portable_export_include_thumbnails', 'portable_export_include_references',
            'portable_export_include_masters', 'portable_export_description_button',
            'portable_export_clipboard_button',
            // security
            'security_lockout_enabled', 'security_force_password_change',
            'security_password_expiry_notify',
            // ai_condition
            'ai_condition_auto_scan', 'ai_condition_overlay_enabled',
            // features
            'enable_3d_viewer', 'enable_iiif', 'research_booking_enabled',
            // email
            'access_request_email_notifications', 'research_email_notifications',
            'workflow_email_notifications',
            // ftp
            'ftp_passive_mode',
        ];

        $selectFields = [
            'spectrum_default_currency' => ['ZAR' => 'ZAR', 'USD' => 'USD', 'EUR' => 'EUR', 'GBP' => 'GBP'],
            'media_player_type' => ['basic' => 'Basic HTML5 Player', 'enhanced' => 'Enhanced Player'],
            'photo_max_upload_size' => ['5242880' => '5 MB', '10485760' => '10 MB', '20971520' => '20 MB', '52428800' => '50 MB'],
            'dp_default_regulation' => ['popia' => 'POPIA (South Africa)', 'gdpr' => 'GDPR (EU)', 'paia' => 'PAIA (South Africa)', 'ccpa' => 'CCPA (California)'],
            'iiif_viewer' => ['openseadragon' => 'OpenSeadragon', 'mirador' => 'Mirador', 'leaflet' => 'Leaflet-IIIF'],
            'face_detect_backend' => ['local' => 'Local (Python)', 'aws' => 'AWS Rekognition', 'azure' => 'Azure Face API'],
            'ftp_protocol' => ['sftp' => 'SFTP', 'ftp' => 'FTP', 'ftps' => 'FTPS'],
            'voice_llm_provider' => ['local' => 'Local (Ollama)', 'cloud' => 'Cloud (Anthropic)', 'hybrid' => 'Hybrid'],
            'voice_cloud_model' => ['claude-sonnet-4-20250514' => 'Claude Sonnet 4', 'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5'],
            'ingest_default_sector' => ['archive' => 'Archive', 'museum' => 'Museum', 'library' => 'Library', 'gallery' => 'Gallery', 'dam' => 'DAM'],
            'ingest_default_standard' => ['isadg' => 'ISAD(G)', 'dacs' => 'DACS', 'rad' => 'RAD', 'dc' => 'Dublin Core'],
            'accession_default_priority' => ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'],
            'ai_condition_notify_grade' => ['poor' => 'Poor', 'fair' => 'Fair', 'good' => 'Good'],
            'default_sector' => ['archive' => 'Archive', 'museum' => 'Museum', 'library' => 'Library', 'gallery' => 'Gallery', 'dam' => 'DAM'],
            'integrity_default_algorithm' => ['sha256' => 'SHA-256', 'sha512' => 'SHA-512', 'md5' => 'MD5'],
            'portable_export_default_mode' => ['read_only' => 'Read Only', 'editable' => 'Editable'],
            'portable_export_default_culture' => ['en' => 'English', 'af' => 'Afrikaans', 'zu' => 'Zulu'],
            'voice_language' => ['en-US' => 'English (US)', 'en-GB' => 'English (UK)', 'de-DE' => 'German', 'af-ZA' => 'Afrikaans'],
            'ingest_spellcheck_lang' => ['en_ZA' => 'English (ZA)', 'en_US' => 'English (US)', 'af' => 'Afrikaans'],
        ];

        $colorFields = [
            'ahg_primary_color', 'ahg_secondary_color', 'ahg_card_header_bg', 'ahg_card_header_text',
            'ahg_button_bg', 'ahg_button_text', 'ahg_link_color', 'ahg_sidebar_bg', 'ahg_sidebar_text',
            'ahg_success_color', 'ahg_danger_color', 'ahg_warning_color', 'ahg_info_color',
            'ahg_light_color', 'ahg_dark_color', 'ahg_muted_color', 'ahg_border_color',
            'ahg_body_bg', 'ahg_body_text',
        ];

        $passwordFields = ['ftp_password', 'voice_anthropic_api_key', 'ai_condition_api_key',
            'fuseki_password', 'azure_face_key'];

        $textareaFields = ['ahg_custom_css'];

        if ($request->isMethod('post')) {
            $postedSettings = $request->input('settings', []);
            // For checkboxes: unchecked checkboxes are not submitted, so set them to '0'
            $allKeys = DB::table('ahg_settings')
                ->where('setting_group', $group)
                ->pluck('setting_key')
                ->toArray();
            foreach ($allKeys as $key) {
                if (in_array($key, $checkboxFields)) {
                    $value = isset($postedSettings[$key]) ? '1' : '0';
                } else {
                    $value = $postedSettings[$key] ?? '';
                }
                DB::table('ahg_settings')
                    ->where('setting_key', $key)
                    ->update(['setting_value' => $value]);
            }
            if ($group === 'general') {
                $this->regenerateThemeCss();
            }
            return redirect()->route('settings.ahg', $group)->with('success', ucfirst(str_replace('_', ' ', $group)) . ' settings saved.');
        }

        $settings = DB::table('ahg_settings')
            ->where('setting_group', $group)
            ->orderBy('setting_key')
            ->select('id', 'setting_key', 'setting_value', 'setting_group', 'description', 'setting_type')
            ->get();

        $groupLabel = ucfirst(str_replace('_', ' ', $group));

        $ahgGroupIcons = [
            'accession' => 'fa-inbox', 'ai_condition' => 'fa-robot', 'compliance' => 'fa-clipboard-check',
            'data_protection' => 'fa-user-shield', 'email' => 'fa-envelope', 'encryption' => 'fa-lock',
            'faces' => 'fa-user-circle', 'features' => 'fa-star', 'fuseki' => 'fa-project-diagram',
            'general' => 'fa-palette', 'iiif' => 'fa-images', 'ingest' => 'fa-file-import',
            'integrity' => 'fa-check-double', 'jobs' => 'fa-tasks', 'media' => 'fa-play-circle',
            'metadata' => 'fa-tags', 'multi_tenant' => 'fa-building', 'photos' => 'fa-camera',
            'portable_export' => 'fa-compact-disc', 'security' => 'fa-shield-alt',
            'spectrum' => 'fa-archive', 'voice_ai' => 'fa-microphone', 'ftp' => 'fa-server',
        ];
        $groupIcon = $ahgGroupIcons[$group] ?? 'fa-puzzle-piece';

        $ahgGroupLabels = [
            'general' => 'Theme Configuration',
            'email' => 'Email',
            'metadata' => 'Metadata Extraction',
            'media' => 'Media Player',
            'jobs' => 'Background Jobs',
            'spectrum' => 'Spectrum / Collections',
            'photos' => 'Condition Photos',
            'data_protection' => 'Data Protection',
            'iiif' => 'IIIF Viewer',
            'faces' => 'Face Detection',
            'fuseki' => 'Fuseki / RIC',
            'ingest' => 'Data Ingest',
            'accession' => 'Accession Management',
            'encryption' => 'Encryption',
            'voice_ai' => 'Voice & AI',
            'integrity' => 'Integrity',
            'multi_tenant' => 'Multi-Tenancy',
            'portable_export' => 'Portable Export',
            'security' => 'Security',
            'features' => 'Features',
            'compliance' => 'Compliance',
            'ftp' => 'FTP / SFTP',
            'ai_condition' => 'AI Condition Assessment',
        ];
        $groupLabel = $ahgGroupLabels[$group] ?? ucfirst(str_replace('_', ' ', $group));

        return view('ahg-settings::ahg-section', compact(
            'settings', 'group', 'groupLabel', 'groupIcon',
            'checkboxFields', 'selectFields', 'colorFields', 'passwordFields', 'textareaFields'
        ));
    }

    // ─── 1. Clipboard ──────────────────────────────────────────────────
    public function clipboard(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('clipboard');

        $settingNames = [
            'clipboard_save_max_age',
            'clipboard_send_enabled',
            'clipboard_send_url',
            'clipboard_send_button_text',
            'clipboard_send_message_html',
            'clipboard_send_http_method',
            'clipboard_export_digitalobjects_enabled',
        ];
        $defaults = [
            'clipboard_save_max_age' => '0',
            'clipboard_send_enabled' => '0',
            'clipboard_send_button_text' => 'Send',
            'clipboard_send_message_html' => 'Sending...',
            'clipboard_send_http_method' => 'POST',
            'clipboard_export_digitalobjects_enabled' => '0',
        ];

        if ($request->isMethod('post')) {
            foreach ($settingNames as $name) {
                $value = $request->input("settings.{$name}", '');
                $this->service->saveSetting($name, null, $value, $culture);
            }
            return redirect()->route('settings.clipboard')->with('success', 'Clipboard settings saved.');
        }

        $settings = [];
        foreach ($settingNames as $name) {
            $settings[$name] = $this->service->getSetting($name, null, $culture) ?? ($defaults[$name] ?? '');
        }

        return view('ahg-settings::clipboard', compact('settings', 'menu'));
    }

    // ─── 2. CSV Validator ────────────────────────────────────────────────
    public function csvValidator(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('csv-validator');

        if ($request->isMethod('post')) {
            $value = $request->input('settings.csv_validator_default_import_behaviour', '0');
            $this->service->saveSetting('csv_validator_default_import_behaviour', null, $value, $culture);
            return redirect()->route('settings.csv-validator')->with('success', 'CSV Validator settings saved.');
        }

        $settings = [
            'csv_validator_default_import_behaviour' => $this->service->getSetting('csv_validator_default_import_behaviour', null, $culture) ?? '0',
        ];

        return view('ahg-settings::csv-validator', compact('settings', 'menu'));
    }

    // ─── 3. Diacritics ──────────────────────────────────────────────────
    public function diacritics(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('diacritics');

        if ($request->isMethod('post')) {
            $diacriticsVal = $request->input('settings.diacritics', '0');
            $this->service->saveSetting('diacritics', null, $diacriticsVal, $culture);

            // Handle YAML file upload
            if ($request->hasFile('mappings') && $request->file('mappings')->isValid()) {
                $file = $request->file('mappings');
                $destPath = storage_path('app/diacritics_mapping.yml');
                try {
                    $file->move(dirname($destPath), basename($destPath));
                } catch (\Exception $e) {
                    return redirect()->route('settings.diacritics')->with('error', 'Unable to upload diacritics mapping yaml file.');
                }
            }

            return redirect()->route('settings.diacritics')->with('success', 'Diacritics settings saved.');
        }

        $settings = [
            'diacritics' => $this->service->getSetting('diacritics', null, $culture) ?? '0',
        ];

        return view('ahg-settings::diacritics', compact('settings', 'menu'));
    }

    // ─── 4. DIP Upload ──────────────────────────────────────────────────
    public function dipUpload(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('dip-upload');

        if ($request->isMethod('post')) {
            $value = $request->input('settings.stripExtensions', '0');
            $this->service->saveSetting('stripExtensions', null, $value, $culture);
            return redirect()->route('settings.dip-upload')->with('success', 'DIP upload settings saved.');
        }

        $settings = [
            'stripExtensions' => $this->service->getSetting('stripExtensions', null, $culture) ?? '0',
        ];

        return view('ahg-settings::dip-upload', compact('settings', 'menu'));
    }

    // ─── 5. Finding Aid ─────────────────────────────────────────────────
    public function findingAid(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('finding-aid');

        $settingMap = [
            'finding_aids_enabled' => 'findingAidsEnabled',
            'finding_aid_format' => 'findingAidFormat',
            'finding_aid_model' => 'findingAidModel',
            'public_finding_aid' => 'publicFindingAid',
        ];
        $defaults = [
            'finding_aids_enabled' => '1',
            'finding_aid_format' => 'pdf',
            'finding_aid_model' => 'inventory-summary',
            'public_finding_aid' => '1',
        ];

        if ($request->isMethod('post')) {
            // AtoM SettingsFindingAidForm uses setNameFormat('finding_aid[%s]')
            foreach ($settingMap as $formKey => $dbName) {
                $value = $request->input("finding_aid.{$formKey}", $request->input("settings.{$formKey}", ''));
                $this->service->saveSetting($dbName, null, $value, $culture);
            }
            return redirect()->route('settings.finding-aid')->with('success', 'Finding aid settings saved.');
        }

        $settings = [];
        foreach ($settingMap as $formKey => $dbName) {
            $settings[$formKey] = $this->service->getSetting($dbName, null, $culture) ?? ($defaults[$formKey] ?? '');
        }

        return view('ahg-settings::finding-aid', compact('settings', 'menu'));
    }

    // ─── 6. Inventory ───────────────────────────────────────────────────
    public function inventory(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('inventory');

        // Get levels of description from taxonomy
        $levels = DB::table('term')
            ->join('term_i18n', function ($j) {
                $j->on('term.id', '=', 'term_i18n.id')->where('term_i18n.culture', '=', 'en');
            })
            ->where('term.taxonomy_id', 34)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get();

        // Get currently selected levels
        $currentValue = $this->service->getSetting('inventory_levels', null, $culture) ?? '';
        $selectedLevels = [];
        if (!empty($currentValue)) {
            $unserialized = @unserialize($currentValue);
            if (is_array($unserialized)) {
                $selectedLevels = $unserialized;
            }
        }

        if ($request->isMethod('post')) {
            $selected = $request->input('settings.levels', []);
            $serialized = serialize($selected);
            $this->service->saveSetting('inventory_levels', null, $serialized, $culture);
            return redirect()->route('settings.inventory')->with('success', 'Inventory settings saved.');
        }

        return view('ahg-settings::inventory', compact('menu', 'levels', 'selectedLevels'));
    }

    // ─── 7. Markdown ────────────────────────────────────────────────────
    public function markdown(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('markdown');

        if ($request->isMethod('post')) {
            $value = $request->input('settings.enabled', '1');
            $this->service->saveSetting('markdown_enabled', null, $value, $culture);
            return redirect()->route('settings.markdown')->with('success', 'Markdown settings saved.');
        }

        $settings = [
            'enabled' => $this->service->getSetting('markdown_enabled', null, $culture) ?? '1',
        ];

        return view('ahg-settings::markdown', compact('settings', 'menu'));
    }

    // ─── 8. Permissions ─────────────────────────────────────────────────
    public function permissions(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('permissions');

        // Get right basis terms from taxonomy 68
        $basis = DB::table('term')
            ->join('term_i18n', function ($j) {
                $j->on('term.id', '=', 'term_i18n.id')->where('term_i18n.culture', '=', 'en');
            })
            ->where('term.taxonomy_id', 68)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get()
            ->mapWithKeys(fn ($t) => [strtolower(str_replace(' ', '-', $t->name)) => $t->name])
            ->toArray();

        // Get right act terms from taxonomy 67
        $acts = DB::table('term')
            ->join('term_i18n', function ($j) {
                $j->on('term.id', '=', 'term_i18n.id')->where('term_i18n.culture', '=', 'en');
            })
            ->where('term.taxonomy_id', 67)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get();

        // Get PREMIS access statements from setting table (scope = 'access_statement')
        $accessStatements = [];
        $stmtRows = DB::table('setting')
            ->leftJoin('setting_i18n', function ($j) use ($culture) {
                $j->on('setting.id', '=', 'setting_i18n.id')->where('setting_i18n.culture', '=', $culture);
            })
            ->where('setting.scope', 'access_statement')
            ->select('setting.id', 'setting.name', 'setting_i18n.value')
            ->get();
        foreach ($stmtRows as $row) {
            $accessStatements[$row->name] = $row->value ?? '';
        }

        // Get PREMIS permissions from granted_right table
        $grantedRights = [];
        if (Schema::hasTable('granted_right')) {
            $grantedRights = DB::table('granted_right')->get()->toArray();
        }

        // Copyright statement settings
        $copyrightStatementEnabled = $this->service->getSetting('digitalobject_copyright_statement_enabled', null, $culture) ?? '0';
        $copyrightStatement = $this->service->getSetting('digitalobject_copyright_statement', null, $culture) ?? '';
        $copyrightStatementApplyGlobally = $this->service->getSetting('digitalobject_copyright_statement_apply_globally', null, $culture) ?? '0';

        // Preservation system access statement
        $preservationEnabled = $this->service->getSetting('digitalobject_preservation_system_access_statement_enabled', null, $culture) ?? '0';
        $preservationStatement = $this->service->getSetting('digitalobject_preservation_system_access_statement', null, $culture) ?? '';

        if ($request->isMethod('post')) {
            // Save PREMIS access statements
            foreach ($request->input('access_statements', []) as $name => $value) {
                $existing = DB::table('setting')->where('name', $name)->where('scope', 'access_statement')->first();
                if ($existing) {
                    DB::table('setting_i18n')->updateOrInsert(
                        ['id' => $existing->id, 'culture' => $culture],
                        ['value' => $value]
                    );
                }
            }

            // Save copyright statement settings
            $this->service->saveSetting('digitalobject_copyright_statement_enabled', null, $request->input('copyrightStatementEnabled', '0'), $culture);
            $this->service->saveSetting('digitalobject_copyright_statement', null, $request->input('copyrightStatement', ''), $culture);
            $this->service->saveSetting('digitalobject_copyright_statement_apply_globally', null, $request->input('copyrightStatementApplyGlobally', '0'), $culture);

            // Save preservation statement settings
            $this->service->saveSetting('digitalobject_preservation_system_access_statement_enabled', null, $request->input('preservationStatementEnabled', '0'), $culture);
            $this->service->saveSetting('digitalobject_preservation_system_access_statement', null, $request->input('preservationStatement', ''), $culture);

            return redirect()->route('settings.permissions')->with('success', 'Permissions saved.');
        }

        return view('ahg-settings::permissions', compact(
            'menu', 'basis', 'acts', 'accessStatements',
            'grantedRights', 'copyrightStatementEnabled', 'copyrightStatement',
            'copyrightStatementApplyGlobally', 'preservationEnabled', 'preservationStatement'
        ));
    }

    // ─── 9. Privacy Notification ────────────────────────────────────────
    public function privacyNotification(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('privacy-notification');

        if ($request->isMethod('post')) {
            $this->service->saveSetting('privacy_notification_enabled', null, $request->input('settings.privacy_notification_enabled', '0'), $culture);
            $this->service->saveSetting('privacy_notification', null, $request->input('settings.privacy_notification', ''), $culture);
            return redirect()->route('settings.privacy-notification')->with('success', 'Privacy notification settings saved.');
        }

        $settings = [
            'privacy_notification_enabled' => $this->service->getSetting('privacy_notification_enabled', null, $culture) ?? '0',
            'privacy_notification' => $this->service->getSetting('privacy_notification', null, $culture) ?? '',
        ];

        return view('ahg-settings::privacy-notification', compact('settings', 'menu'));
    }

    // ─── 10. Uploads ────────────────────────────────────────────────────
    public function uploads(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('uploads');

        $settingNames = [
            'upload_quota',
            'enable_repository_quotas',
            'repository_quota',
            'explode_multipage_files',
        ];
        $defaults = [
            'upload_quota' => '-1',
            'enable_repository_quotas' => '1',
            'repository_quota' => '0',
            'explode_multipage_files' => '0',
        ];

        if ($request->isMethod('post')) {
            foreach ($settingNames as $name) {
                $value = $request->input("settings.{$name}", '');
                $this->service->saveSetting($name, null, $value, $culture);
            }
            return redirect()->route('settings.uploads')->with('success', 'Uploads settings saved.');
        }

        $settings = [];
        foreach ($settingNames as $name) {
            $settings[$name] = $this->service->getSetting($name, null, $culture) ?? ($defaults[$name] ?? '');
        }

        return view('ahg-settings::uploads', compact('settings', 'menu'));
    }

    // ─── 11. Header Customizations ──────────────────────────────────────
    public function headerCustomizations(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('header-customizations');

        $staticDir = public_path('uploads');

        $settingNames = [
            'header_background_colour',
        ];

        if ($request->isMethod('post')) {
            // Save header background colour
            foreach ($settingNames as $name) {
                $value = $request->input("settings.{$name}", '');
                $this->service->saveSetting($name, null, $value, $culture);
            }

            // Ensure uploads directory exists
            if (!is_dir($staticDir)) {
                mkdir($staticDir, 0755, true);
            }

            // Handle logo upload
            if ($request->hasFile('logo') && $request->file('logo')->isValid()) {
                $logoFile = $request->file('logo');
                if ($logoFile->getMimeType() === 'image/png') {
                    $logoFile->move($staticDir, 'logo.png');
                }
            }

            // Handle favicon upload
            if ($request->hasFile('favicon') && $request->file('favicon')->isValid()) {
                $faviconFile = $request->file('favicon');
                $mime = $faviconFile->getMimeType();
                if (in_array($mime, ['image/x-icon', 'image/vnd.microsoft.icon', 'image/ico', 'image/icon'])) {
                    $faviconFile->move($staticDir, 'favicon.ico');
                    // Also copy to public root for direct access
                    copy($staticDir . DIRECTORY_SEPARATOR . 'favicon.ico', public_path('favicon.ico'));
                }
            }

            // Handle restore default logo
            if ($request->input('restore_logo')) {
                $defaultLogoPath = public_path('vendor/ahg-theme-b5/images/logo.png');
                $logoImgPath = $staticDir . DIRECTORY_SEPARATOR . 'logo.png';
                if (file_exists($defaultLogoPath)) {
                    copy($defaultLogoPath, $logoImgPath);
                } else {
                    return redirect()->route('settings.header-customizations')->with('error', 'Default logo not found.');
                }
            }

            // Handle restore default favicon
            if ($request->input('restore_favicon')) {
                $defaultFaviconPath = resource_path('defaults/favicon.ico');
                $faviconImgPath = $staticDir . DIRECTORY_SEPARATOR . 'favicon.ico';
                // Try multiple fallback locations for the default favicon
                $fallbackPaths = [
                    $defaultFaviconPath,
                    public_path('favicon.ico'),
                ];
                $restored = false;
                foreach ($fallbackPaths as $path) {
                    if (file_exists($path) && $path !== $faviconImgPath) {
                        copy($path, $faviconImgPath);
                        copy($faviconImgPath, public_path('favicon.ico'));
                        $restored = true;
                        break;
                    }
                }
                if (!$restored) {
                    // Remove custom favicon to fall back to default
                    if (file_exists($faviconImgPath)) {
                        unlink($faviconImgPath);
                    }
                }
            }

            return redirect()->route('settings.header-customizations')->with('success', 'Header customizations settings saved.');
        }

        $settings = [];
        foreach ($settingNames as $name) {
            $settings[$name] = $this->service->getSetting($name, null, $culture) ?? '';
        }

        return view('ahg-settings::header-customizations', compact('settings', 'menu'));
    }

    // ─── 12. Web Analytics ──────────────────────────────────────────────
    public function webAnalytics(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('web-analytics');

        $settingNames = [
            'google_analytics_api_key',
            'google_tag_manager_id',
        ];

        if ($request->isMethod('post')) {
            foreach ($settingNames as $name) {
                $value = $request->input("settings.{$name}", '');
                $this->service->saveSetting($name, null, $value, $culture);
            }
            return redirect()->route('settings.web-analytics')->with('success', 'Web analytics settings saved.');
        }

        $settings = [];
        foreach ($settingNames as $name) {
            $settings[$name] = $this->service->getSetting($name, null, $culture) ?? '';
        }

        return view('ahg-settings::web-analytics', compact('settings', 'menu'));
    }

    // ─── 13. AI Condition Assessment ──────────────────────────────────
    public function aiCondition(Request $request)
    {
        $menu = $this->buildMenu('ai-condition');

        // ── Load settings from ahg_settings (ai_condition group) ──
        $settings = [];
        if (Schema::hasTable('ahg_settings')) {
            $rows = DB::table('ahg_settings')
                ->where('setting_group', 'ai_condition')
                ->get();
            foreach ($rows as $row) {
                $settings[$row->setting_key] = $row->setting_value;
            }
        }

        // ── Statistics from ahg_ai_condition_assessment ──
        $stats = ['total' => 0, 'confirmed' => 0, 'pending' => 0, 'avg_score' => '--'];
        if (Schema::hasTable('ahg_ai_condition_assessment')) {
            try {
                $stats['total'] = DB::table('ahg_ai_condition_assessment')->count();
                $stats['confirmed'] = DB::table('ahg_ai_condition_assessment')
                    ->where('status', 'confirmed')->count();
                $stats['pending'] = DB::table('ahg_ai_condition_assessment')
                    ->where('status', 'pending')->count();
                $avg = DB::table('ahg_ai_condition_assessment')->avg('score');
                $stats['avg_score'] = $avg !== null ? number_format($avg, 1) : '--';
            } catch (\Throwable $e) {
                // Table may exist but columns differ — keep defaults
            }
        }

        // ── API Clients ──
        $clients = collect();
        if (Schema::hasTable('ahg_ai_condition_client')) {
            try {
                $clients = DB::table('ahg_ai_condition_client')->get();
            } catch (\Throwable $e) {
                // graceful fallback
            }
        }

        // ── Training contributions per client ──
        $trainingContributions = [];
        if (Schema::hasTable('ahg_ai_condition_training')) {
            try {
                $contribs = DB::table('ahg_ai_condition_training')
                    ->select(
                        'client_id',
                        DB::raw('COUNT(*) as total'),
                        DB::raw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending"),
                        DB::raw("SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved")
                    )
                    ->groupBy('client_id')
                    ->get();
                foreach ($contribs as $row) {
                    $trainingContributions[$row->client_id] = $row;
                }
            } catch (\Throwable $e) {
                // graceful fallback
            }
        }

        // ── Handle settings POST ──
        if ($request->isMethod('post') && $request->input('form_action') === 'save_settings') {
            $keys = [
                'ai_condition_service_url', 'ai_condition_api_key',
                'ai_condition_min_confidence', 'ai_condition_overlay_enabled',
                'ai_condition_auto_scan', 'ai_condition_notify_grade',
            ];
            $checkboxes = ['ai_condition_overlay_enabled', 'ai_condition_auto_scan'];

            foreach ($keys as $key) {
                $value = in_array($key, $checkboxes)
                    ? ($request->has($key) ? '1' : '0')
                    : ($request->input($key, '') ?? '');

                DB::table('ahg_settings')->updateOrInsert(
                    ['setting_key' => $key, 'setting_group' => 'ai_condition'],
                    ['setting_value' => $value, 'updated_at' => now()]
                );
                $settings[$key] = $value;
            }

            return redirect()->route('settings.ai-condition')
                ->with('notice', 'AI Condition settings saved.');
        }

        return view('ahg-settings::ai-condition', compact(
            'menu', 'settings', 'stats', 'clients', 'trainingContributions'
        ));
    }

    // ─── Accession Management ──────────────────────────────────────────
    public function accessionSettings(Request $request)
    {
        $menu = $this->buildMenu('accession');

        // ── Load settings from ahg_settings (accession group) ──
        $settings = [];
        if (Schema::hasTable('ahg_settings')) {
            $rows = DB::table('ahg_settings')
                ->where('setting_group', 'accession')
                ->get();
            foreach ($rows as $row) {
                $settings[$row->setting_key] = $row->setting_value;
            }
        }

        // ── Handle POST ──
        if ($request->isMethod('post')) {
            $keys = [
                'accession_numbering_mask',
                'accession_default_priority',
                'accession_auto_assign_enabled',
                'accession_require_donor_agreement',
                'accession_require_appraisal',
                'accession_allow_container_barcodes',
                'accession_rights_inheritance_enabled',
            ];
            $checkboxes = [
                'accession_auto_assign_enabled',
                'accession_require_donor_agreement',
                'accession_require_appraisal',
                'accession_allow_container_barcodes',
                'accession_rights_inheritance_enabled',
            ];

            foreach ($keys as $key) {
                $value = in_array($key, $checkboxes)
                    ? ($request->has($key) ? '1' : '0')
                    : ($request->input($key, '') ?? '');

                DB::table('ahg_settings')->updateOrInsert(
                    ['setting_key' => $key, 'setting_group' => 'accession'],
                    ['setting_value' => $value, 'updated_at' => now()]
                );
                $settings[$key] = $value;
            }

            return redirect()->route('settings.ahg.accession')
                ->with('notice', 'Accession settings saved.');
        }

        return view('ahg-settings::accession-settings', compact('menu', 'settings'));
    }

    // ─── 14. Storage Service ────────────────────────────────────────────
    public function storageService(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('storage-service');

        $settingNames = [
            'storage_service_url',
            'storage_service_api_key',
            'storage_service_username',
            'storage_service_type',
            'storage_service_enabled',
        ];

        if ($request->isMethod('post')) {
            foreach ($settingNames as $name) {
                $value = $request->input("settings.{$name}", '');
                $this->service->saveSetting($name, null, $value, $culture);
            }
            return redirect()->route('settings.storage-service')->with('success', 'Storage service settings saved.');
        }

        $settings = [];
        foreach ($settingNames as $name) {
            $settings[$name] = $this->service->getSetting($name, null, $culture) ?? '';
        }

        return view('ahg-settings::storage-service', compact('settings', 'menu'));
    }

    /**
     * Error Log management page.
     */
    public function errorLog(Request $request)
    {
        // Handle POST actions
        if ($request->isMethod('post')) {
            $authUser = \Illuminate\Support\Facades\Auth::id();

            if ($request->filled('resolve_id')) {
                DB::table('ahg_error_log')
                    ->where('id', $request->input('resolve_id'))
                    ->update(['resolved_at' => now(), 'resolved_by' => $authUser, 'is_read' => 1]);
                return redirect()->route('settings.error-log')->with('success', 'Error resolved.');
            }

            if ($request->filled('reopen_id')) {
                DB::table('ahg_error_log')
                    ->where('id', $request->input('reopen_id'))
                    ->update(['resolved_at' => null, 'resolved_by' => null]);
                return redirect()->route('settings.error-log')->with('success', 'Error reopened.');
            }

            if ($request->has('mark_read')) {
                DB::table('ahg_error_log')
                    ->where('is_read', 0)
                    ->update(['is_read' => 1]);
                return redirect()->route('settings.error-log')->with('success', 'All errors marked as read.');
            }

            if ($request->has('resolve_all')) {
                DB::table('ahg_error_log')
                    ->whereNull('resolved_at')
                    ->update(['resolved_at' => now(), 'resolved_by' => $authUser, 'is_read' => 1]);
                return redirect()->route('settings.error-log')->with('success', 'All open errors resolved.');
            }

            if ($request->has('clear_old')) {
                $days = max(1, (int) $request->input('clear_days', 30));
                DB::table('ahg_error_log')
                    ->where('created_at', '<', DB::raw("DATE_SUB(NOW(), INTERVAL {$days} DAY)"))
                    ->delete();
                return redirect()->route('settings.error-log')->with('success', "Errors older than {$days} days cleared.");
            }

            if ($request->filled('delete_id')) {
                DB::table('ahg_error_log')
                    ->where('id', $request->input('delete_id'))
                    ->delete();
                return redirect()->route('settings.error-log')->with('success', 'Error deleted.');
            }

            return redirect()->route('settings.error-log');
        }

        // GET: Query with filters
        $statusFilter = $request->get('status', 'open');
        $levelFilter = $request->get('level', '');
        $searchFilter = $request->get('search', '');
        $page = max(1, (int) $request->get('page', 1));
        $limit = 25;

        $query = DB::table('ahg_error_log')
            ->where('url', 'LIKE', '%' . $request->getHost() . '%');

        if ($statusFilter === 'open') {
            $query->whereNull('resolved_at');
        } elseif ($statusFilter === 'resolved') {
            $query->whereNotNull('resolved_at');
        }
        // 'all' shows both open and resolved — no filter needed

        if ($levelFilter) {
            $query->where('level', $levelFilter);
        }

        if ($searchFilter) {
            $query->where(function ($q) use ($searchFilter) {
                $q->where('message', 'like', "%{$searchFilter}%")
                  ->orWhere('url', 'like', "%{$searchFilter}%")
                  ->orWhere('file', 'like', "%{$searchFilter}%")
                  ->orWhere('exception_class', 'like', "%{$searchFilter}%");
            });
        }

        $total = (clone $query)->count();
        $totalPages = $limit > 0 ? (int) ceil($total / $limit) : 1;

        $entries = $query
            ->orderByDesc('created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        // Stats (scoped to this instance)
        $hostFilter = fn ($q) => $q->where('url', 'LIKE', '%' . $request->getHost() . '%');
        $openCount = DB::table('ahg_error_log')->where($hostFilter)->whereNull('resolved_at')->count();
        $resolvedCount = DB::table('ahg_error_log')->where($hostFilter)->whereNotNull('resolved_at')->count();
        $unreadCount = DB::table('ahg_error_log')->where($hostFilter)->where('is_read', 0)->count();
        $todayCount = DB::table('ahg_error_log')->where($hostFilter)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        return view('ahg-settings::errorLog', [
            'entries' => $entries,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $totalPages,
            'openCount' => $openCount,
            'resolvedCount' => $resolvedCount,
            'unreadCount' => $unreadCount,
            'todayCount' => $todayCount,
            'filters' => [
                'status' => $statusFilter,
                'level' => $levelFilter,
                'search' => $searchFilter,
            ],
        ]);
    }

    public function plugins(Request $request)
    {
        if ($request->isMethod('post')) {
            $enabled = $request->input('enabled', []);
            DB::table('atom_plugin')->update(['is_enabled' => 0]);
            if (!empty($enabled)) {
                DB::table('atom_plugin')->whereIn('name', $enabled)->update(['is_enabled' => 1]);
            }
            return redirect()->back()->with('success', 'Plugin settings saved.');
        }

        $plugins = DB::table('atom_plugin')->orderBy('name')->get();
        return view('ahg-settings::plugins', compact('plugins'));
    }

    private function buildMenu(string $active): array
    {
        // Sort alphabetically by label, but pin the index ('AHG Settings') to the top.
        $nodes = collect($this->menuNodes)->map(function ($node) use ($active) {
            $node['active'] = ($node['action'] === $active);
            return $node;
        });

        $home = $nodes->firstWhere('action', 'index');
        $rest = $nodes->reject(fn ($n) => $n['action'] === 'index')
            ->sortBy(fn ($n) => strtolower($n['label']))
            ->values();

        return $home ? array_merge([$home], $rest->all()) : $rest->all();
    }

    /**
     * Cron Jobs management page — interactive DB-driven scheduler.
     */
    public function cronJobs(\AhgCore\Services\CronSchedulerService $service)
    {
        $menu = $this->buildMenu('cron-jobs');
        $categories = $service->getAllGrouped();
        $stats = $service->getStats();

        return view('ahg-settings::cron-jobs', compact('categories', 'stats', 'menu'));
    }

    /**
     * Toggle a cron schedule enabled/disabled.
     */
    public function cronJobToggle(\Illuminate\Http\Request $request, int $id, \AhgCore\Services\CronSchedulerService $service)
    {
        $schedule = $service->find($id);
        if (!$schedule) {
            return back()->with('error', 'Schedule not found.');
        }

        $service->toggleEnabled($id, !$schedule->is_enabled);
        $state = $schedule->is_enabled ? 'disabled' : 'enabled';

        return back()->with('success', "Job \"{$schedule->name}\" {$state}.");
    }

    /**
     * Update a cron schedule's settings.
     */
    public function cronJobUpdate(\Illuminate\Http\Request $request, int $id, \AhgCore\Services\CronSchedulerService $service)
    {
        $schedule = $service->find($id);
        if (!$schedule) {
            return back()->with('error', 'Schedule not found.');
        }

        $validated = $request->validate([
            'cron_expression' => ['required', 'string', 'max:60'],
            'timeout_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'notify_on_failure' => ['nullable'],
            'notify_email' => ['nullable', 'email', 'max:200'],
        ]);

        $validated['notify_on_failure'] = $request->has('notify_on_failure');

        $service->updateSchedule($id, $validated);

        return back()->with('success', "Job \"{$schedule->name}\" updated.");
    }

    /**
     * Run a single cron job immediately.
     */
    public function cronJobRunNow(\Illuminate\Http\Request $request, int $id, \AhgCore\Services\CronSchedulerService $service)
    {
        $schedule = $service->find($id);
        if (!$schedule) {
            return back()->with('error', 'Schedule not found.');
        }

        $result = $service->runSingle($schedule);

        if ($result['status'] === 'success') {
            return back()->with('success', "Job \"{$schedule->name}\" completed in {$result['duration_ms']}ms.");
        }

        return back()->with('error', "Job \"{$schedule->name}\" failed after {$result['duration_ms']}ms.");
    }

    /**
     * Re-seed all default cron schedules.
     */
    public function cronJobSeed(\Illuminate\Http\Request $request, \AhgCore\Services\CronSchedulerService $service)
    {
        $reset = $request->has('reset');
        $count = $service->seedDefaults($reset);

        $msg = $reset ? "Reset {$count} schedules to defaults." : "Seeded {$count} schedule entries (new only).";
        return back()->with('success', $msg);
    }

    // ─── LDAP ──────────────────────────────────────────────────────────
    public function ldap(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('ldap');

        $settingNames = ['ldapHost', 'ldapPort', 'ldapBaseDn', 'ldapBindAttribute'];

        if ($request->isMethod('post')) {
            foreach ($settingNames as $name) {
                $this->service->saveSetting($name, null, $request->input("settings.{$name}", ''), $culture);
            }
            return redirect()->route('settings.ldap')->with('success', 'LDAP settings saved.');
        }

        $settings = [];
        foreach ($settingNames as $name) {
            $settings[$name] = $this->service->getSetting($name, null, $culture) ?? '';
        }
        $settings['ldapPort'] = $settings['ldapPort'] ?: '389';
        $settings['ldapBindAttribute'] = $settings['ldapBindAttribute'] ?: 'uid';

        return view('ahg-settings::ldap', compact('settings', 'menu'));
    }

    // ─── Levels ────────────────────────────────────────────────────────
    public function levels(Request $request)
    {
        $menu = $this->buildMenu('levels');

        // Available sectors (matching AtoM: archive always present + any with data)
        $availableSectors = ['archive', 'museum', 'library', 'gallery', 'dam'];

        $currentSector = $request->input('sector', 'archive');
        if (!in_array($currentSector, $availableSectors)) {
            $currentSector = 'archive';
        }

        // Counts per sector for badges
        $sectorCounts = [];
        $countRows = DB::table('level_of_description_sector')
            ->selectRaw('sector, COUNT(*) as cnt')
            ->groupBy('sector')
            ->pluck('cnt', 'sector')
            ->toArray();
        foreach ($availableSectors as $s) {
            $sectorCounts[$s] = $countRows[$s] ?? 0;
        }

        // All levels from taxonomy 34 (for the checkbox grid)
        $sectorAvailableLevels = DB::table('term')
            ->join('term_i18n', function ($j) {
                $j->on('term.id', '=', 'term_i18n.id')->where('term_i18n.culture', '=', 'en');
            })
            ->leftJoin('slug', 'slug.object_id', '=', 'term.id')
            ->where('term.taxonomy_id', 34)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name', 'slug.slug')
            ->get();

        // Levels currently assigned to this sector (ordered)
        $sectorLevels = DB::table('level_of_description_sector as lds')
            ->join('term_i18n', function ($j) {
                $j->on('lds.term_id', '=', 'term_i18n.id')->where('term_i18n.culture', '=', 'en');
            })
            ->leftJoin('slug', 'slug.object_id', '=', 'lds.term_id')
            ->where('lds.sector', $currentSector)
            ->orderBy('lds.display_order')
            ->select('lds.term_id as id', 'term_i18n.name', 'slug.slug', 'lds.display_order')
            ->get();

        $sectorLevelIds = $sectorLevels->pluck('id')->toArray();

        // Handle POST
        if ($request->isMethod('post')) {
            $actionType = $request->input('action_type');

            if ($actionType === 'update_sector') {
                $levelIds = $request->input('levels', []);
                DB::table('level_of_description_sector')
                    ->where('sector', $currentSector)
                    ->delete();

                $order = 10;
                foreach ($levelIds as $levelId) {
                    DB::table('level_of_description_sector')->insert([
                        'term_id'       => (int) $levelId,
                        'sector'        => $currentSector,
                        'display_order' => $order,
                    ]);
                    $order += 10;
                }
                return redirect()->route('settings.levels', ['sector' => $currentSector])
                    ->with('success', 'Sector levels updated successfully.');
            }

            if ($actionType === 'update_order') {
                $orders = $request->input('order', []);
                foreach ($orders as $levelId => $order) {
                    DB::table('level_of_description_sector')
                        ->where('term_id', (int) $levelId)
                        ->where('sector', $currentSector)
                        ->update(['display_order' => (int) $order]);
                }
                return redirect()->route('settings.levels', ['sector' => $currentSector])
                    ->with('success', 'Display order updated.');
            }
        }

        return view('ahg-settings::levels', compact(
            'menu', 'availableSectors', 'currentSector', 'sectorCounts',
            'sectorAvailableLevels', 'sectorLevels', 'sectorLevelIds'
        ));
    }

    // ─── Paths ─────────────────────────────────────────────────────────
    public function paths(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('paths');
        $settingNames = ['bulk', 'bulk_index', 'bulk_optimize_index', 'bulk_rename'];

        if ($request->isMethod('post')) {
            foreach ($settingNames as $name) {
                $this->service->saveSetting($name, null, $request->input("settings.{$name}", ''), $culture);
            }
            return redirect()->route('settings.paths')->with('success', 'Paths saved.');
        }

        $settings = [];
        foreach ($settingNames as $name) {
            $settings[$name] = $this->service->getSetting($name, null, $culture) ?? '';
        }

        return view('ahg-settings::paths', compact('settings', 'menu'));
    }

    // ─── Preservation ──────────────────────────────────────────────────
    public function preservation(Request $request)
    {
        $menu = $this->buildMenu('preservation');

        $targets = collect();
        $stats = ['total_targets' => 0, 'active_targets' => 0, 'successful_syncs' => 0, 'failed_syncs' => 0];
        if (Schema::hasTable('ahg_preservation_targets')) {
            $targets = DB::table('ahg_preservation_targets')->orderBy('name')->get();
            $stats['total_targets'] = $targets->count();
            $stats['active_targets'] = $targets->where('is_active', 1)->count();
            $stats['successful_syncs'] = $targets->sum('successful_syncs');
            $stats['failed_syncs'] = $targets->sum('failed_syncs');
        }

        return view('ahg-settings::preservation', compact('menu', 'targets', 'stats'));
    }

    // ─── Webhooks ──────────────────────────────────────────────────────
    public function webhooks(Request $request)
    {
        $menu = $this->buildMenu('webhooks');

        $webhooks = collect();
        if (Schema::hasTable('ahg_webhooks')) {
            if ($request->isMethod('post')) {
                DB::table('ahg_webhooks')->insert([
                    'name' => $request->input('name', ''),
                    'url' => $request->input('url', ''),
                    'events' => json_encode($request->input('events', [])),
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                return redirect()->route('settings.webhooks')->with('success', 'Webhook created.');
            }
            $webhooks = DB::table('ahg_webhooks')->orderBy('name')->get()->map(function ($w) {
                $w->events = json_decode($w->events, true) ?: [];
                return $w;
            });
        }

        return view('ahg-settings::webhooks', compact('menu', 'webhooks'));
    }

    // ─── TTS (Text-to-Speech) ──────────────────────────────────────────
    public function tts(Request $request)
    {
        $menu = $this->buildMenu('tts');

        $settings = ['all' => ['enabled' => '1', 'default_rate' => '1.0', 'read_labels' => '1', 'default_voice' => '', 'default_pitch' => '1.0']];
        if (Schema::hasTable('ahg_settings')) {
            $rows = DB::table('ahg_settings')->where('setting_group', 'tts')->pluck('setting_value', 'setting_key');
            foreach ($rows as $key => $val) {
                $settings['all'][$key] = $val;
            }
        }

        if ($request->isMethod('post')) {
            foreach ($request->input('tts.all', []) as $key => $value) {
                DB::table('ahg_settings')->updateOrInsert(
                    ['setting_key' => $key, 'setting_group' => 'tts'],
                    ['setting_value' => $value]
                );
            }
            return redirect()->route('settings.tts')->with('success', 'Text-to-Speech settings saved.');
        }

        return view('ahg-settings::tts', compact('menu', 'settings'));
    }

    // ─── ICIP Settings ─────────────────────────────────────────────────
    public function icipSettings(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('icip-settings');

        $settingNames = ['enable_public_notices', 'enable_staff_notices', 'require_acknowledgement_default', 'require_community_consent', 'consultation_period_days'];
        $defaults = ['enable_public_notices' => '0', 'enable_staff_notices' => '0', 'require_acknowledgement_default' => '0', 'require_community_consent' => '0', 'consultation_period_days' => '30'];

        if ($request->isMethod('post')) {
            foreach ($settingNames as $name) {
                $this->service->saveSetting('icip_' . $name, null, $request->input("settings.{$name}", ''), $culture);
            }
            return redirect()->route('settings.icip-settings')->with('success', 'ICIP settings saved.');
        }

        $settings = [];
        foreach ($settingNames as $name) {
            $settings[$name] = $this->service->getSetting('icip_' . $name, null, $culture) ?? ($defaults[$name] ?? '');
        }

        return view('ahg-settings::icip-settings', compact('settings', 'menu'));
    }

    // ─── Sector Numbering ──────────────────────────────────────────────
    public function sectorNumbering(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('sector-numbering');

        $globalValues = [
            'identifier_mask_enabled' => $this->service->getSetting('identifier_mask_enabled', null, $culture) ?? '0',
            'identifier_mask' => $this->service->getSetting('identifier_mask', null, $culture) ?? '',
            'identifier_counter' => $this->service->getSetting('identifier_counter', null, $culture) ?? '0',
        ];

        $sectors = ['archive' => 'Archive', 'museum' => 'Museum', 'library' => 'Library', 'gallery' => 'Gallery', 'dam' => 'DAM'];
        $sectorSettings = [];
        foreach (array_keys($sectors) as $code) {
            $sectorSettings[$code] = [
                'identifier_mask_enabled' => $this->service->getSetting("sector_{$code}_identifier_mask_enabled", null, $culture) ?? '0',
                'identifier_mask' => $this->service->getSetting("sector_{$code}_identifier_mask", null, $culture) ?? '',
                'identifier_counter' => $this->service->getSetting("sector_{$code}_identifier_counter", null, $culture) ?? '0',
            ];
        }

        if ($request->isMethod('post')) {
            foreach (array_keys($sectors) as $code) {
                foreach (['identifier_mask_enabled', 'identifier_mask', 'identifier_counter'] as $field) {
                    $val = $request->input("sector_{$code}__{$field}", '');
                    $this->service->saveSetting("sector_{$code}_{$field}", null, $val, $culture);
                }
            }
            return redirect()->route('settings.sector-numbering')->with('success', 'Sector numbering saved.');
        }

        return view('ahg-settings::sector-numbering', compact('menu', 'globalValues', 'sectors', 'sectorSettings'));
    }

    // ─── Numbering Schemes ─────────────────────────────────────────────
    public function numberingSchemes(Request $request)
    {
        $menu = $this->buildMenu('numbering-schemes');
        $sectorFilter = $request->get('sector', '');
        $sectors = ['archive' => 'Archive', 'museum' => 'Museum', 'library' => 'Library', 'gallery' => 'Gallery', 'dam' => 'DAM'];

        $schemes = collect();
        if (Schema::hasTable('ahg_numbering_schemes')) {
            $query = DB::table('ahg_numbering_schemes')->orderBy('name');
            if ($sectorFilter) {
                $query->where('sector', $sectorFilter);
            }
            $schemes = $query->get();
        }

        return view('ahg-settings::numbering-schemes', compact('menu', 'schemes', 'sectors', 'sectorFilter'));
    }

    // ─── Numbering Scheme Edit ─────────────────────────────────────────
    public function numberingSchemeEdit(Request $request, ?int $id = null)
    {
        $menu = $this->buildMenu('numbering-scheme-edit');
        $isNew = is_null($id);
        $scheme = null;
        $previews = [];
        $schemeId = $id;

        if (!$isNew && Schema::hasTable('ahg_numbering_schemes')) {
            $scheme = DB::table('ahg_numbering_schemes')->find($id);
        }

        if ($request->isMethod('post') && Schema::hasTable('ahg_numbering_schemes')) {
            $data = [
                'name' => $request->input('name', ''),
                'sector' => $request->input('sector', 'archive'),
                'description' => $request->input('description', ''),
                'pattern' => $request->input('pattern', ''),
                'counter_start' => (int) $request->input('counter_start', 1),
                'reset_period' => $request->input('reset_period', 'never'),
                'is_default' => $request->has('is_default') ? 1 : 0,
                'is_active' => 1,
                'updated_at' => now(),
            ];
            if ($isNew) {
                $data['created_at'] = now();
                $schemeId = DB::table('ahg_numbering_schemes')->insertGetId($data);
            } else {
                DB::table('ahg_numbering_schemes')->where('id', $id)->update($data);
            }
            return redirect()->route('settings.numbering-schemes')->with('success', 'Numbering scheme saved.');
        }

        return view('ahg-settings::numbering-scheme-edit', compact('menu', 'isNew', 'scheme', 'schemeId', 'previews'));
    }

    // ─── DAM Tools ─────────────────────────────────────────────────────
    public function damTools(Request $request)
    {
        $menu = $this->buildMenu('dam-tools');
        $mergeSettings = [];
        if (Schema::hasTable('ahg_settings')) {
            $mergeSettings = DB::table('ahg_settings')
                ->where('setting_group', 'dam_tools')
                ->pluck('setting_value', 'setting_key')
                ->toArray();
        }

        return view('ahg-settings::dam-tools', compact('menu', 'mergeSettings'));
    }

    // ─── AI Services ───────────────────────────────────────────────────
    public function aiServices(Request $request)
    {
        $menu = $this->buildMenu('ai-services');

        // Read from ahg_ner_settings (matches AtoM)
        $settings = [];
        if (Schema::hasTable('ahg_ner_settings')) {
            $settings = DB::table('ahg_ner_settings')
                ->pluck('setting_value', 'setting_key')
                ->toArray();
        }

        // Defaults (matching AtoM aiServicesAction)
        $defaults = [
            'ner_enabled' => '1',
            'summarizer_enabled' => '1',
            'spellcheck_enabled' => '0',
            'translation_enabled' => '1',
            'processing_mode' => 'job',
            'summary_field' => 'scopeAndContent',
            'api_url' => 'http://192.168.0.112:5004/ai/v1',
            'api_key' => 'ahg_ai_demo_internal_2026',
            'api_timeout' => '60',
            'auto_extract_on_upload' => '0',
            'ner_entity_types' => '["PERSON","ORG","GPE","DATE"]',
            'summarizer_max_length' => '500',
            'summarizer_min_length' => '100',
            'spellcheck_language' => 'en_ZA',
            'spellcheck_fields' => '["title","scopeAndContent"]',
            'mt_endpoint' => 'http://127.0.0.1:5100/translate',
            'mt_timeout' => '30',
            'translation_source_lang' => 'en',
            'translation_target_lang' => 'af',
            'translation_fields' => '["title","scope_and_content"]',
            'translation_mode' => 'review',
            'translation_overwrite' => '0',
            'translation_sector' => 'archives',
            'translation_save_culture' => '1',
            'translation_field_mappings' => '{}',
            'qdrant_enabled' => '1',
            'qdrant_url' => 'http://localhost:6333',
            'qdrant_collection' => '',
            'qdrant_model' => 'all-MiniLM-L6-v2',
            'qdrant_min_score' => '0.25',
        ];
        foreach ($defaults as $k => $v) {
            if (!isset($settings[$k])) {
                $settings[$k] = $v;
            }
        }

        // Summary target fields
        $summaryFields = [
            'scopeAndContent' => 'Scope and Content',
            'abstract' => 'Abstract',
            'archivalHistory' => 'Archival History',
            'acquisition' => 'Immediate Source of Acquisition',
            'appraisal' => 'Appraisal, Destruction and Scheduling',
            'arrangement' => 'System of Arrangement',
            'physicalCharacteristics' => 'Physical Characteristics',
            'relatedUnitsOfDescription' => 'Related Units of Description',
            'locationOfOriginals' => 'Location of Originals',
            'locationOfCopies' => 'Location of Copies',
            'findingAids' => 'Finding Aids',
            'generalNote' => 'General Note',
        ];

        // Spellcheck languages
        $spellcheckLanguages = [
            'en_US' => 'English (US)',
            'en_GB' => 'English (UK)',
            'en_ZA' => 'English (South Africa)',
            'af_ZA' => 'Afrikaans',
            'zu_ZA' => 'Zulu',
            'xh_ZA' => 'Xhosa',
            'de_DE' => 'German',
            'fr_FR' => 'French',
            'es_ES' => 'Spanish',
            'pt_PT' => 'Portuguese',
            'nl_NL' => 'Dutch',
        ];

        // Spellcheck fields
        $spellcheckFields = [
            'title' => 'Title',
            'scopeAndContent' => 'Scope and Content',
            'abstract' => 'Abstract',
            'archivalHistory' => 'Archival History',
            'acquisition' => 'Immediate Source of Acquisition',
        ];

        // Translation languages (OPUS-MT supported) with culture codes
        $translationLanguages = [
            'en' => ['name' => 'English', 'culture' => app()->getLocale()],
            'af' => ['name' => 'Afrikaans', 'culture' => 'af'],
            'zu' => ['name' => 'Zulu', 'culture' => 'zu'],
            'xh' => ['name' => 'Xhosa', 'culture' => 'xh'],
            'st' => ['name' => 'Sotho', 'culture' => 'st'],
            'tn' => ['name' => 'Tswana', 'culture' => 'tn'],
            'nso' => ['name' => 'Northern Sotho (Sepedi)', 'culture' => 'nso'],
            'ss' => ['name' => 'Swati', 'culture' => 'ss'],
            've' => ['name' => 'Venda', 'culture' => 've'],
            'ts' => ['name' => 'Tsonga', 'culture' => 'ts'],
            'nr' => ['name' => 'Ndebele', 'culture' => 'nr'],
            'sw' => ['name' => 'Swahili', 'culture' => 'sw'],
            'yo' => ['name' => 'Yoruba', 'culture' => 'yo'],
            'ig' => ['name' => 'Igbo', 'culture' => 'ig'],
            'ha' => ['name' => 'Hausa', 'culture' => 'ha'],
            'am' => ['name' => 'Amharic', 'culture' => 'am'],
            'nl' => ['name' => 'Dutch', 'culture' => 'nl'],
            'fr' => ['name' => 'French', 'culture' => 'fr'],
            'de' => ['name' => 'German', 'culture' => 'de'],
            'es' => ['name' => 'Spanish', 'culture' => 'es'],
            'pt' => ['name' => 'Portuguese', 'culture' => 'pt'],
            'it' => ['name' => 'Italian', 'culture' => 'it'],
            'ar' => ['name' => 'Arabic', 'culture' => 'ar'],
            'ru' => ['name' => 'Russian', 'culture' => 'ru'],
            'zh' => ['name' => 'Chinese', 'culture' => 'zh'],
        ];

        // Translatable fields by sector
        $translationFieldsBySector = [
            'archives' => [
                'title' => 'Title', 'scope_and_content' => 'Scope and Content',
                'archival_history' => 'Archival History', 'acquisition' => 'Source of Acquisition',
                'arrangement' => 'Arrangement', 'access_conditions' => 'Access Conditions',
                'reproduction_conditions' => 'Reproduction Conditions', 'finding_aids' => 'Finding Aids',
                'related_units_of_description' => 'Related Units', 'appraisal' => 'Appraisal',
                'accruals' => 'Accruals', 'physical_characteristics' => 'Physical Characteristics',
                'location_of_originals' => 'Location of Originals', 'location_of_copies' => 'Location of Copies',
            ],
            'library' => [
                'title' => 'Title', 'alternate_title' => 'Alternate Title', 'edition' => 'Edition',
                'extent_and_medium' => 'Extent and Medium', 'scope_and_content' => 'Abstract/Summary',
                'access_conditions' => 'Access Conditions', 'reproduction_conditions' => 'Reproduction Conditions',
                'physical_characteristics' => 'Physical Description', 'sources' => 'Sources',
            ],
            'museum' => [
                'title' => 'Object Name/Title', 'alternate_title' => 'Other Names',
                'scope_and_content' => 'Description', 'archival_history' => 'Provenance',
                'acquisition' => 'Acquisition Method', 'physical_characteristics' => 'Physical Description',
                'access_conditions' => 'Display Conditions', 'location_of_originals' => 'Current Location',
                'related_units_of_description' => 'Related Objects',
            ],
            'gallery' => [
                'title' => 'Artwork Title', 'alternate_title' => 'Alternative Titles',
                'scope_and_content' => 'Description/Statement', 'archival_history' => 'Provenance',
                'acquisition' => 'Acquisition', 'physical_characteristics' => 'Medium and Dimensions',
                'access_conditions' => 'Exhibition Conditions', 'reproduction_conditions' => 'Copyright/Reproduction',
                'location_of_originals' => 'Current Location',
            ],
            'dam' => [
                'title' => 'Asset Title', 'alternate_title' => 'Alt Text',
                'scope_and_content' => 'Description', 'access_conditions' => 'Usage Rights',
                'reproduction_conditions' => 'License Terms', 'sources' => 'Source/Credits',
                'finding_aids' => 'Keywords/Tags',
            ],
        ];

        // All target fields for i18n mapping
        $targetFields = [
            'title' => 'Title', 'alternate_title' => 'Alternate Title', 'edition' => 'Edition',
            'extent_and_medium' => 'Extent and Medium', 'archival_history' => 'Archival History',
            'acquisition' => 'Acquisition', 'scope_and_content' => 'Scope and Content',
            'appraisal' => 'Appraisal', 'accruals' => 'Accruals', 'arrangement' => 'Arrangement',
            'access_conditions' => 'Access Conditions', 'reproduction_conditions' => 'Reproduction Conditions',
            'physical_characteristics' => 'Physical Characteristics', 'finding_aids' => 'Finding Aids',
            'location_of_originals' => 'Location of Originals', 'location_of_copies' => 'Location of Copies',
            'related_units_of_description' => 'Related Units of Description', 'rules' => 'Rules',
            'sources' => 'Sources', 'revision_history' => 'Revision History',
        ];

        // Qdrant status check
        $qdrantStatus = $this->checkQdrantStatus(
            $settings['qdrant_url'] ?? 'http://localhost:6333',
            $settings['qdrant_collection'] ?? ''
        );

        if ($request->isMethod('post')) {
            $fieldsToSave = [
                'ner_enabled', 'summarizer_enabled', 'spellcheck_enabled', 'translation_enabled',
                'processing_mode', 'summary_field', 'api_url', 'api_key', 'api_timeout',
                'auto_extract_on_upload', 'summarizer_max_length', 'summarizer_min_length',
                'spellcheck_language', 'mt_endpoint', 'mt_timeout',
                'translation_source_lang', 'translation_target_lang', 'translation_mode',
                'translation_overwrite', 'translation_sector', 'translation_save_culture',
                'qdrant_url', 'qdrant_collection', 'qdrant_model', 'qdrant_min_score',
            ];

            $checkboxFields = [
                'ner_enabled', 'summarizer_enabled', 'spellcheck_enabled', 'translation_enabled',
                'auto_extract_on_upload', 'translation_overwrite', 'translation_save_culture',
            ];

            foreach ($fieldsToSave as $field) {
                $value = $request->input($field, '');
                if (in_array($field, $checkboxFields)) {
                    $value = $request->has($field) && $request->input($field) ? '1' : '0';
                }
                DB::table('ahg_ner_settings')->updateOrInsert(
                    ['setting_key' => $field],
                    ['setting_value' => $value, 'updated_at' => now()]
                );
            }

            // Entity types (checkboxes to JSON)
            $entityTypes = [];
            foreach (['PERSON', 'ORG', 'GPE', 'DATE'] as $type) {
                if ($request->input('entity_' . $type)) {
                    $entityTypes[] = $type;
                }
            }
            DB::table('ahg_ner_settings')->updateOrInsert(
                ['setting_key' => 'ner_entity_types'],
                ['setting_value' => json_encode($entityTypes), 'updated_at' => now()]
            );

            // Spellcheck fields (checkboxes to JSON)
            $spellFields = [];
            foreach (['title', 'scopeAndContent', 'abstract', 'archivalHistory', 'acquisition'] as $f) {
                if ($request->input('spellcheck_field_' . $f)) {
                    $spellFields[] = $f;
                }
            }
            DB::table('ahg_ner_settings')->updateOrInsert(
                ['setting_key' => 'spellcheck_fields'],
                ['setting_value' => json_encode($spellFields), 'updated_at' => now()]
            );

            // Translation fields (checkboxes to JSON)
            $allTranslateFields = [
                'title', 'alternate_title', 'edition', 'extent_and_medium',
                'archival_history', 'acquisition', 'scope_and_content',
                'appraisal', 'accruals', 'arrangement', 'access_conditions',
                'reproduction_conditions', 'physical_characteristics', 'finding_aids',
                'location_of_originals', 'location_of_copies', 'related_units_of_description',
                'rules', 'sources', 'revision_history',
            ];
            $translateFields = [];
            $fieldMappings = [];
            foreach ($allTranslateFields as $f) {
                if ($request->input('translate_field_' . $f)) {
                    $translateFields[] = $f;
                }
                $target = $request->input('translate_target_' . $f, $f);
                if ($target !== $f) {
                    $fieldMappings[$f] = $target;
                }
            }
            DB::table('ahg_ner_settings')->updateOrInsert(
                ['setting_key' => 'translation_fields'],
                ['setting_value' => json_encode($translateFields), 'updated_at' => now()]
            );
            DB::table('ahg_ner_settings')->updateOrInsert(
                ['setting_key' => 'translation_field_mappings'],
                ['setting_value' => json_encode($fieldMappings), 'updated_at' => now()]
            );

            // Sync to ahg_translation_settings if table exists
            if (Schema::hasTable('ahg_translation_settings')) {
                try {
                    DB::table('ahg_translation_settings')->updateOrInsert(
                        ['setting_key' => 'mt.endpoint'],
                        ['setting_value' => $request->input('mt_endpoint', 'http://127.0.0.1:5100/translate')]
                    );
                    DB::table('ahg_translation_settings')->updateOrInsert(
                        ['setting_key' => 'mt.timeout_seconds'],
                        ['setting_value' => $request->input('mt_timeout', '30')]
                    );
                    DB::table('ahg_translation_settings')->updateOrInsert(
                        ['setting_key' => 'mt.target_culture'],
                        ['setting_value' => $request->input('translation_target_lang', 'af')]
                    );
                } catch (\Throwable $e) {
                    // Table might not exist yet
                }
            }

            return redirect()->route('settings.ai-services')->with('success', 'AI Services settings saved.');
        }

        return view('ahg-settings::ai-services', compact(
            'menu', 'settings', 'summaryFields', 'spellcheckLanguages', 'spellcheckFields',
            'translationLanguages', 'translationFieldsBySector', 'targetFields', 'qdrantStatus'
        ));
    }

    /**
     * Check Qdrant service and collection health.
     */
    protected function checkQdrantStatus(string $url, string $collection): array
    {
        $status = ['service' => false, 'version' => '', 'collections' => [], 'collection_status' => ''];

        try {
            $ch = curl_init(rtrim($url, '/') . '/healthz');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 2,
                CURLOPT_CONNECTTIMEOUT => 1,
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $status['service'] = ($code === 200);

            if (!$status['service']) {
                return $status;
            }

            // Get version
            $ch = curl_init(rtrim($url, '/') . '/');
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 2]);
            $resp = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($resp, true);
            $status['version'] = $data['version'] ?? '';

            // List collections
            $ch = curl_init(rtrim($url, '/') . '/collections');
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 2]);
            $resp = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($resp, true);
            if (isset($data['result']['collections'])) {
                foreach ($data['result']['collections'] as $col) {
                    $colName = $col['name'];
                    $ch2 = curl_init(rtrim($url, '/') . '/collections/' . $colName);
                    curl_setopt_array($ch2, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 2]);
                    $resp2 = curl_exec($ch2);
                    curl_close($ch2);
                    $colData = json_decode($resp2, true);
                    $status['collections'][] = [
                        'name' => $colName,
                        'points' => $colData['result']['points_count'] ?? 0,
                        'status' => $colData['result']['status'] ?? 'unknown',
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Qdrant not available
        }

        return $status;
    }

    // ─── AHG Import Settings ───────────────────────────────────────────
    public function ahgImportSettings(Request $request)
    {
        $menu = $this->buildMenu('ahg-import');

        if ($request->isMethod('post') && $request->hasFile('settings_file')) {
            $file = $request->file('settings_file');
            $content = json_decode(file_get_contents($file->getRealPath()), true);
            if (is_array($content) && Schema::hasTable('ahg_settings')) {
                foreach ($content as $key => $value) {
                    DB::table('ahg_settings')->updateOrInsert(
                        ['setting_key' => $key],
                        ['setting_value' => is_array($value) ? json_encode($value) : $value]
                    );
                }
                return redirect()->route('settings.ahg-import')->with('success', 'Settings imported successfully.');
            }
            return redirect()->route('settings.ahg-import')->with('error', 'Invalid JSON file.');
        }

        return view('ahg-settings::ahg-import-settings', compact('menu'));
    }

    // ─── AHG Integration ───────────────────────────────────────────────
    public function ahgIntegration(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('ahg-integration');
        $testResult = null;

        $settingNames = ['ahg_central_enabled', 'ahg_central_api_url', 'ahg_central_api_key'];

        if ($request->isMethod('post')) {
            foreach ($settingNames as $name) {
                $this->service->saveSetting($name, null, $request->input("settings.{$name}", ''), $culture);
            }

            if ($request->input('action') === 'test') {
                $testResult = ['success' => false, 'message' => 'Connection test not yet implemented.'];
                $url = $request->input('settings.ahg_central_api_url', '');
                if (!empty($url)) {
                    try {
                        $ch = curl_init($url . '/ping');
                        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
                        $resp = curl_exec($ch);
                        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        $testResult = $code === 200
                            ? ['success' => true, 'message' => 'Connected successfully.']
                            : ['success' => false, 'message' => "HTTP {$code} response."];
                    } catch (\Exception $e) {
                        $testResult = ['success' => false, 'message' => $e->getMessage()];
                    }
                }
            } else {
                return redirect()->route('settings.ahg-integration')->with('success', 'AHG Central integration settings saved.');
            }
        }

        $settings = [];
        foreach ($settingNames as $name) {
            $settings[$name] = $this->service->getSetting($name, null, $culture) ?? '';
        }
        $settings['ahg_central_api_url'] = $settings['ahg_central_api_url'] ?: 'https://central.theahg.co.za/api/v1';

        return view('ahg-settings::ahg-integration', compact('menu', 'settings', 'testResult'));
    }

    // ─── Page Elements ─────────────────────────────────────────────────
    /**
     * Default page elements settings — clones AtoM SettingsPageElementsAction.
     * Toggles for logo, title, description, language menu, IO carousel,
     * digital object map, copyright/material filters. Stored in `setting`
     * table by name (no scope), not in element_visibility.
     */
    public function pageElements(Request $request)
    {
        $culture = app()->getLocale();
        $menu = $this->buildMenu('page-elements');

        $names = [
            'toggleLogo',
            'toggleTitle',
            'toggleDescription',
            'toggleLanguageMenu',
            'toggleIoSlider',
            'toggleDigitalObjectMap',
            'toggleCopyrightFilter',
            'toggleMaterialFilter',
        ];

        $labels = [
            'toggleLogo' => 'Logo',
            'toggleTitle' => 'Title',
            'toggleDescription' => 'Description',
            'toggleLanguageMenu' => 'Language menu',
            'toggleIoSlider' => 'Digital object carousel',
            'toggleDigitalObjectMap' => 'Digital object map',
            'toggleCopyrightFilter' => 'Copyright status filter',
            'toggleMaterialFilter' => 'General material designation filter',
        ];

        // Has a Google Maps API key been set? (controls digital object map enable)
        $googleMapsApiKeySet = (bool) DB::table('setting')
            ->leftJoin('setting_i18n', function ($j) use ($culture) {
                $j->on('setting.id', '=', 'setting_i18n.id')
                    ->where('setting_i18n.culture', '=', $culture);
            })
            ->where('setting.name', 'google_maps_api_key')
            ->value('setting_i18n.value');

        if ($request->isMethod('post')) {
            foreach ($names as $name) {
                $value = $request->has($name) ? '1' : '0';
                $existing = DB::table('setting')->where('name', $name)->first();
                if ($existing) {
                    DB::table('setting_i18n')->updateOrInsert(
                        ['id' => $existing->id, 'culture' => $culture],
                        ['value' => $value]
                    );
                } else {
                    $id = DB::table('setting')->insertGetId([
                        'name' => $name,
                        'editable' => 1,
                        'deleteable' => 0,
                        'source_culture' => $culture,
                        'serial_number' => 0,
                    ]);
                    DB::table('setting_i18n')->insert([
                        'id' => $id,
                        'culture' => $culture,
                        'value' => $value,
                    ]);
                }
            }
            return redirect()->route('settings.page-elements')
                ->with('success', 'Default page elements saved.');
        }

        // Build settings map for the view: name => ['id', 'value', 'label']
        $rows = DB::table('setting')
            ->leftJoin('setting_i18n', function ($j) use ($culture) {
                $j->on('setting.id', '=', 'setting_i18n.id')
                    ->where('setting_i18n.culture', '=', $culture);
            })
            ->whereIn('setting.name', $names)
            ->select('setting.id', 'setting.name', 'setting_i18n.value')
            ->get()
            ->keyBy('name');

        $settings = [];
        foreach ($names as $name) {
            $row = $rows[$name] ?? null;
            $settings[$name] = (object) [
                'id' => $row->id ?? null,
                'name' => $name,
                'value' => $row->value ?? '0',
                'label' => $labels[$name],
            ];
        }

        return view('ahg-settings::page-elements', compact('settings', 'menu', 'googleMapsApiKeySet'));
    }

    // ─── Dropdown Manager ──────────────────────────────────────────────
    public function dropdownIndex()
    {
        $menu = $this->buildMenu('dropdown');
        $dropdowns = collect();
        if (Schema::hasTable('ahg_dropdowns')) {
            $dropdowns = DB::table('ahg_dropdowns')->orderBy('name')->get();
        }

        return view('ahg-settings::ahgDropdown.index', compact('menu', 'dropdowns'));
    }

    public function dropdownEdit(Request $request, ?int $id = null)
    {
        $menu = $this->buildMenu('dropdown');
        $dropdown = null;
        $values = collect();

        if ($id && Schema::hasTable('ahg_dropdowns')) {
            $dropdown = DB::table('ahg_dropdowns')->find($id);
            if (Schema::hasTable('ahg_dropdown_values')) {
                $values = DB::table('ahg_dropdown_values')->where('dropdown_id', $id)->orderBy('sort_order')->pluck('value');
            }
        }
        if (!$dropdown) {
            $dropdown = (object) ['id' => null, 'name' => '', 'slug' => '', 'description' => ''];
        }

        if ($request->isMethod('post') && Schema::hasTable('ahg_dropdowns')) {
            $data = [
                'name' => $request->input('name', ''),
                'slug' => $request->input('slug', '') ?: \Illuminate\Support\Str::slug($request->input('name', '')),
                'description' => $request->input('description', ''),
                'updated_at' => now(),
            ];
            if ($id) {
                DB::table('ahg_dropdowns')->where('id', $id)->update($data);
            } else {
                $data['created_at'] = now();
                $id = DB::table('ahg_dropdowns')->insertGetId($data);
            }
            // Save values
            if (Schema::hasTable('ahg_dropdown_values')) {
                DB::table('ahg_dropdown_values')->where('dropdown_id', $id)->delete();
                foreach ($request->input('values', []) as $i => $val) {
                    if (trim($val) !== '') {
                        DB::table('ahg_dropdown_values')->insert([
                            'dropdown_id' => $id,
                            'value' => trim($val),
                            'sort_order' => $i,
                        ]);
                    }
                }
            }
            return redirect()->route('settings.dropdown.index')->with('success', 'Dropdown saved.');
        }

        return view('ahg-settings::ahgDropdown.edit', compact('menu', 'dropdown', 'values'));
    }

    /**
     * Library settings stub.
     */
    public function library(Request $request)
    {
        $menu = $this->buildMenu('library');
        return view('ahg-settings::library', compact('menu'));
    }

    /**
     * Carousel settings stub.
     */
    public function carousel(Request $request)
    {
        $menu = $this->buildMenu('carousel');
        return view('ahg-settings::carousel', compact('menu'));
    }

    /**
     * Authority records settings stub.
     */
    public function authority(Request $request)
    {
        $menu = $this->buildMenu('authority');
        return view('ahg-settings::authority', compact('menu'));
    }
}
