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
