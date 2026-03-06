<?php

namespace AhgSettings\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SettingsController extends Controller
{
    /**
     * Scope-to-display-name mapping for setting scopes.
     */
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

    /**
     * Icons for each scope section.
     */
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

    /**
     * Descriptions for each scope section.
     */
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

    /**
     * Settings dashboard: shows cards for Heratio setting scopes and AHG setting groups.
     */
    public function index()
    {
        $culture = app()->getLocale();

        // Get distinct scopes from setting table (editable only)
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

        // AHG Settings groups (if table exists)
        $ahgGroups = collect();
        if (Schema::hasTable('ahg_settings')) {
            $ahgGroups = DB::table('ahg_settings')
                ->select('setting_group', DB::raw('COUNT(*) as cnt'))
                ->groupBy('setting_group')
                ->orderBy('setting_group')
                ->get()
                ->map(function ($row) {
                    return (object) [
                        'key' => $row->setting_group,
                        'label' => ucfirst(str_replace('_', ' ', $row->setting_group)),
                        'count' => $row->cnt,
                    ];
                });
        }

        return view('ahg-settings::index', [
            'scopeCards' => $scopeCards,
            'ahgGroups' => $ahgGroups,
        ]);
    }

    /**
     * Show all editable settings for a given scope.
     */
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

        $settings = $query
            ->select('setting.id', 'setting.name', 'setting.scope', 'setting_i18n.value')
            ->orderBy('setting.name')
            ->get();

        $sectionLabel = $this->scopeLabels[$section] ?? ucfirst(str_replace('_', ' ', $section));

        return view('ahg-settings::section', [
            'settings' => $settings,
            'section' => $section,
            'sectionLabel' => $sectionLabel,
        ]);
    }

    /**
     * Theme settings page — colors, logo, branding, custom CSS.
     */
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

            // Regenerate ahg-generated.css
            $this->regenerateThemeCss();

            return redirect()->route('settings.themes')->with('success', 'Theme settings saved.');
        }

        $settings = DB::table('ahg_settings')
            ->whereIn('setting_key', $themeKeys)
            ->pluck('setting_value', 'setting_key');

        return view('ahg-settings::themes', ['settings' => $settings]);
    }

    /**
     * Regenerate the static ahg-generated.css file from current theme settings.
     */
    private function regenerateThemeCss(): void
    {
        $rows = DB::table('ahg_settings')
            ->where('setting_group', 'general')
            ->pluck('setting_value', 'setting_key');

        $css = "/* AHG Theme - Generated CSS */\n/* Do not edit - regenerated when settings saved */\n";
        $css .= ":root {\n";
        $css .= "    --ahg-primary: " . ($rows['ahg_primary_color'] ?? '#005837') . ";\n";
        $css .= "    --ahg-secondary: " . ($rows['ahg_secondary_color'] ?? '#37A07F') . ";\n";
        $css .= "    --ahg-card-header-bg: " . ($rows['ahg_card_header_bg'] ?? '#005837') . ";\n";
        $css .= "    --ahg-card-header-text: " . ($rows['ahg_card_header_text'] ?? '#ffffff') . ";\n";
        $css .= "    --ahg-btn-bg: " . ($rows['ahg_button_bg'] ?? '#005837') . ";\n";
        $css .= "    --ahg-btn-text: " . ($rows['ahg_button_text'] ?? '#ffffff') . ";\n";
        $css .= "    --ahg-link-color: " . ($rows['ahg_link_color'] ?? '#005837') . ";\n";
        $css .= "    --ahg-sidebar-bg: " . ($rows['ahg_sidebar_bg'] ?? '#f8f9fa') . ";\n";
        $css .= "    --ahg-sidebar-text: " . ($rows['ahg_sidebar_text'] ?? '#333333') . ";\n";
        $css .= "    --ahg-success: " . ($rows['ahg_success_color'] ?? '#28a745') . ";\n";
        $css .= "    --ahg-danger: " . ($rows['ahg_danger_color'] ?? '#dc3545') . ";\n";
        $css .= "    --ahg-warning: " . ($rows['ahg_warning_color'] ?? '#ffc107') . ";\n";
        $css .= "    --ahg-info: " . ($rows['ahg_info_color'] ?? '#17a2b8') . ";\n";
        $css .= "    --ahg-light: " . ($rows['ahg_light_color'] ?? '#f8f9fa') . ";\n";
        $css .= "    --ahg-dark: " . ($rows['ahg_dark_color'] ?? '#343a40') . ";\n";
        $css .= "    --ahg-muted: " . ($rows['ahg_muted_color'] ?? '#6c757d') . ";\n";
        $css .= "    --ahg-border: " . ($rows['ahg_border_color'] ?? '#dee2e6') . ";\n";
        $css .= "}\n";
        $css .= ".card-header {\n    background-color: var(--ahg-card-header-bg) !important;\n    color: var(--ahg-card-header-text) !important;\n}\n";
        $css .= ".card-header * { color: var(--ahg-card-header-text) !important; }\n";
        $css .= ".btn-primary {\n    background-color: var(--ahg-btn-bg) !important;\n    border-color: var(--ahg-btn-bg) !important;\n    color: var(--ahg-btn-text) !important;\n}\n";
        $css .= ".btn-primary:hover, .btn-primary:focus {\n    filter: brightness(0.9);\n}\n";
        $css .= "a:not(.btn):not(.nav-link):not(.dropdown-item) {\n    color: var(--ahg-link-color);\n}\n";
        $css .= ".sidebar, #sidebar-content {\n    background-color: var(--ahg-sidebar-bg) !important;\n    color: var(--ahg-sidebar-text) !important;\n}\n";

        $path = public_path('vendor/ahg-theme-b5/css/ahg-generated.css');
        file_put_contents($path, $css);
    }

    /**
     * Show AHG settings for a specific group.
     */
    public function ahgSection(Request $request, string $group)
    {
        $settings = DB::table('ahg_settings')
            ->where('setting_group', $group)
            ->orderBy('setting_key')
            ->select('id', 'setting_key', 'setting_value', 'setting_group', 'description', 'setting_type')
            ->get();

        $groupLabel = ucfirst(str_replace('_', ' ', $group));

        return view('ahg-settings::ahg-section', [
            'settings' => $settings,
            'group' => $group,
            'groupLabel' => $groupLabel,
        ]);
    }
}
