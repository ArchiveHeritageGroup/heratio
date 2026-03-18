<?php

namespace AhgPortableExport\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PortableExportController extends Controller
{
    public function index()
    {
        $culture = app()->getLocale();

        // Get repositories for scope dropdown
        $repositories = DB::table('repository')
            ->join('actor_i18n', function ($join) use ($culture) {
                $join->on('repository.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $culture);
            })
            ->select([
                'repository.id',
                'actor_i18n.authorized_form_of_name as name',
            ])
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->get();

        // Get available languages/cultures
        $cultures = DB::table('setting_i18n')
            ->join('setting', 'setting_i18n.id', '=', 'setting.id')
            ->where('setting.name', 'siteLanguages')
            ->select('setting_i18n.value', 'setting_i18n.culture')
            ->get();

        // Get available languages from cultures actually used in the database
        $languages = DB::table('information_object_i18n')
            ->select('culture as code')
            ->distinct()
            ->orderBy('culture')
            ->get()
            ->map(function ($row) {
                $row->name = locale_get_display_language($row->code, app()->getLocale()) ?: $row->code;
                return $row;
            });

        // Get past exports if table exists
        $exports = collect();
        $hasTable = Schema::hasTable('portable_export');

        if ($hasTable) {
            $exports = DB::table('portable_export')
                ->orderBy('created_at', 'desc')
                ->limit(25)
                ->get();
        }

        return view('ahg-portable-export::index', [
            'repositories' => $repositories,
            'languages' => $languages,
            'exports' => $exports,
            'hasTable' => $hasTable,
        ]);
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'scope' => 'required|in:all,repository,fonds',
            'repository_id' => 'nullable|integer|exists:repository,id',
            'mode' => 'required|in:read_only,archive',
            'culture' => 'nullable|string|max:10',
            'include_digital_objects' => 'nullable|boolean',
            'include_thumbnails' => 'nullable|boolean',
            'include_references' => 'nullable|boolean',
            'title' => 'nullable|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'footer_text' => 'nullable|string|max:500',
        ]);

        // Create job record if table exists
        if (Schema::hasTable('portable_export')) {
            DB::table('portable_export')->insert([
                'user_id' => auth()->id() ?? 0,
                'title' => $validated['title'] ?? 'Untitled Export',
                'scope_type' => $validated['scope'],
                'scope_repository_id' => $validated['repository_id'] ?? null,
                'mode' => $validated['mode'],
                'culture' => $validated['culture'] ?? app()->getLocale(),
                'include_masters' => !empty($validated['include_digital_objects']),
                'include_thumbnails' => !empty($validated['include_thumbnails']),
                'include_references' => !empty($validated['include_references']),
                'branding' => json_encode([
                    'subtitle' => $validated['subtitle'] ?? null,
                    'footer_text' => $validated['footer_text'] ?? null,
                ]),
                'status' => 'pending',
                'created_at' => now(),
            ]);
        }

        return redirect()
            ->route('portable-export.index')
            ->with('success', 'Portable export has been queued. You will be notified when it is ready for download.');
    }
}
