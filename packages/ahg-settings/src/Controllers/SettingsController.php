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
        ['action' => 'index', 'label' => 'Settings home', 'icon' => 'fa-home'],
        ['action' => 'global', 'label' => 'Global', 'icon' => 'fa-globe'],
        ['action' => 'site-information', 'label' => 'Site information', 'icon' => 'fa-info-circle'],
        ['action' => 'security', 'label' => 'Security', 'icon' => 'fa-shield-alt'],
        ['action' => 'identifier', 'label' => 'Identifiers', 'icon' => 'fa-fingerprint'],
        ['action' => 'email', 'label' => 'Email', 'icon' => 'fa-envelope'],
        ['action' => 'treeview', 'label' => 'Treeview', 'icon' => 'fa-sitemap'],
        ['action' => 'digital-objects', 'label' => 'Digital objects', 'icon' => 'fa-photo-video'],
        ['action' => 'interface-labels', 'label' => 'Interface labels', 'icon' => 'fa-tags'],
        ['action' => 'oai', 'label' => 'OAI repository', 'icon' => 'fa-cloud'],
        ['action' => 'system-info', 'label' => 'System information', 'icon' => 'fa-server'],
        ['action' => 'services', 'label' => 'Services monitor', 'icon' => 'fa-heartbeat'],
        ['action' => 'themes', 'label' => 'Theme configuration', 'icon' => 'fa-palette'],
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
        $vars = [
            'ahg_primary_color' => ['--ahg-primary', '#005837'],
            'ahg_secondary_color' => ['--ahg-secondary', '#37A07F'],
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
        foreach ($vars as $key => [$var, $default]) {
            $css .= "    {$var}: " . ($rows[$key] ?? $default) . ";\n";
        }
        $css .= "}\n";
        $css .= ".card-header { background-color: var(--ahg-card-header-bg) !important; color: var(--ahg-card-header-text) !important; }\n";
        $css .= ".card-header * { color: var(--ahg-card-header-text) !important; }\n";
        $css .= ".btn-primary { background-color: var(--ahg-btn-bg) !important; border-color: var(--ahg-btn-bg) !important; color: var(--ahg-btn-text) !important; }\n";
        $css .= ".btn-primary:hover, .btn-primary:focus { filter: brightness(0.9); }\n";
        $css .= "a:not(.btn):not(.nav-link):not(.dropdown-item) { color: var(--ahg-link-color); }\n";
        $css .= ".sidebar, #sidebar-content { background-color: var(--ahg-sidebar-bg) !important; color: var(--ahg-sidebar-text) !important; }\n";

        $path = public_path('vendor/ahg-theme-b5/css/ahg-generated.css');
        file_put_contents($path, $css);
    }

    public function dynamicCss()
    {
        $rows = DB::table('ahg_settings')
            ->where('setting_group', 'general')
            ->pluck('setting_value', 'setting_key');

        $css = "/* AHG Theme - Dynamic CSS */\n:root {\n";
        $vars = [
            'ahg_primary_color' => ['--ahg-primary', '#005837'],
            'ahg_secondary_color' => ['--ahg-secondary', '#37A07F'],
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
        foreach ($vars as $key => [$var, $default]) {
            $css .= "    {$var}: " . ($rows[$key] ?? $default) . ";\n";
        }
        $css .= "}\n";
        $css .= ".card-header { background-color: var(--ahg-card-header-bg) !important; color: var(--ahg-card-header-text) !important; }\n";
        $css .= ".card-header * { color: var(--ahg-card-header-text) !important; }\n";
        $css .= ".btn-primary { background-color: var(--ahg-btn-bg) !important; border-color: var(--ahg-btn-bg) !important; color: var(--ahg-btn-text) !important; }\n";
        $css .= ".btn-primary:hover, .btn-primary:focus { filter: brightness(0.9); }\n";
        $css .= "a:not(.btn):not(.nav-link):not(.dropdown-item) { color: var(--ahg-link-color); }\n";
        $css .= ".sidebar, #sidebar-content { background-color: var(--ahg-sidebar-bg) !important; color: var(--ahg-sidebar-text) !important; }\n";

        $customCss = $rows['ahg_custom_css'] ?? '';
        if (!empty(trim($customCss))) {
            $css .= "\n/* Custom CSS */\n" . $customCss . "\n";
        }

        return response($css, 200)->header('Content-Type', 'text/css');
    }

    public function ahgSection(Request $request, string $group)
    {
        if ($request->isMethod('post')) {
            foreach ($request->input('settings', []) as $key => $value) {
                DB::table('ahg_settings')
                    ->where('setting_key', $key)
                    ->update(['setting_value' => $value]);
            }
            return redirect()->route('settings.ahg', $group)->with('success', ucfirst(str_replace('_', ' ', $group)) . ' settings saved.');
        }

        $settings = DB::table('ahg_settings')
            ->where('setting_group', $group)
            ->orderBy('setting_key')
            ->select('id', 'setting_key', 'setting_value', 'setting_group', 'description', 'setting_type')
            ->get();

        $groupLabel = ucfirst(str_replace('_', ' ', $group));

        return view('ahg-settings::ahg-section', compact('settings', 'group', 'groupLabel'));
    }

    private function buildMenu(string $active): array
    {
        return collect($this->menuNodes)->map(function ($node) use ($active) {
            $node['active'] = ($node['action'] === $active);
            return $node;
        })->toArray();
    }
}
