<?php

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
        'element_visibility' => 'Default page elements',
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
        'element_visibility' => 'Control visibility of page elements for descriptive standards.',
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
        ['action' => 'visible-elements', 'label' => 'Default page elements', 'icon' => 'fa-eye'],
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
        ['action' => 'ai-condition', 'label' => 'AI Condition', 'icon' => 'fa-robot'],
        ['action' => 'header-customizations', 'label' => 'Header customizations', 'icon' => 'fa-heading'],
        ['action' => 'storage-service', 'label' => 'Storage service', 'icon' => 'fa-hdd'],
        ['action' => 'web-analytics', 'label' => 'Web analytics', 'icon' => 'fa-chart-bar'],
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

        $settings = DB::table('setting')
            ->leftJoin('setting_i18n', function ($join) use ($culture) {
                $join->on('setting.id', '=', 'setting_i18n.id')
                    ->where('setting_i18n.culture', '=', $culture);
            })
            ->where('setting.scope', 'element_visibility')
            ->where('setting.editable', 1)
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
        $uploadsPath = '/mnt/nas/heratio/archive';
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
            'ahg_footer_bg', 'ahg_footer_text_color',
            'ahg_descbar_bg', 'ahg_descbar_text',
            'ahg_card_header_bg', 'ahg_card_header_text',
            'ahg_button_bg', 'ahg_button_text',
            'ahg_link_color', 'ahg_sidebar_bg', 'ahg_sidebar_text',
            'ahg_logo_path', 'ahg_footer_text', 'ahg_show_branding',
            'ahg_custom_css',
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
            $css .= "    {$var}: " . ($rows[$key] ?? $default) . ";\n";
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
            'ahg_descbar_bg' => ['--ahg-descbar-bg', '#005837'],
            'ahg_descbar_text' => ['--ahg-descbar-text', '#ffffff'],
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
            . "body { background-color: var(--ahg-background-light) !important; color: var(--ahg-body-text) !important; }\n";
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
            'ai_condition' => 'AI Condition',
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
            foreach ($settingMap as $formKey => $dbName) {
                $value = $request->input("settings.{$formKey}", '');
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

        $settingNames = [
            'header_background_color',
            'header_text_color',
            'header_custom_css',
            'header_custom_html',
        ];

        if ($request->isMethod('post')) {
            foreach ($settingNames as $name) {
                $value = $request->input("settings.{$name}", '');
                $this->service->saveSetting($name, null, $value, $culture);
            }
            return redirect()->route('settings.header-customizations')->with('success', 'Header customization settings saved.');
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

    // ─── 13. AI Condition ───────────────────────────────────────────────
    public function aiCondition(Request $request)
    {
        $menu = $this->buildMenu('ai-condition');

        // Check if ahg_settings table has ai_condition group
        $hasAhgTable = Schema::hasTable('ahg_settings');
        $hasGroup = false;
        if ($hasAhgTable) {
            $hasGroup = DB::table('ahg_settings')->where('setting_group', 'ai_condition')->exists();
        }

        if ($hasGroup) {
            return redirect()->route('settings.ahg', 'ai_condition');
        }

        return view('ahg-settings::ai-condition', compact('menu'));
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
                    ->update(['resolved_at' => now(), 'resolved_by' => $authUser]);
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
                    ->update(['resolved_at' => now(), 'resolved_by' => $authUser]);
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
        $statusFilter = $request->get('status', '');
        $levelFilter = $request->get('level', '');
        $searchFilter = $request->get('search', '');
        $page = max(1, (int) $request->get('page', 1));
        $limit = 25;

        $query = DB::table('ahg_error_log');

        if ($statusFilter === 'open') {
            $query->whereNull('resolved_at');
        } elseif ($statusFilter === 'resolved') {
            $query->whereNotNull('resolved_at');
        }

        if ($levelFilter) {
            $query->where('level', $levelFilter);
        }

        if ($searchFilter) {
            $query->where(function ($q) use ($searchFilter) {
                $q->where('message', 'like', "%{$searchFilter}%")
                  ->orWhere('url', 'like', "%{$searchFilter}%")
                  ->orWhere('file', 'like', "%{$searchFilter}%");
            });
        }

        $total = (clone $query)->count();
        $totalPages = $limit > 0 ? (int) ceil($total / $limit) : 1;

        $entries = $query
            ->orderByDesc('created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        // Stats
        $openCount = DB::table('ahg_error_log')->whereNull('resolved_at')->count();
        $resolvedCount = DB::table('ahg_error_log')->whereNotNull('resolved_at')->count();
        $unreadCount = DB::table('ahg_error_log')->where('is_read', 0)->count();
        $todayCount = DB::table('ahg_error_log')
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
        return collect($this->menuNodes)->map(function ($node) use ($active) {
            $node['active'] = ($node['action'] === $active);
            return $node;
        })->toArray();
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

}
